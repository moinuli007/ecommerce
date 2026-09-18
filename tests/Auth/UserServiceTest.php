<?php

namespace Tests\Auth;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Password;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\UserService;
use RuntimeException;
use Tests\TestCase;

/**
 * UserService আসল DB টাচ করে (Model/Service লেয়ারে mock নাই — D-05, D-10)।
 * Tests\TestCase প্রতিটা টেস্ট মেথডকে একটা ট্রানজেকশনে মুড়ে শেষে rollback
 * করে, তাই এখানে যা কিছু তৈরি/ডিলিট হচ্ছে তার কিছুই আসল `users` টেবিলে
 * থেকে যাবে না — install.php এর সিড করা super admin সহ।
 */
final class UserServiceTest extends TestCase
{
    public function test_save_creates_a_new_admin_with_a_salted_password_hash(): void
    {
        $id = UserService::save([
            'name'                  => 'Zz Test Admin',
            'email'                 => 'zz.test.admin@example.test',
            'type'                  => Auth::TYPE_STAFF,
            'password'              => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ]);

        $row = User::find($id);

        self::assertSame(Auth::TYPE_STAFF, (int) $row['type']);
        self::assertNotSame('', (string) $row['password_salt']);
        self::assertTrue(Password::verify('a-strong-password', (string) $row['password'], (string) $row['password_salt']));
    }

    public function test_save_rejects_missing_name(): void
    {
        $this->expectException(RuntimeException::class);

        UserService::save(['email' => 'zz.noname@example.test', 'type' => Auth::TYPE_STAFF, 'password' => 'password1']);
    }

    public function test_save_rejects_a_user_with_neither_email_nor_phone(): void
    {
        $this->expectException(RuntimeException::class);

        UserService::save(['name' => 'Zz No Contact', 'type' => Auth::TYPE_STAFF, 'password' => 'password1']);
    }

    public function test_save_rejects_customer_type(): void
    {
        $this->expectException(RuntimeException::class);

        UserService::save([
            'name' => 'Zz Sneaky Customer', 'email' => 'zz.sneaky@example.test',
            'type' => Auth::TYPE_CUSTOMER, 'password' => 'password1',
        ]);
    }

    public function test_save_rejects_a_duplicate_email(): void
    {
        UserService::save([
            'name' => 'Zz First', 'email' => 'zz.dup@example.test',
            'type' => Auth::TYPE_STAFF, 'password' => 'password1',
        ]);

        $this->expectException(RuntimeException::class);

        UserService::save([
            'name' => 'Zz Second', 'email' => 'zz.dup@example.test',
            'type' => Auth::TYPE_STAFF, 'password' => 'password1',
        ]);
    }

    public function test_save_edits_fields_without_touching_the_password(): void
    {
        $id = UserService::save([
            'name' => 'Zz Editable', 'email' => 'zz.editable@example.test',
            'type' => Auth::TYPE_STAFF, 'password' => 'password1',
        ]);

        $before = User::find($id);

        UserService::save(['name' => 'Zz Renamed', 'email' => 'zz.editable@example.test', 'type' => Auth::TYPE_STAFF], $id);

        $after = User::find($id);

        self::assertSame('Zz Renamed', $after['name']);
        self::assertSame($before['password'], $after['password']);
        self::assertSame($before['password_salt'], $after['password_salt']);
    }

    public function test_the_last_active_super_admin_cannot_be_demoted(): void
    {
        // ডিটারমিনিস্টিক থাকতে বিদ্যমান সব super admin কে (install.php এর
        // সিড করা একজন সহ) এই ট্রানজেকশনের ভেতরেই নিষ্ক্রিয় করে দিচ্ছি —
        // rollback এ আসল ডেটা অক্ষত থাকবে
        DB::update('users', ['isActive' => 0], ['type' => Auth::TYPE_SUPER_ADMIN]);

        $id = UserService::save([
            'name' => 'Zz Only Super Admin', 'email' => 'zz.onlysuper@example.test',
            'type' => Auth::TYPE_SUPER_ADMIN, 'password' => 'password1',
        ]);

        self::assertSame(1, User::countActiveSuperAdmins());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Super Admin must remain');

        UserService::save(['name' => 'Zz Only Super Admin', 'email' => 'zz.onlysuper@example.test', 'type' => Auth::TYPE_STAFF], $id);
    }

