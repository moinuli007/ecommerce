<?php

namespace App\Modules\Account\Services;

use App\Core\DB;
use App\Core\Utility;
use App\Enum\MasterAccountType;
use App\Enum\TransactionType;
use App\Enum\VoucherType;
use App\Modules\Account\Models\Ledger;
use App\Modules\Account\Models\LedgerEntry;
use RuntimeException;

/**
 * লেজার স্টেটমেন্ট ও ব্যালেন্স — school এর App\Services\Account\LedgerStatement এর সমতুল্য।
 *
 * সাইন কনভেনশন:
 *   `raw_balance`  = SUM(debit) − SUM(credit)  (সবসময় ডেবিট-পজিটিভ)
 *   `balance`      = অ্যাকাউন্টের nature অনুযায়ী ঠিক করা —
 *                    Asset/Expense এ debit বাড়লে পজিটিভ,
 *                    Liability/Equity/Income এ credit বাড়লে পজিটিভ।
 *
 * অর্থাৎ কাস্টমারের `balance` পজিটিভ মানে সে টাকা পাওনা রেখেছে (আমরা পাব),
 * সাপ্লায়ারের `balance` পজিটিভ মানে আমরা তাকে দেব।
 */
final class LedgerStatement
{
    /**
     * একটা লেজারের পূর্ণ স্টেটমেন্ট।
     *
     * @param  string|null $from YYYY-MM-DD (null = শুরু থেকে)
     * @param  string|null $to   YYYY-MM-DD (null = আজ পর্যন্ত)
     * @return array{
     *   ledger: array<string,mixed>,
     *   from: string, to: string,
     *   opening: array{debit:float,credit:float,balance:float,side:string},
     *   rows: array<int,array<string,mixed>>,
     *   total: array{debit:float,credit:float},
     *   closing: array{debit:float,credit:float,balance:float,side:string}
     * }
     */
    public static function statement(int $ledgerId, ?string $from = null, ?string $to = null): array
    {
        $ledger = Ledger::withChart($ledgerId);

        if ($ledger === []) {
            throw new RuntimeException("Ledger not found (id=$ledgerId).");
        }

        $nature   = MasterAccountType::from((int) $ledger['master_type'])->nature();
        $fromTime = $from !== null && $from !== '' ? Utility::toTime($from) : null;
        $toTime   = $to !== null && $to !== '' ? Utility::toTime($to) + 86399 : null;

        // ওপেনিং = পিরিয়ড শুরুর আগের সব এন্ট্রির যোগফল
        $openingRaw = $fromTime === null
            ? 0.0
            : LedgerEntry::balance($ledgerId, $fromTime - 1);

        $rows    = self::entries($ledgerId, $fromTime, $toTime);
        $running = $openingRaw;

        $totalDebit  = 0.0;
        $totalCredit = 0.0;

        foreach ($rows as $index => $row) {
            $running     += $row['debit'] - $row['credit'];
            $totalDebit  += $row['debit'];
            $totalCredit += $row['credit'];

            $rows[$index]['running_balance'] = round(self::sign($running, $nature), 4);
            $rows[$index]['running_side']    = self::side($running, $nature);
        }

        $closingRaw = $running;

        return [
            'ledger' => [
                'id'                => (int) $ledger['id'],
                'name'              => $ledger['name'],
                'code'              => $ledger['code'],
                'type'              => (int) $ledger['type'],
                'chart_name'        => $ledger['chart_name'],
                'master_name'       => $ledger['master_name'],
                'master_type'       => (int) $ledger['master_type'],
                'nature'            => $nature->label(),
            ],
            'from'    => $from ?? '',
            'to'      => $to ?? date('Y-m-d'),
            'opening' => self::balanceBlock($openingRaw, $nature),
            'rows'    => $rows,
            'total'   => [
                'debit'  => round($totalDebit, 4),
                'credit' => round($totalCredit, 4),
            ],
            'closing' => self::balanceBlock($closingRaw, $nature),
        ];
    }

    /**
     * একটা লেজারের ব্যালেন্স, nature অনুযায়ী সাইন ঠিক করা।
     *
     * @return array{debit:float,credit:float,balance:float,side:string}
     */
    public static function balance(int $ledgerId, ?string $upto = null): array
    {
        $ledger = Ledger::withChart($ledgerId);

        if ($ledger === []) {
            throw new RuntimeException("Ledger not found (id=$ledgerId).");
        }

        $nature   = MasterAccountType::from((int) $ledger['master_type'])->nature();
        $uptoTime = $upto !== null && $upto !== '' ? Utility::toTime($upto) + 86399 : null;

        return self::balanceBlock(LedgerEntry::balance($ledgerId, $uptoTime), $nature);
    }

