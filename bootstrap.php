<?php

/**
 * অ্যাপ্লিকেশন বুটস্ট্র্যাপ — একমাত্র জায়গা যেখানে গ্লোবাল সেটআপ হয়।
 * public/index.php আর যেকোনো CLI স্ক্রিপ্ট এটাই require করে।
 */

use App\Core\Autoloader;
use App\Core\DB;
use App\Core\Env;
use App\Core\Router;
use App\Core\View;

define('BASE_PATH', __DIR__);

require_once __DIR__ . '/app/Core/Autoloader.php';

Autoloader::register(['App\\' => __DIR__ . '/app']);

// composer ডিপেন্ডেন্সি থাকলে সেটাও লোড হবে (না থাকলেও অ্যাপ চলে)
if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

Env::load(__DIR__ . '/.env');

date_default_timezone_set((string) Env::get('APP_TIMEZONE', 'Asia/Dhaka'));

if (Env::bool('APP_DEBUG', false)) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => str_starts_with((string) Env::get('APP_URL', ''), 'https://'),
    ]);
    session_start();
}

View::boot(__DIR__ . '/resources/views', __DIR__ . '/app/Modules');

View::share('appName', (string) Env::get('APP_NAME', 'Ecommerce'));
View::share('appUrl', rtrim((string) Env::get('APP_URL', ''), '/'));

// রিকোয়েস্ট শেষে DB কানেকশন বন্ধ
register_shutdown_function(static function (): void {
    DB::close();
});

// ---------------------------------------------------------------------------
// রাউট টেবিল — ক্রম গুরুত্বপূর্ণ: api → admin → web
// web.php এ '/' এর মতো ব্রড রাউট থাকে, তাই সেটা সবার শেষে।
// ---------------------------------------------------------------------------
require_once __DIR__ . '/routes/api.php';
require_once __DIR__ . '/routes/admin.php';
require_once __DIR__ . '/routes/web.php';

// static analyser কে বোঝানোর জন্য — Router ব্যবহার হয়েছে রাউট ফাইলগুলোতে
class_exists(Router::class);
