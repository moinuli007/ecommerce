<?php

namespace App\Enum;

/**
 * ডেবিট না ক্রেডিট। ওপেনিং ব্যালেন্স, স্টেটমেন্ট, ব্যালেন্স হিসাবে ব্যবহৃত হয়।
 */
enum TransactionType: int
{
    case Debit  = 1;
    case Credit = 2;

    public function label(): string
    {
        return $this === self::Debit ? 'Debit' : 'Credit';
    }

    public function short(): string
    {
        return $this === self::Debit ? 'Dr' : 'Cr';
    }

    public function opposite(): self
    {
        return $this === self::Debit ? self::Credit : self::Debit;
    }
}
