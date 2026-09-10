<?php

namespace App\Modules\Catalog\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Upload;
use App\Core\Validator;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Services\AttributeService;
use App\Modules\Catalog\Services\CategoryService;
use App\Modules\Catalog\Services\ProductService;
use RuntimeException;

/**
 * প্রোডাক্ট API।
 */
final class ProductApi
{
    /**
     * GET /api/v1/products
     * ফিল্টার: category_id, q, isActive, isFeatured, isNew, has_variant, page, per_page
     */
    public static function index(): array
    {
        $result = ProductService::search([
            'category_id' => Request::int('category_id'),
            'q'           => Request::string('q'),
            'isActive'    => Request::has('isActive') ? Request::int('isActive') : '',
            'isFeatured'  => Request::has('isFeatured') ? Request::int('isFeatured') : '',
            'isNew'       => Request::has('isNew') ? Request::int('isNew') : '',
            'page'        => Request::int('page', 1),
            'per_page'    => Request::int('per_page', 25),
        ]);

        Response::ok();
        Response::set('products', $result['data']);
        Response::set('pagination', [
            'total'    => $result['total'],
            'page'     => $result['page'],
            'per_page' => $result['per_page'],
            'pages'    => $result['per_page'] > 0 ? (int) ceil($result['total'] / $result['per_page']) : 1,
        ]);

        return Response::payload();
    }

    /** GET /api/v1/products/{id} */
    public static function show(): array
    {
        $product = ProductService::details(Request::paramInt('id'));

        if ($product === []) {
            return Response::error('Product not found.');
        }

        return Response::success('', ['product' => $product]);
    }

    /** GET /api/v1/products/slug/{slug} — স্টোরফ্রন্টের জন্য */
    public static function showBySlug(): array
    {
        $product = ProductService::detailsBySlug((string) Request::param('slug', ''));

        if ($product === [] || (int) ($product['isActive'] ?? 0) !== 1) {
            return Response::error('Product not found.');
        }

        return Response::success('', ['product' => $product]);
    }

    /**
     * GET /api/v1/products/form-data
     * প্রোডাক্ট ফর্মের ড্রপডাউন — ক্যাটাগরি, ইউনিট, অ্যাট্রিবিউট (ভ্যালু সহ)।
     */
    public static function formData(): array
    {
        return Response::success('', [
            'categories' => CategoryService::options(),
            'units'      => Unit::options(),
            'attributes' => AttributeService::withValues(),
        ]);
    }

