<?php

namespace Tests\Core;

use App\Core\Slug;
use PHPUnit\Framework\TestCase;

/**
 * Slug::make() খাঁটি ফাংশন — কোনো DB লাগে না, তাই সরাসরি PHPUnit\Framework\TestCase
 * (Tests\TestCase না — সেটা DB ট্রানজেকশন খোলে, এখানে দরকার নাই)।
 */
final class SlugTest extends TestCase
{
    public function test_lowercases_and_hyphenates(): void
    {
        self::assertSame('half-shirts', Slug::make('Half Shirts'));
        self::assertSame('quarter-zip-knit-sweater', Slug::make('  Quarter-Zip   Knit Sweater  '));
    }

    public function test_keeps_bengali_letters_and_combining_marks(): void
    {
        // \p{M} (কম্বাইনিং মার্ক) বাদ দিলে "পুরুষদের" ভেঙে "প-র-ষদ-র" হয়ে যেত
        // (Slug.php এর নিজের কমেন্ট দ্রষ্টব্য) — এই টেস্ট সেই রিগ্রেশন ধরবে।
        self::assertSame('পুরুষদের', Slug::make('পুরুষদের'));
    }

    public function test_strips_punctuation_down_to_a_single_hyphen(): void
    {
        self::assertSame('cash-on-delivery', Slug::make('Cash!! On -- Delivery??'));
    }

    public function test_empty_input_falls_back_to_item(): void
    {
        self::assertSame('item', Slug::make(''));
        self::assertSame('item', Slug::make('   ---   '));
    }
}
