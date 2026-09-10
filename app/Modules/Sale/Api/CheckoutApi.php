<?php

namespace App\Modules\Sale\Api;

use App\Core\Request;
use App\Core\Response;
use App\Modules\Sale\Services\OrderService;
use RuntimeException;

/**
 * স্টোরফ্রন্ট চেকআউট ও গেস্ট অর্ডার-ট্র্যাকিং — `guard: guest`।
 * doc/10-storefront-order.md §৬।
 */
final class CheckoutApi
{
    /** POST /api/v1/storefront/checkout */
    public static function store(): array
    {
        try {
            $order = OrderService::checkout(CartTokenResolver::resolve(), Request::all());
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Order placed — ' . $order['code'] . '.', ['order' => $order]);
    }

    /** GET /api/v1/storefront/orders/{code}?phone= — কোড + ফোন দুটোই মিললে দেখাবে */
    public static function track(): array
    {
        $code  = (string) Request::param('code', '');
        $phone = Request::string('phone');

        if ($phone === '') {
            return Response::error('Please provide the phone number used for this order.');
        }

        $order = OrderService::detailsByCodeAndPhone($code, $phone);

        if ($order === []) {
            return Response::error('Order not found.');
        }

        return Response::success('', ['order' => $order]);
    }
}