    /** POST /api/v1/products */
    public static function store(): array
    {
        if (!Validator::check(Request::all(), [
            'name'        => 'required|max:191',
            'category_id' => 'required|int',
            'unit_id'     => 'required|int',
            'sale_price'  => 'numeric|min:0',
        ])) {
            return Response::payload();
        }

        try {
            $id = ProductService::save(Request::all());
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Product added.', ['product' => ProductService::details($id)]);
    }

    /** PUT /api/v1/products/{id} */
    public static function update(): array
    {
        $id = Request::paramInt('id');

        if (Product::find($id) === []) {
            return Response::error('Product not found.');
        }

        if (!Validator::check(Request::all(), [
            'name'        => 'required|max:191',
            'category_id' => 'required|int',
            'unit_id'     => 'required|int',
        ])) {
            return Response::payload();
        }

        try {
            ProductService::save(Request::all(), $id);
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Product updated.', ['product' => ProductService::details($id)]);
    }

    /** DELETE /api/v1/products/{id} */
    public static function destroy(): array
    {
        try {
            $done = ProductService::delete(Request::paramInt('id'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return $done ? Response::success('Product deleted.') : Response::error('Not found.');
    }

    /**
     * POST /api/v1/products/{id}/variants
     * ইনপুট: selection = { "<attribute_id>": [value_id, …], … }
     *
     * উদাহরণ — Color(Navy, White) × Size(M, L, XL) → ৬টা ভ্যারিয়েন্ট:
     *   { "selection": { "2": [23, 21], "1": [3, 4, 5] } }
     */
    public static function syncVariants(): array
    {
        $id        = Request::paramInt('id');
        $selection = Request::array('selection');

        if (Product::find($id) === []) {
            return Response::error('Product not found.');
        }

        try {
            $summary = ProductService::syncVariants($id, $selection);
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        $message = $summary['total'] === 0
            ? 'All variants removed.'
            : sprintf(
                '%d variants — %d new, %d kept, %d deactivated.',
                $summary['total'],
                $summary['created'],
                $summary['kept'],
                $summary['deactivated']
            );

        return Response::success($message, [
            'summary' => $summary,
            'product' => ProductService::details($id),
        ]);
    }

    /** PUT /api/v1/variants/{id} — দাম/স্টক আপডেট */
    public static function updateVariant(): array
    {
        try {
            ProductService::updateVariant(Request::paramInt('id'), Request::all());
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Variant updated.');
    }

    /** POST /api/v1/products/{id}/images — multipart, field image (+ attribute_value_id?, alt?, is_primary?) */
    public static function addImage(): array
    {
        $file = Request::file('image');

        if ($file === []) {
            return Response::error('Please choose an image.');
        }

        try {
            $path    = Upload::save($file, 'products');
            $imageId = ProductService::addImage(Request::paramInt('id'), [
                'path'               => $path,
                'alt'                => Request::string('alt'),
                'attribute_value_id' => Request::int('attribute_value_id'),
                'is_primary'         => Request::int('is_primary'),
            ]);
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Image added.', ['image_id' => $imageId, 'path' => $path]);
    }

    /** PUT /api/v1/images/{id} — primary/alt/color change (no new file) */
    public static function updateImage(): array
    {
        try {
            ProductService::updateImage(Request::paramInt('id'), Request::all());
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Image updated.');
    }

    /** DELETE /api/v1/images/{id} */
    public static function deleteImage(): array
    {
        return ProductService::deleteImage(Request::paramInt('id'))
            ? Response::success('Image removed.')
            : Response::error('Image not found.');
    }

    // -------------------------------------------------------------------------
    // স্টোরফ্রন্ট (guard: guest) — doc/10-storefront-order.md §৬
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/storefront/products
     * ফিল্টার: category (স্লাগ), q, sort (newest|price_asc|price_desc), isFeatured, isNew, on_sale, page, per_page
     */
    public static function publicIndex(): array
    {
        return self::publicListResponse([
            'category'   => Request::string('category'),
            'q'          => Request::string('q'),
            'isFeatured' => Request::has('isFeatured') ? Request::int('isFeatured') : '',
            'isNew'      => Request::has('isNew') ? Request::int('isNew') : '',
            'on_sale'    => Request::int('on_sale'),
            'sort'       => Request::string('sort'),
            'page'       => Request::int('page', 1),
            'per_page'   => Request::int('per_page', 24),
        ]);
    }

    /**
     * `publicIndex()` এর মূল কাজ, কিন্তু `$_GET`/`Request` না ছুঁয়ে সরাসরি
     * ফিল্টার আর্গুমেন্ট নেয় — `Request::all()` এক রিকোয়েস্টে একবারই ক্যাশ হয়
     * (doc/03-response-format.md), তাই হোমপেজের একাধিক সেকশন (New Arrivals,
     * Bestsellers) আলাদা ফিল্টারে একই রিকোয়েস্টে একাধিকবার কল করতে হলে
     * Request পড়া যাবে না — StorefrontController তাই এটা সরাসরি কল করে।
     *
     * @param  array<string,mixed> $filters category (স্লাগ), q?, isFeatured?, isNew?, on_sale?, sort?, page?, per_page?
     * @return array{status:int,m:array<int,mixed>,products:array<int,mixed>,pagination:array<string,mixed>}
     */
    public static function publicListResponse(array $filters): array
    {
        $categoryId   = 0;
        $categorySlug = (string) ($filters['category'] ?? '');

        if ($categorySlug !== '') {
            $category   = Category::bySlug($categorySlug);
            $categoryId = (int) ($category['id'] ?? 0);

            // অচেনা স্লাগ — খালি রেজাল্ট, এরর না (ভুল URL এ ৪০৪-এর মতো ভাঙা পেজ না দেখিয়ে)
            if ($categoryId === 0) {
                Response::ok();
                Response::set('products', []);
                Response::set('pagination', ['total' => 0, 'page' => 1, 'per_page' => 0, 'pages' => 1]);

                return Response::payload();
            }
        }

        $result = ProductService::search([
            'category_id' => $categoryId,
            'q'           => $filters['q'] ?? '',
            'isActive'    => 1, // পাবলিকে সবসময় সক্রিয় প্রোডাক্টই — ক্লায়েন্ট এই ফিল্টার বদলাতে পারবে না
            'isFeatured'  => $filters['isFeatured'] ?? '',
            'isNew'       => $filters['isNew'] ?? '',
            'on_sale'     => $filters['on_sale'] ?? 0,
            'sort'        => $filters['sort'] ?? '',
            'page'        => $filters['page'] ?? 1,
            'per_page'    => $filters['per_page'] ?? 24,
        ]);

        Response::ok();
        Response::set('products', array_map([self::class, 'publicProductRow'], $result['data']));
        Response::set('pagination', [
            'total'    => $result['total'],
            'page'     => $result['page'],
            'per_page' => $result['per_page'],
            'pages'    => $result['per_page'] > 0 ? (int) ceil($result['total'] / $result['per_page']) : 1,
        ]);

        return Response::payload();
    }

    /**
     * হোমপেজের "New Arrivals"/"Bestsellers" এর মতো সেকশনের জন্য — শুধু
     * প্রোডাক্ট লিস্ট (pagination মেটা ছাড়া), সরাসরি ফিল্টার আর্গুমেন্ট নেয়।
     *
     * @param  array<string,mixed> $filters publicListResponse() এর একই ফিল্টার
     * @return array<int,array<string,mixed>>
     */
    public static function section(array $filters): array
    {
        Response::reset();

        return self::publicListResponse($filters)['products'] ?? [];
    }

    /** GET /api/v1/storefront/products/{slug} */
    public static function publicShowBySlug(): array
    {
        $product = ProductService::detailsBySlug((string) Request::param('slug', ''));

        if ($product === [] || (int) ($product['isActive'] ?? 0) !== 1) {
            return Response::error('Product not found.');
        }

        return Response::success('', ['product' => self::publicProductDetail($product)]);
    }

    /**
     * লিস্ট কার্ডের জন্য — `purchase_price` বাদ, exact `stock` এর বদলে বুলিয়ান `in_stock`।
     *
     * @param  array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function publicProductRow(array $row): array
    {
        return [
            'id'               => $row['id'],
            'name'             => $row['name'],
            'slug'             => $row['slug'],
            'category_name'    => $row['category_name'],
            'image'            => $row['image'],
            'price'            => $row['price'],
            'regular_price'    => $row['regular_price'],
            'on_offer'         => $row['on_offer'],
            'discount_percent' => $row['discount_percent'],
            'in_stock'         => (float) $row['stock'] > 0,
            'has_variant'      => $row['has_variant'],
            'isFeatured'       => (bool) $row['isFeatured'],
            'isNew'            => (bool) $row['isNew'],
        ];
    }

    /**
     * PDP এর জন্য — `purchase_price`/`stock_alert` বাদ, প্রোডাক্ট ও প্রতিটা
     * ভ্যারিয়েন্টের exact stock এর বদলে বুলিয়ান `in_stock`।
     *
     * @param  array<string,mixed> $product
     * @return array<string,mixed>
     */
    private static function publicProductDetail(array $product): array
    {
        $out = $product;
        unset($out['purchase_price'], $out['stock_alert']);
        $out['in_stock'] = (float) $product['stock'] > 0;
        unset($out['stock']);

        $out['variants'] = array_map(static function (array $variant): array {
            unset($variant['purchase_price']);
            $variant['in_stock'] = (float) $variant['stock'] > 0;
            unset($variant['stock']);

            return $variant;
        }, $product['variants'] ?? []);

        return $out;
    }
}
