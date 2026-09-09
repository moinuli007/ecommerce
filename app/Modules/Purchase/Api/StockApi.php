<?php

namespace App\Modules\Purchase\Api;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Modules\Purchase\Services\PurchaseReturnService;
use App\Modules\Purchase\Services\StockAdjustmentService;
use RuntimeException;

/**
 * স্টক — খতিয়ান, সমন্বয়, ক্রয় ফেরত।
 */
final class StockApi
{
    // ---------------------------------------------------------------- খতিয়ান

    /** GET /api/v1/stock/ledger?product_id=&variant_id= */
    public static function ledger(): array
    {
        $productId = Request::int('product_id');

        if ($productId <= 0) {
            return Response::error('product_id দিন।');
        }

        $variantId = Request::int('variant_id');

        $rows = DB::select(
            "SELECT sl.*, p.name AS product_name
               FROM stock_ledger sl
               JOIN products p ON p.id = sl.product_id
              WHERE sl.product_id = ? AND sl.variant_id = ?
           ORDER BY sl.action_time DESC, sl.id DESC
              LIMIT 500",
            [$productId, $variantId]
        );

        return Response::success('', ['ledger' => $rows]);
    }

    // ------------------------------------------------------------- সমন্বয়

    /** GET /api/v1/stock/adjustments */
    public static function adjustments(): array
    {
        $result = StockAdjustmentService::list([
            'q'        => Request::string('q'),
            'reason'   => Request::int('reason'),
            'page'     => Request::int('page', 1),
            'per_page' => Request::int('per_page', 25),
        ]);

        return Response::success('', [
            'adjustments' => $result['data'],
            'pagination'  => ['total' => $result['total'], 'page' => $result['page'], 'per_page' => $result['per_page']],
        ]);
    }

    /** GET /api/v1/stock/adjustments/{id} */
    public static function showAdjustment(): array
    {
        $adj = StockAdjustmentService::details(Request::paramInt('id'));

        return $adj === []
            ? Response::error('সমন্বয় এন্ট্রি পাওয়া যায়নি।')
            : Response::success('', ['adjustment' => $adj]);
    }

    /** POST /api/v1/stock/adjustments */
    public static function storeAdjustment(): array
    {
        if (!Validator::check(Request::all(), ['reason' => 'required|int', 'items' => 'required|array'])) {
            return Response::payload();
        }

        try {
            $id = StockAdjustmentService::create(Request::all());
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('সমন্বয় সংরক্ষণ হয়েছে।', ['adjustment' => StockAdjustmentService::details($id)]);
    }

    /** PUT /api/v1/stock/adjustments/{id} */
    public static function updateAdjustment(): array
    {
        if (!Validator::check(Request::all(), ['reason' => 'required|int', 'items' => 'required|array'])) {
            return Response::payload();
        }

        try {
            StockAdjustmentService::update(Request::paramInt('id'), Request::all());
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('সমন্বয় আপডেট হয়েছে।');
    }

    /** DELETE /api/v1/stock/adjustments/{id} */
    public static function destroyAdjustment(): array
    {
        try {
            $done = StockAdjustmentService::delete(Request::paramInt('id'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return $done ? Response::success('সমন্বয় ডিলিট হয়েছে।') : Response::error('পাওয়া যায়নি।');
    }

    // --------------------------------------------------------- ক্রয় ফেরত

    /** GET /api/v1/stock/returns */
    public static function returns(): array
    {
        $result = PurchaseReturnService::list([
            'q'        => Request::string('q'),
            'page'     => Request::int('page', 1),
            'per_page' => Request::int('per_page', 25),
        ]);

        return Response::success('', [
            'returns'    => $result['data'],
            'pagination' => ['total' => $result['total'], 'page' => $result['page'], 'per_page' => $result['per_page']],
        ]);
    }

    /** GET /api/v1/stock/returns/{id} */
    public static function showReturn(): array
    {
        $ret = PurchaseReturnService::details(Request::paramInt('id'));

        return $ret === []
            ? Response::error('ফেরত এন্ট্রি পাওয়া যায়নি।')
            : Response::success('', ['return' => $ret]);
    }

    /** POST /api/v1/stock/returns */
    public static function storeReturn(): array
    {
        if (!Validator::check(Request::all(), ['purchase_id' => 'required|int', 'items' => 'required|array'])) {
            return Response::payload();
        }

        try {
            $id = PurchaseReturnService::create(Request::all());
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('ক্রয় ফেরত সংরক্ষণ হয়েছে।', ['return' => PurchaseReturnService::details($id)]);
    }

    /** DELETE /api/v1/stock/returns/{id} */
    public static function destroyReturn(): array
    {
        try {
            $done = PurchaseReturnService::delete(Request::paramInt('id'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return $done ? Response::success('ফেরত এন্ট্রি ডিলিট হয়েছে।') : Response::error('পাওয়া যায়নি।');
    }
}
