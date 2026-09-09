<?php

namespace App\Modules\Purchase\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Purchase\Api\SupplierApi;

/**
 * সাপ্লায়ারের অ্যাডমিন পেজ।
 *
 * বাকি কন্ট্রোলারের মতোই — কোনো DB/সার্ভিস কল নাই, ডেটা শুধু Api ক্লাস থেকে
 * (সিদ্ধান্ত D-04)। সেভ/ডিলিট JS থেকে সরাসরি API এ যায়।
 */
final class SupplierController
{
    public static function index(): string
    {
        Response::reset();
        $payload = SupplierApi::index();

        return View::layout('layouts/admin', 'Purchase::supplier/list', [
            'title'     => 'Suppliers',
            'suppliers' => $payload['suppliers'] ?? [],
            'filters'   => ['q' => Request::string('q')],
            'messages'  => $payload['m'] ?? [],
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

    private static function form(int $supplierId): string
    {
        $supplier = [];

        if ($supplierId > 0) {
            Response::reset();
            Request::setParams(['id' => (string) $supplierId]);
            $supplier = SupplierApi::show()['supplier'] ?? [];
        }

        return View::layout('layouts/admin', 'Purchase::supplier/form', [
            'title'    => $supplierId > 0 ? 'Edit Supplier' : 'New Supplier',
            'supplier' => $supplier,
            'messages' => [],
        ]);
    }
}
