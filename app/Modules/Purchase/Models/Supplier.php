<?php

namespace App\Modules\Purchase\Models;

use App\Core\Model;

/**
 * সাপ্লায়ার — যাদের কাছ থেকে পণ্য কেনা হয়।
 * প্রতি সাপ্লায়ারের একটা a_ledgers রো থাকে (Accounts Payable এর নিচে);
 * `ledger_id` সেটার ক্যাশ, 0 = এখনো তৈরি হয়নি।
 */
final class Supplier extends Model
{
    protected static string $table = 'suppliers';

    /** @return array<string,mixed> */
    public static function byCode(string $code): array
    {
        return static::where('code', $code)->first();
    }
}
