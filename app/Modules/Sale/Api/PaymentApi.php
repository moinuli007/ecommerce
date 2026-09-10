<?php

namespace App\Modules\Sale\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Modules\Sale\Models\OrderPayment;
use App\Modules\Sale\Services\PaymentService;
use RuntimeException;

/**
 * ম্যানুয়াল bKash/Nagad পেমেন্টের অ্যাডমিন ভেরিফিকেশন। doc/10-storefront-order.md §৭।
 */
final class PaymentApi
{
    /** GET /api/v1/orders/{id}/payments */
    public static function forOrder(): array
    {
        return Response::success('', ['payments' => OrderPayment::ofOrder(Request::paramInt('id'))]);
    }

    /** POST /api/v1/order-payments — অ্যাডমিন হাতে রেকর্ড করে (ফোনে কনফার্ম করে) */
    public static function store(): array
    {
        if (!Validator::check(Request::all(), [
            'order_id' => 'required|int',
            'method'   => 'required|int',
            'amount'   => 'required|numeric|min:0.01',
        ])) {
            return Response::payload();
        }

        try {
            $id = PaymentService::record(Request::all());
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Payment recorded.', ['payment' => OrderPayment::find($id)]);
    }

    /** PUT /api/v1/order-payments/{id}/verify */
    public static function verify(): array
    {
        try {
            $payment = PaymentService::verify(Request::paramInt('id'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Payment verified.', ['payment' => $payment]);
    }

    /** PUT /api/v1/order-payments/{id}/reject */
    public static function reject(): array
    {
        try {
            $payment = PaymentService::reject(Request::paramInt('id'), Request::string('note'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Payment rejected.', ['payment' => $payment]);
    }
}
