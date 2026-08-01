<?php

namespace App\Core;

/**
 * রেসপন্স বিল্ডার — erp_saas এর `$jArray` ফরম্যাট হুবহু মেনে চলে।
 *
 * বেসিক শেপ:
 * {
 *   "status": 0 | 1,                       // 1 = কাজ হয়েছে, 0 = হয়নি
 *   "m": [ ["s", "Voucher saved."] ],      // মেসেজ লিস্ট, Message ক্লাস থেকে
 *   "html": "<tr>...</tr>",                // অপশনাল, পার্শিয়াল রেন্ডার
 *   ... বাকি যেকোনো ডেটা কি ...
 *   "EL#42_Voucher": {...}                 // অপশনাল ডিবাগ (RESPONSE_DEBUG=1 হলে)
 * }
 *
 * ব্যবহার:
 *     Response::set('voucher', $v);
 *     Response::success('Voucher saved.');
 *     return Response::payload();
 */
final class Response
{
    /** @var array<string,mixed> */
    private static array $data = [];

    private static int $status = 0;

    private static ?string $html = null;

    // -------------------------------------------------------------------------
    // স্ট্যাটাস
    // -------------------------------------------------------------------------

    public static function status(?int $status = null): int
    {
        if ($status !== null) {
            self::$status = $status;
        }

        return self::$status;
    }

    public static function ok(): void
    {
        self::$status = 1;
    }

    public static function fail(): void
    {
        self::$status = 0;
    }

    // -------------------------------------------------------------------------
    // ডেটা
    // -------------------------------------------------------------------------

    public static function set(string $key, mixed $value): void
    {
        self::$data[$key] = $value;
    }

    /** @param array<string,mixed> $values */
    public static function merge(array $values): void
    {
        self::$data = array_merge(self::$data, $values);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$data[$key] ?? $default;
    }

    public static function html(string $html): void
    {
        self::$html = $html;
    }

    // -------------------------------------------------------------------------
    // শর্টকাট (মেসেজ + স্ট্যাটাস একসাথে)
    // -------------------------------------------------------------------------

    /** @param array<string,mixed> $data */
    public static function success(string $message = '', array $data = []): array
    {
        self::ok();

        if ($message !== '') {
            Message::success($message);
        }

        self::merge($data);

        return self::payload();
    }

    /** @param array<string,mixed> $data */
    public static function error(string $message = '', array $data = []): array
    {
        self::fail();

        if ($message !== '') {
            Message::error($message);
        }

        self::merge($data);

        return self::payload();
    }

    // -------------------------------------------------------------------------
    // ডিবাগ — erp_saas এর fl() হেল্পারের সমতুল্য
    // -------------------------------------------------------------------------

    /**
     * কল করা লাইন + ফাইলের নামে একটা কি বানিয়ে ভ্যালু বসায়।
     * RESPONSE_DEBUG=0 হলে কিছুই হয় না।
     */
    public static function debug(mixed $value, string $suffix = ''): void
    {
        if (!Env::bool('RESPONSE_DEBUG', false)) {
            return;
        }

        self::$data[self::fl(1) . ($suffix !== '' ? '_' . $suffix : '')] = $value;
    }

    /**
     * "EL#42_Voucher" স্টাইলের কি — কোন ফাইলের কোন লাইন থেকে এসেছে।
     */
    public static function fl(int $depth = 0): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth + 2);
        $frame = $trace[$depth] ?? $trace[0];

        return 'EL#' . ($frame['line'] ?? 0) . '_' . basename($frame['file'] ?? 'unknown', '.php');
    }

    // -------------------------------------------------------------------------
    // ফাইনাল আউটপুট
    // -------------------------------------------------------------------------

    /**
     * পুরো jArray বানিয়ে দেয় (echo করে না)।
     *
     * @return array<string,mixed>
     */
    public static function payload(): array
    {
        $payload = ['status' => self::$status, 'm' => Message::flush()];

        if (self::$html !== null) {
            $payload['html'] = self::$html;
        }

        $payload += self::$data;

        if (Env::bool('RESPONSE_DEBUG', false)) {
            $payload['_sql'] = DB::log();
        }

        return $payload;
    }

    /**
     * JSON হেডার দিয়ে আউটপুট করে স্ক্রিপ্ট শেষ করে।
     *
     * @param array<string,mixed>|null $payload নিজে payload দিলে সেটাই যাবে
     */
    public static function json(?array $payload = null, int $httpCode = 200): never
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($httpCode);
        }

        echo json_encode(
            $payload ?? self::payload(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        exit;
    }

    /**
     * টেস্ট/মাল্টিপল ডিসপ্যাচের জন্য স্টেট রিসেট।
     */
    public static function reset(): void
    {
        self::$data   = [];
        self::$status = 0;
        self::$html   = null;
    }
}
