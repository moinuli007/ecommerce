<?php

namespace App\Modules\Purchase\Models;

use App\Core\DB;
use App\Core\Model;

/**
 * স্টক খতিয়ান — স্টকের একমাত্র সত্যের উৎস (panacea `product_stock_log`)।
 * প্রতিটি রো একটা মুভমেন্ট; `qty` সাইনড ও বেস ইউনিটে। কখনো UPDATE হয় না।
 *
 * doc/08-purchase.md §2, §4।
 */
final class StockLedger extends Model
{
    protected static string $table = 'stock_ledger';

    /**
     * এক আইটেমের অন-হ্যান্ড স্টক = SUM(qty)।
     *
     * @param int      $variantId 0 = সিম্পল প্রোডাক্ট
     * @param int|null $asOf      দেওয়া থাকলে ওই সময় পর্যন্ত (তারিখভিত্তিক রিপোর্ট)
     */
    public static function onHand(int $productId, int $variantId = 0, ?int $asOf = null): float
    {
        $sql = 'SELECT COALESCE(SUM(qty), 0) FROM stock_ledger WHERE product_id = ? AND variant_id = ?';
        $args = [$productId, $variantId];

        if ($asOf !== null) {
            $sql .= ' AND action_time <= ?';
            $args[] = $asOf;
        }

        return (float) DB::scalar($sql, $args, 0);
    }
}
