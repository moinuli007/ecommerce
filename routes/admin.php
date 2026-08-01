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
use App\Modules\Dashboard\Controllers\DashboardController;

// লগইন (গেস্ট)
Router::get('/admin/login',   [LoginController::class, 'form'],   ['name' => 'admin.login']);
Router::post('/admin/login',  [LoginController::class, 'submit'], ['name' => 'admin.login.submit']);
Router::any('/admin/logout',  [LoginController::class, 'logout'], ['name' => 'admin.logout']);

Router::group(['prefix' => '/admin', 'guard' => 'admin'], function (): void {

    Router::get('/', [DashboardController::class, 'index'], ['name' => 'admin.home']);

    // প্রোফাইল
    Router::get('/profile', [ProfileController::class, 'show'], ['name' => 'admin.profile']);

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
