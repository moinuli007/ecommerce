<?php

namespace App\Modules\Account\Models;

use App\Core\DB;
use App\Core\Model;
use App\Core\Utility;
use App\Enum\LedgerType;

final class Ledger extends Model
{
    protected static string $table = 'a_ledgers';

    /** @return array<string,mixed> */
    public static function byTypeAndRef(LedgerType $type, int $referenceId): array
    {
        return static::query()
            ->where('type', $type->value)
            ->where('reference_id', $referenceId)
            ->first();
    }

    /** @return array<int,array<string,mixed>> */
    public static function byChart(int $chartOfAccountsId): array
    {
        return static::active()->where('chart_of_accounts_id', $chartOfAccountsId)->orderBy('name')->get();
    }

    /**
     * ইনকাম/এক্সপেন্স ড্রপডাউনের জন্য।
     *
     * @return array<int,array<string,mixed>>
     */
    public static function forIncome(): array
    {
        return static::active()->where('for_income', 1)->orderBy('name')->get();
    }

    /** @return array<int,array<string,mixed>> */
    public static function forExpense(): array
    {
        return static::active()->where('for_expense', 1)->orderBy('name')->get();
    }

    /**
     * চার্ট + মাস্টার সহ পুরো তথ্য (স্টেটমেন্ট/রিপোর্টে লাগে)।
     *
     * @return array<string,mixed>
     */
    public static function withChart(int $ledgerId): array
    {
        return DB::selectOne(
            'SELECT l.*,
                    c.id   AS chart_id,
                    c.name AS chart_name,
                    c.code AS chart_code,
                    m.id   AS master_account_id,
                    m.name AS master_name,
                    m.type AS master_type
               FROM a_ledgers l
               JOIN a_chart_of_accounts c ON c.id = l.chart_of_accounts_id
               JOIN a_master_account   m ON m.id = c.master_account_id
              WHERE l.id = ?
              LIMIT 1',
            [$ledgerId]
        );
    }

    /**
     * একাধিক লেজারের চার্ট+মাস্টার তথ্য একসাথে (N+1 এড়াতে)।
     *
     * @param  array<int,int> $ledgerIds
     * @return array<int,array<string,mixed>> id দিয়ে কি করা
     */
    public static function withChartMany(array $ledgerIds): array
    {
        if ($ledgerIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ledgerIds), '?'));

        $rows = DB::select(
            "SELECT l.*,
                    c.id   AS chart_id,
                    c.name AS chart_name,
                    c.code AS chart_code,
                    m.id   AS master_account_id,
                    m.name AS master_name,
                    m.type AS master_type
               FROM a_ledgers l
               JOIN a_chart_of_accounts c ON c.id = l.chart_of_accounts_id
               JOIN a_master_account   m ON m.id = c.master_account_id
              WHERE l.id IN ($placeholders)",
            array_values($ledgerIds)
        );

        return Utility::keyBy($rows);
    }
}
