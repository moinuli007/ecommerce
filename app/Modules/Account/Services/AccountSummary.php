<?php

namespace App\Modules\Account\Services;

use App\Core\DB;
use App\Enum\AutoChart;
use App\Enum\AutoLedger;
use App\Enum\MasterAccountType;
use App\Enum\TransactionType;

/**
 * ড্যাশবোর্ড ও রিপোর্টের জন্য যোগফল।
 *
 * সবগুলো মেথড **read-only** — চার্টে নতুন কিছু তৈরি করে না
 * (`LedgerAccounts::findSystemLedger()` ব্যবহার করে, `systemLedger()` নয়)।
 * ফলে ড্যাশবোর্ড দেখলেই খালি লেজার তৈরি হয়ে যায় না।
 */
final class AccountSummary
{
    /**
     * এক বা একাধিক সিস্টেম লেজারের মোট ব্যালেন্স, nature অনুযায়ী সাইন ঠিক করা।
     * যে লেজারগুলো এখনো তৈরি হয়নি সেগুলো `0` ধরা হয়।
     */
    public static function systemBalance(AutoLedger ...$ledgers): float
    {
        $ids = [];

        foreach ($ledgers as $ledger) {
            $id = LedgerAccounts::findSystemLedger($ledger);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            return 0.0;
        }

        return self::signedBalanceOf($ids);
    }

    /**
     * একটা সিস্টেম চার্টের নিচের সব লেজারের মোট ব্যালেন্স।
     * যেমন AccountsReceivable → সব কাস্টমারের মোট পাওনা।
     */
    public static function chartBalance(AutoChart $chart): float
    {
        $chartId = LedgerAccounts::findSystemChart($chart);

        if ($chartId === 0) {
            return 0.0;
        }

        $row = DB::selectOne(
            'SELECT COALESCE(SUM(e.debit), 0) - COALESCE(SUM(e.credit), 0) AS raw,
                    m.type AS master_type
               FROM a_ledger_entry e
               JOIN a_ledgers           l ON l.id = e.ledger_id
               JOIN a_chart_of_accounts c ON c.id = l.chart_of_accounts_id
               JOIN a_master_account    m ON m.id = c.master_account_id
              WHERE l.chart_of_accounts_id = ?
           GROUP BY m.type',
            [$chartId]
        );

        if ($row === []) {
            return 0.0;
        }

        $nature = MasterAccountType::from((int) $row['master_type'])->nature();

        return round(self::sign((float) $row['raw'], $nature), 4);
    }

    /**
     * একটা সময়সীমায় সিস্টেম লেজারগুলোর লেনদেনের পরিমাণ (ব্যালেন্স নয়, movement)।
     * "এ মাসের বিক্রি" টাইপ সংখ্যার জন্য।
     */
    public static function systemMovement(int $from, int $to, AutoLedger ...$ledgers): float
    {
        $ids = [];

        foreach ($ledgers as $ledger) {
            $id = LedgerAccounts::findSystemLedger($ledger);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            return 0.0;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));

        $row = DB::selectOne(
            "SELECT COALESCE(SUM(e.debit), 0) - COALESCE(SUM(e.credit), 0) AS raw,
                    MIN(m.type) AS master_type
               FROM a_ledger_entry e
               JOIN a_ledgers           l ON l.id = e.ledger_id
               JOIN a_chart_of_accounts c ON c.id = l.chart_of_accounts_id
               JOIN a_master_account    m ON m.id = c.master_account_id
              WHERE e.ledger_id IN ($placeholders) AND e.time BETWEEN ? AND ?",
            array_merge($ids, [$from, $to])
        );

        if ($row === [] || $row['master_type'] === null) {
            return 0.0;
        }

        $nature = MasterAccountType::from((int) $row['master_type'])->nature();

