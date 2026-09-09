<?php

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * স্ট্যাটিক রাউটার। রাউট টেবিল কোডে (routes/*.php), DB-তে নয় —
 * erp_saas এ `module` টেবিল থেকে পেজ রেজলভ হতো, এখানে সেটা করা হয়নি
 * (Decision D-02, doc/01-architecture.md)।
 *
 *     Router::group(['prefix' => '/api/v1', 'guard' => 'admin', 'json' => true], function () {
 *         Router::get('/vouchers',      [VoucherApi::class, 'index']);
 *         Router::get('/vouchers/{id}', [VoucherApi::class, 'show']);
 *     });
 *
 * হ্যান্ডলার সবসময় `[ClassName::class, 'staticMethod']` — কোনো কন্ট্রোলার
 * ইনস্ট্যান্স তৈরি হয় না।
 */
final class Router
{
    /** @var array<string,array<int,array{regex:string,params:array<int,string>,handler:mixed,options:array}>> */
    private static array $routes = [];

    /** @var array<string,array{method:string,uri:string}> */
    private static array $names = [];

    /** @var array<int,array<string,mixed>> group stack */
    private static array $stack = [];

    // -------------------------------------------------------------------------
    // রেজিস্ট্রেশন
    // -------------------------------------------------------------------------

    /** @param array<string,mixed> $options */
    public static function group(array $options, callable $callback): void
    {
        self::$stack[] = $options;

        $callback();

        array_pop(self::$stack);
    }

    /** @param array<string,mixed> $options */
    public static function get(string $uri, array|callable $handler, array $options = []): void
    {
        self::add('GET', $uri, $handler, $options);
    }

    /** @param array<string,mixed> $options */
    public static function post(string $uri, array|callable $handler, array $options = []): void
    {
        self::add('POST', $uri, $handler, $options);
    }

    /** @param array<string,mixed> $options */
    public static function put(string $uri, array|callable $handler, array $options = []): void
    {
        self::add('PUT', $uri, $handler, $options);
    }

    /** @param array<string,mixed> $options */
    public static function delete(string $uri, array|callable $handler, array $options = []): void
    {
        self::add('DELETE', $uri, $handler, $options);
    }

    /**
     * একই হ্যান্ডলার GET আর POST দুটোতেই।
     * @param array<string,mixed> $options
     */
    public static function any(string $uri, array|callable $handler, array $options = []): void
    {
        foreach (['GET', 'POST'] as $method) {
            self::add($method, $uri, $handler, $options);
        }
    }

    /** @param array<string,mixed> $options */
    public static function add(string $method, string $uri, array|callable $handler, array $options = []): void
    {
        $prefix   = '';
        $inherited = [];

        foreach (self::$stack as $group) {
            $prefix    .= rtrim($group['prefix'] ?? '', '/');
            $inherited = array_merge($inherited, $group);
        }

        unset($inherited['prefix']);

        $options = array_merge($inherited, $options);
        $full    = '/' . trim($prefix . '/' . ltrim($uri, '/'), '/');
        $full    = $full === '/' ? '/' : rtrim($full, '/');

        // {id} → নামযুক্ত ক্যাপচার গ্রুপ
        $params = [];
        $regex  = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static function (array $m) use (&$params): string {
                $params[] = $m[1];

                return '([^/]+)';
            },
            $full
        );

        self::$routes[$method][] = [
            'regex'   => '#^' . $regex . '$#',
            'params'  => $params,
            'handler' => $handler,
            'options' => $options,
            'uri'     => $full,
        ];

        if (isset($options['name'])) {
            self::$names[$options['name']] = ['method' => $method, 'uri' => $full];
        }
    }

    // -------------------------------------------------------------------------
    // ডিসপ্যাচ
    // -------------------------------------------------------------------------

    public static function dispatch(): void
    {
        $method = Request::method();
        $path   = Request::path();

        // ব্রাউজার form থেকে PUT/DELETE পাঠানোর জন্য _method ফিল্ড
        if ($method === 'POST' && Request::has('_method')) {
            $method = strtoupper(Request::string('_method'));
        }

        foreach (self::$routes[$method] ?? [] as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            array_shift($matches);

            Request::setParams(array_combine($route['params'], $matches) ?: []);

            self::run($route);

            return;
        }

        self::notFound($path);
    }

    /** @param array<string,mixed> $route */
    private static function run(array $route): void
    {
        $options  = $route['options'];
        $wantJson = (bool) ($options['json'] ?? false);

        $denied = self::guard((string) ($options['guard'] ?? 'guest'));

        if ($denied !== null) {
            self::deny($denied, $wantJson);

            return;
        }

        try {
            $result = self::invoke($route['handler']);
        } catch (Throwable $e) {
            self::handleException($e, $wantJson);

            return;
        }

        if ($wantJson) {
            Response::json(is_array($result) ? $result : Response::payload());

            return;
        }

        if (is_string($result)) {
            echo $result;
        }
    }

    private static function invoke(array|callable $handler): mixed
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;

            if (!method_exists($class, $method)) {
                throw new RuntimeException("Route handler not found: $class::$method()");
            }

            return $class::$method();
        }

        return $handler();
    }

    /**
     * অ্যাক্সেস চেক। ঠিক থাকলে null, না হলে HTTP কোড ফেরত দেয়।
     */
    private static function guard(string $guard): ?int
    {
        return match ($guard) {
            'guest'    => null,
            'auth'     => Auth::check() ? null : 401,
            'admin'    => Auth::check() ? (Auth::isAdmin() ? null : 403) : 401,
            'customer' => Auth::check() ? (Auth::isCustomer() ? null : 403) : 401,
            default    => throw new RuntimeException("Unknown guard: $guard"),
        };
    }

    private static function deny(int $code, bool $wantJson): void
    {
        $message = $code === 401 ? 'Login required.' : 'You do not have permission for this action.';

        if ($wantJson) {
            Message::error($message);
            Response::json(Response::payload(), $code);
        }

        if ($code === 401) {
            Message::flash(Message::WARNING, 'Please log in to continue.');

            // যে এলাকা থেকে এসেছে সেই এলাকার লগইন পেজেই ফিরবে —
            // /admin/* এর জন্য /admin/login, স্টোরফ্রন্টের জন্য /login
            $path      = Request::path();
            $loginPath = str_starts_with($path, '/admin') ? '/admin/login' : '/login';
            $base      = rtrim((string) Env::get('APP_URL', ''), '/');

            // লগইনের পর যেখানে যেতে চেয়েছিল সেখানেই ফেরত পাঠানোর জন্য
            $next = $path !== $loginPath ? '?next=' . rawurlencode($path) : '';

            header('Location: ' . $base . $loginPath . $next);
            exit;
        }

        http_response_code(403);
        echo View::exists('errors/403')
            ? View::render('errors/403', ['message' => $message])
            : $message;
    }

    private static function notFound(string $path): void
    {
        $wantJson = str_starts_with($path, '/api/') || Request::isAjax() || Request::isJson();

        if ($wantJson) {
            Message::error('Invalid request.');
            Response::json(Response::payload(), 404);
        }

        http_response_code(404);
        echo View::exists('errors/404') ? View::render('errors/404') : '404 Not Found';
    }

    private static function handleException(Throwable $e, bool $wantJson): void
    {
        $debug = Env::bool('APP_DEBUG', false);

        error_log('[router] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

        if ($wantJson) {
            Message::error($debug ? $e->getMessage() : 'Something went wrong on the server.');

            if ($debug) {
                Response::set('_exception', [
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => explode("\n", $e->getTraceAsString()),
                ]);
            }

            Response::json(Response::payload(), 500);
        }

        http_response_code(500);
        echo $debug
            ? '<pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>'
            : 'Something went wrong on the server.';
    }

    // -------------------------------------------------------------------------
    // হেল্পার
    // -------------------------------------------------------------------------

    /**
     * নাম দিয়ে URL — `Router::url('voucher.show', ['id' => 12])`
     * @param array<string,string|int> $params
     */
    public static function url(string $name, array $params = []): string
    {
        if (!isset(self::$names[$name])) {
            throw new RuntimeException("Route name not found: $name");
        }

        $uri = self::$names[$name]['uri'];

        foreach ($params as $key => $value) {
            $uri = str_replace('{' . $key . '}', (string) $value, $uri);
        }

        return rtrim((string) Env::get('APP_URL', ''), '/') . $uri;
    }

    /** ডকুমেন্টেশন/ডিবাগের জন্য পুরো রাউট টেবিল */
    public static function all(): array
    {
        $out = [];

        foreach (self::$routes as $method => $routes) {
            foreach ($routes as $route) {
                $out[] = [
                    'method' => $method,
                    'uri'    => $route['uri'],
                    'guard'  => $route['options']['guard'] ?? 'guest',
                    'name'   => $route['options']['name'] ?? '',
                    'action' => is_array($route['handler'])
                        ? $route['handler'][0] . '::' . $route['handler'][1]
                        : 'Closure',
                ];
            }
        }

        return $out;
    }
}
