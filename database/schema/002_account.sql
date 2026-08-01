-- =============================================================================
--  002_account.sql — Account / Voucher মডিউল
--
--  হিসাবের গঠন (৩ লেভেল, erp_saas + school দুটোর একই প্যাটার্ন):
--
--      a_master_account          (Asset / Liability / Equity / Income / Expense)
--            └── a_chart_of_accounts   (Current Asset, Fixed Asset, Sales, ...)
--                      └── a_ledgers   (Cash in Hand, bKash, "রহিম স্টোর" ...)
--
--  ট্রানজেকশন (ডাবল এন্ট্রি):
--
--      a_voucher_entry  1 ─── n  a_ledger_entry
--      একটা ভাউচারের সব ledger_entry এর SUM(debit) = SUM(credit) হতেই হবে।
--
--  a_auto_* টেবিলগুলো হলো "সিস্টেম টেমপ্লেট" — কোড থেকে যখন কোনো সিস্টেম লেজার
--  দরকার হয় (যেমন Cash, Sales, Inventory), LedgerAccounts::systemLedger() প্রথমে
--  a_ledgers এ খোঁজে; না পেলে a_auto_ledger টেমপ্লেট থেকে বানিয়ে নেয়। ফলে ফ্রেশ
--  ইনস্টলে কোনো ম্যানুয়াল চার্ট সেটআপ ছাড়াই ভাউচার পোস্ট করা যায়।
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
--  ১. সিস্টেম টেমপ্লেট (a_auto_*) — সিড ডেটা, ইউজার এডিট করে না
-- =============================================================================

