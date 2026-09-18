<?php

/**
 * অ্যাডমিন প্যানেলের পেজ রাউট — সব `/admin` প্রিফিক্সে, রেসপন্স HTML।
 *
 * কন্ট্রোলারগুলো নিজে কোনো DB কোয়েরি করে না — Api ক্লাসের একই static মেথড
 * কল করে যেটা HTTP API রাউটও ব্যবহার করে, তারপর ভিউ রেন্ডার করে।
 * (Decision D-04, doc/01-architecture.md)
 */

use App\Core\Router;
use App\Modules\Account\Controllers\LedgerController;
use App\Modules\Account\Controllers\VoucherController;
use App\Modules\Auth\Controllers\LoginController;
use App\Modules\Auth\Controllers\ProfileController;
use App\Modules\Auth\Controllers\UserController;
use App\Modules\Catalog\Controllers\CatalogController;
use App\Modules\Dashboard\Controllers\DashboardController;
use App\Modules\Purchase\Controllers\PurchaseController;
use App\Modules\Purchase\Controllers\StockController;
use App\Modules\Purchase\Controllers\SupplierController;
use App\Modules\Sale\Controllers\CustomerController;
use App\Modules\Sale\Controllers\DeliveryZoneController;
use App\Modules\Sale\Controllers\OrderController;

// লগইন (গেস্ট)
Router::get('/admin/login',   [LoginController::class, 'form'],   ['name' => 'admin.login']);
Router::post('/admin/login',  [LoginController::class, 'submit'], ['name' => 'admin.login.submit']);
Router::any('/admin/logout',  [LoginController::class, 'logout'], ['name' => 'admin.logout']);

Router::group(['prefix' => '/admin', 'guard' => 'admin'], function (): void {

    Router::get('/', [DashboardController::class, 'index'], ['name' => 'admin.home']);

    // প্রোফাইল
    Router::get('/profile', [ProfileController::class, 'show'], ['name' => 'admin.profile']);

    // ---------------------------------------------------------------------
    // অ্যাডমিন ইউজার ম্যানেজমেন্ট — শুধু Super Admin (doc/13 §৪); স্থির
    // পাথ আগে, {id} পরে
    // ---------------------------------------------------------------------
    Router::get('/users',           [UserController::class, 'index'],  ['guard' => 'super_admin', 'name' => 'admin.user.index']);
    Router::get('/users/create',    [UserController::class, 'create'], ['guard' => 'super_admin', 'name' => 'admin.user.create']);
    Router::get('/users/{id}/edit', [UserController::class, 'edit'],   ['guard' => 'super_admin', 'name' => 'admin.user.edit']);

    // ---------------------------------------------------------------------
    // ক্যাটালগ — স্থির পাথ আগে, {id} পরে
    // ---------------------------------------------------------------------
    Router::get('/categories',          [CatalogController::class, 'categories'],    ['name' => 'admin.category.index']);
    Router::get('/units',               [CatalogController::class, 'units'],         ['name' => 'admin.unit.index']);
    Router::get('/attributes',          [CatalogController::class, 'attributes'],    ['name' => 'admin.attribute.index']);
    Router::get('/products',            [CatalogController::class, 'products'],      ['name' => 'admin.product.index']);
    Router::get('/products/create',     [CatalogController::class, 'productCreate'], ['name' => 'admin.product.create']);
    Router::get('/products/{id}/edit',  [CatalogController::class, 'productEdit'],   ['name' => 'admin.product.edit']);

    // ---------------------------------------------------------------------
    // ক্রয় — স্থির পাথ আগে, {id} পরে
    // ---------------------------------------------------------------------
    Router::get('/suppliers',           [SupplierController::class, 'index'],  ['name' => 'admin.supplier.index']);
    Router::get('/suppliers/create',    [SupplierController::class, 'create'], ['name' => 'admin.supplier.create']);
    Router::get('/suppliers/{id}/edit', [SupplierController::class, 'edit'],   ['name' => 'admin.supplier.edit']);

    Router::get('/purchases',           [PurchaseController::class, 'index'],  ['name' => 'admin.purchase.index']);
    Router::get('/purchases/create',    [PurchaseController::class, 'create'], ['name' => 'admin.purchase.create']);
    Router::get('/purchases/{id}',      [PurchaseController::class, 'show'],   ['name' => 'admin.purchase.show']);
    Router::get('/purchases/{id}/edit', [PurchaseController::class, 'edit'],   ['name' => 'admin.purchase.edit']);

    // স্টক সমন্বয় ও ক্রয় ফেরত — স্থির পাথ আগে
    Router::get('/stock/adjustments',           [StockController::class, 'adjustments'],      ['name' => 'admin.stock.adjustment.index']);
    Router::get('/stock/adjustments/create',    [StockController::class, 'adjustmentCreate'], ['name' => 'admin.stock.adjustment.create']);
    Router::get('/stock/adjustments/{id}/edit', [StockController::class, 'adjustmentEdit'],   ['name' => 'admin.stock.adjustment.edit']);
    Router::get('/stock/returns',               [StockController::class, 'returns'],          ['name' => 'admin.stock.return.index']);
    Router::get('/stock/returns/create',        [StockController::class, 'returnCreate'],     ['name' => 'admin.stock.return.create']);

    // ---------------------------------------------------------------------
    // বিক্রয় — ডেলিভারি জোন (doc/10-storefront-order.md)
    // ---------------------------------------------------------------------
    Router::get('/delivery-zones',           [DeliveryZoneController::class, 'index'],  ['name' => 'admin.deliveryZone.index']);
    Router::get('/delivery-zones/create',    [DeliveryZoneController::class, 'create'], ['name' => 'admin.deliveryZone.create']);
    Router::get('/delivery-zones/{id}/edit', [DeliveryZoneController::class, 'edit'],   ['name' => 'admin.deliveryZone.edit']);

    Router::get('/customers',           [CustomerController::class, 'index'],  ['name' => 'admin.customer.index']);
    Router::get('/customers/create',    [CustomerController::class, 'create'], ['name' => 'admin.customer.create']);
    Router::get('/customers/{id}/edit', [CustomerController::class, 'edit'],   ['name' => 'admin.customer.edit']);

    Router::get('/orders/pending', [OrderController::class, 'pending'], ['name' => 'admin.order.pending']);
    Router::get('/orders',         [OrderController::class, 'index'],   ['name' => 'admin.order.index']);
    Router::get('/orders/{id}',    [OrderController::class, 'show'],    ['name' => 'admin.order.show']);

    // ভাউচার
    Router::get('/vouchers',            [VoucherController::class, 'index'], ['name' => 'admin.voucher.index']);
    Router::get('/vouchers/entry',      [VoucherController::class, 'entry'], ['name' => 'admin.voucher.entry']);
    Router::get('/vouchers/{id}',       [VoucherController::class, 'show'],  ['name' => 'admin.voucher.show']);

    // চার্ট অব অ্যাকাউন্টস
    Router::get('/ledgers',                [LedgerController::class, 'index'],     ['name' => 'admin.ledger.index']);
    Router::get('/ledgers/{id}/statement', [LedgerController::class, 'statement'], ['name' => 'admin.ledger.statement']);

    // রিপোর্ট
    Router::get('/reports/trial-balance', [LedgerController::class, 'trialBalance'], ['name' => 'admin.report.trialBalance']);
});
