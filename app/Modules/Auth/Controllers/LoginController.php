<?php

namespace App\Modules\Auth\Controllers;

use App\Core\Auth;
use App\Core\Env;
use App\Core\Message;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Auth\Api\AuthApi;

/**
 * অ্যাডমিন লগইন পেজ। ডেটার কাজ পুরোটাই AuthApi করে —
 * এই কন্ট্রোলার শুধু রেন্ডার আর রিডাইরেক্ট সামলায়।
 */
final class LoginController
{
    /** লগইনের পর ডিফল্ট গন্তব্য */
    private const HOME = '/admin';

    public static function form(): string
    {
        if (Auth::isAdmin()) {
            self::redirect(self::safeNext());
        }

        return View::render('auth/login', [
            'title'    => 'অ্যাডমিন লগইন',
            'next'     => self::safeNext(),
            'messages' => Message::flush(),
        ]);
    }

    public static function submit(): string
    {
        Response::reset();

        $next   = self::safeNext();
        $result = AuthApi::login();

        if (($result['status'] ?? 0) !== 1) {
            foreach ($result['m'] ?? [] as $message) {
                Message::flash($message[0], $message[1]);
            }

            self::redirect('/admin/login?next=' . rawurlencode($next));
        }

        if (!Auth::isAdmin()) {
            Auth::logout();
            Message::flash(Message::ERROR, 'এই অ্যাকাউন্টের অ্যাডমিন প্যানেলে প্রবেশাধিকার নাই।');

            self::redirect('/admin/login');
        }

        self::redirect($next);
    }

    /**
     * `next` প্যারামিটার শুধু নিজের সাইটের ভেতরের পাথ হতে পারে।
     * `//evil.com` বা `https://evil.com` দিয়ে বাইরে পাঠানো (open redirect) ঠেকায়।
     */
    private static function safeNext(): string
    {
        $next = Request::string('next');

        if ($next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//')) {
            return self::HOME;
        }

        // লগইন পেজেই ফেরত পাঠানোর লুপ এড়ানো
        return str_starts_with($next, '/admin/login') ? self::HOME : $next;
    }

    public static function logout(): string
    {
        Auth::logout();
        Message::flash(Message::SUCCESS, 'লগআউট হয়েছে।');

        self::redirect('/admin/login');
    }

    private static function redirect(string $path): never
    {
        header('Location: ' . rtrim((string) Env::get('APP_URL', ''), '/') . $path);
        exit;
    }
}
