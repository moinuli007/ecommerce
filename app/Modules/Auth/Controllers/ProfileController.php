<?php

namespace App\Modules\Auth\Controllers;

use App\Core\Response;
use App\Core\View;
use App\Modules\Auth\Api\AuthApi;

/**
 * লগইন করা ইউজারের প্রোফাইল। ডেটা AuthApi::me() থেকেই আসে (সিদ্ধান্ত D-04)।
 * পাসওয়ার্ড বদলানো ও এডিট ফেজ ৭-এ যুক্ত হবে।
 */
final class ProfileController
{
    public static function show(): string
    {
        Response::reset();

        $payload = AuthApi::me();

        return View::layout('layouts/admin', 'Auth::profile', [
            'title'    => 'আমার প্রোফাইল',
            'user'     => $payload['user'] ?? [],
            'messages' => $payload['m'] ?? [],
        ]);
    }
}
