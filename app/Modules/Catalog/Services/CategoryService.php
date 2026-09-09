<?php

namespace App\Modules\Catalog\Services;

use App\Core\DB;
use App\Core\Slug;
use App\Core\Upload;
use App\Core\Utility;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use RuntimeException;

/**
 * ক্যাটাগরি গাছ। রেফারেন্স সাইটের "Collections" মেনু এই গাছ থেকেই আসবে।
 *
 * `path` কলামটাই মূল কৌশল — root থেকে নিজের পর্যন্ত id, "/1/3/4/" ফরম্যাটে।
 * ফলে "এই ক্যাটাগরি ও সব সাব-ক্যাটাগরির প্রোডাক্ট" এক LIKE কোয়েরিতেই পাওয়া যায়,
 * recursive CTE বা N+1 ছাড়াই। `path` সবসময় এই সার্ভিস বসায় — হাতে লিখবেন না।
 */
final class CategoryService
{
    /**
     * নতুন ক্যাটাগরি অথবা এডিট।
     *
     * @param  array<string,mixed> $data name, parent_id, slug?, description?, image?, sort_order?, isFeatured?, isActive?
     * @return int ক্যাটাগরি id
     */
    public static function save(array $data, int $id = 0): int
    {
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('Category name is required.');
        }

        $parentId = (int) ($data['parent_id'] ?? 0);

        self::assertValidParent($id, $parentId);

        $row = [
            'parent_id'   => $parentId,
            'name'        => $name,
            'slug'        => Slug::unique((string) ($data['slug'] ?? '') ?: $name, 'categories', $id),
            'description' => (string) ($data['description'] ?? ''),
            'image'       => (string) ($data['image'] ?? ''),
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
            'isFeatured'  => (int) ($data['isFeatured'] ?? 0) === 1 ? 1 : 0,
            'isActive'    => (int) ($data['isActive'] ?? 1) === 1 ? 1 : 0,
        ];

