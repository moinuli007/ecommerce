<?php

/**
 * API রাউট — সব `/api/v1` প্রিফিক্সের নিচে, রেসপন্স সবসময় JSON।
 *
 * guard:
 *   guest    — লগইন লাগে না
 *   auth     — যেকোনো লগইন করা ইউজার
 *   admin    — super_admin বা staff
 *   customer — শুধু কাস্টমার
 */

use App\Core\Router;
use App\Modules\Account\Api\LedgerApi;
use App\Modules\Account\Api\VoucherApi;
use App\Modules\Auth\Api\AuthApi;
use App\Modules\Catalog\Api\AttributeApi;
use App\Modules\Catalog\Api\CategoryApi;
use App\Modules\Catalog\Api\ProductApi;
use App\Modules\Catalog\Api\UnitApi;
use App\Modules\Dashboard\Api\DashboardApi;

Router::group(['prefix' => '/api/v1', 'json' => true], function (): void {

    // ---------------------------------------------------------------------
    // অথ
    // ---------------------------------------------------------------------
    Router::post('/auth/login',  [AuthApi::class, 'login'],  ['name' => 'api.auth.login']);
    Router::post('/auth/logout', [AuthApi::class, 'logout'], ['guard' => 'auth', 'name' => 'api.auth.logout']);
    Router::get('/auth/me',      [AuthApi::class, 'me'],     ['guard' => 'auth', 'name' => 'api.auth.me']);

    // ---------------------------------------------------------------------
    // ভাউচার
    // ---------------------------------------------------------------------
    Router::group(['guard' => 'admin'], function (): void {
        // -----------------------------------------------------------------
        // ড্যাশবোর্ড
        // -----------------------------------------------------------------
        Router::get('/dashboard', [DashboardApi::class, 'index'], ['name' => 'api.dashboard']);

        Router::get('/vouchers',              [VoucherApi::class, 'index'],       ['name' => 'api.voucher.index']);
        Router::get('/vouchers/form-data',    [VoucherApi::class, 'formData'],    ['name' => 'api.voucher.form']);
        Router::get('/vouchers/code/{code}',  [VoucherApi::class, 'showByCode'],  ['name' => 'api.voucher.byCode']);
        Router::get('/vouchers/{id}',         [VoucherApi::class, 'show'],        ['name' => 'api.voucher.show']);
        Router::post('/vouchers',             [VoucherApi::class, 'store'],       ['name' => 'api.voucher.store']);
        Router::post('/vouchers/opening',     [VoucherApi::class, 'storeOpening'], ['name' => 'api.voucher.opening']);
        Router::put('/vouchers/{id}',         [VoucherApi::class, 'update'],      ['name' => 'api.voucher.update']);
        Router::delete('/vouchers/{id}',      [VoucherApi::class, 'destroy'],     ['name' => 'api.voucher.destroy']);

        // -----------------------------------------------------------------
        // চার্ট অব অ্যাকাউন্টস / লেজার
        // -----------------------------------------------------------------
        Router::get('/ledgers',                  [LedgerApi::class, 'index'],     ['name' => 'api.ledger.index']);
        Router::get('/ledgers/tree',             [LedgerApi::class, 'tree'],      ['name' => 'api.ledger.tree']);
        Router::get('/ledgers/options',          [LedgerApi::class, 'options'],   ['name' => 'api.ledger.options']);
        Router::post('/ledgers',                 [LedgerApi::class, 'store'],     ['name' => 'api.ledger.store']);
        Router::get('/ledgers/{id}/statement',   [LedgerApi::class, 'statement'], ['name' => 'api.ledger.statement']);
        Router::get('/ledgers/{id}/balance',     [LedgerApi::class, 'balance'],   ['name' => 'api.ledger.balance']);

        // -----------------------------------------------------------------
        // রিপোর্ট
        // -----------------------------------------------------------------
        Router::get('/reports/trial-balance', [LedgerApi::class, 'trialBalance'], ['name' => 'api.report.trialBalance']);

        // =================================================================
        //  ক্যাটালগ
        // =================================================================

        // ক্যাটাগরি
        Router::get('/categories',         [CategoryApi::class, 'index'],   ['name' => 'api.category.index']);
        Router::get('/categories/{id}',    [CategoryApi::class, 'show'],    ['name' => 'api.category.show']);
        Router::post('/categories',        [CategoryApi::class, 'store'],   ['name' => 'api.category.store']);
        Router::put('/categories/{id}',    [CategoryApi::class, 'update'],  ['name' => 'api.category.update']);
        Router::delete('/categories/{id}', [CategoryApi::class, 'destroy'], ['name' => 'api.category.destroy']);

        // ইউনিট
        Router::get('/units',              [UnitApi::class, 'index'],        ['name' => 'api.unit.index']);
        Router::get('/units/convert',      [UnitApi::class, 'convert'],      ['name' => 'api.unit.convert']);
        Router::post('/units',             [UnitApi::class, 'store'],        ['name' => 'api.unit.store']);
        Router::delete('/units/{id}',      [UnitApi::class, 'destroy'],      ['name' => 'api.unit.destroy']);
        Router::post('/unit-groups',       [UnitApi::class, 'storeGroup'],   ['name' => 'api.unitGroup.store']);
        Router::delete('/unit-groups/{id}', [UnitApi::class, 'destroyGroup'], ['name' => 'api.unitGroup.destroy']);

        // অ্যাট্রিবিউট (Size, Color …)
        Router::get('/attributes',              [AttributeApi::class, 'index'],        ['name' => 'api.attribute.index']);
        Router::post('/attributes',             [AttributeApi::class, 'store'],        ['name' => 'api.attribute.store']);
        Router::delete('/attributes/{id}',      [AttributeApi::class, 'destroy'],      ['name' => 'api.attribute.destroy']);
        Router::post('/attribute-values',       [AttributeApi::class, 'storeValue'],   ['name' => 'api.attributeValue.store']);
        Router::delete('/attribute-values/{id}', [AttributeApi::class, 'destroyValue'], ['name' => 'api.attributeValue.destroy']);

        // প্রোডাক্ট — স্থির পাথ আগে, {id} পরে
        Router::get('/products',                 [ProductApi::class, 'index'],        ['name' => 'api.product.index']);
        Router::get('/products/form-data',       [ProductApi::class, 'formData'],     ['name' => 'api.product.form']);
        Router::get('/products/slug/{slug}',     [ProductApi::class, 'showBySlug'],   ['name' => 'api.product.bySlug']);
        Router::get('/products/{id}',            [ProductApi::class, 'show'],         ['name' => 'api.product.show']);
        Router::post('/products',                [ProductApi::class, 'store'],        ['name' => 'api.product.store']);
        Router::put('/products/{id}',            [ProductApi::class, 'update'],       ['name' => 'api.product.update']);
        Router::delete('/products/{id}',         [ProductApi::class, 'destroy'],      ['name' => 'api.product.destroy']);
        Router::post('/products/{id}/variants',  [ProductApi::class, 'syncVariants'], ['name' => 'api.product.variants']);
        Router::post('/products/{id}/images',    [ProductApi::class, 'addImage'],     ['name' => 'api.product.addImage']);
        Router::put('/variants/{id}',            [ProductApi::class, 'updateVariant'], ['name' => 'api.variant.update']);
        Router::delete('/images/{id}',           [ProductApi::class, 'deleteImage'],  ['name' => 'api.image.destroy']);
    });
});
