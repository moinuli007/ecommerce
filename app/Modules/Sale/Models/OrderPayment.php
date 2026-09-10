<?php

namespace App\Modules\Sale\Models;

use App\Core\Model;
use App\Enum\PaymentStatus;

/**
 * ম্যানুয়াল bKash/Nagad পেমেন্ট রেফারেন্স — `status` = `App\Enum\PaymentStatus`।
 * COD অর্ডারে কোনো রো লাগে না। doc/10-storefront-order.md §৭।
 */
final class OrderPayment extends Model
{
    protected static string $table = 'order_payments';

    /** @return array<int,array<string,mixed>> */
    public static function ofOrder(int $orderId): array
    {
        return static::where('order_id', $orderId)->orderBy('id', 'DESC')->get();
    }

    /** যাচাই হওয়া মোট — Order এর `advance_paid` এর সাথে মেলে কিনা রিবিল্ড/যাচাইয়ে কাজে লাগে */
    public static function verifiedTotal(int $orderId): float
    {
        return (float) static::query()
            ->where('order_id', $orderId)
            ->where('status', PaymentStatus::Verified->value)
            ->sum('amount');
    }
}
