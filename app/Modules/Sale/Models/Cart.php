<?php

namespace App\Modules\Sale\Models;

use App\Core\Model;

/**
 * কার্ট — গেস্ট-friendly, `token` (কুকি/হেডার) দিয়ে চেনা যায়, লগইন লাগে না।
 * দাম এখানে কখনো জমা থাকে না — সবসময় লাইভ ProductService::effectivePrice()।
 *
 * doc/10-storefront-order.md §৪।
 */
final class Cart extends Model
{
    protected static string $table = 'carts';

    /** @return array<string,mixed> */
    public static function byToken(string $token): array
    {
        return static::where('token', $token)->first();
    }
}
