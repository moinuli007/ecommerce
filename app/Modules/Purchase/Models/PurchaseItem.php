<?php

namespace App\Modules\Purchase\Models;

use App\Core\Model;

/**
 * ক্রয়ের লাইন। `variant_id = 0` মানে সিম্পল প্রোডাক্ট।
 */
final class PurchaseItem extends Model
{
    protected static string $table = 'purchase_items';

    /** @return array<int,array<string,mixed>> */
    public static function ofPurchase(int $purchaseId): array
    {
        return static::where('purchase_id', $purchaseId)->orderBy('id')->get();
    }
}
