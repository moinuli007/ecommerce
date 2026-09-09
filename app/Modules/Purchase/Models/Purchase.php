<?php

namespace App\Modules\Purchase\Models;

use App\Core\Model;

/**
 * ক্রয়ের হেডার। `status = 1` মানে posted (স্টক ও ভাউচার বসে গেছে)।
 * doc/08-purchase.md §2।
 */
final class Purchase extends Model
{
    protected static string $table = 'purchases';
    protected static string $activeColumn = 'status';

    /** @return array<string,mixed> */
    public static function byCode(string $code): array
    {
        return static::where('code', $code)->first();
    }
}