    public function test_demoting_one_of_two_super_admins_is_allowed(): void
    {
        DB::update('users', ['isActive' => 0], ['type' => Auth::TYPE_SUPER_ADMIN]);

        $first = UserService::save([
            'name' => 'Zz Super One', 'email' => 'zz.super1@example.test',
            'type' => Auth::TYPE_SUPER_ADMIN, 'password' => 'password1',
        ]);
        UserService::save([
            'name' => 'Zz Super Two', 'email' => 'zz.super2@example.test',
            'type' => Auth::TYPE_SUPER_ADMIN, 'password' => 'password1',
        ]);

        UserService::save(['name' => 'Zz Super One', 'email' => 'zz.super1@example.test', 'type' => Auth::TYPE_STAFF], $first);

        self::assertSame(Auth::TYPE_STAFF, (int) User::find($first)['type']);
    }

    public function test_reset_password_sets_a_new_verifiable_password(): void
    {
        $id = UserService::save([
            'name' => 'Zz Reset Me', 'email' => 'zz.reset@example.test',
            'type' => Auth::TYPE_STAFF, 'password' => 'old-password',
        ]);

        UserService::resetPassword($id, 'brand-new-password');

        $row = User::find($id);

        self::assertTrue(Password::verify('brand-new-password', $row['password'], $row['password_salt']));
        self::assertFalse(Password::verify('old-password', $row['password'], $row['password_salt']));
    }

    public function test_change_own_password_requires_the_correct_current_password(): void
    {
        $id = UserService::save([
            'name' => 'Zz Self Change', 'email' => 'zz.selfchange@example.test',
            'type' => Auth::TYPE_STAFF, 'password' => 'old-password',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Current password is incorrect');

        UserService::changeOwnPassword($id, 'wrong-current-password', 'new-password-here');
    }

    public function test_change_own_password_migrates_a_legacy_salt_less_row(): void
    {
        // password_salt কলাম যোগ হওয়ার আগে যেভাবে হ্যাশ হতো (সরাসরি bcrypt,
        // কোনো salt কলাম ছাড়া) — AuthApi::verifyPassword() এর মতোই একই
        // legacy ফলব্যাক UserService::changeOwnPassword() এও থাকা দরকার
        $id = UserService::save([
            'name' => 'Zz Legacy', 'email' => 'zz.legacy@example.test',
            'type' => Auth::TYPE_STAFF, 'password' => 'whatever-placeholder',
        ]);

        DB::update('users', [
            'password'      => password_hash('legacy-password', PASSWORD_DEFAULT),
            'password_salt' => null,
        ], ['id' => $id]);

        UserService::changeOwnPassword($id, 'legacy-password', 'freshly-salted-password');

        $row = User::find($id);

        self::assertNotSame('', (string) $row['password_salt']);
        self::assertTrue(Password::verify('freshly-salted-password', $row['password'], $row['password_salt']));
    }

    public function test_delete_soft_deletes_by_deactivating(): void
    {
        $id = UserService::save([
            'name' => 'Zz Deletable', 'email' => 'zz.deletable@example.test',
            'type' => Auth::TYPE_STAFF, 'password' => 'password1',
        ]);

        self::assertTrue(UserService::delete($id));
        self::assertSame(0, (int) User::find($id)['isActive']);
    }

    public function test_delete_refuses_to_remove_the_last_super_admin(): void
    {
        DB::update('users', ['isActive' => 0], ['type' => Auth::TYPE_SUPER_ADMIN]);

        $id = UserService::save([
            'name' => 'Zz Sole Super Admin', 'email' => 'zz.solesuper@example.test',
            'type' => Auth::TYPE_SUPER_ADMIN, 'password' => 'password1',
        ]);

        $this->expectException(RuntimeException::class);

        UserService::delete($id);
    }

    public function test_list_filters_by_search_term(): void
    {
        UserService::save([
            'name' => 'Zz Findable Admin', 'email' => 'zz.findable@example.test',
            'type' => Auth::TYPE_STAFF, 'password' => 'password1',
        ]);

        $results = UserService::list(['q' => 'Zz Findable']);

        self::assertNotEmpty($results);

        foreach ($results as $row) {
            self::assertStringContainsStringIgnoringCase('zz findable', strtolower((string) $row['name']));
        }
    }
}