        return DB::transaction(static function () use ($row, $id, $parentId): int {
            if ($id > 0) {
                Category::updateById($id, $row);
                $categoryId = $id;
            } else {
                $categoryId = Category::create($row);
            }

            self::rebuildPath($categoryId, $parentId);

            return $categoryId;
        });
    }

    /**
     * ডিলিট। সাব-ক্যাটাগরি বা প্রোডাক্ট থাকলে আটকে দেয় —
     * নীরবে অনাথ প্রোডাক্ট তৈরি হওয়ার চেয়ে স্পষ্ট এরর ভালো।
     */
    public static function delete(int $id): bool
    {
        if (Category::children($id, false) !== []) {
            throw new RuntimeException('This category has sub-categories — remove them first.');
        }

        if (Product::where('category_id', $id)->exists()) {
            throw new RuntimeException('This category has products — move them to another category first.');
        }

        return Category::deleteById($id) > 0;
    }

    /**
     * ছবি আপলোড — আগের ছবি থাকলে সেটা ডিলিট করে বদলে দেয়।
     *
     * @param array<string,mixed> $file $_FILES এন্ট্রি
     */
    public static function uploadImage(int $id, array $file): string
    {
        $category = Category::find($id);

        if ($category === []) {
            throw new RuntimeException('Category not found.');
        }

        $path = Upload::save($file, 'categories');

        if ((string) $category['image'] !== '') {
            Upload::delete((string) $category['image']);
        }

        Category::updateById($id, ['image' => $path]);

        return $path;
    }

    public static function removeImage(int $id): void
    {
        $category = Category::find($id);

        if ($category === [] || (string) $category['image'] === '') {
            return;
        }

        Upload::delete((string) $category['image']);
        Category::updateById($id, ['image' => '']);
    }

    /**
     * পুরো গাছ, প্রতিটি নোডে `children` সহ।
     *
     * @return array<int,array<string,mixed>>
     */
    public static function tree(bool $activeOnly = false): array
    {
        $query = Category::query();

        if ($activeOnly) {
            $query->active();
        }

        $rows = $query->orderBy('sort_order')->orderBy('name')->get();

        return self::buildTree($rows);
    }

    /**
     * ড্রপডাউনের জন্য সমতল লিস্ট, নামের আগে ইন্ডেন্ট (— — Casual Shirts)।
     *
     * @param  int $excludeId এই ক্যাটাগরি ও তার বংশধর বাদ (নিজেকে নিজের প্যারেন্ট করা ঠেকাতে)
     * @return array<int,array{id:int,name:string,depth:int,label:string}>
     */
    public static function options(int $excludeId = 0): array
    {
        $exclude = $excludeId > 0 ? Category::descendantIds($excludeId) : [];
        $out     = [];

        foreach (self::tree() as $node) {
            self::flatten($node, $exclude, $out);
        }

        return $out;
    }

    /**
     * ব্রেডক্রাম্ব — root থেকে নিজে পর্যন্ত।
     *
     * @return array<int,array{id:int,name:string,slug:string}>
     */
    public static function breadcrumb(int $categoryId): array
    {
        return array_map(
            static fn (array $c) => [
                'id'   => (int) $c['id'],
                'name' => $c['name'],
                'slug' => $c['slug'],
            ],
            Category::ancestors($categoryId)
        );
    }

    /**
     * হোমপেজের "Collection" কার্ডের জন্য।
     *
     * @return array<int,array<string,mixed>>
     */
    public static function featured(int $limit = 8): array
    {
        return Category::active()
            ->where('isFeatured', 1)
            ->orderBy('sort_order')
            ->limit($limit)
            ->get();
    }

    // -------------------------------------------------------------------------
    // ভেতরের কাজ
    // -------------------------------------------------------------------------

    /**
     * নিজের path/depth বসিয়ে সব বংশধরেরটাও নতুন করে হিসাব করে।
     *
     * ক্যাটাগরি সংখ্যায় কম (কয়েক ডজন), তাই এক ধাক্কায় recursive rebuild —
     * SQL এ prefix replace করার চেয়ে এটা স্পষ্ট ও ভুলের সম্ভাবনা কম।
     */
    private static function rebuildPath(int $categoryId, int $parentId): void
    {
        if ($parentId > 0) {
            $parent = Category::find($parentId);

            if ($parent === []) {
                throw new RuntimeException('Parent category not found.');
            }

            $path  = $parent['path'] . $categoryId . '/';
            $depth = (int) $parent['depth'] + 1;
        } else {
            $path  = '/' . $categoryId . '/';
            $depth = 0;
        }

        DB::update('categories', ['path' => $path, 'depth' => $depth], ['id' => $categoryId]);

        foreach (Category::children($categoryId, false) as $child) {
            self::rebuildPath((int) $child['id'], $categoryId);
        }
    }

    /**
     * প্যারেন্ট নিজে বা নিজের বংশধর হলে গাছ ভেঙে যাবে (অসীম লুপ) — আটকাই।
     */
    private static function assertValidParent(int $id, int $parentId): void
    {
        if ($parentId === 0) {
            return;
        }

        if ($id > 0 && $parentId === $id) {
            throw new RuntimeException('A category cannot be its own parent.');
        }

        if ($id > 0 && in_array($parentId, Category::descendantIds($id), true)) {
            throw new RuntimeException('A sub-category cannot be made the parent.');
        }

        if (Category::find($parentId) === []) {
            throw new RuntimeException('Parent category not found.');
        }
    }

    /**
     * @param  array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function buildTree(array $rows): array
    {
        $byParent = Utility::groupBy($rows, 'parent_id');

        $build = static function (int $parentId) use (&$build, $byParent): array {
            $nodes = [];

            foreach ($byParent[$parentId] ?? [] as $row) {
                $row['children'] = $build((int) $row['id']);
                $nodes[]         = $row;
            }

            return $nodes;
        };

        return $build(0);
    }

    /**
     * @param array<string,mixed>              $node
     * @param array<int,int>                   $exclude
     * @param array<int,array<string,mixed>>   $out
     */
    private static function flatten(array $node, array $exclude, array &$out): void
    {
        $id = (int) $node['id'];

        if (in_array($id, $exclude, true)) {
            return; // এই শাখাটা পুরোই বাদ
        }

        $depth = (int) $node['depth'];

        $out[] = [
            'id'    => $id,
            'name'  => $node['name'],
            'depth' => $depth,
            'label' => str_repeat('— ', $depth) . $node['name'],
        ];

        foreach ($node['children'] ?? [] as $child) {
            self::flatten($child, $exclude, $out);
        }
    }
}
