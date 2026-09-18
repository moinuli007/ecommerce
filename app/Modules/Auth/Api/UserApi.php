<?php

namespace App\Modules\Auth\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\UserService;
use RuntimeException;

/**
 * অ্যাডমিন ইউজার (Super Admin / Admin) ম্যানেজমেন্ট API — সবগুলোই
 * guard: super_admin, ব্যতিক্রম শুধু changeOwnPassword() (guard: admin,
 * routes/api.php এ আলাদা করে বসানো)। doc/13-auth-and-user-management.md §৪।
 */
final class UserApi
{
    /** GET /api/v1/users */
    public static function index(): array
    {
        return Response::success('', [
            'users' => array_map([self::class, 'publicUser'], UserService::list([
                'q'           => Request::string('q'),
                'type'        => Request::int('type'),
                'active_only' => Request::int('active_only'),
            ])),
        ]);
    }

    /** GET /api/v1/users/{id} */
    public static function show(): array
    {
        $user = User::find(Request::paramInt('id'));

        if ($user === [] || !in_array((int) $user['type'], [Auth::TYPE_SUPER_ADMIN, Auth::TYPE_STAFF], true)) {
            return Response::error('Admin user not found.');
        }

        return Response::success('', ['user' => self::publicUser($user)]);
    }

    /** POST /api/v1/users */
    public static function store(): array
    {
        if (!Validator::check(Request::all(), [
            'name'     => 'required|max:150',
            'email'    => 'email',
            'type'     => 'required|int',
            'password' => 'required',
        ])) {
            return Response::payload();
        }

        try {
            $id = UserService::save(Request::all());
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Admin user created.', ['user' => self::publicUser(User::find($id))]);
    }

    /** PUT /api/v1/users/{id} */
    public static function update(): array
    {
        $id = Request::paramInt('id');

        if (!Validator::check(Request::all(), [
            'name'  => 'required|max:150',
            'email' => 'email',
            'type'  => 'required|int',
        ])) {
            return Response::payload();
        }

        try {
            UserService::save(Request::all(), $id);
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Admin user updated.', ['user' => self::publicUser(User::find($id))]);
    }

    /** DELETE /api/v1/users/{id} */
    public static function destroy(): array
    {
        try {
            $done = UserService::delete(Request::paramInt('id'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return $done
            ? Response::success('Admin user deactivated.')
            : Response::error('Admin user not found.');
    }

    /**
     * PUT /api/v1/users/{id}/password — Super Admin অন্য কারো পাসওয়ার্ড
     * রিসেট করছে, বর্তমান পাসওয়ার্ড লাগে না।
     */
    public static function resetPassword(): array
    {
        if (!Validator::check(Request::all(), ['password' => 'required'])) {
            return Response::payload();
        }

        if (Request::string('password') !== Request::string('password_confirmation')) {
            return Response::error('Password confirmation does not match.');
        }

        try {
            UserService::resetPassword(Request::paramInt('id'), Request::string('password'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Password reset.');
    }

    /**
     * PUT /api/v1/users/me/password — লগইন করা অ্যাডমিন (Super Admin বা
     * Admin, guard: admin) নিজের পাসওয়ার্ড বদলাচ্ছে, বর্তমান পাসওয়ার্ড লাগে।
     */
    public static function changeOwnPassword(): array
    {
        if (!Validator::check(Request::all(), [
            'current_password' => 'required',
            'password'          => 'required',
        ])) {
            return Response::payload();
        }

        if (Request::string('password') !== Request::string('password_confirmation')) {
            return Response::error('Password confirmation does not match.');
        }

        try {
            UserService::changeOwnPassword(
                Auth::id(),
                Request::string('current_password'),
                Request::string('password')
            );
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Password changed.');
    }

    /**
     * পাসওয়ার্ড হ্যাশ/সল্ট কখনোই রেসপন্সে যাবে না।
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
            'type_label' => Auth::typeLabel((int) $user['type']),
            'isActive'   => (int) $user['isActive'],
            'last_login' => (int) $user['last_login'],
        ];
    }
}
