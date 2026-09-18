<?php

namespace App\Modules\Auth\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Password;
use App\Core\Utility;
use App\Modules\Auth\Models\User;
use RuntimeException;

/**
 * অ্যাডমিন ইউজার (Super Admin / Admin[স্টাফ]) ম্যানেজমেন্ট — শুধু
 * `type` 1/2 নিয়ে কাজ করে, কাস্টমার (`type` 3) এই সার্ভিসের স্কোপে না
 * (কাস্টমার রেজিস্ট্রেশন/প্রোফাইল আগের মতোই AuthApi এ, doc/11)।
 *
 * পুরোটাই guard: super_admin এর পেছনে (routes/api.php) — একজন সাধারণ
 * Admin(staff) অন্য কাউকে অ্যাডমিন বানাতে/মুছতে পারবে না, নিজের পাসওয়ার্ড
 * বদল ছাড়া (changeOwnPassword(), guard: admin) — doc/13-auth-and-user-management.md §৪।
 */
final class UserService
{
    /**
     * নতুন অ্যাডমিন/স্টাফ অথবা এডিট। পাসওয়ার্ড এখানে বদলায় না —
     * নতুন তৈরির সময়ই লাগে, এডিটে changePassword()/changeOwnPassword() ব্যবহার হবে।
     *
     * @param  array<string,mixed> $data name, email?, phone?, type (1|2), isActive?, password? (id=0 হলে required)
     * @return int ইউজার id
     */
    public static function save(array $data, int $id = 0): int
    {
        $name  = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $type  = (int) ($data['type'] ?? Auth::TYPE_STAFF);

        if ($name === '') {
            throw new RuntimeException('Name is required.');
        }

        if ($email === '' && $phone === '') {
            throw new RuntimeException('Provide at least an email or a phone number to log in with.');
        }

        if (!in_array($type, [Auth::TYPE_SUPER_ADMIN, Auth::TYPE_STAFF], true)) {
            throw new RuntimeException('Invalid role — must be Super Admin or Admin.');
        }

        if ($email !== '') {
            $clash = User::byEmail($email);

            if ($clash !== [] && (int) $clash['id'] !== $id) {
                throw new RuntimeException('This email is already in use.');
            }
        }

        if ($phone !== '') {
            $clash = User::byPhone($phone);

            if ($clash !== [] && (int) $clash['id'] !== $id) {
                throw new RuntimeException('This phone number is already in use.');
            }
        }

        $isActive = (int) ($data['isActive'] ?? 1) === 1 ? 1 : 0;

        $row = [
            'name'     => mb_substr($name, 0, 150),
            'email'    => $email !== '' ? mb_substr($email, 0, 150) : null,
            'phone'    => $phone !== '' ? mb_substr($phone, 0, 30) : null,
            'type'     => $type,
            'isActive' => $isActive,
        ];

        if ($id > 0) {
            $existing = User::find($id);

            if ($existing === [] || !in_array((int) $existing['type'], [Auth::TYPE_SUPER_ADMIN, Auth::TYPE_STAFF], true)) {
                throw new RuntimeException('Admin user not found.');
            }

            // শেষ সক্রিয় Super Admin কে নিজেকে demote/deactivate করা থেকে ঠেকানো —
            // নাহলে কেউই আর অ্যাডমিন ম্যানেজ করতে পারবে না (lockout)
            $losesSuperAdmin = (int) $existing['type'] === Auth::TYPE_SUPER_ADMIN
                && ($type !== Auth::TYPE_SUPER_ADMIN || $isActive === 0);

            if ($losesSuperAdmin && User::countActiveSuperAdmins() <= 1) {
                throw new RuntimeException('At least one active Super Admin must remain.');
            }

            Utility::stampUpdate($row);
            DB::update('users', $row, ['id' => $id]);

            return $id;
        }

        $password = (string) ($data['password'] ?? '');

        // দৈর্ঘ্য ম্যানুয়ালি — Validator::min সংখ্যাসদৃশ পাসওয়ার্ডকে (যেমন
        // "123456") স্ট্রিং-দৈর্ঘ্যের বদলে সংখ্যা হিসেবে তুলনা করত (doc/11 §২
        // গোচা, AuthApi::register() এও একই কারণে ম্যানুয়াল)
        if (mb_strlen($password) < 6) {
            throw new RuntimeException('Password must be at least 6 characters.');
        }

        $salt                  = Password::salt();
        $row['password']       = Password::hash($password, $salt);
        $row['password_salt']  = $salt;

        Utility::stampCreate($row);

        return DB::insert('users', $row);
    }

