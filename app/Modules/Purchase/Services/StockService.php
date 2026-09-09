<?php

namespace App\Modules\Purchase\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Enum\StockChangeType;
use App\Modules\Purchase\Models\StockLedger;

/**
 * স্টক বদলানোর একমাত্র দরজা।
 *
 * ⚠ `stock_ledger` বা `products.stock` / `product_variants.stock` তে কখনো সরাসরি
 * লিখবেন না — Purchase, Sale, Adjustment সব এই সার্ভিসের মধ্য দিয়ে যাবে।
 * তাহলেই ক্যাশড কলাম আর খতিয়ানের যোগফল সবসময় মিলবে।
 *
 * panacea `class/somiti.php` এর `productStockChangeLog` + `update_product_closing_stock`।
 * doc/08-purchase.md §4।
 */
final class StockService
{
    /**
     * একটা স্টক মুভমেন্ট লেখে + ক্যাশড স্টক নতুন করে বসায়।
     *
     * `$qty` ম্যাগনিটিউড দিন (ধনাত্মক); সাইন `$type->sign()` থেকে বসে।
     * ব্যতিক্রম Adjust — সেখানে caller নিজেই সাইনড `$qty` দেয় (sign() = +1)।
     *
     * @param int   $variantId    0 = সিম্পল প্রোডাক্ট
     * @param float $qty          বেস ইউনিটে
     * @param int   $referenceId  সোর্স রো — purchase_items.id ইত্যাদি
     * @param int   $actionTime   কার্যকর তারিখ (unix)
     * @param float $rate         প্রতি বেস ইউনিটে খরচ (valuation রিপোর্টের জন্য)
     */
    public static function move(
        int $productId,
        int $variantId,
        float $qty,
        StockChangeType $type,
        int $referenceId,
        int $actionTime,
        float $rate = 0.0
    ): void {
        $signed = round($qty * $type->sign(), 4);

        DB::insert('stock_ledger', [
            'product_id'   => $productId,
            'variant_id'   => $variantId,
            'change_type'  => $type->value,
            'reference_id' => $referenceId,
            'qty'          => $signed,
            'rate'         => round($rate, 4),
            'action_time'  => $actionTime,
            'entry_time'   => time(),
            'created_by'   => Auth::id(),
        ]);

        self::refreshCached($productId, $variantId);
    }

    /**
     * একটা সোর্স ডকুমেন্টের সব স্টক রো মুছে ফেলে (edit/delete এ reverse)।
     *
     * @param array<int,int> $referenceIds যেসব লাইনের rows মুছবে
     */
    public static function reverse(StockChangeType $type, array $referenceIds): void
    {
        $referenceIds = array_values(array_filter(array_map('intval', $referenceIds)));

        if ($referenceIds === []) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($referenceIds), '?'));

        // কোন (product, variant) জোড়া প্রভাবিত হবে আগে জেনে নিই
        $affected = DB::select(
            "SELECT DISTINCT product_id, variant_id FROM stock_ledger
              WHERE change_type = ? AND reference_id IN ($placeholders)",
            array_merge([$type->value], $referenceIds)
        );

        DB::run(
            "DELETE FROM stock_ledger WHERE change_type = ? AND reference_id IN ($placeholders)",
            array_merge([$type->value], $referenceIds)
        );

        foreach ($affected as $row) {
            self::refreshCached((int) $row['product_id'], (int) $row['variant_id']);
        }
    }

    /** অন-হ্যান্ড স্টক (তারিখভিত্তিক রিপোর্টে `$asOf` দিন)। */
    public static function onHand(int $productId, int $variantId = 0, ?int $asOf = null): float
    {
        return StockLedger::onHand($productId, $variantId, $asOf);
    }

    /**
     * খতিয়ানের যোগফল থেকে ক্যাশড `stock` কলাম নতুন করে বসায়।
     *
     * সিম্পল প্রোডাক্ট  → products.stock = SUM(খতিয়ান, variant 0)
     * ভ্যারিয়েন্ট      → product_variants.stock = SUM(খতিয়ান, ওই variant)
     *                    এবং products.stock = active ভ্যারিয়েন্টের যোগফল
     */
    public static function refreshCached(int $productId, int $variantId = 0): void
    {
        if ($variantId > 0) {
            DB::update(
                'product_variants',
                ['stock' => StockLedger::onHand($productId, $variantId)],
                ['id' => $variantId]
            );

            DB::update(
                'products',
                ['stock' => (float) DB::scalar(
                    'SELECT COALESCE(SUM(stock), 0) FROM product_variants WHERE product_id = ? AND isActive = 1',
                    [$productId],
                    0
                )],
                ['id' => $productId]
            );

            return;
        }

        DB::update(
            'products',
            ['stock' => StockLedger::onHand($productId, 0)],
            ['id' => $productId]
        );
    }
}
