<?php

namespace App\Modules\Sale\Models;

use App\Core\Model;

/**
 * কাস্টমার — গেস্ট চেকআউট ডিফল্ট, `phone` দিয়ে ডিডুপ হয়।
 * প্রতি কাস্টমারের একটা a_ledgers রো থাকে (Accounts Receivable এর নিচে);
 * `ledger_id` সেটার ক্যাশ, 0 = এখনো তৈরি হয়নি।
 *
 * doc/10-storefront-order.md §৩।
 */
final class Customer extends Model
{
    protected static string $table = 'customers';

    /** @return array<string,mixed> */
    public static function byPhone(string $phone): array
    {
        return static::where('phone', $phone)->first();
    }
}
