<?php

namespace App\Modules\Account\Models;

use App\Core\DB;
use App\Core\Model;

final class LedgerEntry extends Model
{
    protected static string $table = 'a_ledger_entry';

    /** @return array<int,array<string,mixed>> */
    public static function byVoucher(int $voucherId): array
    {
        return static::where('voucher_id', $voucherId)->orderBy('debit', 'DESC')->get();
    }

    /**
     * @param  array<int,int> $voucherIds
     * @return array<int,array<string,mixed>>
     */
    public static function byVouchers(array $voucherIds): array
    {
        return static::whereIn('voucher_id', $voucherIds)->orderBy('voucher_id')->orderBy('debit', 'DESC')->get();
    }

    /**
     * একটা লেজারের র' ব্যালেন্স = SUM(debit) − SUM(credit)।
     * পজিটিভ মানে ডেবিট ব্যালেন্স, নেগেটিভ মানে ক্রেডিট ব্যালেন্স।
     *
     * অ্যাকাউন্টের nature অনুযায়ী সাইন ঠিক করতে LedgerStatement::balance() ব্যবহার করুন।
     */
    public static function balance(int $ledgerId, ?int $upto = null): float
    {
        $sql      = 'SELECT COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) AS balance
                       FROM a_ledger_entry WHERE ledger_id = ?';
        $bindings = [$ledgerId];

        if ($upto !== null) {
            $sql       .= ' AND time <= ?';
            $bindings[] = $upto;
        }

        return (float) DB::scalar($sql, $bindings, 0);
    }

    /**
     * অনেক লেজারের ব্যালেন্স একসাথে।
     *
     * @param  array<int,int> $ledgerIds
     * @return array<int,float> ledger_id => balance
     */
    public static function balances(array $ledgerIds, ?int $upto = null): array
    {
        if ($ledgerIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ledgerIds), '?'));
        $bindings     = array_values($ledgerIds);

        $sql = "SELECT ledger_id, COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) AS balance
                  FROM a_ledger_entry
                 WHERE ledger_id IN ($placeholders)";

        if ($upto !== null) {
            $sql       .= ' AND time <= ?';
            $bindings[] = $upto;
        }

        $sql .= ' GROUP BY ledger_id';

        $out = [];

        foreach (DB::select($sql, $bindings) as $row) {
            $out[(int) $row['ledger_id']] = (float) $row['balance'];
        }

        return $out;
    }
}
