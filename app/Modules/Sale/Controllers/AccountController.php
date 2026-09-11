<?php

namespace App\Modules\Sale\Controllers;

use App\Core\Auth;
use App\Core\Env;
use App\Core\Message;
use App\Core\View;

/**
 * কাস্টমার সেলফ-সার্ভিস অ্যাকাউন্ট — রেজিস্ট্রেশন/লগইন ফর্ম, প্রোফাইল পেজ।
 * বাকি স্টোরফ্রন্ট কন্ট্রোলারের মতোই GET-only — ফর্ম সাবমিট পেজের নিজের JS
 * দিয়ে সরাসরি `/api/v1/auth/*` কল করে, সার্ভার-সাইড submit/redirect মেথড
 * এখানে নাই (doc/11-customer-account.md সিদ্ধান্ত C-08)।
 */
final class AccountController
{
    public static function registerForm(): string
    {
        if (Auth::isCustomer()) {
            self::redirect('/profile');
        }

        return View::layout('layouts/storefront', 'Sale::storefront/register', array_merge(
            StorefrontController::common(),
            ['title' => 'Create Account']
        ));
    }

    /** `Router::deny()` এর হার্ডকোড করা `/login` রিডাইরেক্টের ঠিক এই রুট — doc/11 §৮ */
    public static function loginForm(): string
    {
        if (Auth::isCustomer()) {
            self::redirect('/profile');
        }

        return View::layout('layouts/storefront', 'Sale::storefront/login', array_merge(
            StorefrontController::common(),
            ['title' => 'Login']
        ));
    }

    public static function logout(): string
    {
        Auth::logout();
        Message::flash(Message::SUCCESS, 'Logged out.');
        self::redirect('/');
    }

    public static function profile(): string
    {
        return View::layout('layouts/storefront', 'Sale::storefront/profile', array_merge(
            StorefrontController::common(),
            ['title' => 'My Account', 'user' => Auth::user()]
        ));
    }

    private static function redirect(string $path): never
    {
        header('Location: ' . rtrim((string) Env::get('APP_URL', ''), '/') . $path);
        exit;
    }
}
