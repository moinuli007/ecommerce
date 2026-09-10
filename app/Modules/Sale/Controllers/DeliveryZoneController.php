<?php

namespace App\Modules\Sale\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Sale\Api\DeliveryZoneApi;

/**
 * ডেলিভারি জোনের অ্যাডমিন পেজ।
 *
 * বাকি কন্ট্রোলারের মতোই — কোনো DB/সার্ভিস কল নাই, ডেটা শুধু Api ক্লাস থেকে
 * (সিদ্ধান্ত D-04)। সেভ/ডিলিট JS থেকে সরাসরি API এ যায়।
 */
final class DeliveryZoneController
{
    public static function index(): string
    {
        Response::reset();
        $payload = DeliveryZoneApi::index();

        return View::layout('layouts/admin', 'Sale::delivery-zone/list', [
            'title'    => 'Delivery Zones',
            'zones'    => $payload['zones'] ?? [],
            'messages' => $payload['m'] ?? [],
        ]);
    }

    public static function create(): string
    {
        return self::form(0);
    }

    public static function edit(): string
    {
        return self::form(Request::paramInt('id'));
    }

    private static function form(int $zoneId): string
    {
        $zone = [];

        if ($zoneId > 0) {
            Response::reset();
            Request::setParams(['id' => (string) $zoneId]);
            $zone = DeliveryZoneApi::show()['zone'] ?? [];
        }

        return View::layout('layouts/admin', 'Sale::delivery-zone/form', [
            'title'    => $zoneId > 0 ? 'Edit Delivery Zone' : 'New Delivery Zone',
            'zone'     => $zone,
            'messages' => [],
        ]);
    }
}
