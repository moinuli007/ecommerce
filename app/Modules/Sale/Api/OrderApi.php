<?php

namespace App\Modules\Sale\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Modules\Sale\Services\OrderService;
use RuntimeException;

/**
 * অর্ডারের অ্যাডমিন API — লিস্ট/ডিটেইল, অনুমোদন (Approve/Reject) ও পরের
 * ধাপের স্ট্যাটাস। doc/10-storefront-order.md §৫, §৯।
 */
final class OrderApi
{
    /** GET /api/v1/orders */
    public static function index(): array
    {
        $result = OrderService::list([
            'status'         => Request::int('status'),
            'customer_id'    => Request::int('customer_id'),
            'payment_method' => Request::int('payment_method'),
            'code'           => Request::string('code'),
            'from'           => Request::string('from'),
            'to'             => Request::string('to'),
            'page'           => Request::int('page', 1),
            'per_page'       => Request::int('per_page', 25),
        ]);

        Response::ok();
        Response::set('orders', $result['data']);
        Response::set('pagination', [
            'total'    => $result['total'],
            'page'     => $result['page'],
            'per_page' => $result['per_page'],
            'pages'    => $result['per_page'] > 0 ? (int) ceil($result['total'] / $result['per_page']) : 1,
        ]);

        return Response::payload();
    }

    /** GET /api/v1/orders/pending — অ্যাডমিনের অনুমোদন-queue, doc §৫ */
    public static function pending(): array
    {
        return Response::success('', ['orders' => OrderService::pendingQueue()]);
    }

    /** GET /api/v1/orders/{id} */
    public static function show(): array
    {
        $order = OrderService::details(Request::paramInt('id'));

        if ($order === []) {
            return Response::error('Order not found.');
        }

        return Response::success('', ['order' => $order]);
    }

    /** POST /api/v1/orders/{id}/approve — শুধু Pending থেকে, doc §৫ */
    public static function approve(): array
    {
        try {
            $order = OrderService::approve(Request::paramInt('id'), Request::string('note'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Order approved.', ['order' => $order]);
    }

    /** POST /api/v1/orders/{id}/reject — শুধু Pending থেকে, note বাধ্যতামূলক */
    public static function reject(): array
    {
        if (!Validator::check(Request::all(), ['note' => 'required|max:255'])) {
            return Response::payload();
        }

        try {
            $order = OrderService::reject(Request::paramInt('id'), Request::string('note'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Order rejected.', ['order' => $order]);
    }

    /** PUT /api/v1/orders/{id}/status — Confirmed এর পরের ধাপগুলোর জন্য */
    public static function updateStatus(): array
    {
        try {
            $order = OrderService::changeStatus(Request::paramInt('id'), Request::int('status'), Request::string('note'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Order status updated.', ['order' => $order]);
    }
}
