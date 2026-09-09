<?php

namespace App\Modules\Auth\Api;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

/**
 * লগইন / লগআউট API।
 *
 * ব্রাউজার থেকে এলে (`with_token` না পাঠালে) PHP session সেট হয়।
 * মোবাইল/এক্সটার্নাল ক্লায়েন্ট `with_token=1` পাঠালে Bearer টোকেনও ফেরত যায়।
 */
final class AuthApi
{
    /**
     * POST /api/v1/auth/login
     * ইনপুট: username (ইমেইল বা ফোন), password, with_token?
     *
     * @return array<string,mixed>
     */
    public static function login(): array
    {
        if (!Validator::check(Request::all(), [
            'username' => 'required',
            'password' => 'required',
        ])) {
            return Response::payload();
        }

        $username = Request::string('username');
        $column   = filter_var($username, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $user = DB::getRow('users', [$column => $username]);

        if ($user === [] || (int) $user['isActive'] !== 1) {
            return Response::error('Incorrect username or password.');
        }

        if (!is_string($user['password']) || !password_verify(Request::string('password'), $user['password'])) {
            return Response::error('Incorrect username or password.');
        }

        Auth::loginSession($user);

        $payload = ['user' => self::publicUser($user)];

        if (Request::int('with_token') === 1) {
            $payload['token'] = Auth::issueToken((int) $user['id'], Request::string('device', 'api'));
        }

        return Response::success('Logged in successfully.', $payload);
    }

    /**
     * POST /api/v1/auth/logout
     *
     * @return array<string,mixed>
     */
    public static function logout(): array
    {
        Auth::logout();

        return Response::success('Logged out.');
    }

    /**
     * GET /api/v1/auth/me
     *
     * @return array<string,mixed>
     */
    public static function me(): array
    {
        return Response::success('', ['user' => self::publicUser(Auth::user())]);
    }

    /**
     * পাসওয়ার্ড হ্যাশ কখনোই রেসপন্সে যাবে না।
     *
     * @param  array<string,mixed> $user
     * @return array<string,mixed>
     */
    private static function publicUser(array $user): array
    {
        if ($user === []) {
            return [];
        }

        return [
            'id'         => (int) $user['id'],
            'name'       => $user['name'],
            'email'      => $user['email'],
            'phone'      => $user['phone'],
            'type'       => (int) $user['type'],
            'avatar'     => $user['avatar'],
            'is_admin'   => in_array((int) $user['type'], [Auth::TYPE_SUPER_ADMIN, Auth::TYPE_STAFF], true),
        ];
    }
}
