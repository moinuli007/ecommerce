<?php

/**
 * ইনস্টলার — schema + seed চালায় এবং একটা super admin ইউজার বানায়।
 *
 * চালানোর নিয়ম (docker কনটেইনারের ভেতর থেকে):
 *
 *     docker compose exec php php /var/www/html/ecommerce/database/install.php
 *
 * অ্যাডমিন ইউজারের তথ্য আর্গুমেন্ট দিয়ে বদলানো যায়:
 *
 *     php database/install.php --email=admin@shop.test --password=secret --name="Store Admin"
 *
 * স্ক্রিপ্টটা idempotent — বারবার চালালেও ডেটা নষ্ট হবে না
 * (সব CREATE TABLE এ IF NOT EXISTS, সব seed এ ON DUPLICATE KEY UPDATE)।
 */

if (PHP_SAPI !== 'cli') {
    exit("এই স্ক্রিপ্টটা শুধু কমান্ড লাইন থেকে চলে।\n");
}

require_once __DIR__ . '/../bootstrap.php';

use App\Core\DB;
use App\Core\Env;

// ---------------------------------------------------------------------------
// আর্গুমেন্ট
// ---------------------------------------------------------------------------
$options = getopt('', ['email::', 'password::', 'name::', 'phone::', 'skip-admin']);

$adminEmail    = $options['email']    ?? 'admin@ecommerce.moi';
$adminPassword = $options['password'] ?? 'admin1234';
$adminName     = $options['name']     ?? 'Super Admin';
$adminPhone    = $options['phone']    ?? '01700000000';

// ---------------------------------------------------------------------------
// ডেটাবেজ তৈরি (না থাকলে)
// ---------------------------------------------------------------------------
$database = (string) Env::get('DB_DATABASE');

$bootstrapConn = new mysqli(
    (string) Env::get('DB_SERVER'),
    (string) Env::get('DB_USERNAME'),
    (string) Env::get('DB_PASSWORD')
);

$bootstrapConn->query(
    "CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
);
$bootstrapConn->close();

echo "✓ ডেটাবেজ প্রস্তুত: $database\n";

// ---------------------------------------------------------------------------
// schema + seed
// ---------------------------------------------------------------------------
$files = array_merge(
    glob(__DIR__ . '/schema/*.sql') ?: [],
    glob(__DIR__ . '/seed/*.sql') ?: []
);

sort($files);

foreach ($files as $file) {
    runSqlFile($file);
    echo '✓ চালানো হয়েছে: ' . basename($file) . "\n";
}

// ---------------------------------------------------------------------------
// অ্যাডমিন ইউজার
// ---------------------------------------------------------------------------
if (!isset($options['skip-admin'])) {
    $existing = DB::getRow('users', ['email' => $adminEmail]);

    if ($existing === []) {
        DB::insert('users', [
            'name'       => $adminName,
            'email'      => $adminEmail,
            'phone'      => $adminPhone,
            'password'   => password_hash($adminPassword, PASSWORD_DEFAULT),
            'type'       => 1, // super_admin
            'isActive'   => 1,
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        echo "✓ অ্যাডমিন তৈরি হয়েছে: $adminEmail / $adminPassword\n";
        echo "  ⚠ লগইন করার পর পাসওয়ার্ড বদলে নিন।\n";
    } else {
        echo "· অ্যাডমিন আগে থেকেই আছে: $adminEmail\n";
    }
}

echo "\nইনস্টল শেষ। এখন দেখুন: " . Env::get('APP_URL') . "/admin/login\n";

// ---------------------------------------------------------------------------

/**
 * একটা .sql ফাইলের সব স্টেটমেন্ট চালায়।
 * (সাধারণ স্কিমা ফাইলের জন্য যথেষ্ট — এখানে কোনো stored procedure/trigger নাই,
 *  তাই DELIMITER হ্যান্ডেল করার দরকার পড়ে না।)
 */
function runSqlFile(string $path): void
{
    $sql = file_get_contents($path);

    if ($sql === false) {
        throw new RuntimeException("SQL ফাইল পড়া যায়নি: $path");
    }

    $connection = DB::connection();

    if (!$connection->multi_query($sql)) {
        throw new RuntimeException(basename($path) . ' — ' . $connection->error);
    }

    do {
        if ($result = $connection->store_result()) {
            $result->free();
        }
    } while ($connection->more_results() && $connection->next_result());

    if ($connection->error !== '') {
        throw new RuntimeException(basename($path) . ' — ' . $connection->error);
    }
}
