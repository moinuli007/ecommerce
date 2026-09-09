<?php

namespace App\Modules\Account\Services;

use App\Core\DB;
use App\Core\Utility;
use App\Enum\AutoChart;
use App\Enum\AutoLedger;
use App\Enum\LedgerType;
use App\Enum\MasterAccountType;
use App\Modules\Account\Models\ChartOfAccount;
use App\Modules\Account\Models\Ledger;
use App\Modules\Account\Models\MasterAccount;
use RuntimeException;

/**
 * চার্ট অব অ্যাকাউন্টস ম্যানেজমেন্ট — erp_saas এর app/Methods/LedgerAccounts.php এর সমতুল্য।
 *
 * মূল ধারণা: ফ্রেশ ইনস্টলে `a_master_account` / `a_chart_of_accounts` / `a_ledgers`
 * সব খালি থাকে। কোডে যখন প্রথমবার কোনো সিস্টেম লেজার দরকার হয় —
 *
 *     $cash = LedgerAccounts::systemLedger(AutoLedger::Cash);
 *
 * — তখন a_auto_* টেমপ্লেট থেকে মাস্টার → চার্ট → লেজার পুরো চেইনটা lazily তৈরি হয়ে যায়।
 * ফলে অ্যাকাউন্টস সেটআপ না করেও প্রথম দিন থেকেই অর্ডার/পারচেজ পোস্ট করা যায়।
 */
final class LedgerAccounts
{
    /** @var array<int,int> রিকোয়েস্ট-লেভেল ক্যাশ: auto_ledger id => a_ledgers.id */
    private static array $ledgerCache = [];

    /** @var array<int,int> auto_chart id => a_chart_of_accounts.id */
    private static array $chartCache = [];

    /** @var array<int,int> auto_master id => a_master_account.id */
    private static array $masterCache = [];

    // -------------------------------------------------------------------------
    // সিস্টেম অ্যাকাউন্ট (না থাকলে বানিয়ে দেয়)
    // -------------------------------------------------------------------------

    public static function systemLedger(AutoLedger $auto): int
    {
        if (isset(self::$ledgerCache[$auto->value])) {
            return self::$ledgerCache[$auto->value];
        }

        $existing = Ledger::byTypeAndRef(LedgerType::AutoLedger, $auto->value);

        if ($existing !== []) {
            return self::$ledgerCache[$auto->value] = (int) $existing['id'];
        }

        $template = DB::getById('a_auto_ledger', $auto->value);

        if ($template === []) {
            throw new RuntimeException(
                "Auto ledger template not found (id={$auto->value}) — "
                . 'has database/seed/002_account_auto.sql been run?'
            );
        }

        $chartId = self::systemChart(AutoChart::from((int) $template['auto_chart_of_accounts_id']));

        $ledgerId = self::createLedger(
            LedgerType::AutoLedger,
            $auto->value,
            (string) $template['name'],
            $chartId,
            (string) $template['code'],
            (int) $template['for_income'],
            (int) $template['for_expense']
        );

        return self::$ledgerCache[$auto->value] = $ledgerId;
    }

    /**
     * systemLedger() এর read-only সংস্করণ — না থাকলে **বানায় না**, `0` দেয়।
     *
     * রিপোর্ট/ড্যাশবোর্ডে এটাই ব্যবহার করবেন; একটা GET রিকোয়েস্ট যেন চার্টে
     * নতুন সারি তৈরি না করে ফেলে।
     */
    public static function findSystemLedger(AutoLedger $auto): int
    {
        if (isset(self::$ledgerCache[$auto->value])) {
            return self::$ledgerCache[$auto->value];
        }

        $existing = Ledger::byTypeAndRef(LedgerType::AutoLedger, $auto->value);

        return $existing === [] ? 0 : self::$ledgerCache[$auto->value] = (int) $existing['id'];
    }

