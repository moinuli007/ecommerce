<?php

namespace App\Modules\Purchase\Api;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Modules\Purchase\Models\Supplier;
use App\Modules\Purchase\Services\PurchaseService;
use RuntimeException;

/**
 * ক্রয় API।
 */
final class PurchaseApi
{
    /** GET /api/v1/purchases */
    public static function index(): array
    {
        $result = PurchaseService::list([
            'q'           => Request::string('q'),
            'supplier_id' => Request::int('supplier_id'),
            'page'        => Request::int('page', 1),
            'per_page'    => Request::int('per_page', 25),
        ]);

        return Response::success('', [
            'purchases'  => $result['data'],
            'pagination' => [
                'total'    => $result['total'],
                'page'     => $result['page'],
                'per_page' => $result['per_page'],
            ],
        ]);
    }

    /** GET /api/v1/purchases/{id} */
    public static function show(): array
    {
        $purchase = PurchaseService::details(Request::paramInt('id'));

        if ($purchase === []) {
            return Response::error('ক্রয় এন্ট্রি পাওয়া যায়নি।');
        }

        return Response::success('', ['purchase' => $purchase]);
    }

    /**
     * GET /api/v1/purchases/form-data — সাপ্লায়ার + প্রোডাক্ট পিকার।
     */
    public static function formData(): array
    {
        $suppliers = Supplier::active()->orderBy('name')->get();

        $products = DB::select(
            "SELECT p.id, p.name, p.sku, p.has_variant, p.unit_id, p.purchase_price,
                    u.name AS unit_name, u.code AS unit_code
               FROM products p
               JOIN units u ON u.id = p.unit_id
              WHERE p.isActive = 1
           ORDER BY p.name"
        );

        $variants = DB::select(
            "SELECT id, product_id, name, sku, purchase_price
               FROM product_variants
              WHERE isActive = 1
           ORDER BY sort_order, id"
        );

        $byProduct = [];

        foreach ($variants as $variant) {
            $byProduct[(int) $variant['product_id']][] = [
                'id'             => (int) $variant['id'],
                'name'           => $variant['name'],
                'sku'            => $variant['sku'],
                'purchase_price' => (float) $variant['purchase_price'],
            ];
        }

        return Response::success('', [
            'suppliers' => array_map(static fn (array $s) => [
                'id'   => (int) $s['id'],
                'name' => $s['name'],
                'code' => $s['code'],
            ], $suppliers),
            'products' => array_map(static fn (array $p) => [
                'id'             => (int) $p['id'],
                'name'           => $p['name'],
                'sku'            => $p['sku'],
                'has_variant'    => (int) $p['has_variant'] === 1,
                'unit_id'        => (int) $p['unit_id'],
                'unit_name'      => $p['unit_name'],
                'unit_code'      => $p['unit_code'],
                'purchase_price' => (float) $p['purchase_price'],
                'variants'       => $byProduct[(int) $p['id']] ?? [],
            ], $products),
        ]);
    }

    /** POST /api/v1/purchases */
    public static function store(): array
    {
        if (!Validator::check(Request::all(), [
            'supplier_id' => 'required|int',
            'items'       => 'required|array',
        ])) {
            return Response::payload();
        }

        try {
            $id = PurchaseService::create(Request::all());
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('ক্রয় সংরক্ষণ হয়েছে।', ['purchase' => PurchaseService::details($id)]);
    }

    /** PUT /api/v1/purchases/{id} */
    public static function update(): array
    {
        $id = Request::paramInt('id');

        if (!Validator::check(Request::all(), [
            'supplier_id' => 'required|int',
            'items'       => 'required|array',
        ])) {
            return Response::payload();
        }

        try {
            PurchaseService::update($id, Request::all());
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('ক্রয় আপডেট হয়েছে।', ['purchase' => PurchaseService::details($id)]);
    }

    /** DELETE /api/v1/purchases/{id} */
    public static function destroy(): array
    {
        try {
            $done = PurchaseService::delete(Request::paramInt('id'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return $done
            ? Response::success('ক্রয় এন্ট্রি ডিলিট হয়েছে।')
            : Response::error('ক্রয় এন্ট্রি পাওয়া যায়নি।');
    }
}
