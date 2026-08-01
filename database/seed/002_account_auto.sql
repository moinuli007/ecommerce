-- =============================================================================
--  002_account_auto.sql — সিস্টেম চার্ট টেমপ্লেট (a_auto_* টেবিল)
--
--  ⚠ এখানকার `id` গুলো কোডের enum ভ্যালুর সাথে হুবহু মিলতে হবে:
--      a_auto_master_account.id      ↔ App\Enum\MasterAccountType
--      a_auto_chart_of_accounts.id   ↔ App\Enum\AutoChart
--      a_auto_ledger.id              ↔ App\Enum\AutoLedger
--  নতুন এন্ট্রি সবসময় লিস্টের শেষে নতুন id দিয়ে যোগ করবেন, মাঝখানের id বদলাবেন না।
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- লেভেল ১ — Master Account
-- type: 1=Asset 2=Liability 3=Equity 4=Income 5=Expense
-- -----------------------------------------------------------------------------
INSERT INTO `a_auto_master_account` (`id`, `name`, `code`, `type`) VALUES
  (1, 'Assets',      'A', 1),
  (2, 'Liabilities', 'L', 2),
  (3, 'Equity',      'E', 3),
  (4, 'Income',      'I', 4),
  (5, 'Expenses',    'X', 5)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `code` = VALUES(`code`), `type` = VALUES(`type`);

-- -----------------------------------------------------------------------------
-- লেভেল ২ — Chart of Accounts
-- -----------------------------------------------------------------------------
INSERT INTO `a_auto_chart_of_accounts` (`id`, `auto_master_account_id`, `name`, `code`) VALUES
  ( 1, 1, 'Current Assets',       'A-CA'),
  ( 2, 1, 'Fixed Assets',         'A-FA'),
  ( 3, 1, 'Accounts Receivable',  'A-AR'),
  ( 4, 1, 'Inventory',            'A-IN'),
  ( 5, 2, 'Current Liabilities',  'L-CL'),
  ( 6, 2, 'Accounts Payable',     'L-AP'),
  ( 7, 3, 'Owners Equity',        'E-OE'),
  ( 8, 4, 'Sales Revenue',        'I-SR'),
  ( 9, 4, 'Other Income',         'I-OI'),
  (10, 5, 'Cost of Goods Sold',   'X-CG'),
  (11, 5, 'Operating Expenses',   'X-OP')
ON DUPLICATE KEY UPDATE
  `auto_master_account_id` = VALUES(`auto_master_account_id`),
  `name` = VALUES(`name`), `code` = VALUES(`code`);

-- -----------------------------------------------------------------------------
-- লেভেল ৩ — Auto Ledger
-- এগুলো ফ্রেশ ইনস্টলে তৈরি হয় না; কোডে প্রথমবার দরকার হলে
-- LedgerAccounts::systemLedger(AutoLedger::Cash) এই টেমপ্লেট থেকে বানিয়ে নেয়।
-- -----------------------------------------------------------------------------
INSERT INTO `a_auto_ledger` (`id`, `auto_chart_of_accounts_id`, `name`, `code`, `for_income`, `for_expense`) VALUES
  -- Equity
  ( 1,  7, 'Opening Balance',            'AT-OPN', 0, 0),
  -- Current Assets
  ( 2,  1, 'Cash in Hand',               'AT-CSH', 0, 0),
  ( 3,  1, 'Bank Account',               'AT-BNK', 0, 0),
  ( 4,  1, 'Mobile Banking',             'AT-MFS', 0, 0),
  -- Accounts Receivable
  ( 5,  3, 'Payment Gateway Receivable', 'AT-PGR', 0, 0),
  ( 6,  3, 'COD / Courier Receivable',   'AT-COD', 0, 0),
  -- Inventory
  ( 7,  4, 'Inventory',                  'AT-INV', 0, 0),
  -- Sales Revenue
  ( 8,  8, 'Sales',                      'AT-SAL', 0, 0),
  ( 9,  8, 'Sales Return',               'AT-SRT', 0, 0),
  (10,  8, 'Sales Discount',             'AT-SDS', 0, 0),
  -- Other Income
  (11,  9, 'Shipping Income',            'AT-SHI', 1, 0),
  (12,  9, 'Other Income',               'AT-OIN', 1, 0),
  -- Cost of Goods Sold
  (13, 10, 'Purchase',                   'AT-PUR', 0, 0),
  (14, 10, 'Purchase Return',            'AT-PRT', 0, 0),
  (15, 10, 'Cost of Goods Sold',         'AT-CGS', 0, 0),
  (16, 10, 'Stock Adjustment',           'AT-STA', 0, 0),
  -- Operating Expenses
  (17, 11, 'Delivery Expense',           'AT-DLV', 0, 1),
  (18, 11, 'Payment Gateway Fee',        'AT-PGF', 0, 1),
  (19, 11, 'Other Expense',              'AT-OEX', 0, 1),
  -- Current Liabilities
  (20,  5, 'VAT / Tax Payable',          'AT-VAT', 0, 0),
  (21,  5, 'Advance from Customer',      'AT-ADV', 0, 0)
ON DUPLICATE KEY UPDATE
  `auto_chart_of_accounts_id` = VALUES(`auto_chart_of_accounts_id`),
  `name` = VALUES(`name`), `code` = VALUES(`code`),
  `for_income` = VALUES(`for_income`), `for_expense` = VALUES(`for_expense`);