    /**
     * ট্রায়াল ব্যালেন্স — সব লেজারের ডেবিট/ক্রেডিট যোগফল।
     * ঠিকঠাক থাকলে `is_balanced` সবসময় true থাকবে।
     *
     * @return array{rows:array<int,array<string,mixed>>,total:array{debit:float,credit:float},is_balanced:bool}
     */
    public static function trialBalance(?string $from = null, ?string $to = null): array
    {
        $where    = [];
        $bindings = [];

        if ($from !== null && $from !== '') {
            $where[]    = 'e.time >= ?';
            $bindings[] = Utility::toTime($from);
        }

        if ($to !== null && $to !== '') {
            $where[]    = 'e.time <= ?';
            $bindings[] = Utility::toTime($to) + 86399;
        }

        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $rows = DB::select(
            "SELECT l.id, l.name, l.code,
                    c.name AS chart_name,
                    m.name AS master_name, m.type AS master_type,
                    COALESCE(SUM(e.debit), 0)  AS debit,
                    COALESCE(SUM(e.credit), 0) AS credit
               FROM a_ledger_entry e
               JOIN a_ledgers            l ON l.id = e.ledger_id
               JOIN a_chart_of_accounts  c ON c.id = l.chart_of_accounts_id
               JOIN a_master_account     m ON m.id = c.master_account_id
               $clause
           GROUP BY l.id, l.name, l.code, c.name, m.name, m.type
             HAVING debit <> 0 OR credit <> 0
           ORDER BY m.type, c.name, l.name",
            $bindings
        );

        $totalDebit  = 0.0;
        $totalCredit = 0.0;
        $out         = [];

        foreach ($rows as $row) {
            $debit  = (float) $row['debit'];
            $credit = (float) $row['credit'];
            $raw    = $debit - $credit;

            $totalDebit  += $debit;
            $totalCredit += $credit;

            $out[] = [
                'ledger_id'      => (int) $row['id'],
                'ledger_name'    => $row['name'],
                'ledger_code'    => $row['code'],
                'chart_name'     => $row['chart_name'],
                'master_name'    => $row['master_name'],
                'master_type'    => (int) $row['master_type'],
                'debit'          => round($debit, 4),
                'credit'         => round($credit, 4),
                'balance_debit'  => $raw > 0 ? round($raw, 4) : 0.0,
                'balance_credit' => $raw < 0 ? round(-$raw, 4) : 0.0,
            ];
        }

        return [
            'rows'        => $out,
            'total'       => ['debit' => round($totalDebit, 4), 'credit' => round($totalCredit, 4)],
            'is_balanced' => abs($totalDebit - $totalCredit) < 0.0001,
        ];
    }

    // -------------------------------------------------------------------------
    // ভেতরের কাজ
    // -------------------------------------------------------------------------

    /**
     * স্টেটমেন্টের লাইনগুলো — ভাউচারের কোড/টাইপ এবং "বিপরীত লেজার" সহ।
     *
     * @return array<int,array<string,mixed>>
     */
    private static function entries(int $ledgerId, ?int $fromTime, ?int $toTime): array
    {
        $where    = ['e.ledger_id = ?'];
        $bindings = [$ledgerId];

        if ($fromTime !== null) {
            $where[]    = 'e.time >= ?';
            $bindings[] = $fromTime;
        }

        if ($toTime !== null) {
            $where[]    = 'e.time <= ?';
            $bindings[] = $toTime;
        }

        $rows = DB::select(
            'SELECT e.id, e.code, e.voucher_id, e.debit, e.credit, e.note, e.time,
                    v.code AS voucher_code, v.type AS voucher_type, v.reference
               FROM a_ledger_entry e
               JOIN a_voucher_entry v ON v.id = e.voucher_id
              WHERE ' . implode(' AND ', $where) . '
           ORDER BY e.time, e.voucher_id, e.id',
            $bindings
        );

        if ($rows === []) {
            return [];
        }

        // প্রতিটা লাইনের "বিপরীত দিকের" লেজার — স্টেটমেন্টে "Particulars" কলামে দেখানো হয়
        $voucherIds = array_values(array_unique(array_map(
            static fn (array $r) => (int) $r['voucher_id'],
            $rows
        )));

        $placeholders = implode(', ', array_fill(0, count($voucherIds), '?'));

        $counterparts = Utility::groupBy(
            DB::select(
                "SELECT e.voucher_id, e.ledger_id, e.debit, e.credit, l.name, l.code
                   FROM a_ledger_entry e
                   JOIN a_ledgers l ON l.id = e.ledger_id
                  WHERE e.voucher_id IN ($placeholders) AND e.ledger_id <> ?",
                array_merge($voucherIds, [$ledgerId])
            ),
            'voucher_id'
        );

        $out = [];

        foreach ($rows as $row) {
            $others = $counterparts[$row['voucher_id']] ?? [];

            $out[] = [
                'id'             => (int) $row['id'],
                'code'           => (string) $row['code'],
                'voucher_id'     => (int) $row['voucher_id'],
                'voucher_code'   => (string) $row['voucher_code'],
                'voucher_type'   => VoucherType::labelByValue((int) $row['voucher_type']),
                'voucher_type_id' => (int) $row['voucher_type'],
                'reference'      => (string) $row['reference'],
                'time'           => (int) $row['time'],
                'date'           => date('d-m-Y', (int) $row['time']),
                'particulars'    => implode(', ', array_column($others, 'name')),
                'against'        => array_map(
                    static fn (array $o) => [
                        'ledger_id' => (int) $o['ledger_id'],
                        'name'      => $o['name'],
                        'code'      => $o['code'],
                    ],
                    $others
                ),
                'note'           => (string) $row['note'],
                'debit'          => (float) $row['debit'],
                'credit'         => (float) $row['credit'],
            ];
        }

        return $out;
    }

    /**
     * @return array{debit:float,credit:float,balance:float,side:string}
     */
    private static function balanceBlock(float $raw, TransactionType $nature): array
    {
        return [
            'debit'   => $raw > 0 ? round($raw, 4) : 0.0,
            'credit'  => $raw < 0 ? round(-$raw, 4) : 0.0,
            'balance' => round(self::sign($raw, $nature), 4),
            'side'    => self::side($raw, $nature),
        ];
    }

    /** nature অনুযায়ী সাইন ঠিক করা */
    private static function sign(float $raw, TransactionType $nature): float
    {
        return $nature === TransactionType::Debit ? $raw : -$raw;
    }

    private static function side(float $raw, TransactionType $nature): string
    {
        if (abs($raw) < 0.0001) {
            return '';
        }

        return $raw > 0 ? TransactionType::Debit->short() : TransactionType::Credit->short();
    }
}
