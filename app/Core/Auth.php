<?php

namespace App\Core;

/**
 * অথেনটিকেশন — তিন ভাবে কাজ করে (Decision D-03/D-12/D-13, doc/01-architecture.md,
 * doc/13-auth-and-user-management.md):
 *
 *   ১. PHP session, admin এলাকা    → ২০ মিনিট (ENV: ADMIN_SESSION_TTL) নিষ্ক্রিয়তায়
 *                                     অটো লগআউট, প্রতিটা অথেনটিকেটেড রিকোয়েস্টে বাড়ে (sliding)
 *   ২. PHP session, customer এলাকা → কোনো ইনঅ্যাক্টিভিটি-টাইমআউট নাই
 *   ৩. Bearer token                → মোবাইল/এক্সটার্নাল ক্লায়েন্ট (api_tokens টেবিল),
 *                                     এলাকা-নিরপেক্ষ
 *
 * একই ব্রাউজারে admin আর customer — দুটো লগইন **একই সাথে** থাকতে পারে, কারণ
 * দুটো এলাকার আইডেন্টিটি একই PHP session-এর ভেতর আলাদা কি-তে থাকে
 * (`admin_user_id` বনাম `customer_user_id`) — একটা এলাকায় লগআউট করলে
 * অন্যটা অক্ষত থাকে। কোন এলাকা এই রিকোয়েস্টের, সেটা ঠিক হয় area() দিয়ে।
 *
 * resolve() রিকোয়েস্ট-প্রতি একবারই চলে, ফলাফল static এ ক্যাশ থাকে।
 */
final class Auth
{
    public const TYPE_SUPER_ADMIN = 1;
    public const TYPE_STAFF       = 2;
    public const TYPE_CUSTOMER    = 3;

    public const AREA_ADMIN    = 'admin';
    public const AREA_CUSTOMER = 'customer';

    /** @var array<string,mixed>|null */
    private static ?array $user = null;

    private static bool $resolved = false;

    /** ইনঅ্যাক্টিভিটির কারণে এই রিকোয়েস্টে admin সেশন এক্সপায়ার হয়েছিল কি না */
    private static bool $adminSessionExpired = false;

    // -------------------------------------------------------------------------
    // এলাকা (area) — কোন সেশন-স্লট ব্যবহার হবে
    // -------------------------------------------------------------------------

    /**
     * বর্তমান রিকোয়েস্ট admin এলাকার নাকি customer এলাকার।
     *
     * `/admin/*` পাথ মানেই admin। কিন্তু অ্যাডমিন প্যানেলের নিজের AJAX-ও
     * `/api/v1/...` দিয়ে যায় (D-04 — web/admin এর সব ডেটা একই API লেয়ার
     * দিয়ে), যেটা পাথ দেখে admin/customer আলাদা করা যায় না। তাই admin.php
     * লেআউটের শেয়ার্ড `api()`/`apiUpload()` হেল্পার প্রতিটা কলে
     * `X-Auth-Area: admin` হেডার পাঠায় — হেডার থাকলে সেটাই চূড়ান্ত, পাথ
     * উপেক্ষিত হয়। storefront-এর নিজের fetch হেল্পার কোনো হেডার পাঠায় না,
     * তাই তার সব কল ডিফল্ট customer-ই থেকে যায়।
     */
    public static function area(): string
    {
        $header = strtolower(Request::header('X-Auth-Area'));

        if ($header === self::AREA_ADMIN || $header === self::AREA_CUSTOMER) {
            return $header;
        }

        return str_starts_with(Request::path(), '/admin') ? self::AREA_ADMIN : self::AREA_CUSTOMER;
    }

    // -------------------------------------------------------------------------
    // রিড
    // -------------------------------------------------------------------------

    /** @return array<string,mixed> লগইন না থাকলে খালি array */
    public static function user(): array
    {
        if (!self::$resolved) {
            self::resolve();
        }

        return self::$user ?? [];
    }

    public static function id(): int
    {
        return (int) (self::user()['id'] ?? 0);
    }

    public static function check(): bool
    {
        return self::id() > 0;
    }

    public static function type(): int
    {
        return (int) (self::user()['type'] ?? 0);
    }

