<?php

namespace Tests\Core;

use App\Core\Message;
use App\Core\Validator;
use PHPUnit\Framework\TestCase;

/**
 * Validator ও DB ছোঁয় না — plain PHPUnit\Framework\TestCase যথেষ্ট।
 * তবে Validator::check() ডিফল্টে Message এর static কিউতে পুশ করে (pushMessages=true),
 * তাই প্রতিটা টেস্টের শেষে সেই কিউ ফ্লাশ করে দিচ্ছি — নাহলে এক টেস্টের এরর
 * মেসেজ পরের ফাইলের টেস্টে লিক করতে পারে।
 */
final class ValidatorTest extends TestCase
{
    protected function tearDown(): void
    {
        Message::flush();

        parent::tearDown();
    }

    public function test_required_field_missing_fails(): void
    {
        $ok = Validator::check(['name' => ''], ['name' => 'required'], false);

        self::assertFalse($ok);
        self::assertArrayHasKey('name', Validator::errors());
    }

    public function test_valid_data_passes(): void
    {
        $ok = Validator::check(
            ['amount' => '1200.50', 'email' => 'shop@riylon.test'],
            ['amount' => 'required|numeric|min:0.01', 'email' => 'required|email'],
            false
        );

        self::assertTrue($ok);
        self::assertSame([], Validator::errors());
    }

    public function test_numeric_rule_rejects_non_numeric_string(): void
    {
        $ok = Validator::check(['stock' => 'twenty'], ['stock' => 'numeric'], false);

        self::assertFalse($ok);
    }

    public function test_min_rule_on_a_number_checks_value_not_length(): void
    {
        $ok = Validator::check(['price' => '5'], ['price' => 'min:10'], false);

        self::assertFalse($ok, 'সংখ্যার জন্য min তুলনা করবে ভ্যালু দিয়ে, ক্যারেক্টার লেংথ দিয়ে না।');
    }

    public function test_in_rule_only_accepts_listed_values(): void
    {
        self::assertTrue(Validator::check(['status' => 'active'], ['status' => 'in:active,inactive'], false));
        self::assertFalse(Validator::check(['status' => 'pending'], ['status' => 'in:active,inactive'], false));
    }

    public function test_optional_field_left_empty_does_not_fail(): void
    {
        // required ছাড়া বাকি সব নিয়ম খালি ভ্যালুতে স্কিপ হয় (Validator::apply() দ্রষ্টব্য)
        $ok = Validator::check(['note' => ''], ['note' => 'max:255'], false);

        self::assertTrue($ok);
    }

    public function test_check_pushes_messages_by_default(): void
    {
        Validator::check(['name' => ''], ['name' => 'required']); // pushMessages ডিফল্ট true

        self::assertTrue(Message::has());
    }
}
