<?php

namespace App\Modules\Sale\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Sale\Api\OrderApi;

/**
 * অর্ডারের অ্যাডমিন পেজ — কোনো DB/সার্ভিস কল নাই, ডেটা শুধু Api ক্লাস থেকে
 * (সিদ্ধান্ত D-04)। Approve/Reject/status বদল JS থেকে সরাসরি API এ যায়।
 */
final class OrderController
{
    /** `/admin/orders/pending` — অ্যাডমিনের হোম-বেস, doc §৫ */
    public static function pending(): string
    {
        Response::reset();
        $payload = OrderApi::pending();

        return View::layout('layouts/admin', 'Sale::order/pending-list', [
            'title'    => 'Pending Orders',
            'orders'   => $payload['orders'] ?? [],
            'messages' => $payload['m'] ?? [],
        ]);
    }

    public static function index(): string
    {
        Response::reset();
        $payload = OrderApi::index();

        return View::layout('layouts/admin', 'Sale::order/list', [
            'title'    => 'Orders',
            'orders'   => $payload['orders'] ?? [],
            'filters'  => [
                'status' => Request::string('status'),
                'code'   => Request::string('code'),
            ],
            'messages' => $payload['m'] ?? [],
        ]);
    }

    public static function show(): string
    {
        Response::reset();
        $payload = OrderApi::show();

        return View::layout('layouts/admin', 'Sale::order/show', [
            'title'    => 'Order ' . ($payload['order']['code'] ?? ''),
            'order'    => $payload['order'] ?? [],
            'messages' => $payload['m'] ?? [],
        ]);
    }
}
