-- =============================================================================
--  004_purchase.sql — পারচেস ও স্টক মডিউল (ফেজ ৪)
--  Engine: MySQL 8.0 / InnoDB / utf8mb4
--
--  পূর্ণ ব্যাখ্যা: doc/08-purchase.md
--
--  মূল নিয়ম: স্টক হাতে বসানো নয় — Purchase / Stock Adjustment থেকে আসে।
--  `products.stock` / `product_variants.stock` = ক্যাশড মান;
--  সত্যের উৎস `stock_ledger` (SUM(qty))।
--
--  কনভেনশন 001_core.sql এর মতোই:
--   * সময়   : INT UNSIGNED (unix timestamp)
--   * টাকা   : DECIMAL(20,4)
--   * qty/স্টক: DECIMAL(20,4), বেস ইউনিটে
--   * অডিট   : created_at, created_by, updated_at, updated_by
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- suppliers — যাদের কাছ থেকে পণ্য কেনা হয়
-- `ledger_id` হলো এই সাপ্লায়ারের a_ledgers রো-র ক্যাশ; ০ = এখনো তৈরি হয়নি।
-- আসল লিংক a_ledgers.(type=Supplier, reference_id=suppliers.id) — তাই FK নাই।
-- LedgerAccounts::supplierLedger() প্রথমবার দরকার হলে লেজার বানিয়ে এখানে বসায়।
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`             VARCHAR(20)     NOT NULL DEFAULT '' COMMENT 'SUP-000123',
  `name`             VARCHAR(150)    NOT NULL,
  `phone`            VARCHAR(30)     NOT NULL DEFAULT '',
  `email`            VARCHAR(150)    NOT NULL DEFAULT '',
  `address`          VARCHAR(255)    NOT NULL DEFAULT '',
  `opening_balance`  DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'ইনস্টলের সময়কার বকেয়া; OpeningSupplier ভাউচারে পোস্ট হয়',
  `ledger_id`        BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'a_ledgers.id ক্যাশ; 0 = lazy',
  `isActive`         TINYINT         NOT NULL DEFAULT 1,
  `created_at`       INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`       BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`       INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`       BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_suppliers_code` (`code`),
  KEY `ix_suppliers_active` (`isActive`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- purchases — ক্রয়ের হেডার। এক সাপ্লায়ারের এক বিল = এক রো।
-- VAT/AIT নাই (সিদ্ধান্ত P-05): total = sub_total − discount।
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchases` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`                 VARCHAR(20)     NOT NULL COMMENT 'PU-000123',
  `supplier_id`          BIGINT UNSIGNED NOT NULL,
  `supplier_invoice_no`  VARCHAR(60)     NOT NULL DEFAULT '' COMMENT 'সাপ্লায়ারের বিল নং',
  `invoice_date`         INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'বিলের কার্যকর তারিখ — ভাউচার ও stock_ledger এই সময় নেয়',
  `challan_no`           VARCHAR(60)     NOT NULL DEFAULT '',
  `po_no`                VARCHAR(60)     NOT NULL DEFAULT '',
  `sub_total`            DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'Σ purchase_items.line_total',
  `discount`             DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'invoice-লেভেল ছাড়',
  `total`                DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'sub_total − discount',
  `status`               TINYINT         NOT NULL DEFAULT 1 COMMENT '1 = posted',
  `note`                 VARCHAR(255)    NOT NULL DEFAULT '',
  `created_at`           INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`           BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`           INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`           BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_purchases_code` (`code`),
  KEY `ix_purchases_supplier` (`supplier_id`, `invoice_date`),
  KEY `ix_purchases_date` (`invoice_date`),
  CONSTRAINT `fk_purchases_supplier` FOREIGN KEY (`supplier_id`)
      REFERENCES `suppliers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- purchase_items — ক্রয়ের লাইন
-- qty ইনপুট ইউনিটে; qty_base = qty × units.conversion (স্টকে এটাই যায়)।
-- variant_id = 0 → সিম্পল প্রোডাক্ট (product_images এর মতো FK নাই, শুধু KEY)।
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_items` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_id`  BIGINT UNSIGNED NOT NULL,
  `product_id`   BIGINT UNSIGNED NOT NULL,
  `variant_id`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `unit_id`      BIGINT UNSIGNED NOT NULL,
  `qty`          DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'ইনপুট ইউনিটে',
  `qty_base`     DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'বেস ইউনিটে',
  `unit_price`   DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'প্রতি ইনপুট ইউনিটে ল্যান্ডেড ক্রয়মূল্য',
  `line_total`   DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'qty × unit_price',
  PRIMARY KEY (`id`),
  KEY `ix_pi_purchase` (`purchase_id`),
  KEY `ix_pi_product` (`product_id`, `variant_id`),
  CONSTRAINT `fk_pi_purchase` FOREIGN KEY (`purchase_id`)
      REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pi_product` FOREIGN KEY (`product_id`)
      REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pi_unit` FOREIGN KEY (`unit_id`)
      REFERENCES `units` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- stock_ledger — স্টকের একমাত্র সত্যের উৎস (panacea product_stock_log)