    /** systemChart() এর read-only সংস্করণ */
    public static function findSystemChart(AutoChart $auto): int
    {
        if (isset(self::$chartCache[$auto->value])) {
            return self::$chartCache[$auto->value];
        }

        $existing = ChartOfAccount::bySystemChart($auto->value);

        return $existing === [] ? 0 : self::$chartCache[$auto->value] = (int) $existing['id'];
    }

    public static function systemChart(AutoChart $auto): int
    {
        if (isset(self::$chartCache[$auto->value])) {
            return self::$chartCache[$auto->value];
        }

        $existing = ChartOfAccount::bySystemChart($auto->value);

        if ($existing !== []) {
            return self::$chartCache[$auto->value] = (int) $existing['id'];
        }

        $template = DB::getById('a_auto_chart_of_accounts', $auto->value);

        if ($template === []) {
            throw new RuntimeException("Auto chart template not found (id={$auto->value})");
        }

        $masterId = self::systemMasterAccount(
            MasterAccountType::from((int) $template['auto_master_account_id'])
        );

        $chartId = ChartOfAccount::create([
            'master_account_id' => $masterId,
            'name'              => $template['name'],
            'code'              => $template['code'],
            'system_chart'      => $auto->value,
            'isActive'          => 1,
        ]);

        return self::$chartCache[$auto->value] = $chartId;
    }

    public static function systemMasterAccount(MasterAccountType $auto): int
    {
        if (isset(self::$masterCache[$auto->value])) {
            return self::$masterCache[$auto->value];
        }

        $existing = MasterAccount::bySystemRef($auto->value);

        if ($existing !== []) {
            return self::$masterCache[$auto->value] = (int) $existing['id'];
        }

        $template = DB::getById('a_auto_master_account', $auto->value);

        if ($template === []) {
            throw new RuntimeException("Auto master account template not found (id={$auto->value})");
        }

        $masterId = MasterAccount::create([
            'name'       => $template['name'],
            'code'       => $template['code'],
            'type'       => (int) $template['type'],
            'system_ref' => $auto->value,
            'isActive'   => 1,
        ]);

        return self::$masterCache[$auto->value] = $masterId;
    }

    // -------------------------------------------------------------------------
    // লেজার তৈরি
    // -------------------------------------------------------------------------

    /**
     * নতুন লেজার। `code` না দিলে LedgerType এর প্রিফিক্স + reference_id দিয়ে বানানো হয়।
     */
    public static function createLedger(
        LedgerType $type,
        int $referenceId,
        string $name,
        int $chartOfAccountsId,
        string $code = '',
        int $forIncome = 0,
        int $forExpense = 0
    ): int {
        if (trim($name) === '') {
            throw new RuntimeException('Ledger name cannot be empty.');
        }

        if ($code === '') {
            $code = $type === LedgerType::Custom
                ? CodeGenerator::next('ledger', $type->prefix())
                : $type->prefix() . '-' . $referenceId;
        }

        $data = [
            'chart_of_accounts_id' => $chartOfAccountsId,
            'name'                 => $name,
            'code'                 => $code,
            'type'                 => $type->value,
            'reference_id'         => $referenceId,
            'system_ledger'        => $type->isSystem() ? 1 : 0,
            'for_income'           => $forIncome,
            'for_expense'          => $forExpense,
            'isActive'             => 1,
        ];

        Utility::stampCreate($data);

        $ledgerId = DB::insert('a_ledgers', $data);

        if ($ledgerId === 0) {
            throw new RuntimeException("Could not create ledger: $name");
        }

        return $ledgerId;
    }

    /**
     * কাস্টমারের নিজস্ব লেজার (Accounts Receivable এর নিচে)।
     * `$customer` array-তে `ledger_id` থাকলে সেটাই ফেরত যায়, নাহলে বানিয়ে
     * `customers.ledger_id` আপডেট করে দেয়।
     *
     * @param array<string,mixed> $customer `id` ও `name` লাগবেই
     */
    public static function customerLedger(array &$customer): int
    {
        return self::partyLedger($customer, LedgerType::Customer, AutoChart::AccountsReceivable, 'customers');
    }

