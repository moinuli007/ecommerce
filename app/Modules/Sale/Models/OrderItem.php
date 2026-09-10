<?php

namespace App\Modules\Sale\Models;

use App\Core\Model;

/**
 * অর্ডার লাইন — নাম/দাম/cost checkout সময়ের স্ন্যাপশট (products/product_variants
 * এর পরের পরিবর্তন এখানে প্রতিফলিত হয় না)। কোনো audit কলাম নাই (purchase_items
 * এর একই প্যাটার্ন — অপরিবর্তনীয় লাইন) — ইনসার্ট `DB::insert()` দিয়ে সরাসরি,
 * `Model::create()` দিয়ে না।
 */
final class OrderItem extends Model
{
    protected static string $table = 'order_items';

    /** @return array<int,array<string,mixed>> */
    public static function ofOrder(int $orderId): array
    {
        return static::where('order_id', $orderId)->orderBy('id')->get();
    }
}
