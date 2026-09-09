<?php

namespace App\Modules\Account\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Account\Api\VoucherApi;

/**
 * অ্যাডমিন ভাউচার পেজ।
 *
 * খেয়াল করুন: এখানে কোনো DB কোয়েরি বা সার্ভিস কল নাই — সব ডেটা
 * VoucherApi এর মধ্য দিয়ে আসে, ঠিক যেভাবে HTTP API ক্লায়েন্ট পায়।
 */
final class VoucherController
{
    public static function index(): string
    {
        Response::reset();

        $payload = VoucherApi::index();

        Response::reset();
        $form = VoucherApi::formData();

        return View::layout('layouts/admin', 'Account::voucher/list', [
            'title'      => 'Voucher List',
            'vouchers'   => $payload['vouchers'] ?? [],
            'pagination' => $payload['pagination'] ?? [],
            'messages'   => $payload['m'] ?? [],
            'types'      => $form['voucher_types'] ?? [],
            'filters'    => [
                'type' => Request::string('type'),
                'from' => Request::string('from'),
                'to'   => Request::string('to'),
                'code' => Request::string('code'),
            ],
        ]);
    }

    public static function entry(): string
    {
        Response::reset();

        $form = VoucherApi::formData();

        return View::layout('layouts/admin', 'Account::voucher/entry', [
            'title'    => 'New Voucher',
            'types'    => $form['voucher_types'] ?? [],
            'ledgers'  => $form['ledgers'] ?? [],
            'natures'  => $form['natures'] ?? [],
            'today'    => $form['today'] ?? date('Y-m-d'),
            'messages' => $form['m'] ?? [],
        ]);
    }

    public static function show(): string
    {
        Response::reset();

        $payload = VoucherApi::show();

        return View::layout('layouts/admin', 'Account::voucher/show', [
            'title'    => 'Voucher Detail',
            'voucher'  => $payload['voucher'] ?? [],
            'messages' => $payload['m'] ?? [],
        ]);
    }
}
