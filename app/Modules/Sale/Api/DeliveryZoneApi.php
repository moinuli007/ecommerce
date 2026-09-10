<?php

namespace App\Modules\Sale\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Modules\Sale\Models\DeliveryZone;
use App\Modules\Sale\Services\DeliveryZoneService;
use RuntimeException;

/**
 * ডেলিভারি জোন API — অ্যাডমিন CRUD (doc §৯) ও স্টোরফ্রন্ট চেকআউট (doc §৬),
 * শেষেরটার জন্য আলাদা `publicIndex()` — ক্লায়েন্ট `active_only` বাদ দিলেও
 * নিষ্ক্রিয় জোন যেন কখনো পাবলিকে না দেখায়।
 */
final class DeliveryZoneApi
{
    /** GET /api/v1/delivery-zones — অ্যাডমিন, সব জোন (ফিল্টার ঐচ্ছিক) */
    public static function index(): array
    {
        return Response::success('', [
            'zones' => DeliveryZoneService::list([
                'active_only' => Request::int('active_only'),
            ]),
        ]);
    }

    /** GET /api/v1/storefront/delivery-zones — শুধু সক্রিয় জোন */
    public static function publicIndex(): array
    {
        return Response::success('', [
            'zones' => DeliveryZoneService::list(['active_only' => 1]),
        ]);
    }

    /** GET /api/v1/delivery-zones/{id} */
    public static function show(): array
    {
        $zone = DeliveryZone::find(Request::paramInt('id'));

        if ($zone === []) {
            return Response::error('Delivery zone not found.');
        }

        return Response::success('', ['zone' => $zone]);
    }

    /** POST /api/v1/delivery-zones */
    public static function store(): array
    {
        if (!Validator::check(Request::all(), [
            'name'                     => 'required|max:100',
            'fee'                      => 'required|numeric|min:0',
            'free_delivery_threshold' => 'numeric|min:0',
        ])) {
            return Response::payload();
        }

        try {
            $id = DeliveryZoneService::save(Request::all());
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Delivery zone added.', ['zone' => DeliveryZone::find($id)]);
    }

    /** PUT /api/v1/delivery-zones/{id} */
    public static function update(): array
    {
        $id = Request::paramInt('id');

        if (DeliveryZone::find($id) === []) {
            return Response::error('Delivery zone not found.');
        }

        if (!Validator::check(Request::all(), [
            'name'                     => 'required|max:100',
            'fee'                      => 'required|numeric|min:0',
            'free_delivery_threshold' => 'numeric|min:0',
        ])) {
            return Response::payload();
        }

        try {
            DeliveryZoneService::save(Request::all(), $id);
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Delivery zone updated.', ['zone' => DeliveryZone::find($id)]);
    }

    /** DELETE /api/v1/delivery-zones/{id} */
    public static function destroy(): array
    {
        try {
            $done = DeliveryZoneService::delete(Request::paramInt('id'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return $done
            ? Response::success('Delivery zone deleted.')
            : Response::error('Delivery zone not found.');
    }
}
