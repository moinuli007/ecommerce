<?php

namespace App\Core;

/**
 * পুরো রিকোয়েস্ট/প্রসেস জুড়ে **একটাই** "এখন" — `time()` কে সরাসরি ছড়িয়ে-ছিটিয়ে
 * কল করলে একই রিকোয়েস্টে একাধিক টেবিলে লেখা row গুলোর `created_at`/`updated_at`
 * (বা অন্য কোনো "এখনকার সময়" ফিল্ড) সেকেন্ড-বাউন্ডারি পেরোলে আলাদা হয়ে যেতে
 * পারে — ডিবাগে একই ট্রানজ্যাকশনের সাথে সম্পর্কিত row গুলো মেলানো কঠিন করে
 * তোলে (doc/14-request-time.md)।
 *
 * সমাধান: `time()` এর বদলে সবখানে `RequestTime::now()` — প্রথম কলেই ভ্যালু
 * static এ ক্যাশ হয়ে যায়, বাকি পুরো রিকোয়েস্টে সেই একই ভ্যালু ফেরত আসে।
 *
 *     Utility::stampCreate($row); // এখন time() এর বদলে RequestTime::now() ব্যবহার করে
 *
 * PHP-FPM/CLI প্রতিটা রিকোয়েস্ট/প্রসেসে ফ্রেশ static state দিয়ে শুরু করে,
 * তাই প্রোডাকশনে আলাদা করে reset() লাগে না — টেস্টে একাধিক "রিকোয়েস্ট"
 * সিমুলেট করতে লাগে (D-05 এর request-level static ক্যাশ প্যাটার্ন, দেখুন
 * App\Core\Auth::reset())।
 */
final class RequestTime
{
    private static ?int $now = null;

    /** এই রিকোয়েস্টের "এখন" — প্রথম কলে ফিক্সড হয়ে যায়, তারপর সবসময় একই ভ্যালু। */
    public static function now(): int
    {
        if (self::$now === null) {
            self::$now = time();
        }

        return self::$now;
    }

    /**
     * টেস্টে সময়-নির্ভর লজিক ডিটারমিনিস্টিকভাবে যাচাই করতে একটা নির্দিষ্ট
     * টাইমস্ট্যাম্প বসিয়ে দেয় — প্রোডাকশন কোডে লাগে না।
     */
    public static function set(int $timestamp): void
    {
        self::$now = $timestamp;
    }

    /**
     * রিকোয়েস্ট-লেভেল ক্যাশ রিসেট — পরের `now()` কল আবার আসল `time()` থেকে
     * ফ্রেশ ভ্যালু নেবে। টেস্টে এক টেস্ট মেথডের ফিক্সড সময় পরের মেথডে লিক
     * না করতে ব্যবহার হয় (`Tests\TestCase::tearDown()`)। প্রোডাকশন কোডে লাগে না।
     */
    public static function reset(): void
    {
        self::$now = null;
    }
}
