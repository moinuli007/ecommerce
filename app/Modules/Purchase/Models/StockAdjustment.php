<?php

namespace App\Modules\Purchase\Models;

use App\Core\DB;
use App\Core\Model;

/**
 * স্টক সমন্বয় — ওপেনিং স্টক / নষ্ট / গণনা-মিলকরণ।
 * reason: 1 = Opening, 2 = Damage, 3 = Count correction।
 * doc/08-purchase.md §2, §10।
 */
final class StockAdjustment extends Model
{
    protected static string $table = 'stock_adjustments';
    protected static string $activeColumn = 'status';

    public const REASON_OPENING = 1;
    public const REASON_DAMAGE  = 2;
    public const REASON_COUNT   = 3;

    /** @return array<int,array<string,mixed>> */
    public static function itemsOf(int $adjustmentId): array
    {
        return DB::select(
            'SELECT * FROM stock_adjustment_items WHERE adjustment_id = ? ORDER BY id',
            [$adjustmentId]
        );
    }
}
