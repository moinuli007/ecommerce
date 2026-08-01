<?php

namespace App\Enum;

/**
 * ভাউচারের ধরন — `a_voucher_entry.type`।
 *
 * ⚠ ভ্যালু কখনো বদলাবেন না, পুরোনো ভাউচারের হিসাব নষ্ট হয়ে যাবে।
 * নতুন ধরন সবসময় শেষে নতুন নাম্বার দিয়ে যোগ করবেন।
 *
 * `code()` হলো ভাউচার নাম্বারের প্রিফিক্স — যেমন Sale এর ভাউচার কোড হয় `SL-000123`।
 * `reference()` বলে দেয় ওই টাইপের ভাউচারে `a_voucher_entry.reference` কলামে কী থাকে।
 */
enum VoucherType: int
{
    // ওপেনিং
    case OpeningCustomer = 1;
    case OpeningSupplier = 2;
    case OpeningBalance  = 3;

    // ক্রয়
    case Purchase        = 4;
    case PurchaseReturn  = 5;
    case SupplierPayment = 6;
    case SupplierReceive = 7;

    // বিক্রয়
    case Sale            = 8;
    case SaleReturn      = 9;
    case CustomerReceive = 10;
    case CustomerRefund  = 11;
    case ShippingCharge  = 12;
    case SaleDiscount    = 13;

    // ইনভেন্টরি
    case CostOfGoodsSold = 14;
    case StockAdjustment = 15;

    // সাধারণ হিসাব
    case Income  = 16;
    case Expense = 17;
    case Contra  = 18;
    case Journal = 19;

    // আদায় / সেটেলমেন্ট
    case CourierSettlement = 20;
    case GatewaySettlement = 21;

    /**
     * নাম + কোড + reference কলামে কী থাকে — একটাই সোর্স অব ট্রুথ।
     *
     * @return array{name:string,code:string,reference:string}
     */
    private function meta(): array
    {
        return match ($this) {
            self::OpeningCustomer   => ['name' => 'Opening Customer',   'code' => 'OC',  'reference' => 'customers.id'],
            self::OpeningSupplier   => ['name' => 'Opening Supplier',   'code' => 'OS',  'reference' => 'suppliers.id'],
            self::OpeningBalance    => ['name' => 'Opening Balance',    'code' => 'OB',  'reference' => ''],
            self::Purchase          => ['name' => 'Purchase',           'code' => 'PU',  'reference' => 'purchases.id'],
            self::PurchaseReturn    => ['name' => 'Purchase Return',    'code' => 'PR',  'reference' => 'purchase_returns.id'],
            self::SupplierPayment   => ['name' => 'Supplier Payment',   'code' => 'SP',  'reference' => 'suppliers.id'],
            self::SupplierReceive   => ['name' => 'Supplier Receive',   'code' => 'SRC', 'reference' => 'suppliers.id'],
            self::Sale              => ['name' => 'Sale',               'code' => 'SL',  'reference' => 'orders.id'],
            self::SaleReturn        => ['name' => 'Sale Return',        'code' => 'SRT', 'reference' => 'order_returns.id'],
            self::CustomerReceive   => ['name' => 'Customer Receive',   'code' => 'CR',  'reference' => 'orders.id'],
            self::CustomerRefund    => ['name' => 'Customer Refund',    'code' => 'CRF', 'reference' => 'orders.id'],
            self::ShippingCharge    => ['name' => 'Shipping Charge',    'code' => 'SH',  'reference' => 'orders.id'],
            self::SaleDiscount      => ['name' => 'Sale Discount',      'code' => 'SD',  'reference' => 'orders.id'],
            self::CostOfGoodsSold   => ['name' => 'Cost of Goods Sold', 'code' => 'CG',  'reference' => 'orders.id'],
            self::StockAdjustment   => ['name' => 'Stock Adjustment',   'code' => 'SA',  'reference' => 'stock_adjustments.id'],
            self::Income            => ['name' => 'Income',             'code' => 'IN',  'reference' => ''],
            self::Expense           => ['name' => 'Expense',            'code' => 'EX',  'reference' => ''],
            self::Contra            => ['name' => 'Contra',             'code' => 'CN',  'reference' => ''],
            self::Journal           => ['name' => 'Journal',            'code' => 'JV',  'reference' => ''],
            self::CourierSettlement => ['name' => 'Courier Settlement', 'code' => 'CS',  'reference' => 'couriers.id'],
            self::GatewaySettlement => ['name' => 'Gateway Settlement', 'code' => 'GS',  'reference' => 'payment_gateways.id'],
        };
    }

    public function label(): string
    {
        return $this->meta()['name'];
    }

    public function code(): string
    {
        return $this->meta()['code'];
    }

    public function referenceMeaning(): string
    {
        return $this->meta()['reference'];
    }

    /** ওপেনিং ভাউচার কি না — এগুলো সাধারণ লিস্টে/রিপোর্টে আলাদা করে দেখানো হয় */
    public function isOpening(): bool
    {
        return in_array($this, [self::OpeningCustomer, self::OpeningSupplier, self::OpeningBalance], true);
    }

    /**
     * ইউজার নিজে হাতে বানাতে পারে এমন ভাউচার।
     * বাকিগুলো অর্ডার/পারচেজ পোস্ট হলে সিস্টেম নিজেই বানায়, তাই হাতে বানানো/এডিট করা নিষেধ।
     */
    public function isManual(): bool
    {
        return in_array($this, [
            self::Income, self::Expense, self::Contra, self::Journal,
            self::OpeningCustomer, self::OpeningSupplier, self::OpeningBalance,
        ], true);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id'       => $this->value,
            'name'     => $this->label(),
            'code'     => $this->code(),
            'manual'   => $this->isManual(),
            'opening'  => $this->isOpening(),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return array_map(static fn (self $case) => $case->toArray(), self::cases());
    }

    /** @return array<int,array<string,mixed>> শুধু হাতে বানানো যায় এমনগুলো */
    public static function manual(): array
    {
        return array_values(array_map(
            static fn (self $case) => $case->toArray(),
            array_filter(self::cases(), static fn (self $case) => $case->isManual())
        ));
    }

    /** অজানা ভ্যালু হলে সেই নাম্বারটাই স্ট্রিং হিসেবে ফেরত যায় (পুরোনো ডেটার জন্য) */
    public static function labelByValue(int $value): string
    {
        return self::tryFrom($value)?->label() ?? (string) $value;
    }
}
