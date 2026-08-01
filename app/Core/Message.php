<?php

namespace App\Core;

/**
 * মেসেজ কিউ — erp_saas এর setMessage() / show_msg('y') এর সমতুল্য।
 *
 * রেসপন্সের `m` কি-তে এই ফরম্যাটেই যায় (erp_saas এর সাথে হুবহু মিল):
 *
 *     "m": [ ["e", "Invalid request."], ["s", "Voucher saved successfully."] ]
 *
 * টাইপ কোড:  e = error, s = success, w = warning, i = info
 */
final class Message
{
    public const ERROR   = 'e';
    public const SUCCESS = 's';
    public const WARNING = 'w';
    public const INFO    = 'i';

    /** @var array<int,array{0:string,1:string}> */
    private static array $queue = [];

    public static function push(string $type, string $text): void
    {
        self::$queue[] = [$type, $text];
    }

    public static function error(string $text): void
    {
        self::push(self::ERROR, $text);
    }

    public static function success(string $text): void
    {
        self::push(self::SUCCESS, $text);
    }

    public static function warning(string $text): void
    {
        self::push(self::WARNING, $text);
    }

    public static function info(string $text): void
    {
        self::push(self::INFO, $text);
    }

    public static function has(): bool
    {
        return self::$queue !== [];
    }

    /**
     * কিউ খালি করে সব মেসেজ ফেরত দেয় (erp_saas এর show_msg('y') এর মতো)।
     *
     * @return array<int,array{0:string,1:string}>
     */
    public static function flush(): array
    {
        // পেজ রিডাইরেক্টের সময় সেশনে জমানো মেসেজও এখানে মিশে যায়
        if (isset($_SESSION['msg']) && is_array($_SESSION['msg'])) {
            self::$queue = array_merge($_SESSION['msg'], self::$queue);
            unset($_SESSION['msg']);
        }

        $out         = self::$queue;
        self::$queue = [];

        return $out;
    }

    /**
     * রিডাইরেক্টের পরেও যে মেসেজ দেখাতে হবে সেটা সেশনে রাখে।
     */
    public static function flash(string $type, string $text): void
    {
        $_SESSION['msg'][] = [$type, $text];
    }
}
