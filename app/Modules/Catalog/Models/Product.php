<?php

namespace App\Modules\Catalog\Models;

use App\Core\Model;

final class Product extends Model
{
    protected static string $table = 'products';

    /** @return array<string,mixed> */
    public static function bySlug(string $slug): array
    {
        return static::where('slug', $slug)->first();
    }

    /** @return array<string,mixed> */
    public static function bySku(string $sku): array
    {
        return static::where('sku', $sku)->first();
    }

    /**
     * একটা ক্যাটাগরি ও তার সব সাব-ক্যাটাগরির প্রোডাক্ট।
     *
     * @return array<int,array<string,mixed>>
     */
    public static function inCategoryTree(int $categoryId, int $limit = 0): array
    {
        $ids = Category::descendantIds($categoryId);

        if ($ids === []) {
            return [];
        }

        $query = static::active()->whereIn('category_id', $ids)->orderBy('sort_order')->orderBy('id', 'DESC');

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }
}