-- প্রতিটি রো একটা মুভমেন্ট; qty সাইনড ও বেস ইউনিটে (ইন +, আউট −)।
-- append-only — কখনো UPDATE হয় না, শুধু INSERT / DELETE (reverse)।
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_ledger` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`    BIGINT UNSIGNED NOT NULL,
  `variant_id`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `change_type`   TINYINT         NOT NULL COMMENT 'App\\Enum\\StockChangeType',
  `reference_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'সোর্স রো — purchase_items.id, stock_adjustment_items.id ...',
  `qty`           DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'সাইনড, বেস ইউনিটে',
  `rate`          DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'প্রতি বেস ইউনিটে খরচ (valuation রিপোর্ট)',
  `action_time`   INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'কার্যকর তারিখ — তারিখভিত্তিক স্টক হিসাবের ভিত্তি',
  `entry_time`    INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'রো তৈরির সময়',
  `created_by`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_sl_item_time` (`product_id`, `variant_id`, `action_time`),
  KEY `ix_sl_reference` (`change_type`, `reference_id`),
  CONSTRAINT `fk_sl_product` FOREIGN KEY (`product_id`)
      REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- stock_adjustments — ওপেনিং স্টক / নষ্ট / গণনা-মিলকরণ
-- reason: 1 = Opening, 2 = Damage, 3 = Count correction
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_adjustments` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`         VARCHAR(20)     NOT NULL COMMENT 'STA-000123',
  `reason`       TINYINT         NOT NULL DEFAULT 1 COMMENT '1=Opening 2=Damage 3=Count',
  `adjust_date`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `status`       TINYINT         NOT NULL DEFAULT 1 COMMENT '1 = posted',
  `note`         VARCHAR(255)    NOT NULL DEFAULT '',
  `created_at`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sa_code` (`code`),
  KEY `ix_sa_date` (`adjust_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- stock_adjustment_items — qty সাইনড (বাড়তি +, ঘাটতি −); Opening এ সবসময় +
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_adjustment_items` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `adjustment_id`  BIGINT UNSIGNED NOT NULL,
  `product_id`     BIGINT UNSIGNED NOT NULL,
  `variant_id`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `qty`            DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'সাইনড, বেস ইউনিটে',
  `rate`           DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'প্রতি বেস ইউনিটে মূল্য',
  PRIMARY KEY (`id`),
  KEY `ix_sai_adjustment` (`adjustment_id`),
  KEY `ix_sai_product` (`product_id`, `variant_id`),
  CONSTRAINT `fk_sai_adjustment` FOREIGN KEY (`adjustment_id`)
      REFERENCES `stock_adjustments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sai_product` FOREIGN KEY (`product_id`)
      REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- purchase_returns — কোনো ক্রয় থেকে পণ্য ফেরত
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_returns` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`         VARCHAR(20)     NOT NULL COMMENT 'PRN-000123',
  `purchase_id`  BIGINT UNSIGNED NOT NULL,
  `supplier_id`  BIGINT UNSIGNED NOT NULL,
  `return_date`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `sub_total`    DECIMAL(20,4)   NOT NULL DEFAULT 0.0000,
  `status`       TINYINT         NOT NULL DEFAULT 1 COMMENT '1 = posted',
  `note`         VARCHAR(255)    NOT NULL DEFAULT '',
  `created_at`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pr_code` (`code`),
  KEY `ix_pr_purchase` (`purchase_id`),
  KEY `ix_pr_supplier` (`supplier_id`, `return_date`),
  CONSTRAINT `fk_pr_purchase` FOREIGN KEY (`purchase_id`)
      REFERENCES `purchases` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pr_supplier` FOREIGN KEY (`supplier_id`)
      REFERENCES `suppliers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- purchase_return_items
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_return_items` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `return_id`   BIGINT UNSIGNED NOT NULL,
  `product_id`  BIGINT UNSIGNED NOT NULL,
  `variant_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `unit_id`     BIGINT UNSIGNED NOT NULL,
  `qty`         DECIMAL(20,4)   NOT NULL DEFAULT 0.0000,
  `qty_base`    DECIMAL(20,4)   NOT NULL DEFAULT 0.0000,
  `unit_price`  DECIMAL(20,4)   NOT NULL DEFAULT 0.0000,
  `line_total`  DECIMAL(20,4)   NOT NULL DEFAULT 0.0000,
  PRIMARY KEY (`id`),
  KEY `ix_pri_return` (`return_id`),
  KEY `ix_pri_product` (`product_id`, `variant_id`),
  CONSTRAINT `fk_pri_return` FOREIGN KEY (`return_id`)
      REFERENCES `purchase_returns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pri_product` FOREIGN KEY (`product_id`)
      REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pri_unit` FOREIGN KEY (`unit_id`)
      REFERENCES `units` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- product_price_log — weighted-average cost বদলের ইতিহাস (append-only)
-- CostService প্রতিবার purchase_price বদলালে এখানে এক রো।
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_price_log` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`      BIGINT UNSIGNED NOT NULL,
  `variant_id`      BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `purchase_price`  DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'নতুন weighted-average cost',
  `source_type`     TINYINT         NOT NULL DEFAULT 0 COMMENT 'App\\Enum\\StockChangeType — কোন মুভমেন্টে বদলাল',
  `reference_id`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`      INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`      BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_ppl_product` (`product_id`, `variant_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
