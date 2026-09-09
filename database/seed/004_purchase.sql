-- =============================================================================
--  004_purchase.sql — পারচেস মডিউলের সিড
--
--  এখানে শুধু code_sequence prefix — অটো লেজার (Inventory, Purchase,
--  Purchase Return, Stock Adjustment) আগে থেকেই 002_account_auto.sql এ আছে,
--  আর a_ledgers রো lazy তৈরি হয় (LedgerAccounts::systemLedger)।
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- ডকুমেন্ট কোড সিকোয়েন্স — CodeGenerator::next('purchase', 'PU') → PU-000123
-- -----------------------------------------------------------------------------
INSERT INTO `code_sequence` (`name`, `prefix`, `next_number`, `padding`) VALUES
  ('supplier',         'SUP', 1, 6),
  ('purchase',         'PU',  1, 6),
  ('purchase_return',  'PRN', 1, 6),
  ('stock_adjustment', 'STA', 1, 6)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
