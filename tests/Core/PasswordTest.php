<?php

namespace Tests\Core;

use App\Core\Password;
use PHPUnit\Framework\TestCase;

/**
 * Password কোনো DB ছোঁয় না — plain PHPUnit\Framework\TestCase যথেষ্ট
 * (D-11, doc/12-unit-testing.md)।
 */
final class PasswordTest extends TestCase
{
    public function test_salt_is_thirty_two_hex_characters(): void
    {
        $salt = Password::salt();

        self::assertSame(32, strlen($salt));
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $salt);
    }

    public function test_two_salts_are_never_the_same(): void
    {
        self::assertNotSame(Password::salt(), Password::salt());
    }

    public function test_correct_password_and_salt_verify(): void
    {
        $salt = Password::salt();
        $hash = Password::hash('correct horse battery staple', $salt);

        self::assertTrue(Password::verify('correct horse battery staple', $hash, $salt));
    }

    public function test_wrong_password_is_rejected(): void
    {
        $salt = Password::salt();
        $hash = Password::hash('the-real-password', $salt);

        self::assertFalse(Password::verify('a-guessed-password', $hash, $salt));
    }

    public function test_same_password_with_a_different_salt_does_not_verify(): void
    {
        // password_salt আসলেই হ্যাশে অংশ নেয় — শুধু password মিললেই যথেষ্ট না
        $hash = Password::hash('same-password', Password::salt());

        self::assertFalse(Password::verify('same-password', $hash, Password::salt()));
    }

    public function test_same_password_hashed_twice_gives_different_hashes(): void
    {
        // bcrypt এর নিজস্ব র‍্যান্ডম সল্ট থাকে বলে একই ইনপুটেও হ্যাশ ভিন্ন —
        // rainbow-table এর বিরুদ্ধে defense-in-depth
        $salt = Password::salt();

        self::assertNotSame(
            Password::hash('repeat-me', $salt),
            Password::hash('repeat-me', $salt)
        );
    }

    public function test_verify_rejects_empty_hash_or_salt(): void
    {
        self::assertFalse(Password::verify('anything', '', 'somesalt'));
        self::assertFalse(Password::verify('anything', 'somehash', ''));
    }
}
