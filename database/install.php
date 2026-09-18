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
    exit("This script only runs from the command line.\n");
}

require_once __DIR__ . '/../bootstrap.php';

use App\Core\DB;
use App\Core\Env;
use App\Core\Password;
use App\Core\RequestTime;

// ---------------------------------------------------------------------------
// আর্গুমেন্ট
// ---------------------------------------------------------------------------
$options = getopt('', ['email::', 'password::', 'name::', 'phone::', 'skip-admin', 'demo']);

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

echo "✓ Database ready: $database\n";

// ---------------------------------------------------------------------------
// schema + seed
// ---------------------------------------------------------------------------
$schema = glob(__DIR__ . '/schema/*.sql') ?: [];
$seed   = glob(__DIR__ . '/seed/*.sql') ?: [];

sort($schema);
sort($seed);

foreach (array_merge($schema, $seed) as $file) {
    runSqlFile($file);
    echo '✓ Ran: ' . basename($file) . "\n";
}

// আগে ইনস্টল করা DB-তে `users` টেবিল ইতিমধ্যে ছিল বলে CREATE TABLE IF NOT
// EXISTS নতুন কলাম (password_salt) যোগ করবে না — MySQL এ
// "ALTER TABLE ... ADD COLUMN IF NOT EXISTS" নাই, তাই এখানে PHP দিয়ে
// idempotent-ভাবে চেক করে দরকার হলেই ALTER (doc/13-auth-and-user-management.md §২)।
migrateUsersPasswordSaltColumn();

// --demo দিলে ডেমো ডেটাও (রেফারেন্স সাইটের ক্যাটাগরি গাছ ইত্যাদি)
if (isset($options['demo'])) {
    $demo = glob(__DIR__ . '/demo/*.sql') ?: [];
    sort($demo);

    foreach ($demo as $file) {
        runSqlFile($file);
        echo '✓ Demo: ' . basename($file) . "\n";
    }
}

// ---------------------------------------------------------------------------
// অ্যাডমিন ইউজার
// ---------------------------------------------------------------------------
if (!isset($options['skip-admin'])) {
    $existing = DB::getRow('users', ['email' => $adminEmail]);

    if ($existing === []) {
        $salt = Password::salt();

        DB::insert('users', [
            'name'          => $adminName,
            'email'         => $adminEmail,
            'phone'         => $adminPhone,
            'password'      => Password::hash($adminPassword, $salt), // password_salt + password, doc/13 §২
            'password_salt' => $salt,
            'type'          => 1, // super_admin
            'isActive'      => 1,
            'created_at'    => RequestTime::now(),
            'updated_at'    => RequestTime::now(),
        ]);

        echo "✓ Admin created: $adminEmail / $adminPassword\n";
        echo "  ⚠ Change the password after logging in.\n";
    } else {
        echo "· Admin already exists: $adminEmail\n";
    }
}

echo "\nInstall finished. Now visit: " . Env::get('APP_URL') . "/admin/login\n";

// ---------------------------------------------------------------------------

/**
 * `users.password_salt` কলাম না থাকলে যোগ করে — পুরনো ইনস্টলে
 * CREATE TABLE IF NOT EXISTS কিছু করে না বলে এই আলাদা ধাপ লাগে
 * (doc/13-auth-and-user-management.md §২)।
 */
function migrateUsersPasswordSaltColumn(): void
{
    $database = (string) Env::get('DB_DATABASE');

    $exists = DB::scalar(
        'SELECT COUNT(*) AS c FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$database, 'users', 'password_salt']
    );

    if ((int) $exists > 0) {
        echo "· users.password_salt already exists\n";

        return;
    }

    DB::connection()->query(
        "ALTER TABLE `users` ADD COLUMN `password_salt` CHAR(32) NULL "
            . "COMMENT 'random, App\\\\Core\\\\Password::salt()' AFTER `password`"
    );

    echo "✓ Migrated: users.password_salt added\n";
}

/**
 * একটা .sql ফাইলের সব স্টেটমেন্ট চালায়।
 * (সাধারণ স্কিমা ফাইলের জন্য যথেষ্ট — এখানে কোনো stored procedure/trigger নাই,
 *  তাই DELIMITER হ্যান্ডেল করার দরকার পড়ে না।)
 */
function runSqlFile(string $path): void
{
    $sql = file_get_contents($path);

    if ($sql === false) {
        throw new RuntimeException("Could not read the SQL file: $path");
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
