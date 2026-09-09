<?php

/**
 * স্টোরফ্রন্ট (কাস্টমার) রাউট।
 *
 * Phase 2-তে এখানে ক্যাটালগ, প্রোডাক্ট, কার্ট, চেকআউট যোগ হবে —
 * রেফারেন্স সাইটের স্ট্রাকচার অনুযায়ী:
 *   /                      হোম (new arrivals, bestsellers, collections)
 *   /collections/{slug}    কালেকশন / ক্যাটাগরি
 *   /products/{slug}       প্রোডাক্ট ডিটেইল
 *   /cart                  কার্ট
 *   /checkout              চেকআউট (COD + ফ্রি ডেলিভারি ৳3000+)
 *   /account/orders        কাস্টমারের অর্ডার লিস্ট
 *
 * Phase 1-এ শুধু একটা হেলথ/ইনডেক্স রাউট আছে।
 */

use App\Core\Env;
use App\Core\Router;
Router::get('/', static function (): string {
    $appUrl = rtrim((string) Env::get('APP_URL', ''), '/');

    return '<!doctype html><meta charset="utf-8">'
        . '<title>' . htmlspecialchars((string) Env::get('APP_NAME', 'Ecommerce')) . '</title>'
        . '<p>স্টোরফ্রন্ট Phase 2-এ আসছে। এখন <a href="' . $appUrl . '/admin/vouchers">অ্যাডমিন → ভাউচার</a> দেখুন।</p>';
}, ['name' => 'web.home']);
