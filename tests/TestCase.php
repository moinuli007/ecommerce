<?php

namespace Tests;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Message;
use App\Core\RequestTime;
use App\Core\Response;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * সব ইউনিট টেস্টের বেস ক্লাস।
 *
 * প্রজেক্টের সব ক্লাস static (Decision D-05, doc/01-architecture.md) — কোনো DI
 * কনটেইনার বা mock ফ্রেমওয়ার্ক নাই, তাই সার্ভিস টেস্ট মানেই আসল DB-র বিপরীতে চালানো।
 * সেটা নিরাপদ রাখতে প্রতিটা টেস্ট একটা DB ট্রানজেকশনের ভেতরে চলে (DB::begin()),
 * আর tearDown এ rollback হয়ে যায় — ফলে টেস্ট যত ইচ্ছা insert/update/delete করুক না
 * কেন, আসল ক্যাটাগরি/প্রোডাক্ট/অর্ডার ডেটা কখনো স্পর্শ হয় না।
 *
 * Response/Message/Auth/RequestTime এর static state ও রিসেট হয়
 * (doc/01-architecture.md এর D-05 এ উল্লেখিত রুল) — নাহলে এক টেস্টের
 * মেসেজ/ডেটা/লগইন-আইডেন্টিটি/ফিক্সড-করা "এখন" পরের টেস্টে লিক করবে।
 * `$_SESSION` ও খালি করা হয় — Auth এর dual-session (admin/customer) লজিক
 * টেস্ট করতে যে টেস্ট নিজে $_SESSION['admin_user_id'] ইত্যাদি বসায়, সেটা
 * যেন অন্য টেস্টে না ছড়ায় (doc/13-auth-and-user-management.md §৫,
 * doc/14-request-time.md §৪)।
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::begin();
    }

    protected function tearDown(): void
    {
        DB::rollback();

        Response::reset();
        Message::flush();
        Auth::reset();
        RequestTime::reset();
        $_SESSION = [];

        parent::tearDown();
    }
}
