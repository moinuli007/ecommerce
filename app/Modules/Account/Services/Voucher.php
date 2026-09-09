<?php

namespace App\Modules\Account\Services;

use App\Core\DB;
use App\Core\Utility;
use App\Enum\AutoLedger;
use App\Enum\TransactionType;
use App\Enum\VoucherType;
use App\Modules\Account\Models\Ledger;
use App\Modules\Account\Models\LedgerEntry;
use App\Modules\Account\Models\VoucherEntry;
use RuntimeException;

/**
 * ভাউচার তৈরি / এডিট / ডিলিট — পুরো প্রজেক্টে হিসাব পোস্ট করার একমাত্র দরজা।
 *
 * ⚠ নিয়ম: `a_voucher_entry` বা `a_ledger_entry` তে কখনো সরাসরি insert/update
 * করবেন না। অর্ডার, পারচেজ, রিটার্ন — সবকিছু এই ক্লাসের মধ্য দিয়ে যাবে।
 * তাহলেই ডাবল এন্ট্রি সবসময় ব্যালেন্সড থাকবে।
 *
 * দুইটা এন্ট্রি পয়েন্ট:
 *
 *   create()          — সাধারণ ভাউচার: এক ডেবিট + এক ক্রেডিট
 *   createCompound()  — কম্পাউন্ড ভাউচার: অনেক লাইন, শুধু SUM মিললেই হলো
 *
 * উদাহরণ (কাস্টমার বাকিতে ৳১০০০ এর অর্ডার দিল):
 *
 *     Voucher::create(
 *         VoucherType::Sale,
 *         1000.00,
 *         LedgerAccounts::customerLedger($customer),   // Dr — কাস্টমারের কাছে পাওনা বাড়ল
 *         LedgerAccounts::systemLedger(AutoLedger::Sales), // Cr — বিক্রয় আয় বাড়ল
 *         strtotime('today'),
 *         'Order #1024',
 *         (string) $order['id']
 *     );
 */
final class Voucher
{
    /** ভাসমান সংখ্যার তুলনায় এতটুকু পার্থক্য মেনে নেওয়া হয় */
    private const EPSILON = 0.0001;

    // -------------------------------------------------------------------------
    // তৈরি
    // -------------------------------------------------------------------------

    /**
     * সাধারণ (এক ডেবিট + এক ক্রেডিট) ভাউচার।
     *
     * @param  int $time ভাউচারের কার্যকর তারিখ (unix timestamp)
     * @return int নতুন voucher id
     */
    public static function create(
        VoucherType $type,
        float $amount,
        int $debitLedger,
        int $creditLedger,
        int $time,
        string $note = '',
        string $reference = ''
    ): int {
        if ($amount <= 0) {
            throw new RuntimeException('Voucher amount must be greater than zero.');
        }

        if ($debitLedger === $creditLedger) {
            throw new RuntimeException('Debit and credit cannot be the same ledger.');
        }

        return self::createCompound(
            $type,
            [
                ['ledger_id' => $debitLedger,  'debit' => $amount, 'credit' => 0,       'note' => $note],
                ['ledger_id' => $creditLedger, 'debit' => 0,       'credit' => $amount, 'note' => $note],
            ],
            $time,
            $note,
            $reference
        );
    }

    /**
     * কম্পাউন্ড ভাউচার — অনেকগুলো লাইন।
     *
     * প্রতিটা লাইন: ['ledger_id' => int, 'debit' => float, 'credit' => float, 'note' => string]
     * SUM(debit) আর SUM(credit) সমান না হলে exception।
     *
     * @param  array<int,array<string,mixed>> $lines
     * @return int নতুন voucher id
     */
    public static function createCompound(
        VoucherType $type,
        array $lines,
        int $time,
        string $note = '',
        string $reference = ''
    ): int {
        $lines = self::normalizeLines($lines, $note);

        self::assertBalanced($lines);
        self::assertLedgersExist(array_column($lines, 'ledger_id'));

        return DB::transaction(static function () use ($type, $lines, $time, $note, $reference): int {
            $voucherData = [
                'code'      => '',
                'type'      => $type->value,
                'reference' => $reference,
                'note'      => mb_substr($note, 0, 255),
                'time'      => $time,
                'isActive'  => 1,
            ];

            Utility::stampCreate($voucherData);

            $voucherId = DB::insert('a_voucher_entry', $voucherData);

            if ($voucherId === 0) {
                throw new RuntimeException('Could not create the voucher.');
            }

            DB::update(
                'a_voucher_entry',
                ['code' => CodeGenerator::next('voucher', $type->code())],
                ['id' => $voucherId]
            );

            $entryCodes = CodeGenerator::nextMany('ledger_entry', count($lines));
            $rows       = [];

            foreach ($lines as $index => $line) {
                $row = [
                    'code'       => $entryCodes[$index] ?? '',
                    'voucher_id' => $voucherId,
                    'ledger_id'  => $line['ledger_id'],
                    'debit'      => $line['debit'],
                    'credit'     => $line['credit'],
                    'note'       => $line['note'],
                    'time'       => $time,
                ];

                Utility::stampCreate($row);

                $rows[] = $row;
            }

            DB::insertMany('a_ledger_entry', $rows);

            return $voucherId;
        });
    }

