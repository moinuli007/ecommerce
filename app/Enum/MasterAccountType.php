<?php

namespace App\Enum;

/**
 * হিসাবের সর্বোচ্চ লেভেল। ভ্যালুগুলো `a_auto_master_account.id` এর সাথে মেলে
 * (database/seed/002_account_auto.sql)।
 */
enum MasterAccountType: int
{
    case Asset     = 1;
    case Liability = 2;
    case Equity    = 3;
    case Income    = 4;
    case Expense   = 5;

    public function label(): string
    {
        return match ($this) {
            self::Asset     => 'Assets',
            self::Liability => 'Liabilities',
            self::Equity    => 'Equity',
            self::Income    => 'Income',
            self::Expense   => 'Expenses',
        };
    }

    /**
     * অ্যাকাউন্টের স্বাভাবিক ব্যালেন্স কোন দিকে।
     * ব্যালেন্স বের করার সূত্র:
     *   Debit nature  → SUM(debit) - SUM(credit)
     *   Credit nature → SUM(credit) - SUM(debit)
     */
    public function nature(): TransactionType
    {
        return match ($this) {
            self::Asset, self::Expense              => TransactionType::Debit,
            self::Liability, self::Equity, self::Income => TransactionType::Credit,
        };
    }

    /** ব্যালেন্স শিটে যায় না কি ইনকাম স্টেটমেন্টে */
    public function isBalanceSheet(): bool
    {
        return in_array($this, [self::Asset, self::Liability, self::Equity], true);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id'             => $this->value,
            'name'           => $this->label(),
            'nature'         => $this->nature()->label(),
            'balance_sheet'  => $this->isBalanceSheet(),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return array_map(static fn (self $case) => $case->toArray(), self::cases());
    }
}
