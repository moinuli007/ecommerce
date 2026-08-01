<?php

namespace App\Modules\Dashboard\Api;

use App\Core\Auth;
use App\Core\Response;
use App\Enum\AutoChart;
use App\Enum\AutoLedger;
use App\Modules\Account\Services\AccountSummary;
use App\Modules\Account\Services\LedgerStatement;
use App\Modules\Account\Services\VoucherDetails;

/**
 * অ্যাডমিন ড্যাশবোর্ডের ডেটা।
 *
 * বাকি সব Api ক্লাসের মতোই payload array ফেরত দেয় — HTTP রাউট আর
 * DashboardController দুই জায়গা থেকেই একই মেথড কল হয় (সিদ্ধান্ত D-04)।
 *
 * ফেজ ১-এ শুধু হিসাবের সংখ্যা আছে। ফেজ ৩-এ অর্ডার এলে এখানে
 * আজকের অর্ডার / পেন্ডিং শিপমেন্ট / স্টক অ্যালার্ট যোগ হবে।
 */
final class DashboardApi
{
    /**
     * GET /api/v1/dashboard
     *
     * @return array<string,mixed>
     */
    public static function index(): array
    {
        $monthStart = strtotime('first day of this month midnight');
        $monthEnd   = strtotime('last day of this month 23:59:59');

        $cash     = AccountSummary::systemBalance(AutoLedger::Cash);
        $bank     = AccountSummary::systemBalance(AutoLedger::Bank, AutoLedger::MobileBanking);
        $received = AccountSummary::chartBalance(AutoChart::AccountsReceivable);
        $payable  = AccountSummary::chartBalance(AutoChart::AccountsPayable);

        $salesThisMonth = AccountSummary::systemMovement($monthStart, $monthEnd, AutoLedger::Sales);
        $expenseThisMonth = AccountSummary::systemMovement(
            $monthStart,
            $monthEnd,
            AutoLedger::DeliveryExpense,
            AutoLedger::GatewayFee,
            AutoLedger::OtherExpense
        );

        $codPending     = AccountSummary::systemBalance(AutoLedger::CodReceivable);
        $gatewayPending = AccountSummary::systemBalance(AutoLedger::GatewayReceivable);
        $inventory      = AccountSummary::systemBalance(AutoLedger::Inventory);

        $trial = LedgerStatement::trialBalance();

        return Response::success('', [
            'greeting' => self::greeting(),
            'user'     => ['name' => Auth::user()['name'] ?? ''],
            'period'   => [
                'month_label' => date('F Y'),
                'from'        => date('Y-m-d', $monthStart),
                'to'          => date('Y-m-d', $monthEnd),
            ],
            'stats' => [
                [
                    'key'   => 'cash',
                    'label' => 'ক্যাশ ইন হ্যান্ড',
                    'value' => $cash,
                    'icon'  => 'wallet',
                    'tone'  => $cash < 0 ? 'danger' : 'brand',
                ],
                [
                    'key'   => 'bank',
                    'label' => 'ব্যাংক + মোবাইল ব্যাংকিং',
                    'value' => $bank,
                    'icon'  => 'wallet',
                    'tone'  => 'info',
                ],
                [
                    'key'   => 'receivable',
                    'label' => 'কাস্টমারের কাছে পাওনা',
                    'value' => $received,
                    'icon'  => 'in',
                    'tone'  => 'success',
                ],
                [
                    'key'   => 'payable',
                    'label' => 'সাপ্লায়ারকে দেনা',
                    'value' => $payable,
                    'icon'  => 'out',
                    'tone'  => 'warning',
                ],
                [
                    'key'   => 'sales_month',
                    'label' => 'এ মাসের বিক্রি',
                    'value' => $salesThisMonth,
                    'icon'  => 'trend-up',
                    'tone'  => 'success',
                ],
                [
                    'key'   => 'expense_month',
                    'label' => 'এ মাসের খরচ',
                    'value' => $expenseThisMonth,
                    'icon'  => 'trend-down',
                    'tone'  => 'danger',
                ],
            ],
            'pending' => [
                ['label' => 'কুরিয়ারে আটকে থাকা COD', 'value' => $codPending],
                ['label' => 'গেটওয়েতে জমা',            'value' => $gatewayPending],
                ['label' => 'স্টকের মূল্য',             'value' => $inventory],
            ],
            'daily_sales'     => AccountSummary::dailySales(14),
            'master_summary'  => AccountSummary::byMaster(),
            'top_ledgers'     => AccountSummary::topLedgers(8),
            'recent_vouchers' => VoucherDetails::list(['per_page' => 8, 'page' => 1]),
            'trial_balance'   => [
                'is_balanced' => $trial['is_balanced'],
                'debit'       => $trial['total']['debit'],
                'credit'      => $trial['total']['credit'],
                'ledgers'     => count($trial['rows']),
            ],
        ]);
    }

    private static function greeting(): string
    {
        $hour = (int) date('G');

        return match (true) {
            $hour < 6  => 'শুভ রাত্রি',
            $hour < 12 => 'শুভ সকাল',
            $hour < 16 => 'শুভ দুপুর',
            $hour < 19 => 'শুভ বিকাল',
            default    => 'শুভ সন্ধ্যা',
        };
    }
}
