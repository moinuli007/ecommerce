<?php

namespace App\Core;

/**
 * রিকোয়েস্ট র‍্যাপার — সব static।
 * JSON body আর form-encoded body দুটোই এক জায়গা থেকে পড়া যায়।
 */
final class Request
{
    /** @var array<string,mixed>|null */
    private static ?array $input = null;

    /** @var array<string,string> route থেকে আসা {param} ভ্যালু */
    private static array $params = [];

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * query string বাদ দিয়ে শুধু path, শুরুতে "/" আর শেষে "/" ছাড়া।
     * উদাহরণ: /api/v1/vouchers
     */
    public static function path(): string
    {
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = '/' . trim(rawurldecode($path), '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public static function isJson(): bool
    {
        return str_contains(strtolower(self::header('Content-Type', '')), 'application/json');
    }

    public static function isAjax(): bool
    {
        return strtolower(self::header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    /**
     * GET + POST + JSON body — সব মিলিয়ে এক array।
     *
     * @return array<string,mixed>
     */
    public static function all(): array
    {
        if (self::$input === null) {
            $body = [];

            if (self::isJson()) {
                $raw     = file_get_contents('php://input') ?: '';
                $decoded = json_decode($raw, true);
                $body    = is_array($decoded) ? $decoded : [];
            } else {
                $body = $_POST;
            }

            self::$input = Utility::sanitize(array_merge($_GET, $body));
        }

        return self::$input;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);

        return $value === null || $value === '' ? $default : (int) $value;
    }

    public static function float(string $key, float $default = 0.0): float
    {
        $value = self::get($key);

        return $value === null || $value === '' ? $default : (float) $value;
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = self::get($key);

        return $value === null || is_array($value) ? $default : (string) $value;
    }

    /** @return array<int|string,mixed> */
    public static function array(string $key): array
    {
        $value = self::get($key);

        return is_array($value) ? $value : ($value === null || $value === '' ? [] : [$value]);
    }

    /**
     * $_FILES এর একটা এন্ট্রি — ফাইল না থাকলে/এরর হলে খালি array।
     *
     * @return array<string,mixed>
     */
    public static function file(string $key): array
    {
        $file = $_FILES[$key] ?? [];

        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [];
        }

        return $file;
    }

    public static function header(string $name, string $default = ''): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        if (isset($_SERVER[$key])) {
            return (string) $_SERVER[$key];
        }

        // Content-Type / Content-Length এ HTTP_ প্রিফিক্স থাকে না
        $plain = strtoupper(str_replace('-', '_', $name));

        return (string) ($_SERVER[$plain] ?? $default);
    }

    /**
     * Authorization: Bearer <token> থেকে টোকেন।
     */
    public static function bearerToken(): string
    {
        $header = self::header('Authorization');

        if (stripos($header, 'Bearer ') === 0) {
            return trim(substr($header, 7));
        }

        return '';
    }

    public static function ip(): string
    {
        return (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
    }

    // -------------------------------------------------------------------------
    // রাউট প্যারামিটার ({id} ইত্যাদি) — Router সেট করে দেয়
    // -------------------------------------------------------------------------

    /** @param array<string,string> $params */
    public static function setParams(array $params): void
    {
        self::$params = $params;
        self::$input  = null; // param যোগ হলে input ক্যাশ রিফ্রেশ হবে
    }

    /** @return array<string,string> */
    public static function params(): array
    {
        return self::$params;
    }

    public static function param(string $key, mixed $default = null): mixed
    {
        return self::$params[$key] ?? $default;
    }

    public static function paramInt(string $key, int $default = 0): int
    {
        return isset(self::$params[$key]) ? (int) self::$params[$key] : $default;
    }
}
