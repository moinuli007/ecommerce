<?php

namespace App\Enum;

/**
 * সিস্টেম লেজার। ভ্যালু = `a_auto_ledger.id`
 * (database/seed/002_account_auto.sql এর সাথে হুবহু মিলতে হবে)।
 *
 * ব্যবহার:
 *     $cash = LedgerAccounts::systemLedger(AutoLedger::Cash);
 *
 * প্রথমবার কল হলে `a_ledgers` এ লেজারটা তৈরি হয়ে যায়, পরেরবার থেকে ক্যাশ থেকে আসে।
 */
enum AutoLedger: int
{
    // Equity
    case Opening = 1;

    // Current Assets
    case Cash          = 2;
    case Bank          = 3;
    case MobileBanking = 4;

    // Accounts Receivable
    case GatewayReceivable = 5;
    case CodReceivable     = 6;

    // Inventory
    case Inventory = 7;

    // Sales Revenue
    case Sales         = 8;
    case SalesReturn   = 9;
    case SalesDiscount = 10;

    // Other Income
    case ShippingIncome = 11;
    case OtherIncome    = 12;

    // Cost of Goods Sold
    case Purchase        = 13;
    case PurchaseReturn  = 14;
    case CostOfGoodsSold = 15;
    case StockAdjustment = 16;

    // Operating Expenses
    case DeliveryExpense = 17;
    case GatewayFee      = 18;
    case OtherExpense    = 19;

    // Current Liabilities
    case VatPayable         = 20;
    case AdvanceFromCustomer = 21;
}
