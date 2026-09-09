<?php

namespace App\Modules\Catalog\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Upload;
use App\Core\Validator;
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
}
