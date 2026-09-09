<?php

namespace App\Modules\Purchase\Models;

use App\Core\DB;
use App\Core\Model;

/**
 * ক্রয় ফেরত — কোনো ক্রয় থেকে সাপ্লায়ারকে পণ্য ফেরত।
 * doc/08-purchase.md §2, §7。
 */
final class PurchaseReturn extends Model
{
    protected static string $table = 'purchase_returns';
    protected static string $activeColumn = 'status';

    /** @return array<int,array<string,mixed>> */
    public static function itemsOf(int $returnId): array
    {
        return DB::select(
            'SELECT * FROM purchase_return_items WHERE return_id = ? ORDER BY id',
            [$returnId]
        );
    }
}
