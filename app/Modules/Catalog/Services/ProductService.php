<?php

namespace App\Modules\Catalog\Services;

use App\Core\DB;
use App\Core\QueryBuilder;
use App\Core\Slug;
use App\Core\Upload;
use App\Core\Utility;
use App\Modules\Account\Services\CodeGenerator;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeValue;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Unit;
use RuntimeException;

/**
 * প্রোডাক্ট — ক্যাটালগের কেন্দ্র।
 *
 * দুই ধরনের প্রোডাক্ট:
 *   has_variant = 0 → দাম `products` টেবিলেই (একটা বেল্ট, একটা মগ)
 *   has_variant = 1 → দাম প্রতি ভ্যারিয়েন্টে (শার্ট: Navy/M, Navy/L, White/XL …)
 *
 * স্টক (`products.stock` / `product_variants.stock`) হাতে বসানো হয় না — এটা
 * StockService এর ক্যাশড মান, আসে Purchase / Stock Adjustment থেকে
 * (doc/08-purchase.md §৯)।
 *
 * ভ্যারিয়েন্ট হাতে বানাতে হয় না — অ্যাট্রিবিউট ভ্যালু বেছে দিলে
 * syncVariants() সব কম্বিনেশন (cartesian product) নিজে তৈরি করে দেয়।
 */
final class ProductService
{
    // -------------------------------------------------------------------------
    // সেভ
    // -------------------------------------------------------------------------

    /**
     * @param  array<string,mixed> $data
     * @return int product id
     */
    public static function save(array $data, int $id = 0): int
    {
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('Product name is required.');
        }

        $categoryId = (int) ($data['category_id'] ?? 0);
        $unitId     = (int) ($data['unit_id'] ?? 0);

        if (Category::find($categoryId) === []) {
            throw new RuntimeException('Please choose a category.');
        }

        if (Unit::find($unitId) === []) {
            throw new RuntimeException('Please choose a unit.');
        }

        $salePrice  = (float) ($data['sale_price'] ?? 0);
        $offerPrice = (float) ($data['offer_price'] ?? 0);

        if ($salePrice < 0 || $offerPrice < 0) {
            throw new RuntimeException('Price cannot be negative.');
        }

        if ($offerPrice > 0 && $salePrice > 0 && $offerPrice >= $salePrice) {
            throw new RuntimeException('Offer price must be less than the sale price.');
        }

        $offerStart = Utility::toTime((string) ($data['offer_start'] ?? ''), 0);
        $offerEnd   = Utility::toTime((string) ($data['offer_end'] ?? ''), 0);

        if ($offerStart > 0 && $offerEnd > 0 && $offerEnd < $offerStart) {
            throw new RuntimeException('Offer end date cannot be before the start date.');
        }

        $row = [
            'category_id'       => $categoryId,
            'unit_id'           => $unitId,
            'name'              => $name,
            'slug'              => Slug::unique((string) ($data['slug'] ?? '') ?: $name, 'products', $id),
            'short_description' => mb_substr((string) ($data['short_description'] ?? ''), 0, 500),
            'description'       => (string) ($data['description'] ?? ''),
            'sale_price'        => $salePrice,
            'offer_price'       => $offerPrice,
            'offer_start'       => $offerStart,
            'offer_end'         => $offerEnd > 0 ? $offerEnd + 86399 : 0, // ওই দিনের শেষ পর্যন্ত
            'stock_alert'       => (float) ($data['stock_alert'] ?? 0),
            'weight'            => (float) ($data['weight'] ?? 0),
            'isFeatured'        => (int) ($data['isFeatured'] ?? 0) === 1 ? 1 : 0,
            'isNew'             => (int) ($data['isNew'] ?? 0) === 1 ? 1 : 0,
            'isActive'          => (int) ($data['isActive'] ?? 1) === 1 ? 1 : 0,
            'sort_order'        => (int) ($data['sort_order'] ?? 0),
            'meta_title'        => mb_substr((string) ($data['meta_title'] ?? ''), 0, 191),
            'meta_description'  => mb_substr((string) ($data['meta_description'] ?? ''), 0, 300),
        ];

        // স্টক এখানে বসে না — Purchase / Stock Adjustment থেকে StockService বসায়
        // purchase_price ও বসে না — CostService বসায় (weighted-average, doc/08-purchase.md §৫, §৯)।
        // দুটোই নতুন প্রোডাক্টে DB ডিফল্ট 0.0000।

