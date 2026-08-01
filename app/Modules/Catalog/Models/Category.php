<?php

namespace App\Modules\Catalog\Models;

use App\Core\DB;
use App\Core\Model;

final class Category extends Model
{
    protected static string $table = 'categories';

    /** @return array<string,mixed> */
    public static function bySlug(string $slug): array
    {
        return static::where('slug', $slug)->first();
    }

    /** @return array<int,array<string,mixed>> */
    public static function children(int $parentId, bool $activeOnly = true): array
    {
        $query = static::where('parent_id', $parentId);

        if ($activeOnly) {
            $query->active();
        }

        return $query->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * এই ক্যাটাগরি ও তার সব বংশধরের id — `path` কলামের LIKE দিয়ে, এক কোয়েরিতে।
     *
     * @return array<int,int>
     */
    public static function descendantIds(int $categoryId): array
    {
        $category = static::find($categoryId);

        if ($category === []) {
            return [];
        }

        $rows = DB::select(
            'SELECT id FROM categories WHERE id = ? OR path LIKE ?',
            [$categoryId, $category['path'] . '%']
        );

        return array_map(static fn (array $r) => (int) $r['id'], $rows);
    }

    /**
     * root → নিজে পর্যন্ত পথ (ব্রেডক্রাম্বের জন্য)।
     *
     * @return array<int,array<string,mixed>>
     */
    public static function ancestors(int $categoryId): array
    {
        $category = static::find($categoryId);

        if ($category === []) {
            return [];
        }

        $ids = array_filter(array_map('intval', explode('/', (string) $category['path'])));

        if ($ids === []) {
            return [];
        }

        $rows = static::whereIn('id', array_values($ids))->get();

        // path এর ক্রম বজায় রাখি
        $byId  = [];
        foreach ($rows as $row) {
            $byId[(int) $row['id']] = $row;
        }

        $out = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $out[] = $byId[$id];
            }
        }

        return $out;
    }

    /** এই ক্যাটাগরিতে (সরাসরি) কতগুলো প্রোডাক্ট আছে */
    public static function productCount(int $categoryId): int
    {
        return Product::where('category_id', $categoryId)->count();
    }
}
