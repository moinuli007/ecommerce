<?php

namespace Tests\Core;

use App\Core\RequestTime;
use App\Core\Utility;
use PHPUnit\Framework\TestCase;

/**
 * RequestTime কোনো DB ছোঁয় না — plain PHPUnit\Framework\TestCase যথেষ্ট
 * (D-11, doc/12-unit-testing.md)। নিজের tearDown এ RequestTime::reset()
 * কল আছে যাতে এক টেস্টের ফিক্সড সময় পরের টেস্টে (এই ফাইলে বা অন্য কোথাও)
 * লিক না করে — `Tests\TestCase` এক্সটেন্ড করা টেস্টগুলোতে এটা
 * `Tests\TestCase::tearDown()` নিজেই করে দেয়, কিন্তু এই ক্লাস pure হওয়ায়
 * সেই বেস ক্লাস এক্সটেন্ড করছে না, তাই নিজের tearDown লাগবে।
 */
final class RequestTimeTest extends TestCase
{
    protected function tearDown(): void
    {
        RequestTime::reset();

        parent::tearDown();
    }

    public function test_now_matches_the_real_current_time_by_default(): void
    {
        self::assertEqualsWithDelta(time(), RequestTime::now(), 2);
    }

    public function test_repeated_calls_return_the_exact_same_value(): void
    {
        $first = RequestTime::now();

        // real time() কে এগিয়ে যাওয়ার সুযোগ দিতে সামান্য অপেক্ষা — তাও
        // RequestTime::now() একই ফ্রোজেন ভ্যালু ফেরত দেওয়ার কথা
        usleep(1_100_000); // ~১.১ সেকেন্ড

        self::assertSame($first, RequestTime::now());
        self::assertSame($first, RequestTime::now());
    }

    public function test_set_overrides_the_cached_value_for_deterministic_tests(): void
    {
        RequestTime::set(1_700_000_000);

        self::assertSame(1_700_000_000, RequestTime::now());
    }

    public function test_reset_clears_the_cache_so_the_next_call_is_fresh(): void
    {
        RequestTime::set(1_700_000_000);
        RequestTime::reset();

        // রিসেটের পর আবার আসল "এখন" থেকে ফ্রেশ ভ্যালু আসা উচিত, পুরনো
        // set() করা মানটা না
        self::assertNotSame(1_700_000_000, RequestTime::now());
        self::assertEqualsWithDelta(time(), RequestTime::now(), 2);
    }

    /**
     * আসল সমস্যাটাই যাচাই — একই "রিকোয়েস্টে" দুইটা আলাদা টেবিলের row
     * (এখানে সিমুলেট করা দুইটা Utility::stampCreate() কল, বাস্তবে যেমন
     * `purchases` + `purchase_items`, বা `orders` + `order_status_log`)
     * সেকেন্ড-বাউন্ডারি পেরোলেও **হুবহু একই** created_at পাবে — আগে
     * `time()` সরাসরি কল হলে এই দুটো ভিন্ন হয়ে যেতে পারত।
     */
    public function test_two_stamps_in_the_same_request_always_match_even_across_a_second_boundary(): void
    {
        $firstRow = [];
        Utility::stampCreate($firstRow);

        usleep(1_100_000); // বাস্তবে দুই টেবিলে ইনসার্টের মাঝে যতটুকু সময় লাগতে পারে তার চেয়ে বেশি

        $secondRow = [];
        Utility::stampCreate($secondRow);

        self::assertSame($firstRow['created_at'], $secondRow['created_at']);
    }
}