CREATE TABLE IF NOT EXISTS `a_auto_master_account` (
  `id`     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`   VARCHAR(100)    NOT NULL,
  `code`   VARCHAR(20)     NOT NULL,
  `type`   TINYINT         NOT NULL COMMENT 'MasterAccountType: 1=Asset 2=Liability 3=Equity 4=Income 5=Expense',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_auto_master_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `a_auto_chart_of_accounts` (
  `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `auto_master_account_id` BIGINT UNSIGNED NOT NULL,
  `name`                   VARCHAR(100)    NOT NULL,
  `code`                   VARCHAR(20)     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_auto_chart_code` (`code`),
  KEY `ix_auto_chart_master` (`auto_master_account_id`),
  CONSTRAINT `fk_auto_chart_master` FOREIGN KEY (`auto_master_account_id`)
      REFERENCES `a_auto_master_account` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `a_auto_ledger` (
  `id`                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `auto_chart_of_accounts_id`   BIGINT UNSIGNED NOT NULL,
  `name`                        VARCHAR(100)    NOT NULL,
  `code`                        VARCHAR(20)     NOT NULL,
  `for_income`                  TINYINT         NOT NULL DEFAULT 0,
  `for_expense`                 TINYINT         NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_auto_ledger_code` (`code`),
  KEY `ix_auto_ledger_chart` (`auto_chart_of_accounts_id`),
  CONSTRAINT `fk_auto_ledger_chart` FOREIGN KEY (`auto_chart_of_accounts_id`)
      REFERENCES `a_auto_chart_of_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  ২. আসল চার্ট (ইউজার/সিস্টেম দুটোই এখানে বানায়)
-- =============================================================================

-- লেভেল ১ — Asset / Liability / Equity / Income / Expense
CREATE TABLE IF NOT EXISTS `a_master_account` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(150)    NOT NULL,
  `code`        VARCHAR(30)     NOT NULL,
  `type`        TINYINT         NOT NULL COMMENT 'MasterAccountType enum',
  `system_ref`  BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'a_auto_master_account.id, 0 = ম্যানুয়াল',
  `isActive`    TINYINT         NOT NULL DEFAULT 1,
  `created_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_master_code` (`code`),
  KEY `ix_master_system_ref` (`system_ref`),
  KEY `ix_master_type` (`type`, `isActive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- লেভেল ২ — Current Asset, Sales Revenue, Operating Expense ...
CREATE TABLE IF NOT EXISTS `a_chart_of_accounts` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `master_account_id`  BIGINT UNSIGNED NOT NULL,
  `name`               VARCHAR(150)    NOT NULL,
  `code`               VARCHAR(30)     NOT NULL,
  `system_chart`       BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'a_auto_chart_of_accounts.id, 0 = ম্যানুয়াল',
  `isActive`           TINYINT         NOT NULL DEFAULT 1,
  `created_at`         INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`         BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`         INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`         BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chart_code` (`code`),
  KEY `ix_chart_master` (`master_account_id`, `isActive`),
  KEY `ix_chart_system` (`system_chart`),
  CONSTRAINT `fk_chart_master` FOREIGN KEY (`master_account_id`)
      REFERENCES `a_master_account` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- লেভেল ৩ — আসল লেজার। ভাউচার এখানেই ডেবিট/ক্রেডিট হয়।
-- type + reference_id দিয়ে বোঝা যায় লেজারটা কার:
--   type=Customer, reference_id=customers.id  → ওই কাস্টমারের পার্সোনাল লেজার
--   type=AutoLedger, reference_id=a_auto_ledger.id → সিস্টেম লেজার (Cash, Sales ...)
CREATE TABLE IF NOT EXISTS `a_ledgers` (
  `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `chart_of_accounts_id`  BIGINT UNSIGNED NOT NULL,
  `name`                  VARCHAR(191)    NOT NULL,
  `code`                  VARCHAR(30)     NOT NULL,
  `type`                  TINYINT         NOT NULL COMMENT 'LedgerType enum',
  `reference_id`          BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'type অনুযায়ী customer/supplier/bank/auto_ledger এর id',
  `system_ledger`         TINYINT         NOT NULL DEFAULT 0 COMMENT '1 হলে ইউজার ডিলিট করতে পারবে না',
  `for_income`            TINYINT         NOT NULL DEFAULT 0 COMMENT 'Income এন্ট্রির ড্রপডাউনে দেখাবে',
  `for_expense`           TINYINT         NOT NULL DEFAULT 0 COMMENT 'Expense এন্ট্রির ড্রপডাউনে দেখাবে',
  `isActive`              TINYINT         NOT NULL DEFAULT 1,
  `created_at`            INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`            BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`            INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`            BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ledger_code` (`code`),
  UNIQUE KEY `uq_ledger_type_ref` (`type`, `reference_id`),
  KEY `ix_ledger_chart` (`chart_of_accounts_id`, `isActive`),
  CONSTRAINT `fk_ledger_chart` FOREIGN KEY (`chart_of_accounts_id`)
      REFERENCES `a_chart_of_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  ৩. ট্রানজেকশন
-- =============================================================================

-- ভাউচার হেডার। `reference` দিয়ে সোর্স ডকুমেন্ট ট্র্যাক হয় —
-- যেমন Sale ভাউচারের reference = orders.id, Purchase এর reference = purchases.id
CREATE TABLE IF NOT EXISTS `a_voucher_entry` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(30)     NOT NULL DEFAULT '' COMMENT 'CodeGenerator, যেমন SL-000123',
  `type`        SMALLINT        NOT NULL COMMENT 'VoucherType enum',
  `reference`   VARCHAR(191)    NOT NULL DEFAULT '' COMMENT 'সোর্স ডকুমেন্টের id/code',
  `note`        VARCHAR(255)    NOT NULL DEFAULT '',
  `time`        INT UNSIGNED    NOT NULL COMMENT 'ভাউচারের কার্যকর তারিখ (unix ts)',
  `isActive`    TINYINT         NOT NULL DEFAULT 1,
  `created_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_voucher_code` (`code`),
  KEY `ix_voucher_type_ref` (`type`, `reference`),
  KEY `ix_voucher_time` (`time`),
  KEY `ix_voucher_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ভাউচার ডিটেইল — প্রতিটা লাইন একটা লেজারে debit বা credit।
-- সাধারণ ভাউচারে ২টা লাইন (১ debit + ১ credit), কম্পাউন্ড ভাউচারে অনেকগুলো।
CREATE TABLE IF NOT EXISTS `a_ledger_entry` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(30)     NOT NULL DEFAULT '',
  `voucher_id`  BIGINT UNSIGNED NOT NULL,
  `ledger_id`   BIGINT UNSIGNED NOT NULL,
  `debit`       DECIMAL(20,4)   NOT NULL DEFAULT 0.0000,
  `credit`      DECIMAL(20,4)   NOT NULL DEFAULT 0.0000,
  `note`        VARCHAR(255)    NOT NULL DEFAULT '',
  `time`        INT UNSIGNED    NOT NULL COMMENT 'ভাউচারের time এর কপি — লেজার রিপোর্টে join এড়ানোর জন্য',
  `created_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_le_voucher` (`voucher_id`),
  KEY `ix_le_ledger_time` (`ledger_id`, `time`),
  CONSTRAINT `fk_le_voucher` FOREIGN KEY (`voucher_id`)
      REFERENCES `a_voucher_entry` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_le_ledger` FOREIGN KEY (`ledger_id`)
      REFERENCES `a_ledgers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
