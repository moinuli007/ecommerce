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
    });
});
