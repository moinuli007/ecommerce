<?php

namespace App\Enum;

/**
 * অর্ডারের ধরন — `orders.status`।
 *
 * `Pending → Confirmed` ধাপটাই অ্যাডমিনের অর্ডার-অনুমোদন (Approve/Reject) —
 * COD অর্ডারের একমাত্র ফ্রড-বিরোধী গেট। স্টক ও ভাউচার শুধু `Shipped`-এ পোস্ট
 * হয় — `Pending`/`Confirmed`/`Processing`-এ বাতিল হলে কিছুই রিভার্স করতে হয় না।
 *
 * পূর্ণ ব্যাখ্যা ও ট্রানজিশন গ্রাফ: doc/10-storefront-order.md §৫।
 *
 * ⚠ ভ্যালু কখনো বদলাবেন না, নতুন ধরন সবসময় শেষে যোগ করবেন।
 */
enum OrderStatus: int
{
    case Pending    = 1;
    case Confirmed  = 2;
    case Processing = 3;
    case Shipped    = 4;
    case Delivered  = 5;
    case Cancelled  = 6;
    case Returned   = 7;

    public function label(): string
    {
        return match ($this) {
            self::Pending    => 'Pending',
            self::Confirmed  => 'Confirmed',
            self::Processing => 'Processing',
            self::Shipped    => 'Shipped',
            self::Delivered  => 'Delivered',
            self::Cancelled  => 'Cancelled',
            self::Returned   => 'Returned',
        };
    }

    /**
     * বৈধ পরের ধাপ কী কী — doc §৫ এর গ্রাফ হুবহু।
     *
     * @return array<int,self>
     */
    public function nextStates(): array
    {
        return match ($this) {
            self::Pending    => [self::Confirmed, self::Cancelled],
            self::Confirmed  => [self::Processing, self::Cancelled],
            self::Processing => [self::Shipped, self::Cancelled],
            self::Shipped    => [self::Delivered, self::Returned],
            self::Delivered  => [self::Returned],
            self::Cancelled, self::Returned => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->nextStates(), true);
    }

    /** পণ্য এখনো গুদাম থেকে বের হয়নি — স্টক/ভাউচার কিছুই পোস্ট হয়নি */
    public function isBeforeShipment(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::Processing], true);
    }

    /** আর কোনো ট্রানজিশন সম্ভব না */
    public function isFinal(): bool
    {
        return $this->nextStates() === [];
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id'    => $this->value,
            'name'  => $this->label(),
            'final' => $this->isFinal(),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return array_map(static fn (self $case) => $case->toArray(), self::cases());
    }
}
