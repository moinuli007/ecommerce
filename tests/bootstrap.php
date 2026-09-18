<?php

/**
 * PHPUnit bootstrap — bootstrap.php এর হালকা সংস্করণ, শুধু টেস্টের জন্য দরকারি অংশ।
 * সেশন শুরু হয় না, রাউট টেবিলও লোড হয় না (routes/*.php) — টেস্ট সরাসরি Service/Model/Core
 * ক্লাস কল করে, HTTP dispatch এর মধ্য দিয়ে না।
 */

define('BASE_PATH', dirname(__DIR__));

// প্রজেক্টের নিজস্ব PSR-4 অটোলোডার (App\\) — bootstrap.php এর মতোই (Decision D-06)
require_once BASE_PATH . '/app/Core/Autoloader.php';

App\Core\Autoloader::register(['App\\' => BASE_PATH . '/app']);

// composer dev-dependency (phpunit/phpunit, vlucas/phpdotenv)
if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

App\Core\Env::load(BASE_PATH . '/.env');

date_default_timezone_set((string) App\Core\Env::get('APP_TIMEZONE', 'Asia/Dhaka'));

// ডেভেলপমেন্ট DB-র সাথেই টেস্ট চলে — প্রতিটা টেস্ট নিজের ট্রানজেকশনে থাকে আর
// শেষে rollback হয় (Tests\TestCase দেখুন), তাই আসল ডেটা কখনো বদলায় না।
// আলাদা টেস্ট DB চাইলে .env এ আলাদা DB_DATABASE দিয়ে একটা .env.testing বানিয়ে
// এখানে সেটা লোড করুন (DB_DATABASE=ecommerce_test জাতীয়)।