    /**
     * সাপ্লায়ারের নিজস্ব লেজার (Accounts Payable এর নিচে)।
     *
     * @param array<string,mixed> $supplier
     */
    public static function supplierLedger(array &$supplier): int
    {
        return self::partyLedger($supplier, LedgerType::Supplier, AutoChart::AccountsPayable, 'suppliers');
    }

    /**
     * @param array<string,mixed> $party
     */
    private static function partyLedger(array &$party, LedgerType $type, AutoChart $chart, string $table): int
    {
        if ((int) ($party['ledger_id'] ?? 0) > 0) {
            return (int) $party['ledger_id'];
        }

        if (!isset($party['id'], $party['name'])) {
            throw new RuntimeException($type->label() . ' is missing id or name.');
        }

        // আগে থেকে লেজার থাকতে পারে (ledger_id কলাম খালি হলেও)
        $existing = Ledger::byTypeAndRef($type, (int) $party['id']);

        if ($existing !== []) {
            $ledgerId = (int) $existing['id'];
        } else {
            $ledgerId = self::createLedger(
                $type,
                (int) $party['id'],
                (string) $party['name'],
                self::systemChart($chart)
            );
        }

        $party['ledger_id'] = $ledgerId;

        DB::update($table, ['ledger_id' => $ledgerId, 'updated_at' => time()], ['id' => (int) $party['id']]);

        return $ledgerId;
    }

    // -------------------------------------------------------------------------
    // পড়া
    // -------------------------------------------------------------------------

    /**
     * ড্রপডাউনের জন্য পুরো চার্ট ট্রি (master → chart → ledger)।
     *
     * @return array<int,array<string,mixed>>
     */
    public static function tree(): array
    {
        $masters = MasterAccount::active()->orderBy('code')->get();
        $charts  = Utility::groupBy(ChartOfAccount::active()->orderBy('code')->get(), 'master_account_id');
        $ledgers = Utility::groupBy(Ledger::active()->orderBy('name')->get(), 'chart_of_accounts_id');

        $tree = [];

        foreach ($masters as $master) {
            $type       = MasterAccountType::tryFrom((int) $master['type']);
            $masterNode = [
                'id'     => (int) $master['id'],
                'name'   => $master['name'],
                'code'   => $master['code'],
                'type'   => $master['type'],
                'nature' => $type?->nature()->label() ?? '',
                'charts' => [],
            ];

            foreach ($charts[$master['id']] ?? [] as $chart) {
                $masterNode['charts'][] = [
                    'id'      => (int) $chart['id'],
                    'name'    => $chart['name'],
                    'code'    => $chart['code'],
                    'ledgers' => array_map(
                        static fn (array $l) => [
                            'id'   => (int) $l['id'],
                            'name' => $l['name'],
                            'code' => $l['code'],
                            'type' => (int) $l['type'],
                        ],
                        $ledgers[$chart['id']] ?? []
                    ),
                ];
            }

            $tree[] = $masterNode;
        }

        return $tree;
    }

    /**
     * ভাউচার এন্ট্রি ফর্মের ড্রপডাউনের জন্য ফ্ল্যাট লিস্ট।
     *
     * @return array<int,array{id:int,name:string,code:string,chart:string}>
     */
    public static function options(): array
    {
        $rows = DB::select(
            'SELECT l.id, l.name, l.code, c.name AS chart
               FROM a_ledgers l
               JOIN a_chart_of_accounts c ON c.id = l.chart_of_accounts_id
              WHERE l.isActive = 1
           ORDER BY c.code, l.name'
        );

        return array_map(
            static fn (array $r) => [
                'id'    => (int) $r['id'],
                'name'  => $r['name'],
                'code'  => $r['code'],
                'chart' => $r['chart'],
            ],
            $rows
        );
    }

    /** টেস্টের জন্য ক্যাশ ক্লিয়ার */
    public static function clearCache(): void
    {
        self::$ledgerCache = [];
        self::$chartCache  = [];
        self::$masterCache = [];
    }
}
