<?php

namespace App\Core;

/**
 * পাসওয়ার্ড হ্যাশিং — সরাসরি `password_hash($plain, ...)` নয়, দুইটা
 * ভেরিয়েবল দিয়ে (doc/13-auth-and-user-management.md §২):
 *
 *   password_salt — রেজিস্ট্রেশন/অ্যাকাউন্ট তৈরির সময় random_bytes() দিয়ে
 *                   একবারই তৈরি হয়, `users.password_salt` কলামে থাকে
 *   password      — hash_hmac('sha256', $plain, $salt) কে আবার bcrypt
 *                   (PASSWORD_DEFAULT) দিয়ে হ্যাশ করা, `users.password` এ থাকে
 *
 * কেন এক ধাপ bcrypt-এর বদলে দুই ধাপ:
 *   bcrypt একাই যথেষ্ট নিরাপদ, কিন্তু bcrypt এর নিজের সল্ট আর হ্যাশ একই
 *   কলামে (`password`) থাকে — কলামটা leak হলে attacker এর কাছে সল্ট+হ্যাশ
 *   দুটোই চলে যায়। এখানে সল্ট আলাদা কলামে রেখে, সেটাকে আগে HMAC পেপার
 *   হিসেবে ব্যবহার করে, তারপর bcrypt — ফলে verify তবু ধীরগতির (bcrypt
 *   cost factor অক্ষত) থাকে, আর দুই কলাম না মিললে হ্যাশ বানানো/মেলানো
 *   কোনোটাই সম্ভব না।
 */
final class Password
{
    /** নতুন ইউজারের জন্য random password_salt (৩২ হেক্স ক্যারেক্টার)। */
    public static function salt(): string
    {
        return bin2hex(random_bytes(16));
    }

    /** password + password_salt মিলিয়ে DB তে রাখার মতো চূড়ান্ত হ্যাশ। */
    public static function hash(string $plain, string $salt): string
    {
        return password_hash(hash_hmac('sha256', $plain, $salt), PASSWORD_DEFAULT);
    }

    /**
     * লগইন/পাসওয়ার্ড-বদলের সময় মেলানো — একই দুই ধাপ আবার করে
     * `password_verify()` দিয়ে তুলনা।
     */
    public static function verify(string $plain, string $hash, string $salt): bool
    {
        if ($hash === '' || $salt === '') {
            return false;
        }

        return password_verify(hash_hmac('sha256', $plain, $salt), $hash);
    }
}
