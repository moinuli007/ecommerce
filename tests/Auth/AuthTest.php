<?php

namespace Tests\Auth;

use App\Core\Auth;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\UserService;
use Tests\TestCase;

/**
 * App\Core\Auth এর dual-session (admin+customer একসাথে) আর admin-এর
 * sliding ২০-মিনিট ইনঅ্যাক্টিভিটি-টাইমআউট টেস্ট (doc/13-auth-and-user-management.md)।
 *
 * Auth পুরোটাই static এবং $_SESSION/$_SERVER-নির্ভর (D-05) — mock করার
 * উপায় নাই, তাই সরাসরি এই সুপারগ্লোবাল দুটো বসিয়ে টেস্ট করা হয়েছে। প্রতিটা
 * টেস্ট মেথডের আগে/পরে path/header রিসেট হয় যাতে এক টেস্ট আরেকটাকে প্রভাবিত
 * না করে ({@see setUp()}/{@see tearDown()})। `session_start()` টেস্টে চলে না
 * (tests/bootstrap.php) — কিন্তু $_SESSION শুধু একটা plain array হিসেবেই
 * ব্যবহার হচ্ছে এখানে, তার জন্য আসল কুকি-ভিত্তিক সেশন লাগে না।
 */
final class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        unset($_SERVER['REQUEST_URI'], $_SERVER['HTTP_X_AUTH_AREA']);
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_URI'], $_SERVER['HTTP_X_AUTH_AREA']);

        parent::tearDown();
    }

    private function asPath(string $path): void
    {
        $_SERVER['REQUEST_URI'] = $path;
    }

    private function withHeader(string $area): void
    {
        $_SERVER['HTTP_X_AUTH_AREA'] = $area;
    }

    // -------------------------------------------------------------------------
    // area()
    // -------------------------------------------------------------------------

    public function test_area_is_admin_for_admin_prefixed_paths(): void
    {
        $this->asPath('/admin/products');

        self::assertSame(Auth::AREA_ADMIN, Auth::area());
    }

    public function test_area_is_customer_for_every_other_path(): void
    {
        $this->asPath('/api/v1/storefront/products');

        self::assertSame(Auth::AREA_CUSTOMER, Auth::area());
    }

    public function test_x_auth_area_header_overrides_the_path(): void
    {
        // অ্যাডমিন প্যানেলের নিজের AJAX ও /api/v1/... দিয়ে যায় (D-04) — পাথ
        // /admin দিয়ে শুরু না হলেও admin.php লেআউটের হেল্পার এই হেডার পাঠায়
        $this->asPath('/api/v1/suppliers');
        $this->withHeader('admin');

        self::assertSame(Auth::AREA_ADMIN, Auth::area());
    }

    // -------------------------------------------------------------------------
    // dual-session — admin আর customer একসাথে
    // -------------------------------------------------------------------------

    public function test_admin_and_customer_can_be_logged_in_at_the_same_time(): void
    {
        $adminId    = UserService::save([
            'name' => 'Zz Auth Admin', 'email' => 'zz.authadmin@example.test',
            'type' => Auth::TYPE_STAFF, 'password' => 'password1',
        ]);
        $customerId = self::makeCustomer('zz.authcustomer@example.test');

        $this->asPath('/admin/dashboard');
        Auth::loginSession(User::find($adminId));

        $this->asPath('/'); // এলাকা বদলালো, কিন্তু $_SESSION এখনো একই array
        Auth::loginSession(User::find($customerId));

        // দুটো স্লটই এখন $_SESSION এ আলাদাভাবে বসে আছে
        self::assertSame($adminId, (int) ($_SESSION['admin_user_id'] ?? 0));
        self::assertSame($customerId, (int) ($_SESSION['customer_user_id'] ?? 0));

        // নতুন "রিকোয়েস্টে" (Auth::reset()) admin এলাকায় গেলে admin-ই পাওয়া যায়
        Auth::reset();
        $this->asPath('/admin/dashboard');
        self::assertSame($adminId, Auth::id());
        self::assertTrue(Auth::isAdmin());

        // আর customer এলাকায় গেলে customer-ই, একই ব্রাউজার সেশনে
        Auth::reset();
        $this->asPath('/');
        self::assertSame($customerId, Auth::id());
        self::assertTrue(Auth::isCustomer());
    }

    public function test_logging_out_of_admin_area_leaves_customer_session_intact(): void
    {
        $adminId    = UserService::save([
            'name' => 'Zz Logout Admin', 'email' => 'zz.logoutadmin@example.test',
            'type' => Auth::TYPE_STAFF, 'password' => 'password1',
        ]);
        $customerId = self::makeCustomer('zz.logoutcustomer@example.test');

        $this->asPath('/admin');
        Auth::loginSession(User::find($adminId));
        $this->asPath('/');
        Auth::loginSession(User::find($customerId));

        Auth::reset();
        $this->asPath('/admin');
        Auth::logout();

        self::assertArrayNotHasKey('admin_user_id', $_SESSION);
        self::assertSame($customerId, (int) ($_SESSION['customer_user_id'] ?? 0));
    }

    // -------------------------------------------------------------------------
    // admin sliding session-timeout
    // -------------------------------------------------------------------------

    public function test_admin_session_expires_after_inactivity_beyond_the_ttl(): void
    {
        $adminId = UserService::save([
            'name' => 'Zz Idle Admin', 'email' => 'zz.idleadmin@example.test',
            'type' => Auth::TYPE_STAFF, 'password' => 'password1',
        ]);

        $_SESSION['admin_user_id']       = $adminId;
        $_SESSION['admin_last_activity'] = time() - 1201; // ২০ মিনিট + ১ সেকেন্ড আগে

        $this->asPath('/admin');

        // UserService::save() উপরে Utility::stampCreate() এর ভেতর দিয়ে
        // Auth::id() কল করেছে (created_by বসাতে) — তখনই Auth::resolve()
        // একবার চলে "not logged in" ক্যাশ করে ফেলেছিল (D-05 এর request-level
        // ক্যাশ)। নতুন "রিকোয়েস্টের" মতো আচরণ করতে সেই ক্যাশ ফেলে দিতে হবে,
        // নাহলে নিচের assertion গুলো আসল TTL-লজিক না টেস্ট করেই পাস করে যেত।
        Auth::reset();

        self::assertFalse(Auth::check());
        self::assertTrue(Auth::adminSessionExpired());
        self::assertArrayNotHasKey('admin_user_id', $_SESSION);
    }

    public function test_admin_session_survives_and_slides_forward_within_the_ttl(): void
    {
        $adminId = UserService::save([
            'name' => 'Zz Active Admin', 'email' => 'zz.activeadmin@example.test',
            'type' => Auth::TYPE_STAFF, 'password' => 'password1',
        ]);

        $oldActivity = time() - 5; // ৫ সেকেন্ড আগে, ২০ মিনিটের ভেতরেই
        $_SESSION['admin_user_id']       = $adminId;
        $_SESSION['admin_last_activity'] = $oldActivity;

        $this->asPath('/admin');
        Auth::reset(); // UserService::save() এর সময়কার স্টেল ক্যাশ ফেলা, উপরের টেস্টের মন্তব্য দ্রষ্টব্য

        self::assertTrue(Auth::check());
        self::assertFalse(Auth::adminSessionExpired());

        // "কিছু করলে সেশন বাড়বে" — রিজলভ করার পরই last_activity এগিয়ে যাওয়া উচিত
        self::assertGreaterThan($oldActivity, $_SESSION['admin_last_activity']);
    }

    public function test_customer_session_never_expires_from_inactivity(): void
    {
        $customerId = self::makeCustomer('zz.foreverCustomer@example.test');

        $_SESSION['customer_user_id'] = $customerId;
        $this->asPath('/');

        self::assertTrue(Auth::check());
        self::assertSame($customerId, Auth::id());
        // customer স্লটে কোনো *_last_activity কি বসে না
        self::assertArrayNotHasKey('customer_last_activity', $_SESSION);
    }

    public function test_a_deactivated_admin_is_rejected_even_with_a_valid_session(): void
    {
        $adminId = UserService::save([
            'name' => 'Zz Deactivated Admin', 'email' => 'zz.deactivated@example.test',
            'type' => Auth::TYPE_STAFF, 'password' => 'password1',
        ]);
        UserService::delete($adminId); // সফট-ডিলিট, isActive = 0

        $_SESSION['admin_user_id']       = $adminId;
        $_SESSION['admin_last_activity'] = time();
        $this->asPath('/admin');
        Auth::reset(); // স্টেল ক্যাশ ফেলা — test_admin_session_expires_... এর মন্তব্য দ্রষ্টব্য

        self::assertFalse(Auth::check());
    }

    /** সহজ কাস্টমার তৈরি — UserService টাইপ 1/2 তেই সীমাবদ্ধ, তাই সরাসরি DB */
    private static function makeCustomer(string $email): int
    {
        $salt = \App\Core\Password::salt();

        return \App\Core\DB::insert('users', [
            'name'          => 'Zz Test Customer',
            'email'         => $email,
            'password'      => \App\Core\Password::hash('password1', $salt),
            'password_salt' => $salt,
            'type'          => Auth::TYPE_CUSTOMER,
            'isActive'      => 1,
            'created_at'    => time(),
            'updated_at'    => time(),
        ]);
    }
}
