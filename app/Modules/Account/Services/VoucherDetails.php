<?php

namespace App\Modules\Account\Services;

use App\Core\DB;
use App\Core\QueryBuilder;
use App\Core\Utility;
use App\Enum\VoucherType;
use App\Modules\Account\Models\Ledger;
use App\Modules\Account\Models\VoucherEntry;

/**
 * ভাউচার পড়ার সব লজিক — school এর App\Services\Account\Vouchers::voucherDetails()
 * এর সমতুল্য, তবে N+1 কোয়েরি ছাড়া (ভাউচার → এন্ট্রি → লেজার, মোট ৩টা কোয়েরি)।
 *
 * রিটার্ন শেপ (প্রতিটি ভাউচার):
 * {
 *   id, code, time, date, type, type_id, reference, note, amount, amount_in_words,
 *   created_by, created_by_name,
 *   debit_ledger_id, debit_ledger_name, debit_ledger_code,
 *   credit_ledger_id, credit_ledger_name, credit_ledger_code,
 *   is_compound,                        // ২ লাইনের বেশি হলে true
 *   lines: [ {id, ledger_id, ledger_name, ledger_code, debit, credit, note}, ... ]
 * }
 *
 * কম্পাউন্ড ভাউচারে `debit_ledger_*` এ প্রথম ডেবিট লাইনটা বসে —
 * পুরো ছবি দেখতে `lines` ব্যবহার করুন।
 */
final class VoucherDetails
{
    public const DEFAULT_PER_PAGE = 50;

