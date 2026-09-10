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
 *
 * কন্ট্রোলার কোনো DB/সার্ভিস কল করে না — Api ক্লাসের একই static মেথড কল করে
 * (Decision D-04, doc/01-architecture.md)।
 */

use App\Core\Router;
use App\Modules\Sale\Controllers\StorefrontController;

Router::get('/',                    [StorefrontController::class, 'home'],       ['name' => 'web.home']);
Router::get('/collections/{slug}',  [StorefrontController::class, 'collection'], ['name' => 'web.collection']);
Router::get('/products/{slug}',     [StorefrontController::class, 'product'],    ['name' => 'web.product']);
Router::get('/cart',                [StorefrontController::class, 'cart'],       ['name' => 'web.cart']);
Router::get('/checkout',            [StorefrontController::class, 'checkout'],   ['name' => 'web.checkout']);
Router::get('/orders/track',        [StorefrontController::class, 'trackOrder'], ['name' => 'web.order.track']);
