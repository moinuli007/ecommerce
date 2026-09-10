<?php

namespace App\Enum;

/**
 * অর্ডারের পেমেন্ট মাধ্যম — `orders.payment_method`।
 *
 * bKash/Nagad এ পেমেন্ট গেটওয়ে ইন্টিগ্রেশন নাই (এখনো) — কাস্টমার নিজের
 * পার্সোনাল নাম্বার থেকে টাকা পাঠিয়ে TrxID হাতে জমা দেয়, অ্যাডমিন হাতে
 * ভেরিফাই করে (`order_payments`, `App\Enum\PaymentStatus`)।
 *
 * পূর্ণ ব্যাখ্যা: doc/10-storefront-order.md §৭।
 *
 * ⚠ ভ্যালু কখনো বদলাবেন না, নতুন মাধ্যম (Rocket, Upay …) সবসময় শেষে যোগ করবেন।
 */
enum PaymentMethod: int
{
    case Cod   = 1;
    case Bkash = 2;
    case Nagad = 3;

    public function label(): string
    {
        return match ($this) {
            self::Cod   => 'Cash on Delivery',
            self::Bkash => 'bKash',
            self::Nagad => 'Nagad',
        };
    }

    /** COD ছাড়া বাকি সবগুলোতে sender_number/transaction_id বাধ্যতামূলক */
    public function requiresReference(): bool
    {
        return $this !== self::Cod;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id'                  => $this->value,
            'name'                => $this->label(),
            'requires_reference'  => $this->requiresReference(),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return array_map(static fn (self $case) => $case->toArray(), self::cases());
    }
}
