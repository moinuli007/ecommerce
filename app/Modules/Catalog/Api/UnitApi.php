<?php

namespace App\Modules\Catalog\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Models\UnitGroup;
use App\Modules\Catalog\Services\UnitService;
use RuntimeException;

/**
 * ইউনিট ও ইউনিট গ্রুপ API।
 */
final class UnitApi
{
    /** GET /api/v1/units — গ্রুপসহ পুরো তালিকা */
    public static function index(): array
    {
        return Response::success('', [
            'groups'  => UnitService::grouped(),
            'options' => Unit::options(),
        ]);
    }

    /** POST /api/v1/unit-groups */
    public static function storeGroup(): array
    {
        if (!Validator::check(Request::all(), ['name' => 'required|max:100'])) {
            return Response::payload();
        }

        try {
            $id = UnitService::saveGroup(Request::all(), Request::int('id'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('ইউনিট গ্রুপ সংরক্ষণ হয়েছে।', ['group' => UnitGroup::find($id)]);
    }

    /** DELETE /api/v1/unit-groups/{id} */
    public static function destroyGroup(): array
    {
        try {
            $done = UnitService::deleteGroup(Request::paramInt('id'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return $done ? Response::success('গ্রুপ ডিলিট হয়েছে।') : Response::error('গ্রুপ পাওয়া যায়নি।');
    }

    /** POST /api/v1/units */
    public static function store(): array
    {
        if (!Validator::check(Request::all(), [
            'name'          => 'required|max:100',
            'code'          => 'required|max:20',
            'unit_group_id' => 'required|int',
        ])) {
            return Response::payload();
        }

        try {
            $id = UnitService::saveUnit(Request::all(), Request::int('id'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('ইউনিট সংরক্ষণ হয়েছে।', ['unit' => Unit::find($id)]);
    }

    /** DELETE /api/v1/units/{id} */
    public static function destroy(): array
    {
        try {
            $done = UnitService::deleteUnit(Request::paramInt('id'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return $done ? Response::success('ইউনিট ডিলিট হয়েছে।') : Response::error('ইউনিট পাওয়া যায়নি।');
    }

    /** GET /api/v1/units/convert?quantity=&from=&to= */
    public static function convert(): array
    {
        try {
            $result = UnitService::convert(
                Request::float('quantity'),
                Request::int('from'),
                Request::int('to')
            );
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('', ['result' => $result]);
    }
}