    /**
     * ফিল্টার দিয়ে ভাউচার লিস্ট।
     *
     * সাপোর্টেড ফিল্টার:
     *   voucher_id  int|int[]      নির্দিষ্ট ভাউচার
     *   type        int|int[]      VoucherType ভ্যালু
     *   ledger_id   int|int[]      এই লেজার আছে এমন ভাউচার
     *   reference   string|array   সোর্স ডকুমেন্ট
     *   user_id     int|int[]      কে বানিয়েছে
     *   from / to   string         তারিখ (YYYY-MM-DD), inclusive
     *   code        string         কোড দিয়ে খোঁজা (LIKE)
     *   page        int            ডিফল্ট 1
     *   per_page    int            ডিফল্ট 50, সর্বোচ্চ 500; 0 দিলে সব
     *
     * @param  array<string,mixed> $filters
     * @return array{data:array<int,array<string,mixed>>,total:int,page:int,per_page:int}
     */
    public static function search(array $filters = []): array
    {
        $query = self::baseQuery($filters);
        $total = $query->count();

        $perPage = (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE);
        $page    = max(1, (int) ($filters['page'] ?? 1));

        $listQuery = self::baseQuery($filters)->orderBy('time', 'DESC')->orderBy('id', 'DESC');

        if ($perPage > 0) {
            $listQuery->page($page, min($perPage, 500));
        }

        $vouchers = $listQuery->get();

        return [
            'data'     => self::hydrate($vouchers),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * ফিল্টার ছাড়া শুধু লিস্ট (pagination মোড়ক ছাড়া)।
     *
     * @param  array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    public static function list(array $filters = []): array
    {
        return self::search($filters)['data'];
    }

    /** @return array<string,mixed> না পেলে খালি array */
    public static function byId(int $voucherId): array
    {
        $rows = self::hydrate([VoucherEntry::find($voucherId)] );

        return $rows[0] ?? [];
    }

    /** @return array<string,mixed> */
    public static function byCode(string $code): array
    {
        $voucher = VoucherEntry::byCode($code);

        if ($voucher === []) {
            return [];
        }

        return self::hydrate([$voucher])[0] ?? [];
    }

    /**
     * সোর্স ডকুমেন্টের ভাউচারগুলো — যেমন একটা অর্ডারের সব হিসাব।
     *
     * @param  VoucherType|array<int,VoucherType> $types
     * @return array<int,array<string,mixed>>
     */
    public static function byReference(VoucherType|array $types, string $reference): array
    {
        // ⚠ backed enum এ (array) কাস্ট করা যাবে না — ওটা enum এর প্রপার্টি array দেয়
        $types = is_array($types) ? $types : [$types];

        return self::list([
            'type'      => array_map(static fn (VoucherType $t) => $t->value, $types),
            'reference' => $reference,
            'per_page'  => 0,
        ]);
    }

    // -------------------------------------------------------------------------
    // ভেতরের কাজ
    // -------------------------------------------------------------------------

    /** @param array<string,mixed> $filters */
    private static function baseQuery(array $filters): QueryBuilder
    {
        $query = VoucherEntry::query();

        // ledger_id ফিল্টার — আগে ওই লেজারের ভাউচার আইডিগুলো বের করি
        if (!empty($filters['ledger_id'])) {
            $ledgerIds  = array_map('intval', (array) $filters['ledger_id']);
            $voucherIds = array_values(array_unique(array_map(
                'intval',
                DB::table('a_ledger_entry')->whereIn('ledger_id', $ledgerIds)->pluck('voucher_id')
            )));

            $query->whereIn('id', $voucherIds === [] ? [0] : $voucherIds);
        }

        if (!empty($filters['voucher_id'])) {
            $query->whereIn('id', array_map('intval', (array) $filters['voucher_id']));
        }

        if (!empty($filters['type'])) {
            $query->whereIn('type', array_map('intval', (array) $filters['type']));
        }

        if (isset($filters['reference']) && $filters['reference'] !== '') {
            $query->whereIn('reference', array_map('strval', (array) $filters['reference']));
        }

        if (!empty($filters['user_id'])) {
            $query->whereIn('created_by', array_map('intval', (array) $filters['user_id']));
        }

        if (!empty($filters['from'])) {
            $query->where('time', Utility::toTime((string) $filters['from']), '>=');
        }

        if (!empty($filters['to'])) {
            // to তারিখটা inclusive — ওই দিনের শেষ সেকেন্ড পর্যন্ত
            $query->where('time', Utility::toTime((string) $filters['to']) + 86399, '<=');
        }

        if (!empty($filters['code'])) {
            $query->whereLike('code', (string) $filters['code']);
        }

        return $query;
    }

    /**
     * ভাউচার row গুলোতে লেজার এন্ট্রি + লেজারের নাম জুড়ে দেয়।
     *
     * @param  array<int,array<string,mixed>> $vouchers
     * @return array<int,array<string,mixed>>
     */
    private static function hydrate(array $vouchers): array
    {
        $vouchers = array_values(array_filter($vouchers, static fn ($v) => $v !== []));

        if ($vouchers === []) {
            return [];
        }

        $voucherIds = array_map(static fn (array $v) => (int) $v['id'], $vouchers);

        $entriesByVoucher = Utility::groupBy(
            DB::table('a_ledger_entry')
                ->whereIn('voucher_id', $voucherIds)
                ->orderBy('debit', 'DESC')
                ->orderBy('id')
                ->get(),
            'voucher_id'
        );

        $ledgerIds = [];

        foreach ($entriesByVoucher as $entries) {
            foreach ($entries as $entry) {
                $ledgerIds[(int) $entry['ledger_id']] = true;
            }
        }

        $ledgers = $ledgerIds === []
            ? []
            : Utility::keyBy(Ledger::whereIn('id', array_keys($ledgerIds))->get());

        $userIds = array_values(array_unique(array_map(
            static fn (array $v) => (int) $v['created_by'],
            $vouchers
        )));

        $users = $userIds === []
            ? []
            : Utility::keyBy(DB::table('users')->whereIn('id', $userIds)->select('id', 'name')->get());

        $out = [];

        foreach ($vouchers as $voucher) {
            $out[] = self::buildOne($voucher, $entriesByVoucher[$voucher['id']] ?? [], $ledgers, $users);
        }

        return $out;
    }

    /**
     * @param  array<string,mixed>                 $voucher
     * @param  array<int,array<string,mixed>>      $entries
     * @param  array<int,array<string,mixed>>      $ledgers
     * @param  array<int,array<string,mixed>>      $users
     * @return array<string,mixed>
     */
    private static function buildOne(array $voucher, array $entries, array $ledgers, array $users): array
    {
        $type = VoucherType::tryFrom((int) $voucher['type']);

        $details = [
            'id'                  => (int) $voucher['id'],
            'code'                => (string) $voucher['code'],
            'time'                => (int) $voucher['time'],
            'date'                => date('Y-m-d', (int) $voucher['time']),
            'date_display'        => date('d-m-Y', (int) $voucher['time']),
            'type'                => $type?->label() ?? (string) $voucher['type'],
            'type_id'             => (int) $voucher['type'],
            'type_code'           => $type?->code() ?? '',
            'reference'           => (string) $voucher['reference'],
            'note'                => (string) $voucher['note'],
            'created_by'          => (int) $voucher['created_by'],
            'created_by_name'     => $users[(int) $voucher['created_by']]['name'] ?? '',
            'created_at'          => (int) $voucher['created_at'],
            'amount'              => 0.0,
            'is_compound'         => count($entries) > 2,
            'debit_ledger_id'     => 0,
            'debit_ledger_name'   => '',
            'debit_ledger_code'   => '',
            'credit_ledger_id'    => 0,
            'credit_ledger_name'  => '',
            'credit_ledger_code'  => '',
            'lines'               => [],
        ];

        $totalDebit = 0.0;

        foreach ($entries as $entry) {
            $ledgerId = (int) $entry['ledger_id'];
            $ledger   = $ledgers[$ledgerId] ?? [];
            $debit    = (float) $entry['debit'];
            $credit   = (float) $entry['credit'];

            $totalDebit += $debit;

            $details['lines'][] = [
                'id'          => (int) $entry['id'],
                'code'        => (string) $entry['code'],
                'ledger_id'   => $ledgerId,
                'ledger_name' => $ledger['name'] ?? '',
                'ledger_code' => $ledger['code'] ?? '',
                'debit'       => $debit,
                'credit'      => $credit,
                'note'        => (string) $entry['note'],
            ];

            if ($debit > 0 && $details['debit_ledger_id'] === 0) {
                $details['debit_ledger_id']   = $ledgerId;
                $details['debit_ledger_name'] = $ledger['name'] ?? '';
                $details['debit_ledger_code'] = $ledger['code'] ?? '';
            }

            if ($credit > 0 && $details['credit_ledger_id'] === 0) {
                $details['credit_ledger_id']   = $ledgerId;
                $details['credit_ledger_name'] = $ledger['name'] ?? '';
                $details['credit_ledger_code'] = $ledger['code'] ?? '';
            }

            if ($details['note'] === '' && $entry['note'] !== '') {
                $details['note'] = (string) $entry['note'];
            }
        }

        $details['amount']          = round($totalDebit, 4);
        $details['amount_in_words'] = Utility::amountInWords($details['amount']);

        return $details;
    }
}
