<?php

namespace App\Enum;

/**
 * লেজারটা আসলে কার — `a_ledgers.type`।
 * `a_ledgers.reference_id` এর মানে এই type এর উপর নির্ভর করে:
 *
 *   Custom      → 0 (ম্যানুয়ালি বানানো লেজার)
 *   Customer    → customers.id
 *   Supplier    → suppliers.id
 *   Bank        → banks.id
 *   User        → users.id
 *   AutoLedger  → a_auto_ledger.id  (সিস্টেম লেজার: Cash, Sales, Inventory ...)
 *   Courier     → couriers.id       (COD আদায়ের হিসাব)
 *   Gateway     → payment_gateways.id (bKash/SSLCommerz merchant অ্যাকাউন্ট)
 */
enum LedgerType: int
{
    case Custom     = 1;
    case Supplier   = 2;
    case Bank       = 3;
    case User       = 4;
    case AutoLedger = 5;
    case Customer   = 6;
    case Courier    = 7;
    case Gateway    = 8;

    public function label(): string
    {
        return match ($this) {
            self::Custom     => 'Custom',
            self::Supplier   => 'Supplier',
            self::Bank       => 'Bank',
            self::User       => 'User',
            self::AutoLedger => 'System',
            self::Customer   => 'Customer',
            self::Courier    => 'Courier',
            self::Gateway    => 'Payment Gateway',
        };
    }

    /** কোড প্রিফিক্স — নতুন লেজারের code এভাবে বসে: CUS-14 */
    public function prefix(): string
    {
        return match ($this) {
            self::Custom     => 'LG',
            self::Supplier   => 'SP',
            self::Bank       => 'BNK',
            self::User       => 'USR',
            self::AutoLedger => 'AT',
            self::Customer   => 'CUS',
            self::Courier    => 'CUR',
            self::Gateway    => 'PG',
        };
    }

    /** সিস্টেম লেজার ইউজার ডিলিট/এডিট করতে পারবে না */
    public function isSystem(): bool
    {
        return $this !== self::Custom;
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
