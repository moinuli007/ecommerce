<?php

namespace App\Modules\Sale\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Sale\Api\CustomerApi;

/**
 * কাস্টমারের অ্যাডমিন পেজ।
 *
 * বাকি কন্ট্রোলারের মতোই — কোনো DB/সার্ভিস কল নাই, ডেটা শুধু Api ক্লাস থেকে
 * (সিদ্ধান্ত D-04)। সেভ/ডিলিট JS থেকে সরাসরি API এ যায়।
 */
final class CustomerController
{
    public static function index(): string
    {
        Response::reset();
        $payload = CustomerApi::index();

        return View::layout('layouts/admin', 'Sale::customer/list', [
            'title'     => 'Customers',
            'customers' => $payload['customers'] ?? [],
            'filters'   => ['q' => Request::string('q')],
            'messages'  => $payload['m'] ?? [],
        ]);
    }

    public static function create(): string
    {
        return self::form(0);
    }

    public static function edit(): string
    {
        return self::form(Request::paramInt('id'));
    }

    private static function form(int $customerId): string
    {
        $customer      = [];
        $ledgerBalance = ['balance' => 0.0, 'side' => ''];

        if ($customerId > 0) {
            Response::reset();
            Request::setParams(['id' => (string) $customerId]);
            $payload       = CustomerApi::show();
            $customer      = $payload['customer'] ?? [];
            $ledgerBalance = $payload['ledger_balance'] ?? $ledgerBalance;
        }

        return View::layout('layouts/admin', 'Sale::customer/form', [
            'title'          => $customerId > 0 ? 'Edit Customer' : 'New Customer',
            'customer'       => $customer,
            'ledgerBalance'  => $ledgerBalance,
            'messages'       => [],
        ]);
    }
}
