<?php

namespace App\Modules\Auth\Models;

use App\Core\Model;

/**
 * `users` টেবিল — admin/staff/customer সবাই এখানেই, `type` দিয়ে আলাদা
 * ({@see \App\Core\Auth::TYPE_SUPER_ADMIN} ইত্যাদি)। এই মডেল প্রধানত
 * UserService (অ্যাডমিন ইউজার ম্যানেজমেন্ট) ব্যবহার করে — কাস্টমার
 * রেজিস্ট্রেশন/লগইন এখনো AuthApi সরাসরি DB::getRow() দিয়েই করে (আগে থেকে
 * যেমন ছিল, অপরিবর্তিত)।
 */
final class User extends Model
{
    protected static string $table = 'users';

    /** @return array<string,mixed> */
    public static function byEmail(string $email): array
    {
        return static::where('email', $email)->first();
    }

    /** @return array<string,mixed> */
    public static function byPhone(string $phone): array
    {
        return static::where('phone', $phone)->first();
    }

    /**
     * শুধু admin/staff — কাস্টমার বাদে (অ্যাডমিন ইউজার ম্যানেজমেন্ট লিস্টের জন্য)।
     *
     * @return array<int,array<string,mixed>>
     */
    public static function admins(): array
    {
        return static::query()
            ->whereIn('type', [1, 2])
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    public static function countActiveSuperAdmins(): int
    {
        return static::query()->where('type', 1)->active()->count();
    }
}
