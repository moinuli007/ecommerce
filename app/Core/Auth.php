<?php

namespace App\Core;

/**
 * অথেনটিকেশন — দুই ভাবেই কাজ করে (Decision D-03, doc/01-architecture.md):
 *
 *   ১. PHP session   → ব্রাউজার (web + admin)
 *   ২. Bearer token  → মোবাইল অ্যাপ / এক্সটার্নাল ক্লায়েন্ট (api_tokens টেবিল)
 *
 * resolve() একবারই চলে, ফলাফল static এ ক্যাশ থাকে।
 */
final class Auth
{
    public const TYPE_SUPER_ADMIN = 1;
    public const TYPE_STAFF       = 2;
    public const TYPE_CUSTOMER    = 3;

    /** @var array<string,mixed>|null */
    private static ?array $user = null;

    private static bool $resolved = false;

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

        $userId = (int) ($_SESSION['user_id'] ?? 0);

        if ($userId > 0) {
            $user = DB::getRow('users', ['id' => $userId, 'isActive' => 1]);

            self::$user = $user === [] ? null : $user;
        }
    }

    /** @return array<string,mixed>|null */
    private static function userByToken(string $plainToken): ?array
    {
        $row = DB::getRow('api_tokens', ['token_hash' => hash('sha256', $plainToken)]);

        if ($row === []) {
            return null;
        }

        if ((int) $row['expires_at'] > 0 && (int) $row['expires_at'] < time()) {
            return null;
        }

        DB::update('api_tokens', ['last_used' => time()], ['id' => (int) $row['id']]);

        $user = DB::getRow('users', ['id' => (int) $row['user_id'], 'isActive' => 1]);

        return $user === [] ? null : $user;
    }

    // -------------------------------------------------------------------------
    // লগইন / লগআউট
    // -------------------------------------------------------------------------

    /** @param array<string,mixed> $user */
    public static function loginSession(array $user): void
    {
        session_regenerate_id(true);

        $_SESSION['user_id'] = (int) $user['id'];

        self::$user     = $user;
        self::$resolved = true;

        DB::update('users', ['last_login' => time()], ['id' => (int) $user['id']]);
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
            'expires_at' => $ttl > 0 ? time() + $ttl : 0,
            'created_at' => time(),
            'created_by' => $userId,
            'updated_at' => time(),
            'updated_by' => $userId,
        ]);

        return $plain;
    }

    public static function logout(): void
    {
        $token = Request::bearerToken();

        if ($token !== '') {
            DB::delete('api_tokens', ['token_hash' => hash('sha256', $token)]);
        }

        unset($_SESSION['user_id']);

        self::$user     = null;
        self::$resolved = true;
    }
}