        return DB::transaction(static function () use ($row, $data, $id, $name): int {
            if ($id > 0) {
                Product::updateById($id, $row);

                return $id;
            }

            // SKU — দেওয়া না থাকলে সিকোয়েন্স থেকে
            $sku = trim((string) ($data['sku'] ?? ''));

            if ($sku === '') {
                $sku = CodeGenerator::next('product', 'P');
            } elseif (Product::bySku($sku) !== []) {
                throw new RuntimeException("SKU '$sku' already exists.");
            }

            $row['sku']         = $sku;
            $row['has_variant'] = 0;

            return Product::create($row);
        });
    }

    public static function delete(int $id): bool
    {
        $product = Product::find($id);

        if ($product === []) {
            throw new RuntimeException('Product not found.');
        }

        // ফেজ ৩-এ অর্ডার এলে এখানে "অর্ডারে ব্যবহৃত হয়েছে কি না" চেক যোগ হবে।
        // তখন ডিলিটের বদলে isActive = 0 ই একমাত্র পথ হবে।
        return Product::deleteById($id) > 0;
    }

    // -------------------------------------------------------------------------
    // ভ্যারিয়েন্ট
    // -------------------------------------------------------------------------

    /**
     * বেছে দেওয়া অ্যাট্রিবিউট ভ্যালু থেকে সব কম্বিনেশন তৈরি/হালনাগাদ করে।
     *
     *     syncVariants($productId, [
     *         1 => [23, 21],      // Color: Navy, White
     *         2 => [3, 4, 5],     // Size:  M, L, XL
     *     ]);
     *     // → ২ × ৩ = ৬টা ভ্যারিয়েন্ট
     *
     * আগের ভ্যারিয়েন্ট **মুছে ফেলা হয় না** — নতুন তালিকায় না থাকলে শুধু
     * isActive = 0 হয়, যাতে পুরোনো অর্ডার/স্টকের ইতিহাস অক্ষত থাকে।
     *
     * @param  array<int,array<int,int>> $selection attribute_id => [value_id, …]
     * @return array{created:int,kept:int,deactivated:int,total:int}
     */
    public static function syncVariants(int $productId, array $selection): array
    {
        $product = Product::find($productId);

        if ($product === []) {
            throw new RuntimeException('Product not found.');
        }

        $axes = self::resolveAxes($selection);

        return DB::transaction(static function () use ($productId, $product, $axes): array {
            $combos   = self::cartesian($axes);
            $existing = Utility::keyBy(ProductVariant::ofProduct($productId), 'signature');

            $created = 0;
            $kept    = 0;
            $keep    = [];

            foreach ($combos as $index => $combo) {
                $signature = self::signature($combo);
                $keep[]    = $signature;

                $label = implode(' / ', array_column($combo, 'value'));

                if (isset($existing[$signature])) {
                    ProductVariant::updateById((int) $existing[$signature]['id'], [
                        'name'       => $label,
                        'sort_order' => $index,
                        'isActive'   => 1,
                    ]);
                    $kept++;

                    continue;
                }

                $variantId = ProductVariant::create([
                    'product_id'     => $productId,
                    'signature'      => $signature,
                    'name'           => $label,
                    'sku'            => self::variantSku((string) $product['sku'], $combo, $productId),
                    'purchase_price' => (float) $product['purchase_price'],
                    'offer_price'    => (float) $product['offer_price'],
                    'stock'          => 0,
                    'sort_order'     => $index,
                    'isActive'       => 1,
                ]);

                foreach ($combo as $part) {
                    DB::insert('product_variant_values', [
                        'variant_id'         => $variantId,
                        'product_id'         => $productId,
                        'attribute_id'       => $part['attribute_id'],
                        'attribute_value_id' => $part['value_id'],
                    ]);
                }

                $created++;
            }

            // তালিকার বাইরে পড়ে যাওয়াগুলো নিষ্ক্রিয়
            $deactivated = 0;

            foreach ($existing as $signature => $variant) {
                if (!in_array((string) $signature, $keep, true) && (int) $variant['isActive'] === 1) {
                    ProductVariant::updateById((int) $variant['id'], ['isActive' => 0]);
                    $deactivated++;
                }
            }

            $hasVariant = $combos !== [];

            Product::updateById($productId, [
                'has_variant' => $hasVariant ? 1 : 0,
                'stock'       => $hasVariant ? ProductVariant::totalStock($productId) : (float) $product['stock'],
            ]);

            return [
                'created'     => $created,
                'kept'        => $kept,
                'deactivated' => $deactivated,
                'total'       => count($combos),
            ];
        });
    }

    /**
     * একটা ভ্যারিয়েন্টের দাম/স্টক আপডেট (ভ্যারিয়েন্ট টেবিলের গ্রিড থেকে)।
     *
     * @param array<string,mixed> $data
     */
    public static function updateVariant(int $variantId, array $data): bool
    {
        $variant = ProductVariant::find($variantId);

        if ($variant === []) {
            throw new RuntimeException('Variant not found.');
        }

        $row = [];

        // স্টক এখানে বদলায় না — StockService এর দায়িত্ব (doc/08-purchase.md §৯)
        // purchase_price ও বদলায় না — CostService বসায় (weighted-average, শুধু Purchase থেকে)
        // sale_price ভ্যারিয়েন্টে নাই — সবসময় products.sale_price (একই প্রোডাক্টের সব রঙ/সাইজ এক দামে)
        if (array_key_exists('offer_price', $data)) {
            $offerPrice = (float) $data['offer_price'];

            if ($offerPrice < 0) {
                throw new RuntimeException('Price cannot be negative.');
            }

            $row['offer_price'] = $offerPrice;
        }

        foreach (['barcode', 'image'] as $field) {
            if (array_key_exists($field, $data)) {
                $row[$field] = (string) $data[$field];
            }
        }

        if (array_key_exists('isActive', $data)) {
            $row['isActive'] = (int) $data['isActive'] === 1 ? 1 : 0;
        }

        if ($row === []) {
            return false;
        }

        return DB::transaction(static function () use ($variantId, $variant, $row): bool {
            ProductVariant::updateById($variantId, $row);

            $productId = (int) $variant['product_id'];

            Product::updateById($productId, ['stock' => ProductVariant::totalStock($productId)]);

            return true;
        });
    }

    // -------------------------------------------------------------------------
    // দাম
    // -------------------------------------------------------------------------

    /**
     * কার্যকর দাম — অফার চালু থাকলে অফার মূল্য, নাহলে স্বাভাবিক মূল্য।
     *
     * বিক্রয়মূল্য (`regular`) সবসময় **প্রোডাক্টের** — একই প্রোডাক্টের সব রঙ/সাইজ
     * এক দামে বিক্রি হয়, ভ্যারিয়েন্টে আলাদা sale_price নাই। অফার (এবং তার
     * সময়সীমা) প্রোডাক্টে থাকে, চাইলে কোনো ভ্যারিয়েন্টে আলাদা অফার-মূল্য থাকতে পারে।
     *
     * @param  array<string,mixed> $product
     * @param  array<string,mixed> $variant খালি হলে সাধারণ (variant-নির্দিষ্ট অফার না থাকলে)
     * @return array{price:float,regular:float,on_offer:bool,discount:float,discount_percent:float}
     */
    public static function effectivePrice(array $product, array $variant = []): array
    {
        $regular = (float) ($product['sale_price'] ?? 0);
        $offer   = (float) ($variant['offer_price'] ?? 0) ?: (float) ($product['offer_price'] ?? 0);

        $running = $offer > 0 && $offer < $regular && self::offerRunning($product);
        $price   = $running ? $offer : $regular;

        return [
            'price'            => round($price, 2),
            'regular'          => round($regular, 2),
            'on_offer'         => $running,
            'discount'         => round($running ? $regular - $offer : 0, 2),
            'discount_percent' => $running && $regular > 0
                ? round(($regular - $offer) / $regular * 100, 1)
                : 0.0,
        ];
    }

    /** @param array<string,mixed> $product */
    public static function offerRunning(array $product, ?int $now = null): bool
    {
        $now   = $now ?? time();
        $start = (int) ($product['offer_start'] ?? 0);
        $end   = (int) ($product['offer_end'] ?? 0);

        if ($start > 0 && $now < $start) {
            return false;
        }

        if ($end > 0 && $now > $end) {
            return false;
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // পড়া
    // -------------------------------------------------------------------------

    /**
     * একটা প্রোডাক্টের পূর্ণ তথ্য — ক্যাটাগরি পথ, ইউনিট, ভ্যারিয়েন্ট (ভ্যালু সহ), ছবি, দাম।
     *
     * @return array<string,mixed> না পেলে খালি array
     */
    public static function details(int $productId): array
    {
        $product = Product::find($productId);

        if ($product === []) {
            return [];
        }

        return self::hydrate($product);
    }

    /** @return array<string,mixed> */
    public static function detailsBySlug(string $slug): array
    {
        $product = Product::bySlug($slug);

        return $product === [] ? [] : self::hydrate($product);
    }

    /**
     * ফিল্টার সহ প্রোডাক্ট লিস্ট।
     *
     * ফিল্টার: category_id (গাছসহ), q (নাম/SKU), isActive, isFeatured, isNew,
     *          has_variant, on_sale (অফার চলছে এমন), sort (newest|price_asc|price_desc),
     *          page, per_page
     *
     * @param  array<string,mixed> $filters
     * @return array{data:array<int,array<string,mixed>>,total:int,page:int,per_page:int}
     */
    public static function search(array $filters = []): array
    {
        $total = self::searchQuery($filters)->count();

        $perPage = (int) ($filters['per_page'] ?? 25);
        $page    = max(1, (int) ($filters['page'] ?? 1));

        $query = self::applySort(self::searchQuery($filters), (string) ($filters['sort'] ?? ''));

        if ($perPage > 0) {
            $query->page($page, min($perPage, 200));
        }

        $rows = $query->get();

        return [
            'data'     => self::decorate($rows),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }

    // -------------------------------------------------------------------------
    // ছবি
    // -------------------------------------------------------------------------

    /**
     * নতুন ছবি — কোন রঙের (Color অ্যাট্রিবিউট ভ্যালু) সেটা `attribute_value_id`
     * দিয়ে ট্যাগ করা যায় (0 = সাধারণ, সব রঙের জন্য)। এতে একই রঙের সব সাইজ
     * (ভ্যারিয়েন্ট) একই ছবি শেয়ার করে (doc/09-media-and-purchase-pricing.md M-01)।
     *
     * @param array<string,mixed> $data path, alt?, attribute_value_id?, is_primary?, sort_order?
     */
    public static function addImage(int $productId, array $data): int
    {
        if (Product::find($productId) === []) {
            throw new RuntimeException('Product not found.');
        }

        $path = trim((string) ($data['path'] ?? ''));

        if ($path === '') {
            throw new RuntimeException('Image path is required.');
        }

        $attributeValueId = (int) ($data['attribute_value_id'] ?? 0);

        if ($attributeValueId > 0 && AttributeValue::find($attributeValueId) === []) {
            throw new RuntimeException('Color value not found.');
        }

        $first = !ProductImage::where('product_id', $productId)->exists();

        $imageId = ProductImage::create([
            'product_id'         => $productId,
            'attribute_value_id' => $attributeValueId,
            'path'               => $path,
            'alt'                => (string) ($data['alt'] ?? ''),
            'is_primary'         => $first || (int) ($data['is_primary'] ?? 0) === 1 ? 1 : 0,
            'sort_order'         => (int) ($data['sort_order'] ?? 0),
        ]);

        if ($first || (int) ($data['is_primary'] ?? 0) === 1) {
            ProductImage::makePrimary($imageId);
        }

        return $imageId;
    }

    /** @param array<string,mixed> $data is_primary?, alt?, attribute_value_id? */
    public static function updateImage(int $imageId, array $data): bool
    {
        $image = ProductImage::find($imageId);

        if ($image === []) {
            throw new RuntimeException('Image not found.');
        }

        if ((int) ($data['is_primary'] ?? 0) === 1) {
            ProductImage::makePrimary($imageId);
        }

        $row = [];

        if (array_key_exists('alt', $data)) {
            $row['alt'] = (string) $data['alt'];
        }

        if (array_key_exists('attribute_value_id', $data)) {
            $row['attribute_value_id'] = (int) $data['attribute_value_id'];
        }

        if ($row !== []) {
            ProductImage::updateById($imageId, $row);
        }

        return true;
    }

    public static function deleteImage(int $imageId): bool
    {
        $image = ProductImage::find($imageId);

        if ($image === []) {
            return false;
        }

        ProductImage::deleteById($imageId);
        Upload::delete((string) $image['path']);

        // প্রধান ছবি মুছে ফেললে পরেরটাকে প্রধান করি
        if ((int) $image['is_primary'] === 1) {
            $next = ProductImage::ofProduct((int) $image['product_id']);

            if ($next !== []) {
                ProductImage::makePrimary((int) $next[0]['id']);
            }
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // ভেতরের কাজ
    // -------------------------------------------------------------------------

    /** @param array<string,mixed> $filters */
    private static function searchQuery(array $filters): QueryBuilder
    {
        $query = Product::query();

        if (!empty($filters['category_id'])) {
            $ids = Category::descendantIds((int) $filters['category_id']);
            $query->whereIn('category_id', $ids === [] ? [0] : $ids);
        }

        if (!empty($filters['q'])) {
            $term = (string) $filters['q'];
            $query->whereRaw('(name LIKE ? OR sku LIKE ?)', ['%' . $term . '%', '%' . $term . '%']);
        }

        foreach (['isActive', 'isFeatured', 'isNew', 'has_variant'] as $flag) {
            if (isset($filters[$flag]) && $filters[$flag] !== '') {
                $query->where($flag, (int) $filters[$flag]);
            }
        }

        // অফার এখন চলছে এমন প্রোডাক্ট — effectivePrice()/offerRunning() এর একই শর্ত,
        // SQL এ যাতে pagination-এর total ও ঠিক থাকে (post-filter না)
        if (!empty($filters['on_sale'])) {
            $now = time();
            $query->whereRaw(
                '(offer_price > 0 AND offer_price < sale_price '
                . 'AND (offer_start = 0 OR offer_start <= ?) '
                . 'AND (offer_end = 0 OR offer_end >= ?))',
                [$now, $now]
            );
        }

        return $query;
    }

    /** স্টোরফ্রন্টের সর্ট অপশন — খালি/অচেনা হলে আগের ডিফল্ট (sort_order, id DESC) */
    private static function applySort(QueryBuilder $query, string $sort): QueryBuilder
    {
        return match ($sort) {
            'price_asc'  => $query->orderBy('sale_price', 'ASC')->orderBy('id', 'DESC'),
            'price_desc' => $query->orderBy('sale_price', 'DESC')->orderBy('id', 'DESC'),
            'newest'     => $query->orderBy('id', 'DESC'),
            default      => $query->orderBy('sort_order')->orderBy('id', 'DESC'),
        };
    }

    /**
     * লিস্টের সারিগুলোতে ক্যাটাগরি/ইউনিট/ছবি/দাম জুড়ে দেয় (N+1 ছাড়া)।
     *
     * @param  array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function decorate(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $productIds  = array_map(static fn (array $r) => (int) $r['id'], $rows);
        $categoryIds = array_values(array_unique(array_map(static fn (array $r) => (int) $r['category_id'], $rows)));
        $unitIds     = array_values(array_unique(array_map(static fn (array $r) => (int) $r['unit_id'], $rows)));

        $categories = Utility::keyBy(Category::whereIn('id', $categoryIds)->get());
        $units      = Utility::keyBy(Unit::whereIn('id', $unitIds)->get());
        $images     = ProductImage::primaryOf($productIds);

        $variantCounts = [];

        foreach (DB::select(
            'SELECT product_id, COUNT(*) AS c FROM product_variants
              WHERE product_id IN (' . implode(', ', array_fill(0, count($productIds), '?')) . ')
                AND isActive = 1
           GROUP BY product_id',
            $productIds
        ) as $row) {
            $variantCounts[(int) $row['product_id']] = (int) $row['c'];
        }

        $out = [];

        foreach ($rows as $row) {
            $price = self::effectivePrice($row);

            $out[] = [
                'id'            => (int) $row['id'],
                'name'          => $row['name'],
                'slug'          => $row['slug'],
                'sku'           => $row['sku'],
                'category_id'   => (int) $row['category_id'],
                'category_name' => $categories[(int) $row['category_id']]['name'] ?? '',
                'unit_id'       => (int) $row['unit_id'],
                'unit_name'     => $units[(int) $row['unit_id']]['name'] ?? '',
                'unit_code'     => $units[(int) $row['unit_id']]['code'] ?? '',
                'image'         => $images[(int) $row['id']] ?? '',
                'purchase_price' => (float) $row['purchase_price'],
                'price'         => $price['price'],
                'regular_price' => $price['regular'],
                'on_offer'      => $price['on_offer'],
                'discount_percent' => $price['discount_percent'],
                'stock'         => (float) $row['stock'],
                'stock_alert'   => (float) $row['stock_alert'],
                'low_stock'     => (float) $row['stock_alert'] > 0 && (float) $row['stock'] <= (float) $row['stock_alert'],
                'has_variant'   => (int) $row['has_variant'] === 1,
                'variant_count' => $variantCounts[(int) $row['id']] ?? 0,
                'isActive'      => (int) $row['isActive'],
                'isFeatured'    => (int) $row['isFeatured'],
                'isNew'         => (int) $row['isNew'],
            ];
        }

        return $out;
    }

    /**
     * @param  array<string,mixed> $product
     * @return array<string,mixed>
     */
    private static function hydrate(array $product): array
    {
        $productId = (int) $product['id'];
        $variants  = ProductVariant::ofProduct($productId);
        $values    = ProductVariant::valuesOf(array_map(static fn (array $v) => (int) $v['id'], $variants));

        $variantList = [];

        foreach ($variants as $variant) {
            $price = self::effectivePrice($product, $variant);

            $variantList[] = [
                'id'             => (int) $variant['id'],
                'name'           => $variant['name'],
                'sku'            => $variant['sku'],
                'barcode'        => $variant['barcode'],
                'signature'      => $variant['signature'],
                'purchase_price' => (float) $variant['purchase_price'],
                'offer_price'    => (float) $variant['offer_price'],
                'price'          => $price['price'],
                'regular_price'  => $price['regular'],
                'on_offer'       => $price['on_offer'],
                'stock'          => (float) $variant['stock'],
                'image'          => $variant['image'],
                'isActive'       => (int) $variant['isActive'],
                'values'         => $values[(int) $variant['id']] ?? [],
            ];
        }

        $unit     = Unit::find((int) $product['unit_id']);
        $price    = self::effectivePrice($product);
        $images   = ProductImage::ofProduct($productId);

        $colorValueIds = array_values(array_unique(array_filter(
            array_map(static fn (array $i) => (int) $i['attribute_value_id'], $images)
        )));
        $colorValues = $colorValueIds === []
            ? []
            : Utility::keyBy(AttributeValue::whereIn('id', $colorValueIds)->get());

        return [
            'id'                => $productId,
            'name'              => $product['name'],
            'slug'              => $product['slug'],
            'sku'               => $product['sku'],
            'short_description' => $product['short_description'],
            'description'       => $product['description'],
            'category_id'       => (int) $product['category_id'],
            'category_path'     => CategoryService::breadcrumb((int) $product['category_id']),
            'unit_id'           => (int) $product['unit_id'],
            'unit_name'         => $unit['name'] ?? '',
            'unit_code'         => $unit['code'] ?? '',
            'purchase_price'    => (float) $product['purchase_price'],
            'sale_price'        => (float) $product['sale_price'],
            'offer_price'       => (float) $product['offer_price'],
            'offer_start'       => (int) $product['offer_start'],
            'offer_end'         => (int) $product['offer_end'],
            'offer_start_date'  => (int) $product['offer_start'] > 0 ? date('Y-m-d', (int) $product['offer_start']) : '',
            'offer_end_date'    => (int) $product['offer_end'] > 0 ? date('Y-m-d', (int) $product['offer_end']) : '',
            'price'             => $price['price'],
            'regular_price'     => $price['regular'],
            'on_offer'          => $price['on_offer'],
            'discount_percent'  => $price['discount_percent'],
            'stock'             => (float) $product['stock'],
            'stock_alert'       => (float) $product['stock_alert'],
            'weight'            => (float) $product['weight'],
            'has_variant'       => (int) $product['has_variant'] === 1,
            'isFeatured'        => (int) $product['isFeatured'],
            'isNew'             => (int) $product['isNew'],
            'isActive'          => (int) $product['isActive'],
            'sort_order'        => (int) $product['sort_order'],
            'meta_title'        => $product['meta_title'],
            'meta_description'  => $product['meta_description'],
            'variants'          => $variantList,
            'images'            => array_map(
                static function (array $i) use ($colorValues): array {
                    $valueId = (int) $i['attribute_value_id'];

                    return [
                        'id'                 => (int) $i['id'],
                        'path'               => $i['path'],
                        'alt'                => $i['alt'],
                        'attribute_value_id' => $valueId,
                        'color_name'         => $colorValues[$valueId]['value'] ?? '',
                        'color_hex'          => $colorValues[$valueId]['color_hex'] ?? '',
                        'is_primary'         => (int) $i['is_primary'],
                    ];
                },
                $images
            ),
            'selected_values' => self::selectedValueIds($productId),
        ];
    }

    /**
     * এই প্রোডাক্টে বর্তমানে কোন অ্যাট্রিবিউট ভ্যালুগুলো ব্যবহৃত হচ্ছে —
     * ভ্যারিয়েন্ট বিল্ডারে চেকবক্স আগে থেকে টিক দেখাতে।
     *
     * @return array<int,array<int,int>> attribute_id => [value_id, …]
     */
    private static function selectedValueIds(int $productId): array
    {
        $rows = DB::select(
            'SELECT DISTINCT v.attribute_id, v.attribute_value_id
               FROM product_variant_values v
               JOIN product_variants pv ON pv.id = v.variant_id
              WHERE v.product_id = ? AND pv.isActive = 1',
            [$productId]
        );

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row['attribute_id']][] = (int) $row['attribute_value_id'];
        }

        return $out;
    }

    /**
     * ইনপুট যাচাই করে প্রতিটি অ্যাট্রিবিউটের ভ্যালুগুলো সাজিয়ে দেয়।
     *
     * @param  array<int,array<int,int>> $selection
     * @return array<int,array<int,array{attribute_id:int,value_id:int,value:string,code:string}>>
     */
    private static function resolveAxes(array $selection): array
    {
        $axes = [];

        foreach ($selection as $attributeId => $valueIds) {
            $attributeId = (int) $attributeId;
            $valueIds    = array_values(array_unique(array_map('intval', (array) $valueIds)));

            if ($valueIds === []) {
                continue;
            }

            $attribute = Attribute::find($attributeId);

            if ($attribute === []) {
                throw new RuntimeException("Attribute not found (id=$attributeId).");
            }

            $values = AttributeValue::whereIn('id', $valueIds)
                ->where('attribute_id', $attributeId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            if (count($values) !== count($valueIds)) {
                throw new RuntimeException("Some values for '{$attribute['name']}' were not found.");
            }

            $axes[(int) $attribute['sort_order'] . '_' . $attributeId] = array_map(
                static fn (array $v) => [
                    'attribute_id' => $attributeId,
                    'value_id'     => (int) $v['id'],
                    'value'        => (string) $v['value'],
                    'code'         => (string) $v['code'],
                ],
                $values
            );
        }

        ksort($axes, SORT_NATURAL);

        return array_values($axes);
    }

    /**
     * কার্তেসীয় গুণফল — [[A,B],[1,2]] → [[A,1],[A,2],[B,1],[B,2]]
     *
     * @param  array<int,array<int,array<string,mixed>>> $axes
     * @return array<int,array<int,array<string,mixed>>>
     */
    private static function cartesian(array $axes): array
    {
        if ($axes === []) {
            return [];
        }

        $result = [[]];

        foreach ($axes as $axis) {
            $next = [];

            foreach ($result as $combo) {
                foreach ($axis as $item) {
                    $next[] = array_merge($combo, [$item]);
                }
            }

            $result = $next;
        }

        return $result;
    }

    /**
     * কম্বিনেশনের স্থায়ী পরিচয় — value id গুলো সংখ্যাক্রমে সাজিয়ে জোড়া।
     * ক্রম নির্বিশেষে একই কম্বিনেশন সবসময় একই signature দেয়।
     *
     * @param array<int,array<string,mixed>> $combo
     */
    private static function signature(array $combo): string
    {
        $ids = array_map(static fn (array $c) => (int) $c['value_id'], $combo);
        sort($ids, SORT_NUMERIC);

        return implode('-', $ids);
    }

    /**
     * ভ্যারিয়েন্ট SKU — প্রোডাক্টের SKU + ভ্যালু কোড। সংঘর্ষ হলে শেষে সিরিয়াল।
     *
     * @param array<int,array<string,mixed>> $combo
     */
    private static function variantSku(string $productSku, array $combo, int $productId): string
    {
        $base = $productSku . '-' . implode('-', array_column($combo, 'code'));
        $sku  = $base;
        $n    = 1;

        while (DB::getRow('product_variants', ['sku' => $sku]) !== []) {
            $sku = $base . '-' . (++$n);
        }

        return mb_substr($sku, 0, 80);
    }
}