    public static function isAdmin(): bool
    {
        return in_array(self::type(), [self::TYPE_SUPER_ADMIN, self::TYPE_STAFF], true);
    }

    public static function isSuperAdmin(): bool
    {
        return self::type() === self::TYPE_SUPER_ADMIN;
    }

    public static function isCustomer(): bool
    {
        return self::type() === self::TYPE_CUSTOMER;
    }

    /** রোল লেবেল — UI-তে বারবার ternary না লিখে এক জায়গা থেকে। */
    public static function typeLabel(int $type): string
    {
        return match ($type) {
            self::TYPE_SUPER_ADMIN => 'Super Admin',
            self::TYPE_STAFF       => 'Admin',
            self::TYPE_CUSTOMER    => 'Customer',
            default                => 'Unknown',
        };
    }

    /**
     * এই রিকোয়েস্টে ইনঅ্যাক্টিভিটির কারণে admin সেশন এক্সপায়ার পাওয়া
     * গিয়েছিল কি না — LoginController::form() এটা পড়ে আলাদা মেসেজ দেখায়।
     */
    public static function adminSessionExpired(): bool
    {
        if (!self::$resolved) {
            self::resolve();
        }

        return self::$adminSessionExpired;
    }

    /**
     * রিকোয়েস্ট-লেভেল ক্যাশ (D-05) রিসেট — টেস্টে এক টেস্ট মেথডের রেজলভড
     * আইডেন্টিটি পরের মেথডে লিক না করার জন্য (Response::reset()/
     * LedgerAccounts::clearCache() এর একই প্যাটার্ন)। প্রোডাকশন কোডে
     * লাগে না — প্রতিটা HTTP রিকোয়েস্ট এমনিতেই ফ্রেশ static state পায়।
     */
    public static function reset(): void
    {
        self::$user                = null;
        self::$resolved            = false;
        self::$adminSessionExpired = false;
    }

    // -------------------------------------------------------------------------
    // রেজলভ
    // -------------------------------------------------------------------------

    private static function resolve(): void
    {
        self::$resolved = true;

        $token = Request::bearerToken();

        if ($token !== '') {
            self::$user = self::userByToken($token);

            return;
        }

        self::$user = self::area() === self::AREA_ADMIN
            ? self::resolveAdmin()
            : self::resolveCustomer();
    }

    /**
     * admin এলাকার আইডেন্টিটি — টাইপ (super_admin/staff) আর ২০ মিনিটের
     * sliding inactivity-timeout দুটোই এখানে চেক হয়।
     *
     * @return array<string,mixed>|null
     */
    private static function resolveAdmin(): ?array
    {
        $userId = (int) ($_SESSION['admin_user_id'] ?? 0);

        if ($userId <= 0) {
            return null;
        }

        $ttl = Env::int('ADMIN_SESSION_TTL', 1200); // ২০ মিনিট

        if ($ttl > 0) {
            $lastActivity = (int) ($_SESSION['admin_last_activity'] ?? 0);

            if ($lastActivity > 0 && (RequestTime::now() - $lastActivity) > $ttl) {
                unset($_SESSION['admin_user_id'], $_SESSION['admin_last_activity']);
                self::$adminSessionExpired = true;

                return null;
            }
        }

        $user = DB::getRow('users', ['id' => $userId, 'isActive' => 1]);

        if ($user === [] || !in_array((int) $user['type'], [self::TYPE_SUPER_ADMIN, self::TYPE_STAFF], true)) {
            unset($_SESSION['admin_user_id'], $_SESSION['admin_last_activity']);

            return null;
        }

        // প্রতিটা অথেনটিকেটেড রিকোয়েস্টই "activity" ধরা হয় — তাই সেশন
        // এক্সপায়ার না হয়ে থাকলে প্রতিবার একটু করে বেড়ে যায় (sliding window)
        $_SESSION['admin_last_activity'] = RequestTime::now();

        return $user;
    }

