<?php

namespace App\Modules\Catalog\Models;

use App\Core\DB;
use App\Core\Model;
use App\Core\Utility;

final class ProductImage extends Model
{
    protected static string $table = 'product_images';

    /** @return array<int,array<string,mixed>> */
    public static function ofProduct(int $productId): array
    {
        return static::where('product_id', $productId)
            ->orderBy('is_primary', 'DESC')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * অনেক প্রোডাক্টের প্রধান ছবি একসাথে (লিস্ট পেজে N+1 এড়াতে)।
     *
     * @param  array<int,int> $productIds
     * @return array<int,string> product_id => path
     */
    public static function primaryOf(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($productIds), '?'));

        $rows = DB::select(
            "SELECT product_id, path
               FROM product_images
              WHERE product_id IN ($placeholders)
           ORDER BY is_primary DESC, sort_order, id",
            array_values($productIds)
        );

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row['product_id']] ??= (string) $row['path'];
        }

        return $out;
    }

    /**
     * একটা ছবিকে প্রধান করা — বাকিগুলোর ফ্ল্যাগ নামিয়ে দেয়।
     */
    public static function makePrimary(int $imageId): void
    {
        $image = static::find($imageId);

        if ($image === []) {
            return;
        }

        DB::update('product_images', ['is_primary' => 0], ['product_id' => (int) $image['product_id']]);
        DB::update('product_images', ['is_primary' => 1], ['id' => $imageId]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function keyById(array $rows): array
    {
        return Utility::keyBy($rows);
    }
}
