<?php

namespace App\Modules\Account\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Enum\LedgerType;
use App\Enum\MasterAccountType;
use App\Modules\Account\Models\ChartOfAccount;
use App\Modules\Account\Models\Ledger;
use App\Modules\Account\Services\LedgerAccounts;
use App\Modules\Account\Services\LedgerStatement;
use RuntimeException;

/**
 * চার্ট অব অ্যাকাউন্টস ও লেজার স্টেটমেন্ট API।
 * VoucherApi এর মতোই — payload array ফেরত দেয়, HTTP আর web দুই জায়গা থেকেই কল হয়।
 */
final class LedgerApi
{
    /**
     * GET /api/v1/ledgers
     * ফিল্টার: chart_id, type, for_income, for_expense, search
     *
     * @return array<string,mixed>
     */
    public static function index(): array
    {
        $query = Ledger::active();

        if (Request::int('chart_id') > 0) {
            $query->where('chart_of_accounts_id', Request::int('chart_id'));
        }

        if (Request::int('type') > 0) {
            $query->where('type', Request::int('type'));
        }

        if (Request::int('for_income') === 1) {
            $query->where('for_income', 1);
        }

        if (Request::int('for_expense') === 1) {
            $query->where('for_expense', 1);
        }

        if (Request::string('search') !== '') {
            $query->whereLike('name', Request::string('search'));
        }

        return Response::success('', ['ledgers' => $query->orderBy('name')->get()]);
    }

    /**
     * GET /api/v1/ledgers/tree — master → chart → ledger পুরো গাছ
     *
     * @return array<string,mixed>
     */
    public static function tree(): array
    {
        return Response::success('', [
            'tree'          => LedgerAccounts::tree(),
            'master_types'  => MasterAccountType::all(),
            'ledger_types'  => LedgerType::all(),
        ]);
    }

    /**
     * GET /api/v1/ledgers/options — ড্রপডাউনের ফ্ল্যাট লিস্ট
     *
     * @return array<string,mixed>
     */
    public static function options(): array
    {
        return Response::success('', ['ledgers' => LedgerAccounts::options()]);
    }

    /**
     * POST /api/v1/ledgers — ম্যানুয়াল লেজার তৈরি
     *
     * @return array<string,mixed>
     */
    public static function store(): array
    {
        if (!Validator::check(Request::all(), [
            'name'     => 'required|max:191',
            'chart_id' => 'required|int',
        ])) {
            return Response::payload();
        }

        $chart = ChartOfAccount::find(Request::int('chart_id'));

        if ($chart === []) {
            return Response::error('চার্ট অব অ্যাকাউন্ট পাওয়া যায়নি।');
        }

        try {
            $ledgerId = LedgerAccounts::createLedger(
                LedgerType::Custom,
                0,
                Request::string('name'),
                (int) $chart['id'],
                Request::string('code'),
                Request::int('for_income'),
                Request::int('for_expense')
            );
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('লেজার তৈরি হয়েছে।', ['ledger' => Ledger::find($ledgerId)]);
    }

    /**
     * GET /api/v1/ledgers/{id}/statement?from=&to=
     *
     * @return array<string,mixed>
     */
    public static function statement(): array
    {
        try {
            $statement = LedgerStatement::statement(
                Request::paramInt('id'),
                Request::string('from') ?: null,
                Request::string('to') ?: null
            );
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('', $statement);
    }

    /**
     * GET /api/v1/ledgers/{id}/balance?upto=
     *
     * @return array<string,mixed>
     */
    public static function balance(): array
    {
        try {
            $balance = LedgerStatement::balance(
                Request::paramInt('id'),
                Request::string('upto') ?: null
            );
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('', ['balance' => $balance]);
    }

    /**
     * GET /api/v1/reports/trial-balance?from=&to=
     *
     * @return array<string,mixed>
     */
    public static function trialBalance(): array
    {
        return Response::success('', LedgerStatement::trialBalance(
            Request::string('from') ?: null,
            Request::string('to') ?: null
        ));
    }
}
