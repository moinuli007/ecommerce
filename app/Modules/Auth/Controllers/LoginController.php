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

        // ইনঅ্যাক্টিভিটির কারণে আগের admin সেশন এইমাত্র এক্সপায়ার হয়ে থাকলে
        // ("Please log in to continue." জেনেরিক মেসেজের বদলে) একটা স্পষ্ট কারণ
        // দেখানো — doc/13-auth-and-user-management.md §৩
        if (Auth::adminSessionExpired()) {
            Message::push(Message::WARNING, 'Your session has expired due to 20 minutes of inactivity. Please log in again.');
        }

        return View::render('auth/login', [
            'title'    => 'Admin Login',
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
            Message::flash(Message::ERROR, 'This account does not have access to the admin panel.');

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
        Message::flash(Message::SUCCESS, 'Logged out.');

        self::redirect('/admin/login');
    }

    private static function redirect(string $path): never
    {
        header('Location: ' . rtrim((string) Env::get('APP_URL', ''), '/') . $path);
        exit;
    }
}
