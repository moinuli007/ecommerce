<?php

namespace App\Modules\Auth\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Auth\Api\UserApi;

/**
 * অ্যাডমিন ইউজার ম্যানেজমেন্টের পেজ — শুধু Super Admin দেখতে পায়
 * (route guard: super_admin, routes/admin.php)। সেভ/ডিলিট JS থেকে
 * সরাসরি API-তে যায় (বাকি সব অ্যাডমিন পেজের মতোই, D-04)।
 */
final class UserController
{
    public static function index(): string
    {
        Response::reset();
        $payload = UserApi::index();

        return View::layout('layouts/admin', 'Auth::user/list', [
            'title'    => 'Admin Users',
            'users'    => $payload['users'] ?? [],
            'filters'  => ['q' => Request::string('q')],
            'messages' => $payload['m'] ?? [],
        ]);
    }

    public static function create(): string
    {
        return self::form(0);
    }

    public static function edit(): string
    {
        return self::form(Request::paramInt('id'));
    }

    private static function form(int $userId): string
    {
        $user = [];

        if ($userId > 0) {
            Response::reset();
            Request::setParams(['id' => (string) $userId]);
            $user = UserApi::show()['user'] ?? [];
        }

        return View::layout('layouts/admin', 'Auth::user/form', [
            'title'    => $userId > 0 ? 'Edit Admin User' : 'New Admin User',
            'user'     => $user,
            'messages' => [],
        ]);
    }
}