    /**
     * ওপেনিং ব্যালেন্স ভাউচার। কাউন্টার পার্ট সবসময় "Opening Balance" সিস্টেম লেজার।
     *
     * @param TransactionType $nature পার্টির দিক থেকে — Debit মানে আমরা পাব, Credit মানে আমরা দেব
     */
    public static function opening(
        VoucherType $type,
        int $ledgerId,
        float $amount,
        TransactionType $nature,
        int $time,
        string $reference = '',
        string $note = 'Opening balance'
    ): int {
        if (!$type->isOpening()) {
            throw new RuntimeException($type->label() . ' is not an opening voucher type.');
        }

        $openingLedger = LedgerAccounts::systemLedger(AutoLedger::Opening);

        [$debit, $credit] = $nature === TransactionType::Debit
            ? [$ledgerId, $openingLedger]
            : [$openingLedger, $ledgerId];

        return self::create($type, $amount, $debit, $credit, $time, $note, $reference);
    }

    // -------------------------------------------------------------------------
    // এডিট
    // -------------------------------------------------------------------------

    /**
     * ভাউচার আপডেট। শুধু যেগুলো পাঠানো হয়েছে সেগুলোই বদলায় (null = অপরিবর্তিত)।
     *
     * শুধু সাধারণ (২ লাইনের) ভাউচারেই amount/ledger বদলানো যায় —
     * কম্পাউন্ড ভাউচার বদলাতে হলে delete করে নতুন করে তৈরি করুন।
     */
    public static function update(
        int $voucherId,
        ?float $amount = null,
        ?string $note = null,
        ?int $time = null,
        ?int $debitLedger = null,
        ?int $creditLedger = null
    ): bool {
        $voucher = VoucherEntry::find($voucherId);

        if ($voucher === []) {
            throw new RuntimeException("Voucher not found (id=$voucherId).");
        }

        $entries = LedgerEntry::byVoucher($voucherId);

        if (count($entries) !== 2 && ($amount !== null || $debitLedger !== null || $creditLedger !== null)) {
            throw new RuntimeException(
                'A compound voucher\'s amount/ledger cannot be changed here — delete it and create a new one.'
            );
        }

        return DB::transaction(static function () use (
            $voucherId, $voucher, $entries, $amount, $note, $time, $debitLedger, $creditLedger
        ): bool {
            foreach ($entries as $entry) {
                $data = [];

                if ($time !== null) {
                    $data['time'] = $time;
                }

                if ($note !== null) {
                    $data['note'] = mb_substr($note, 0, 255);
                }

                $isDebitRow = (float) $entry['debit'] > 0;

                if ($amount !== null) {
                    if ($amount <= 0) {
                        throw new RuntimeException('Voucher amount must be greater than zero.');
                    }

                    $data['debit']  = $isDebitRow ? $amount : 0;
                    $data['credit'] = $isDebitRow ? 0 : $amount;
                }

                if ($isDebitRow && $debitLedger !== null) {
                    $data['ledger_id'] = $debitLedger;
                }

                if (!$isDebitRow && $creditLedger !== null) {
                    $data['ledger_id'] = $creditLedger;
                }

                if ($data === []) {
                    continue;
                }

                Utility::stampUpdate($data);

                DB::update('a_ledger_entry', $data, ['id' => (int) $entry['id']]);
            }

            $header = [];

            if ($time !== null) {
                $header['time'] = $time;
            }

            if ($note !== null) {
                $header['note'] = mb_substr($note, 0, 255);
            }

            if ($header !== []) {
                Utility::stampUpdate($header);
                DB::update('a_voucher_entry', $header, ['id' => $voucherId]);
            }

            // বদলানোর পরেও ব্যালেন্স ঠিক আছে কি না, শেষবার যাচাই
            self::assertBalanced(LedgerEntry::byVoucher($voucherId));

            return true;
        });
    }

    // -------------------------------------------------------------------------
    // ডিলিট
    // -------------------------------------------------------------------------

