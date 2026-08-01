<?php

namespace App\Enum;

/**
 * সিস্টেম চার্ট অব অ্যাকাউন্টস। ভ্যালু = `a_auto_chart_of_accounts.id`
 * (database/seed/002_account_auto.sql এর সাথে হুবহু মিলতে হবে)।
 *
 * কাস্টমার/সাপ্লায়ার লেজার এই চার্টগুলোর নিচে তৈরি হয়:
 *   কাস্টমার  → AccountsReceivable
 *   সাপ্লায়ার → AccountsPayable
 */
enum AutoChart: int
{
    case CurrentAssets      = 1;
    case FixedAssets        = 2;
    case AccountsReceivable = 3;
    case Inventory          = 4;
    case CurrentLiabilities = 5;
    case AccountsPayable    = 6;
    case OwnersEquity       = 7;
    case SalesRevenue       = 8;
    case OtherIncome        = 9;
    case CostOfGoodsSold    = 10;
    case OperatingExpenses  = 11;
}
