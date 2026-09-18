<?php

namespace App\Modules\Purchase\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\RequestTime;
use App\Enum\StockChangeType;
use App\Modules\Purchase\Models\StockLedger;

/**
 * Moving weighted-average cost — `products.purchase_price` / `product_variants.purchase_price`।
 *
 *   new_cost = ((old_stock × old_cost) + (qty × rate)) / (old_stock + qty)
 *
 * panacea `ajax/operation/purchaseAdd.php` line 112।
 * doc/08-purchase.md §5।
 */
final class CostService
{
    /**
     * একটা ক্রয় লাইনের পর নতুন weighted-average cost বসায়।
     * ⚠ StockService::move() এর **আগে** কল করতে হবে — old stock লাগে।
     *
     * @param int   $variantId       0 = সিম্পল প্রোডাক্ট
     * @param float $qtyBase         বেস ইউনিটে
     * @param float $rateBase        প্রতি বেস ইউনিটে ল্যান্ডেড ক্রয়মূল্য
     * @param int   $referenceId     purchase_items.id (লগের জন্য)
     * @return float নতুন cost
     */
    public static function applyPurchase(
        int $productId,
        int $variantId,
        float $qtyBase,
        float $rateBase,
        int $referenceId
    ): float {
        [$table, $rowId] = self::target($productId, $variantId);

        $row      = DB::getById($table, $rowId);
        $oldStock = (float) ($row['stock'] ?? 0);
        $oldCost  = (float) ($row['purchase_price'] ?? 0);

        $newStock = $oldStock + $qtyBase;

        $newCost = ($oldStock <= 0 || $newStock <= 0)
            ? round($rateBase, 4)
            : round((($oldStock * $oldCost) + ($qtyBase * $rateBase)) / $newStock, 4);

        self::write($table, $rowId, $productId, $variantId, $newCost, $referenceId);

        return $newCost;
    }

    /**
     * সব ইন-মুভমেন্ট `action_time` ক্রমে চালিয়ে cost নতুন করে হিসাব করে
     * (Purchase edit/delete এর পর — reverse হওয়ার পর ডাকতে হয়)।
     */
    public static function rebuild(int $productId, int $variantId): float
    {
        [$table, $rowId] = self::target($productId, $variantId);

        $rows = DB::select(
            'SELECT qty, rate FROM stock_ledger
              WHERE product_id = ? AND variant_id = ? AND qty > 0
           ORDER BY action_time ASC, id ASC',
            [$productId, $variantId]
        );

        $stock = 0.0;
        $cost  = 0.0;

        foreach ($rows as $r) {
            $qty  = (float) $r['qty'];
            $rate = (float) $r['rate'];
            $next = $stock + $qty;

            $cost  = ($stock <= 0 || $next <= 0) ? $rate : (($stock * $cost) + ($qty * $rate)) / $next;
            $stock = $next;
        }

        $cost = round($cost, 4);
        self::write($table, $rowId, $productId, $variantId, $cost, 0);

        return $cost;
    }

    /** @return array{0:string,1:int} */
    private static function target(int $productId, int $variantId): array
    {
        return $variantId > 0
            ? ['product_variants', $variantId]
            : ['products', $productId];
    }

    private static function write(
        string $table,
        int $rowId,
        int $productId,
        int $variantId,
        float $cost,
        int $referenceId
    ): void {
        DB::update($table, ['purchase_price' => $cost], ['id' => $rowId]);

        DB::insert('product_price_log', [
            'product_id'     => $productId,
            'variant_id'     => $variantId,
            'purchase_price' => $cost,
            'source_type'    => StockChangeType::Purchase->value,
            'reference_id'   => $referenceId,
            'created_at'     => RequestTime::now(),
            'created_by'     => Auth::id(),
        ]);
    }
}