    /**
     * সুপার অ্যাডমিন অন্য কারো পাসওয়ার্ড রিসেট করছে — বর্তমান পাসওয়ার্ড
     * লাগে না (ভুলে-যাওয়া পাসওয়ার্ড রিকভারির পথ)।
     */
    public static function resetPassword(int $id, string $newPassword): void
    {
        $user = User::find($id);

        if ($user === [] || !in_array((int) $user['type'], [Auth::TYPE_SUPER_ADMIN, Auth::TYPE_STAFF], true)) {
            throw new RuntimeException('Admin user not found.');
        }

        if (mb_strlen($newPassword) < 6) {
            throw new RuntimeException('Password must be at least 6 characters.');
        }

        $salt = Password::salt();

        User::updateById($id, [
            'password'      => Password::hash($newPassword, $salt),
            'password_salt' => $salt,
        ]);
    }

    /**
     * লগইন করা অ্যাডমিন (Super Admin অথবা Admin) নিজের পাসওয়ার্ড বদলাচ্ছে —
     * বর্তমান পাসওয়ার্ড যাচাই লাগে।
     */
    public static function changeOwnPassword(int $id, string $currentPassword, string $newPassword): void
    {
        $user = User::find($id);

        if ($user === []) {
            throw new RuntimeException('User not found.');
        }

        $salt = (string) ($user['password_salt'] ?? '');
        $hash = (string) ($user['password'] ?? '');

        // পুরনো (salt-বিহীন) অ্যাকাউন্টের জন্য legacy ফলব্যাক — AuthApi::login()
        // এর একই যুক্তি, doc/13-auth-and-user-management.md §২.৩
        $verified = $salt !== ''
            ? Password::verify($currentPassword, $hash, $salt)
            : password_verify($currentPassword, $hash);

        if (!$verified) {
            throw new RuntimeException('Current password is incorrect.');
        }

        if (mb_strlen($newPassword) < 6) {
            throw new RuntimeException('Password must be at least 6 characters.');
        }

        $newSalt = Password::salt();

        User::updateById($id, [
            'password'      => Password::hash($newPassword, $newSalt),
            'password_salt' => $newSalt,
        ]);
    }

    /**
     * সফট-ডিলিট (`isActive = 0`) — কাস্টমার অ্যাকাউন্ট ডিলিটের মতোই
     * (AuthApi::deleteAccount(), doc/11 §৬); হার্ড-ডিলিট না করার কারণ
     * একই — created_by/updated_by অনেক টেবিলে এই id রেফার করে, সেই
     * হিসাবের ট্রেইল অক্ষত রাখতে হবে।
     */
    public static function delete(int $id): bool
    {
        $user = User::find($id);

        if ($user === [] || !in_array((int) $user['type'], [Auth::TYPE_SUPER_ADMIN, Auth::TYPE_STAFF], true)) {
            return false;
        }

        if ($id === Auth::id()) {
            throw new RuntimeException('You cannot delete your own account while logged in.');
        }

        if ((int) $user['type'] === Auth::TYPE_SUPER_ADMIN && User::countActiveSuperAdmins() <= 1) {
            throw new RuntimeException('At least one active Super Admin must remain.');
        }

        $row = ['isActive' => 0];
        Utility::stampUpdate($row);

        return DB::update('users', $row, ['id' => $id]) > 0;
    }

    /**
     * অ্যাডমিন ইউজার লিস্ট (Super Admin/Admin, কাস্টমার বাদে)।
     *
     * @param  array<string,mixed> $filters q?, type?, active_only?
     * @return array<int,array<string,mixed>>
     */
    public static function list(array $filters = []): array
    {
        $query = User::query()->whereIn('type', [Auth::TYPE_SUPER_ADMIN, Auth::TYPE_STAFF])
            ->orderBy('type')
            ->orderBy('name');

        if (($filters['active_only'] ?? 0) == 1) {
            $query->active();
        }

        $type = (int) ($filters['type'] ?? 0);

        if (in_array($type, [Auth::TYPE_SUPER_ADMIN, Auth::TYPE_STAFF], true)) {
            $query->where('type', $type);
        }

        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            $query->whereRaw('(name LIKE ? OR email LIKE ? OR phone LIKE ?)', [
                "%$search%", "%$search%", "%$search%",
            ]);
        }

        return $query->get();
    }
}
