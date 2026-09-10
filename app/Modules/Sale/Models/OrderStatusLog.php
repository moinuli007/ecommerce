<?php

namespace App\Modules\Sale\Models;

use App\Core\Model;

/**
 * অর্ডারের স্ট্যাটাস ট্রানজিশনের অডিট ট্রেইল — append-only, `updated_at`/`by`
 * নাই (stock_ledger/a_ledger_entry এর একই প্যাটার্ন)। তাই ইনসার্ট সবসময়
 * `DB::insert()` দিয়ে সরাসরি, `Model::create()` দিয়ে না (সেটা updated_at/by
 * ও স্ট্যাম্প করতে চাইবে যা এই টেবিলে নাই)।
 */
final class OrderStatusLog extends Model
{
    protected static string $table = 'order_status_log';

    /** @return array<int,array<string,mixed>> */
    public static function ofOrder(int $orderId): array
    {
        return static::where('order_id', $orderId)->orderBy('id')->get();
    }
}
