<?php

namespace App\Modules\Purchase\Controllers;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Purchase\Api\PurchaseApi;
use App\Modules\Purchase\Api\StockApi;

/**
 * স্টক সমন্বয় ও ক্রয় ফেরতের অ্যাডমিন পেজ। D-04 — শুধু Api কল।
 */
final class StockController
{
    // ------------------------------------------------------------- সমন্বয়

    public static function adjustments(): string
    {
        Response::reset();
        $payload = StockApi::adjustments();

        return View::layout('layouts/admin', 'Purchase::stock/adjustment-list', [
            'title'       => 'স্টক সমন্বয়',
            'adjustments' => $payload['adjustments'] ?? [],
            'filters'     => ['q' => Request::string('q')],
            'messages'    => $payload['m'] ?? [],
        ]);
    }

    public static function adjustmentCreate(): string
    {
        return self::adjustmentForm(0);
    }

    public static function adjustmentEdit(): string
    {
        return self::adjustmentForm(Request::paramInt('id'));
    }

    private static function adjustmentForm(int $id): string
    {
        Response::reset();
        $form = PurchaseApi::formData();

        $adjustment = [];

        if ($id > 0) {
            Response::reset();
            Request::setParams(['id' => (string) $id]);
            $adjustment = StockApi::showAdjustment()['adjustment'] ?? [];
        }

        return View::layout('layouts/admin', 'Purchase::stock/adjustment-form', [
            'title'      => $id > 0 ? 'সমন্বয় এডিট' : 'নতুন স্টক সমন্বয়',
            'adjustment' => $adjustment,
            'products'   => $form['products'] ?? [],
            'messages'   => [],
        ]);
    }

    // --------------------------------------------------------- ক্রয় ফেরত

    public static function returns(): string
    {
        Response::reset();
        $payload = StockApi::returns();

        return View::layout('layouts/admin', 'Purchase::stock/return-list', [
            'title'    => 'ক্রয় ফেরত',
            'returns'  => $payload['returns'] ?? [],
            'filters'  => ['q' => Request::string('q')],
            'messages' => $payload['m'] ?? [],
        ]);
    }

    public static function returnCreate(): string
    {
        // ফেরতের জন্য ক্রয়ের তালিকা + তাদের লাইন
        $purchases = DB::select(
            "SELECT p.id, p.code, s.name AS supplier_name
               FROM purchases p JOIN suppliers s ON s.id = p.supplier_id
           ORDER BY p.id DESC LIMIT 200"
        );

        return View::layout('layouts/admin', 'Purchase::stock/return-form', [
            'title'     => 'নতুন ক্রয় ফেরত',
            'purchases' => $purchases,
            'messages'  => [],
        ]);
    }
}
