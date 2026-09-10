<?php

namespace App\Modules\Sale\Models;

use App\Core\Model;

/**
 * অর্ডার হেডার — `status` = `App\Enum\OrderStatus`। ঠিকানা/দাম চেকআউটের
 * মুহূর্তের স্ন্যাপশট — পরে customer/delivery_zone বদলালেও অপরিবর্তিত থাকে।
 *
 * doc/10-storefront-order.md §৫।
 */
final class Order extends Model
{
    protected static string $table = 'orders';

    /** @return array<string,mixed> */
    public static function byCode(string $code): array
    {
        return static::where('code', $code)->first();
    }
}
