<?php

namespace App\Modules\Purchase\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Purchase\Api\PurchaseApi;

/**
 * ক্রয়ের অ্যাডমিন পেজ। D-04 — শুধু Api কল, DB/সার্ভিস নয়।
 */
final class PurchaseController
{
    public static function index(): string
    {
        Response::reset();
        $payload = PurchaseApi::index();

        return View::layout('layouts/admin', 'Purchase::purchase/list', [
            'title'      => 'ক্রয়',
            'purchases'  => $payload['purchases'] ?? [],
            'pagination' => $payload['pagination'] ?? [],
            'filters'    => ['q' => Request::string('q')],
            'messages'   => $payload['m'] ?? [],
        ]);
    }

    public static function create(): string
    {
        Response::reset();
        $form = PurchaseApi::formData();

        return View::layout('layouts/admin', 'Purchase::purchase/form', [
            'title'     => 'নতুন ক্রয়',
            'purchase'  => [],
            'suppliers' => $form['suppliers'] ?? [],
            'products'  => $form['products'] ?? [],
            'messages'  => [],
        ]);
    }

    public static function edit(): string
    {
        $id = Request::paramInt('id');

        Response::reset();
        $form = PurchaseApi::formData();

        Response::reset();
        Request::setParams(['id' => (string) $id]);
        $purchase = PurchaseApi::show()['purchase'] ?? [];

        return View::layout('layouts/admin', 'Purchase::purchase/form', [
            'title'     => 'ক্রয় এডিট',
            'purchase'  => $purchase,
            'suppliers' => $form['suppliers'] ?? [],
            'products'  => $form['products'] ?? [],
            'messages'  => [],
        ]);
    }

    public static function show(): string
    {
        $id = Request::paramInt('id');

        Response::reset();
        Request::setParams(['id' => (string) $id]);
        $purchase = PurchaseApi::show()['purchase'] ?? [];

        return View::layout('layouts/admin', 'Purchase::purchase/show', [
            'title'    => 'ক্রয় ' . ($purchase['code'] ?? ''),
            'purchase' => $purchase,
            'messages' => [],
        ]);
    }
}
