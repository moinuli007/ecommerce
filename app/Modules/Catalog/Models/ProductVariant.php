<?php

namespace App\Modules\Catalog\Models;

use App\Core\DB;
use App\Core\Model;

final class ProductVariant extends Model
{
    protected static string $table = 'product_variants';

    /** @return array<int,array<string,mixed>> */
    public static function ofProduct(int $productId, bool $activeOnly = false): array
    {
        $query = static::where('product_id', $productId);

        if ($activeOnly) {
            $query->active();
        }

        return $query->orderBy('sort_order')->orderBy('id')->get();
    }

    /** @return array<string,mixed> */
    public static function bySignature(int $productId, string $signature): array
    {
        return static::query()
            ->where('product_id', $productId)
            ->where('signature', $signature)
            ->first();
    }

    /**
     * ভ্যারিয়েন্টগুলোর অ্যাট্রিবিউট ভ্যালু — এক কোয়েরিতে, নামসহ।
     *
     * @param  array<int,int> $variantIds
     * @return array<int,array<int,array<string,mixed>>> variant_id => values
     */
    public static function valuesOf(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($variantIds), '?'));

        $rows = DB::select(
            "SELECT v.variant_id, v.attribute_id, v.attribute_value_id,
                    a.name AS attribute_name, a.code AS attribute_code, a.type AS attribute_type,
                    av.value, av.code AS value_code, av.color_hex
               FROM product_variant_values v
               JOIN attributes       a  ON a.id  = v.attribute_id
               JOIN attribute_values av ON av.id = v.attribute_value_id
              WHERE v.variant_id IN ($placeholders)
           ORDER BY a.sort_order, av.sort_order",
            array_values($variantIds)
        );

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row['variant_id']][] = [
                'attribute_id'       => (int) $row['attribute_id'],
                'attribute_name'     => $row['attribute_name'],
                'attribute_code'     => $row['attribute_code'],
                'attribute_type'     => $row['attribute_type'],
                'attribute_value_id' => (int) $row['attribute_value_id'],
                'value'              => $row['value'],
                'value_code'         => $row['value_code'],
                'color_hex'          => $row['color_hex'],
            ];
        }

        return $out;
    }

    /** প্রোডাক্টের সব ভ্যারিয়েন্টের স্টকের যোগফল */
    public static function totalStock(int $productId): float
    {
        return (float) DB::scalar(
            'SELECT COALESCE(SUM(stock), 0) FROM product_variants WHERE product_id = ? AND isActive = 1',
            [$productId],
            0
        );
    }
}