        return round(self::sign((float) $row['raw'], $nature), 4);
    }

    /**
     * মাস্টার অ্যাকাউন্ট অনুযায়ী মোট — ব্যালেন্স শিট/ইনকাম স্টেটমেন্টের ভিত্তি।
     *
     * @return array<int,array{type:int,name:string,nature:string,balance:float,balance_sheet:bool}>
     */
    public static function byMaster(?int $from = null, ?int $to = null): array
    {
        $where    = [];
        $bindings = [];

        if ($from !== null) {
            $where[]    = 'e.time >= ?';
            $bindings[] = $from;
        }

        if ($to !== null) {
            $where[]    = 'e.time <= ?';
            $bindings[] = $to;
        }

        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $rows = DB::select(
            "SELECT m.type,
                    COALESCE(SUM(e.debit), 0) - COALESCE(SUM(e.credit), 0) AS raw
               FROM a_ledger_entry e
               JOIN a_ledgers           l ON l.id = e.ledger_id
               JOIN a_chart_of_accounts c ON c.id = l.chart_of_accounts_id
               JOIN a_master_account    m ON m.id = c.master_account_id
               $clause
           GROUP BY m.type
           ORDER BY m.type",
            $bindings
        );

        $out = [];

        foreach ($rows as $row) {
            $type = MasterAccountType::from((int) $row['type']);

            $out[] = [
                'type'          => $type->value,
                'name'          => $type->label(),
                'nature'        => $type->nature()->label(),
                'balance'       => round(self::sign((float) $row['raw'], $type->nature()), 4),
                'balance_sheet' => $type->isBalanceSheet(),
            ];
        }

        return $out;
    }

    /**
     * সবচেয়ে বেশি ব্যালেন্স আছে এমন লেজারগুলো।
     *
     * @return array<int,array<string,mixed>>
     */
    public static function topLedgers(int $limit = 8): array
    {
        $rows = DB::select(
            'SELECT l.id, l.name, l.code,
                    c.name AS chart_name,
                    m.type AS master_type,
                    COALESCE(SUM(e.debit), 0) - COALESCE(SUM(e.credit), 0) AS raw
               FROM a_ledger_entry e
               JOIN a_ledgers           l ON l.id = e.ledger_id
               JOIN a_chart_of_accounts c ON c.id = l.chart_of_accounts_id
               JOIN a_master_account    m ON m.id = c.master_account_id
           GROUP BY l.id, l.name, l.code, c.name, m.type
             HAVING raw <> 0
           ORDER BY ABS(raw) DESC
              LIMIT ' . max(1, min($limit, 50))
        );

        $out = [];

        foreach ($rows as $row) {
            $nature  = MasterAccountType::from((int) $row['master_type'])->nature();
            $balance = round(self::sign((float) $row['raw'], $nature), 4);

            $out[] = [
                'id'         => (int) $row['id'],
                'name'       => $row['name'],
                'code'       => $row['code'],
                'chart_name' => $row['chart_name'],
                'balance'    => $balance,
                'side'       => (float) $row['raw'] > 0 ? 'Dr' : 'Cr',
            ];
        }

        return $out;
    }

    /**
     * শেষ কয়েক দিনের দৈনিক বিক্রি — ড্যাশবোর্ডের চার্টের জন্য।
     *
     * @return array<int,array{date:string,label:string,amount:float}>
     */
    public static function dailySales(int $days = 14): array
    {
        $salesLedger = LedgerAccounts::findSystemLedger(AutoLedger::Sales);
        $days        = max(1, min($days, 90));
        $start       = strtotime('today -' . ($days - 1) . ' days');

        $totals = [];

        if ($salesLedger > 0) {
            $rows = DB::select(
                'SELECT FROM_UNIXTIME(e.time, "%Y-%m-%d") AS d,
                        COALESCE(SUM(e.credit), 0) - COALESCE(SUM(e.debit), 0) AS amount
                   FROM a_ledger_entry e
                  WHERE e.ledger_id = ? AND e.time >= ?
               GROUP BY d',
                [$salesLedger, $start]
            );

            foreach ($rows as $row) {
                $totals[$row['d']] = (float) $row['amount'];
            }
        }

        $out = [];

        for ($i = 0; $i < $days; $i++) {
            $time = strtotime("+$i days", $start);
            $key  = date('Y-m-d', $time);

            $out[] = [
                'date'   => $key,
                'label'  => date('d M', $time),
                'amount' => round($totals[$key] ?? 0.0, 2),
            ];
        }

        return $out;
    }

    // -------------------------------------------------------------------------

    /** @param array<int,int> $ledgerIds */
    private static function signedBalanceOf(array $ledgerIds): float
    {
        $placeholders = implode(', ', array_fill(0, count($ledgerIds), '?'));

        $rows = DB::select(
            "SELECT m.type,
                    COALESCE(SUM(e.debit), 0) - COALESCE(SUM(e.credit), 0) AS raw
               FROM a_ledger_entry e
               JOIN a_ledgers           l ON l.id = e.ledger_id
               JOIN a_chart_of_accounts c ON c.id = l.chart_of_accounts_id
               JOIN a_master_account    m ON m.id = c.master_account_id
              WHERE e.ledger_id IN ($placeholders)
           GROUP BY m.type",
            $ledgerIds
        );

        $total = 0.0;

        foreach ($rows as $row) {
            $nature = MasterAccountType::from((int) $row['type'])->nature();
            $total += self::sign((float) $row['raw'], $nature);
        }

        return round($total, 4);
    }

    private static function sign(float $raw, TransactionType $nature): float
    {
        return $nature === TransactionType::Debit ? $raw : -$raw;
    }
}
