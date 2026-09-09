<?php

namespace App\Modules\Catalog\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeValue;
use App\Modules\Catalog\Services\AttributeService;
use RuntimeException;

/**
 * অ্যাট্রিবিউট API — Size (S/M/L/XL/XXL), Color ইত্যাদি।
 */
final class AttributeApi
{
    /** GET /api/v1/attributes — সব অ্যাট্রিবিউট, ভ্যালু সহ */
    public static function index(): array
    {
        return Response::success('', [
            'attributes' => AttributeService::withValues(Request::int('active_only') === 1),
        ]);
    }

    /** POST /api/v1/attributes */
    public static function store(): array
    {
        if (!Validator::check(Request::all(), ['name' => 'required|max:100'])) {
            return Response::payload();
        }

        try {
            $id = AttributeService::save(Request::all(), Request::int('id'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Attribute saved.', ['attribute' => Attribute::find($id)]);
    }

    /** DELETE /api/v1/attributes/{id} */
    public static function destroy(): array
    {
        try {
            $done = AttributeService::delete(Request::paramInt('id'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return $done ? Response::success('Attribute deleted.') : Response::error('Not found.');
    }

    /** POST /api/v1/attribute-values */
    public static function storeValue(): array
    {
        if (!Validator::check(Request::all(), [
            'attribute_id' => 'required|int',
            'value'        => 'required|max:100',
        ])) {
            return Response::payload();
        }

        try {
            $id = AttributeService::saveValue(Request::all(), Request::int('id'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Value saved.', ['value' => AttributeValue::find($id)]);
    }

    /** DELETE /api/v1/attribute-values/{id} */
    public static function destroyValue(): array
    {
        try {
            $done = AttributeService::deleteValue(Request::paramInt('id'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return $done ? Response::success('Value deleted.') : Response::error('Not found.');
    }
}