    /**
     * এক বা একাধিক ভাউচার ডিলিট। `a_ledger_entry` FK cascade দিয়ে নিজেই মুছে যায়,
     * তবু স্পষ্টতার জন্য এখানে আগে ডিটেইল মুছে ফেলা হচ্ছে।
     *
     * @param int|array<int,int> $voucherId
     */
    public static function delete(int|array $voucherId): bool
    {
        $ids = array_values(array_filter(array_map('intval', (array) $voucherId)));

        if ($ids === []) {
            return false;
        }

        return DB::transaction(static function () use ($ids): bool {
            $placeholders = implode(', ', array_fill(0, count($ids), '?'));

            DB::run("DELETE FROM a_ledger_entry WHERE voucher_id IN ($placeholders)", $ids);
            DB::run("DELETE FROM a_voucher_entry WHERE id IN ($placeholders)", $ids);

            return true;
        });
    }

    /**
     * সোর্স ডকুমেন্ট ধরে ভাউচার মুছে ফেলা — যেমন অর্ডার ক্যানসেল হলে
     * ওই অর্ডারের Sale + COGS ভাউচার সব মুছে যাবে।
     *
     * @param VoucherType|array<int,VoucherType> $types
     */
    public static function deleteByReference(VoucherType|array $types, string $reference): int
    {
        // ⚠ backed enum এ (array) কাস্ট করা যাবে না — ওটা enum এর প্রপার্টি array দেয়
        $types      = is_array($types) ? $types : [$types];
        $typeValues = array_map(static fn (VoucherType $t) => $t->value, $types);

        $ids = DB::table('a_voucher_entry')
            ->whereIn('type', $typeValues)
            ->where('reference', $reference)
            ->pluck('id');

        if ($ids === []) {
            return 0;
        }

        self::delete(array_map('intval', $ids));

        return count($ids);
    }

    // -------------------------------------------------------------------------
    // ভেতরের হেল্পার
    // -------------------------------------------------------------------------

    /**
     * @param  array<int,array<string,mixed>> $lines
     * @return array<int,array{ledger_id:int,debit:float,credit:float,note:string}>
     */
    private static function normalizeLines(array $lines, string $defaultNote): array
    {
        $out = [];

        foreach ($lines as $line) {
            $ledgerId = (int) ($line['ledger_id'] ?? 0);
            $debit    = round((float) ($line['debit'] ?? 0), 4);
            $credit   = round((float) ($line['credit'] ?? 0), 4);

            if ($ledgerId <= 0) {
                throw new RuntimeException('Every voucher line needs a ledger_id.');
            }

            if ($debit < 0 || $credit < 0) {
                throw new RuntimeException('Debit or credit cannot be negative.');
            }

            if ($debit > 0 && $credit > 0) {
                throw new RuntimeException('A line cannot have both debit and credit.');
            }

            if ($debit === 0.0 && $credit === 0.0) {
                continue; // শূন্য লাইন বাদ
            }

            $out[] = [
                'ledger_id' => $ledgerId,
                'debit'     => $debit,
                'credit'    => $credit,
                'note'      => mb_substr((string) ($line['note'] ?? $defaultNote), 0, 255),
            ];
        }

        if (count($out) < 2) {
            throw new RuntimeException('A voucher needs at least two lines.');
        }

        return $out;
    }

    /**
     * ডাবল এন্ট্রির মূল শর্ত: SUM(debit) === SUM(credit)।
     *
     * @param array<int,array<string,mixed>> $lines
     */
    private static function assertBalanced(array $lines): void
    {
        $debit  = 0.0;
        $credit = 0.0;

        foreach ($lines as $line) {
            $debit  += (float) ($line['debit'] ?? 0);
            $credit += (float) ($line['credit'] ?? 0);
        }

        if (abs($debit - $credit) > self::EPSILON) {
            throw new RuntimeException(
                sprintf('Voucher is not balanced — debit %.4f, credit %.4f', $debit, $credit)
            );
        }

        if ($debit <= 0) {
            throw new RuntimeException('Voucher total must be greater than zero.');
        }
    }

    /** @param array<int,int> $ledgerIds */
    private static function assertLedgersExist(array $ledgerIds): void
    {
        $unique = array_values(array_unique(array_map('intval', $ledgerIds)));
        $found  = Ledger::whereIn('id', $unique)->pluck('id');
        $found  = array_map('intval', $found);
        $missing = array_diff($unique, $found);

        if ($missing !== []) {
            throw new RuntimeException('Ledger not found: ' . implode(', ', $missing));
        }
    }
}
