<?php

namespace App\Modules\Account\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Account\Api\LedgerApi;

/**
 * অ্যাডমিন চার্ট অব অ্যাকাউন্টস ও রিপোর্ট পেজ।
 * VoucherController এর মতোই — ডেটা শুধু LedgerApi থেকে।
 */
final class LedgerController
{
    public static function index(): string
    {
        Response::reset();

        $payload = LedgerApi::tree();

        return View::layout('layouts/admin', 'Account::ledger/tree', [
            'title'    => 'Chart of Accounts',
            'tree'     => $payload['tree'] ?? [],
            'messages' => $payload['m'] ?? [],
        ]);
    }

    public static function statement(): string
    {
        Response::reset();

        $payload = LedgerApi::statement();

        return View::layout('layouts/admin', 'Account::ledger/statement', [
            'title'     => 'Ledger Statement',
            'statement' => $payload,
            'filters'   => ['from' => Request::string('from'), 'to' => Request::string('to')],
            'messages'  => $payload['m'] ?? [],
        ]);
    }

    public static function trialBalance(): string
    {
        Response::reset();

        $payload = LedgerApi::trialBalance();

        return View::layout('layouts/admin', 'Account::ledger/trial-balance', [
            'title'    => 'Trial Balance',
            'report'   => $payload,
            'filters'  => ['from' => Request::string('from'), 'to' => Request::string('to')],
            'messages' => $payload['m'] ?? [],
        ]);
    }
}
