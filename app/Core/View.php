<?php

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * সাদামাটা PHP টেমপ্লেট রেন্ডারার।
 *
 * টেমপ্লেট খোঁজার ক্রম:
 *   ১. app/Modules/<Module>/Views/<name>.php   → View::render('Account::voucher/list')
 *   ২. resources/views/<name>.php              → View::render('layouts/admin')
 */
final class View
{
    private static string $viewRoot   = '';
    private static string $moduleRoot = '';

    /** @var array<string,mixed> সব ভিউতে পাওয়া যাবে এমন ডেটা */
    private static array $shared = [];

    public static function boot(string $viewRoot, string $moduleRoot): void
    {
        self::$viewRoot   = rtrim($viewRoot, '/\\');
        self::$moduleRoot = rtrim($moduleRoot, '/\\');
    }

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function exists(string $name): bool
    {
        return self::resolve($name) !== null;
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function render(string $name, array $data = []): string
    {
        $file = self::resolve($name);

        if ($file === null) {
            throw new RuntimeException("ভিউ পাওয়া যায়নি: $name");
        }

        return self::evaluate($file, array_merge(self::$shared, $data));
    }

    /**
     * ভিউ ফাইলটা আলাদা স্কোপে চালায়।
     *
     * ⚠ লোকাল ভ্যারিয়েবলের নাম ইচ্ছাকৃতভাবে `$__view*` — `extract()` এর
     * EXTR_SKIP মোডে যেকোনো লোকাল নামের সাথে মিলে গেলে ওই ডেটা কি-টা
     * নীরবে বাদ পড়ে যায়। আগে এখানে `$name`/`$data`/`$file` ছিল, ফলে
     * `render('x', ['data' => ...])` করলে ভিউ `$data` পেত না।
     *
     * @param array<string,mixed> $__viewData
     */
    private static function evaluate(string $__viewFile, array $__viewData): string
    {
        extract($__viewData, EXTR_SKIP);

        ob_start();

        try {
            include $__viewFile;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }

    /**
     * লেআউটের ভেতরে একটা ভিউ বসিয়ে রেন্ডার করে।
     * লেআউট ফাইলে `$content` ভ্যারিয়েবলটা পাওয়া যাবে।
     *
     * @param array<string,mixed> $data
     */
    public static function layout(string $layout, string $name, array $data = []): string
    {
        $content = self::render($name, $data);

        return self::render($layout, $data + ['content' => $content]);
    }

    /** HTML এস্কেপ শর্টকাট — টেমপ্লেটে `<?= View::e($x) ?>` */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function resolve(string $name): ?string
    {
        // "Account::voucher/list" ফরম্যাট
        if (str_contains($name, '::')) {
            [$module, $path] = explode('::', $name, 2);
            $file            = self::$moduleRoot . '/' . $module . '/Views/' . $path . '.php';

            return is_file($file) ? $file : null;
        }

        $file = self::$viewRoot . '/' . ltrim($name, '/') . '.php';

        return is_file($file) ? $file : null;
    }
}
