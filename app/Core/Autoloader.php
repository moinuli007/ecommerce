<?php

namespace App\Core;

/**
 * সাদামাটা PSR-4 অটোলোডার।
 *
 * erp_saas এ পুরো app/ ফোল্ডার RecursiveIteratorIterator দিয়ে include করা হয় —
 * এখানে সেটা করা হয়নি, কারণ মডিউল বাড়লে প্রতি রিকোয়েস্টে শত শত ফাইল লোড হবে।
 * এখানে ক্লাস যখন প্রথম দরকার হয় তখনই ফাইলটা লোড হয়।
 */
final class Autoloader
{
    /** @var array<string,string> namespace prefix => base directory */
    private static array $map = [];

    private static bool $registered = false;

    /**
     * @param array<string,string> $map ['App\\' => __DIR__ . '/../']
     */
    public static function register(array $map): void
    {
        foreach ($map as $prefix => $dir) {
            self::$map[rtrim($prefix, '\\') . '\\'] = rtrim(str_replace('\\', '/', $dir), '/');
        }

        if (!self::$registered) {
            spl_autoload_register([self::class, 'load']);
            self::$registered = true;
        }
    }

    public static function load(string $class): void
    {
        foreach (self::$map as $prefix => $baseDir) {
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                continue;
            }

            $relative = substr($class, strlen($prefix));
            $file     = $baseDir . '/' . str_replace('\\', '/', $relative) . '.php';

            if (is_file($file)) {
                require_once $file;
                return;
            }
        }
    }
}
