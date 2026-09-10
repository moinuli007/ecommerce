-- =============================================================================
--  005_order.sql — অর্ডার মডিউলের সিড
--
--  ডেলিভারি জোনের id স্থির রাখা হয়েছে যাতে কোড/ডক থেকে রেফার করা যায়
--  (doc/10-storefront-order.md §২)। ফ্রি ডেলিভারি থ্রেশহোল্ড doc/00-overview.md
--  এর প্রোমো বার নোট ("Free Delivery on Orders Over ৳3,000") এর সাথে মিলিয়ে।
--  অ্যাকাউন্টের অটো লেজার (CodReceivable, MobileBanking, AdvanceFromCustomer
--  ইত্যাদি) আগে থেকেই seed/002_account_auto.sql এ আছে, a_ledgers রো lazy
--  তৈরি হয় (LedgerAccounts::systemLedger)।
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- ডেলিভারি জোন — ঢাকার ভিতরে / বাইরে
-- -----------------------------------------------------------------------------
INSERT INTO `delivery_zones` (`id`, `name`, `fee`, `free_delivery_threshold`, `is_default`, `sort_order`) VALUES
  (1, 'ঢাকার ভিতরে (Inside Dhaka)',   70.0000, 3000.0000, 1, 1),
  (2, 'ঢাকার বাইরে (Outside Dhaka)', 130.0000,    0.0000, 0, 2)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`), `fee` = VALUES(`fee`),
  `free_delivery_threshold` = VALUES(`free_delivery_threshold`),
  `is_default` = VALUES(`is_default`), `sort_order` = VALUES(`sort_order`);

-- -----------------------------------------------------------------------------
-- অর্ডার কোড সিকোয়েন্স — CodeGenerator::next('order', 'ORD') → ORD-000123
-- -----------------------------------------------------------------------------
INSERT INTO `code_sequence` (`name`, `prefix`, `next_number`, `padding`) VALUES
  ('order', 'ORD', 1, 6)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
