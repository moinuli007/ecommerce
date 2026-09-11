<?php

namespace App\Modules\Auth\Api;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Utility;
use App\Core\Validator;
use App\Modules\Sale\Models\Customer;
use App\Modules\Sale\Services\CustomerService;
use RuntimeException;

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
     * POST /api/v1/auth/register
     * ইনপুট: name, phone, email?, password, password_confirmation
     *
     * ফোনই ডিডুপ কি ([10-storefront-order.md](../../../../doc/10-storefront-order.md)
     * এর O-08 ধারাবাহিকতা) — আগে গেস্ট চেকআউট করা ফোন হলে সেই `customers`
     * রো-তেই `user_id` লিংক হয় (CustomerService::findOrCreateByPhone(),
     * doc/11-customer-account.md সিদ্ধান্ত C-01)। আগে ডিলিট করা
     * (isActive=0) অ্যাকাউন্ট একই ফোনে আবার রেজিস্টার করলে নতুন রো না
     * বানিয়ে রিঅ্যাক্টিভেট হয় (C-04)।
     *
     * @return array<string,mixed>
     */
    public static function register(): array
    {
        if (!Validator::check(Request::all(), [
            'name'     => 'required|max:150',
            'phone'    => 'required',
            'email'    => 'email',
            'password' => 'required',
        ])) {
            return Response::payload();
        }

        $phone    = Request::string('phone');
        $name     = Request::string('name');
        $email    = trim(Request::string('email'));
        $password = Request::string('password');

        // দৈর্ঘ্য ম্যানুয়ালি — Validator::min সংখ্যাসদৃশ পাসওয়ার্ডকে
        // (যেমন "12345") স্ট্রিং-দৈর্ঘ্যের বদলে সংখ্যা হিসেবে তুলনা করত
        // (doc/11-customer-account.md §২ গোচা)
        if (mb_strlen($password) < 6) {
            return Response::error('Password must be at least 6 characters.');
        }

        if ($password !== Request::string('password_confirmation')) {
            return Response::error('Password confirmation does not match.');
        }

        $existingUser = DB::getRow('users', ['phone' => $phone]);

        if ($existingUser !== [] && (int) $existingUser['isActive'] === 1) {
            return Response::error('This phone number is already registered. Please log in instead.');
        }

        if ($email !== '') {
            $emailClash = DB::getRow('users', ['email' => $email]);

            if ($emailClash !== [] && (int) $emailClash['id'] !== (int) ($existingUser['id'] ?? 0)) {
                return Response::error('This email is already registered.');
            }
        }

        $row = [
            'name'     => mb_substr($name, 0, 150),
            'phone'    => mb_substr($phone, 0, 30),
            'email'    => $email !== '' ? mb_substr($email, 0, 150) : null, // NULL, '' না — doc/11 §২ গোচা
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'type'     => Auth::TYPE_CUSTOMER,
            'isActive' => 1,
        ];

        try {
            $userId = DB::transaction(static function () use ($row, $existingUser, $name, $phone, $email): int {
                if ($existingUser !== []) {
                    Utility::stampUpdate($row);
                    DB::update('users', $row, ['id' => (int) $existingUser['id']]);
                    $userId = (int) $existingUser['id'];
                } else {
                    Utility::stampCreate($row);
                    $userId = DB::insert('users', $row);
                }

                CustomerService::findOrCreateByPhone([
                    'phone'   => $phone,
                    'name'    => $name,
                    'email'   => $email,
                    'user_id' => $userId,
                ]);

                return $userId;
            });
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        $user = DB::getRow('users', ['id' => $userId]);
        Auth::loginSession($user);

        return Response::success('Account created.', ['user' => self::publicUser($user)]);
    }

    /**
     * PUT /api/v1/auth/profile — যেকোনো লগইন ইউজার (guard: auth) নিজের
     * নাম/ফোন/ইমেইল বদলায়। কাস্টমার হলে সাথে সাথে লিংকড `customers` রো-ও
     * সিঙ্ক হয় — admin/staff এর কোনো customers রো নেই বলে ওদের জন্য এই
     * ধাপ স্কিপ হয়। doc/11-customer-account.md §৪।
     *
     * @return array<string,mixed>
     */
    public static function updateProfile(): array
    {
        if (!Validator::check(Request::all(), [
            'name'  => 'required|max:150',
            'phone' => 'required',
            'email' => 'email',
        ])) {
            return Response::payload();
        }

        $userId = Auth::id();
        $name   = Request::string('name');
        $phone  = Request::string('phone');
        $email  = trim(Request::string('email'));

        $phoneClash = DB::getRow('users', ['phone' => $phone]);

        if ($phoneClash !== [] && (int) $phoneClash['id'] !== $userId) {
            return Response::error('This phone number is already in use.');
        }

        if ($email !== '') {
            $emailClash = DB::getRow('users', ['email' => $email]);

            if ($emailClash !== [] && (int) $emailClash['id'] !== $userId) {
                return Response::error('This email is already registered.');
            }
        }

        $row = [
            'name'  => mb_substr($name, 0, 150),
            'phone' => mb_substr($phone, 0, 30),
            'email' => $email !== '' ? mb_substr($email, 0, 150) : null,
        ];

        $isCustomer = Auth::isCustomer();

        try {
            DB::transaction(static function () use ($row, $userId, $isCustomer): void {
                Utility::stampUpdate($row);
                DB::update('users', $row, ['id' => $userId]);

                if (!$isCustomer) {
                    return;
                }

                $customer = DB::getRow('customers', ['user_id' => $userId]);

                if ($customer === []) {
                    return;
                }

                $clash = Customer::byPhone($row['phone']);

                if ($clash !== [] && (int) $clash['id'] !== (int) $customer['id']) {
                    throw new RuntimeException('This phone number is already in use.');
                }

                Customer::updateById((int) $customer['id'], [
                    'name'  => $row['name'],
                    'phone' => $row['phone'],
                    'email' => $row['email'] ?? '',
                ]);
            });
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        $user = DB::getRow('users', ['id' => $userId]);

        return Response::success('Profile updated.', ['user' => self::publicUser($user)]);
    }

    /**
     * PUT /api/v1/auth/password — ইনপুট: current_password, password,
     * password_confirmation। doc/11-customer-account.md §৫।
     *
     * @return array<string,mixed>
     */
    public static function changePassword(): array
    {
        if (!Validator::check(Request::all(), [
            'current_password' => 'required',
            'password'          => 'required',
        ])) {
            return Response::payload();
        }

        $user = DB::getRow('users', ['id' => Auth::id()]);

        if (!is_string($user['password']) || !password_verify(Request::string('current_password'), $user['password'])) {
            return Response::error('Current password is incorrect.');
        }

        $password = Request::string('password');

        // দৈর্ঘ্য ম্যানুয়ালি — একই গোচা register() এ যেমন
        if (mb_strlen($password) < 6) {
            return Response::error('Password must be at least 6 characters.');
        }

        if ($password !== Request::string('password_confirmation')) {
            return Response::error('Password confirmation does not match.');
        }

        $row = ['password' => password_hash($password, PASSWORD_DEFAULT)];
        Utility::stampUpdate($row);
        DB::update('users', $row, ['id' => (int) $user['id']]);

        return Response::success('Password changed.');
    }

    /**
     * DELETE /api/v1/auth/account — সফট-ডিলিট, শুধু কাস্টমার (guard:
     * customer)। `customers`/`orders`/লেজার কিছুই ছোঁয়া হয় না, হিসাবের
     * ইতিহাস অক্ষত থাকে — শুধু লগইন বন্ধ হয়ে যায় (`isActive = 0`)।
     * doc/11-customer-account.md §৬।
     *
     * @return array<string,mixed>
     */
    public static function deleteAccount(): array
    {
        $userId = Auth::id();
        $row    = ['isActive' => 0];
        Utility::stampUpdate($row);

        DB::update('users', $row, ['id' => $userId]);
        DB::delete('api_tokens', ['user_id' => $userId]);
        Auth::logout();

        return Response::success('Your account has been deleted.');
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
