<?php

namespace App\Modules\Catalog\Api;

use App\Core\Request;
use App\Core\Response;
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
            return Response::error('প্রোডাক্ট পাওয়া যায়নি।');
        }

        return Response::success('', ['product' => $product]);
    }

    /** GET /api/v1/products/slug/{slug} — স্টোরফ্রন্টের জন্য */
    public static function showBySlug(): array
    {
        $product = ProductService::detailsBySlug((string) Request::param('slug', ''));

        if ($product === [] || (int) ($product['isActive'] ?? 0) !== 1) {
            return Response::error('প্রোডাক্ট পাওয়া যায়নি।');
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

        return Response::success('প্রোডাক্ট যোগ হয়েছে।', ['product' => ProductService::details($id)]);
    }

    /** PUT /api/v1/products/{id} */
    public static function update(): array
    {
        $id = Request::paramInt('id');

        if (Product::find($id) === []) {
            return Response::error('প্রোডাক্ট পাওয়া যায়নি।');
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

        return Response::success('প্রোডাক্ট আপডেট হয়েছে।', ['product' => ProductService::details($id)]);
    }

    /** DELETE /api/v1/products/{id} */
    public static function destroy(): array
    {
        try {
            $done = ProductService::delete(Request::paramInt('id'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return $done ? Response::success('প্রোডাক্ট ডিলিট হয়েছে।') : Response::error('পাওয়া যায়নি।');
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
            return Response::error('প্রোডাক্ট পাওয়া যায়নি।');
        }

        try {
            $summary = ProductService::syncVariants($id, $selection);
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        $message = $summary['total'] === 0
            ? 'সব ভ্যারিয়েন্ট সরানো হয়েছে।'
            : sprintf(
                '%d টি ভ্যারিয়েন্ট — নতুন %d, আগের %d, নিষ্ক্রিয় %d।',
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

        return Response::success('ভ্যারিয়েন্ট আপডেট হয়েছে।');
    }

    /** POST /api/v1/products/{id}/images */
    public static function addImage(): array
    {
        if (!Validator::check(Request::all(), ['path' => 'required|max:255'])) {
            return Response::payload();
        }

        try {
            $imageId = ProductService::addImage(Request::paramInt('id'), Request::all());
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('ছবি যোগ হয়েছে।', ['image_id' => $imageId]);
    }

    /** DELETE /api/v1/images/{id} */
    public static function deleteImage(): array
    {
        return ProductService::deleteImage(Request::paramInt('id'))
            ? Response::success('ছবি সরানো হয়েছে।')
            : Response::error('ছবি পাওয়া যায়নি।');
    }
}