    /**
     * customer এলাকার আইডেন্টিটি — কোনো ইনঅ্যাক্টিভিটি-টাইমআউট নাই,
     * PHP-র নিজস্ব সেশন-লাইফটাইম (session.gc_maxlifetime) ছাড়া।
     *
     * @return array<string,mixed>|null
     */
    private static function resolveCustomer(): ?array
    {
        $userId = (int) ($_SESSION['customer_user_id'] ?? 0);

        if ($userId <= 0) {
            return null;
        }

        $user = DB::getRow('users', ['id' => $userId, 'isActive' => 1]);

        if ($user === [] || (int) $user['type'] !== self::TYPE_CUSTOMER) {
            unset($_SESSION['customer_user_id']);

            return null;
        }

        return $user;
    }

    /** @return array<string,mixed>|null */
    private static function userByToken(string $plainToken): ?array
    {
        $row = DB::getRow('api_tokens', ['token_hash' => hash('sha256', $plainToken)]);

        if ($row === []) {
            return null;
        }

        if ((int) $row['expires_at'] > 0 && (int) $row['expires_at'] < RequestTime::now()) {
            return null;
        }

        DB::update('api_tokens', ['last_used' => RequestTime::now()], ['id' => (int) $row['id']]);

        $user = DB::getRow('users', ['id' => (int) $row['user_id'], 'isActive' => 1]);

        return $user === [] ? null : $user;
    }

    // -------------------------------------------------------------------------
    // লগইন / লগআউট
    // -------------------------------------------------------------------------

    /**
     * সেশনে লগইন বসায় — কোন এলাকার স্লটে বসবে সেটা area() ঠিক করে
     * ({@see area()})। অন্য এলাকার লগইন (যদি থাকে) অক্ষত থাকে।
     *
     * @param array<string,mixed> $user
     */
    public static function loginSession(array $user): void
    {
        // session_regenerate_id() পুরনো সেশন আইডি বাতিল করে নতুনটা বানায়
        // (session fixation ঠেকাতে), কিন্তু $_SESSION-এর ডেটা অক্ষত থাকে —
        // তাই অন্য এলাকার আগে থেকে চলতে থাকা লগইন হারায় না। সক্রিয় PHP
        // সেশন না থাকলে (যেমন PHPUnit — tests/bootstrap.php session_start()
        // করে না, doc/13 §৫) এই কল একটা warning ছোঁড়ে, তাই গার্ড করা।
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        if (self::area() === self::AREA_ADMIN) {
            $_SESSION['admin_user_id']       = (int) $user['id'];
            $_SESSION['admin_last_activity'] = RequestTime::now();
        } else {
            $_SESSION['customer_user_id'] = (int) $user['id'];
        }

        self::$user     = $user;
        self::$resolved = true;

        DB::update('users', ['last_login' => RequestTime::now()], ['id' => (int) $user['id']]);
    }

    /**
     * নতুন Bearer টোকেন বানায়। **প্লেইন টোকেন শুধু একবারই পাওয়া যাবে** —
     * DB তে শুধু sha256 হ্যাশ থাকে।
     */
    public static function issueToken(int $userId, string $name = 'api'): string
    {
        $plain = bin2hex(random_bytes(32));
        $ttl   = Env::int('API_TOKEN_TTL', 0);

        DB::insert('api_tokens', [
            'user_id'    => $userId,
            'name'       => $name,
            'token_hash' => hash('sha256', $plain),
            'abilities'  => '*',
            'expires_at' => $ttl > 0 ? RequestTime::now() + $ttl : 0,
            'created_at' => RequestTime::now(),
            'created_by' => $userId,
            'updated_at' => RequestTime::now(),
            'updated_by' => $userId,
        ]);

        return $plain;
    }

    /** বর্তমান এলাকার লগআউট — অন্য এলাকার লগইন (যদি থাকে) ছোঁয়া হয় না। */
    public static function logout(): void
    {
        $token = Request::bearerToken();

        if ($token !== '') {
            DB::delete('api_tokens', ['token_hash' => hash('sha256', $token)]);
        }

        if (self::area() === self::AREA_ADMIN) {
            unset($_SESSION['admin_user_id'], $_SESSION['admin_last_activity']);
        } else {
            unset($_SESSION['customer_user_id']);
        }

        self::$user     = null;
        self::$resolved = true;
    }
}
