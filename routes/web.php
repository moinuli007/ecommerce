<?php

/**
 * স্টোরফ্রন্ট (কাস্টমার) রাউট — সব `guard: guest` (ডিফল্ট)।
 *
 * /                      হোম (new arrivals, bestsellers, collections)
 * /collections/{slug}    কালেকশন — slug="all" মানে সব প্রোডাক্ট
 * /products/{slug}       প্রোডাক্ট ডিটেইল
 * /cart                  কার্ট
 * /checkout              চেকআউট (COD + ডেলিভারি জোন + bKash/Nagad রেফারেন্স)
 * /orders/track          গেস্ট অর্ডার ট্র্যাকিং (কোড + ফোন)
 * /register               কাস্টমার রেজিস্ট্রেশন ফর্ম
 * /login                  কাস্টমার লগইন ফর্ম — `Router::deny()` এর হার্ডকোড করা
 *                          401 রিডাইরেক্ট টার্গেট এই পাথটাই ধরে নেয়, নাম বদলানো যাবে না
 * /profile                প্রোফাইল দেখা/এডিট/পাসওয়ার্ড/ডিলিট (guard: customer)
 *
 * কন্ট্রোলার কোনো DB/সার্ভিস কল করে না — Api ক্লাসের একই static মেথড কল করে
 * (Decision D-04, doc/01-architecture.md)। অ্যাকাউন্ট রাউট doc/11-customer-account.md §৮।
 */

use App\Core\Router;
use App\Modules\Sale\Controllers\AccountController;
use App\Modules\Sale\Controllers\StorefrontController;

Router::get('/',                    [StorefrontController::class, 'home'],       ['name' => 'web.home']);
Router::get('/collections/{slug}',  [StorefrontController::class, 'collection'], ['name' => 'web.collection']);
Router::get('/products/{slug}',     [StorefrontController::class, 'product'],    ['name' => 'web.product']);
Router::get('/cart',                [StorefrontController::class, 'cart'],       ['name' => 'web.cart']);
Router::get('/checkout',            [StorefrontController::class, 'checkout'],   ['name' => 'web.checkout']);
Router::get('/orders/track',        [StorefrontController::class, 'trackOrder'], ['name' => 'web.order.track']);

Router::get('/register', [AccountController::class, 'registerForm'], ['name' => 'web.account.register']);
Router::get('/login',    [AccountController::class, 'loginForm'],    ['name' => 'web.account.login']);
Router::any('/logout',   [AccountController::class, 'logout'],       ['name' => 'web.account.logout']);

Router::group(['guard' => 'customer'], function (): void {
    Router::get('/profile', [AccountController::class, 'profile'], ['name' => 'web.account.profile']);
});
