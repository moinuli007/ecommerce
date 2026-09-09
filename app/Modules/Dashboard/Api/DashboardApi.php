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
                    'label' => 'Cash in Hand',
                    'value' => $cash,
                    'icon'  => 'wallet',
                    'tone'  => $cash < 0 ? 'danger' : 'brand',
                ],
                [
                    'key'   => 'bank',
                    'label' => 'Bank + Mobile Banking',
                    'value' => $bank,
                    'icon'  => 'wallet',
                    'tone'  => 'info',
                ],
                [
                    'key'   => 'receivable',
                    'label' => 'Receivable from Customers',
                    'value' => $received,
                    'icon'  => 'in',
                    'tone'  => 'success',
                ],
                [
                    'key'   => 'payable',
                    'label' => 'Payable to Suppliers',
                    'value' => $payable,
                    'icon'  => 'out',
                    'tone'  => 'warning',
                ],
                [
                    'key'   => 'sales_month',
                    'label' => 'Sales This Month',
                    'value' => $salesThisMonth,
                    'icon'  => 'trend-up',
                    'tone'  => 'success',
                ],
                [
                    'key'   => 'expense_month',
                    'label' => 'Expense This Month',
                    'value' => $expenseThisMonth,
                    'icon'  => 'trend-down',
                    'tone'  => 'danger',
                ],
            ],
            'pending' => [
                ['label' => 'COD Stuck at Courier', 'value' => $codPending],
                ['label' => 'Pending at Gateway',   'value' => $gatewayPending],
                ['label' => 'Stock Value',          'value' => $inventory],
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
            $hour < 6  => 'Good Night',
            $hour < 12 => 'Good Morning',
            $hour < 16 => 'Good Afternoon',
            $hour < 19 => 'Good Evening',
            default    => 'Good Evening',
        };
    }
}
