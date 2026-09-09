<?php

namespace App\Enum;

/**
 * স্টক মুভমেন্টের ধরন — `stock_ledger.change_type`।
 * panacea `ST_CH_*` (class/somiti.php) এর সাবসেট।
 *
 * ⚠ ভ্যালু কখনো বদলাবে না — পুরোনো stock_ledger রো ভুল অর্থে চলে যাবে।
 * নতুন ধরন সবসময় শেষে নতুন নাম্বার দিয়ে যোগ করবেন।
 *
 * পূর্ণ ব্যাখ্যা: doc/08-purchase.md §৩।
 */
enum StockChangeType: int
{
    case Purchase       = 1; // ক্রয় — স্টক ইন
    case Sale           = 2; // বিক্রয় — স্টক আউট (ফেজ ৫)
    case SaleReturn     = 3; // বিক্রয় ফেরত — স্টক ইন (ফেজ ৫)
    case PurchaseReturn = 4; // ক্রয় ফেরত — স্টক আউট
    case Opening        = 5; // ওপেনিং স্টক — স্টক ইন
    case Adjust         = 6; // গণনা-সংশোধন / নষ্ট — qty নিজেই সাইনড

    /**
     * এই ধরনের মুভমেন্ট স্টক বাড়ায় (+1) না কমায় (−1)।
     *
     * StockService::move() ম্যাগনিটিউড qty নেয় আর এই সাইন গুণ করে।
     * Adjust ব্যতিক্রম — সেখানে caller নিজেই সাইনড qty দেয়, তাই +1।
     */
    public function sign(): int
    {
        return match ($this) {
            self::Sale, self::PurchaseReturn => -1,
            default => 1,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Purchase       => 'Purchase',
            self::Sale           => 'Sale',
            self::SaleReturn     => 'Sale Return',
            self::PurchaseReturn => 'Purchase Return',
            self::Opening        => 'Opening Stock',
            self::Adjust         => 'Stock Adjustment',
        };
    }
}
