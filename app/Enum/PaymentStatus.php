<?php

namespace App\Enum;

/**
 * ম্যানুয়াল bKash/Nagad পেমেন্টের যাচাই অবস্থা — `order_payments.status`।
 *
 * `Verified` হলেই `CustomerReceive` ভাউচার পোস্ট হয় (Dr MobileBanking /
 * Cr AdvanceFromCustomer) — `Pending` বা `Rejected` অবস্থায় হিসাবে কিছু বসে না।
 *
 * পূর্ণ ব্যাখ্যা: doc/10-storefront-order.md §৭, §৮।
 *
 * ⚠ ভ্যালু কখনো বদলাবেন না।
 */
enum PaymentStatus: int
{
    case Pending  = 1;
    case Verified = 2;
    case Rejected = 3;

    public function label(): string
    {
        return match ($this) {
            self::Pending  => 'Pending',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
        };
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return array_map(
            static fn (self $case) => ['id' => $case->value, 'name' => $case->label()],
            self::cases()
        );
    }
}
