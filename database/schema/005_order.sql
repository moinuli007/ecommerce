-- =============================================================================
--  005_order.sql — স্টোরফ্রন্ট, কার্ট, চেকআউট, অর্ডার (ফেজ ৩)
--
--  পূর্ণ ডিজাইন doc/10-storefront-order.md এ।
--
--  গঠন:
--
--      delivery_zones                               ঢাকার ভিতরে / বাইরে — fee, free_delivery_threshold
--            │
--      customers ──< orders ──┬──< order_items       product/variant স্ন্যাপশট
--                              ├──< order_payments    bKash/Nagad রেফারেন্স, ম্যানুয়াল ভেরিফাই
--                              └──< order_status_log  Pending→Confirmed→Processing→Shipped→Delivered
--
--      carts (গেস্ট-friendly, টোকেন কুকি) ──< cart_items ──> products / product_variants
--
--  ⚠ স্টক ও ভাউচার `Pending`/`Confirmed`-এ পোস্ট হয় না — শুধু `Shipped`-এ।
--    `Pending → Confirmed` ধাপটাই অর্ডার-অনুমোদন (Approve/Reject) — COD
--    অর্ডারের একমাত্র ফ্রড-বিরোধী গেট (doc/10-storefront-order.md §৫)।
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
--  ১. ডেলিভারি জোন
-- =============================================================================

-- ঢাকার ভিতরে/বাইরে আলাদা চার্জ — ডেটা-চালিত, হার্ডকোড না (নতুন জোন পরে
-- অ্যাডমিন থেকেই যোগ করা যাবে)। সিড: seed/005_order.sql।
CREATE TABLE IF NOT EXISTS `delivery_zones` (
  `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`                     VARCHAR(100)    NOT NULL COMMENT 'ঢাকার ভিতরে / ঢাকার বাইরে',
  `fee`                      DECIMAL(20,4)   NOT NULL DEFAULT 0.0000,
  `free_delivery_threshold`  DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT '0 = ফ্রি ডেলিভারি নাই; sub_total এর বেশি হলে fee মাফ',
  `is_default`               TINYINT         NOT NULL DEFAULT 0 COMMENT 'চেকআউটে প্রি-সিলেক্টেড থাকবে',
  `sort_order`               INT             NOT NULL DEFAULT 0,
  `isActive`                 TINYINT         NOT NULL DEFAULT 1,
  `created_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  ২. কাস্টমার
-- =============================================================================

-- গেস্ট চেকআউট ডিফল্ট — `user_id = 0` মানে অ্যাকাউন্ট নাই। `phone` দিয়েই
-- ডিডুপ হয় (চেকআউটে একই ফোনে আগের রো পাওয়া গেলে সেটাই পুনর্ব্যবহার)।
-- `ledger_id` lazy তৈরি হয় — ঠিক `suppliers.ledger_id` এর মতোই
-- (LedgerAccounts::customerLedger(), প্রথম Sale ভাউচার পোস্টের সময়)।
CREATE TABLE IF NOT EXISTS `customers` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`          BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = গেস্ট; নইলে FK → users.id',
  `name`             VARCHAR(150)    NOT NULL,
  `phone`            VARCHAR(20)     NOT NULL COMMENT 'প্রধান পরিচয় — গেস্ট ডিডুপ এই দিয়ে',
  `email`            VARCHAR(150)    NOT NULL DEFAULT '',
  `ledger_id`        BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'FK → a_ledgers, lazy',
  `opening_balance`  DECIMAL(20,4)   NOT NULL DEFAULT 0.0000,
  `isActive`         TINYINT         NOT NULL DEFAULT 1,
  `created_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customer_phone` (`phone`),
  KEY `ix_customer_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  ৩. কার্ট
-- =============================================================================

-- গেস্ট-friendly — লগইন ছাড়াই `token` (র‍্যান্ডম, কুকি/হেডারে) দিয়ে কার্ট
-- চেনা যায়। দাম এখানে কখনো জমা থাকে না — সবসময় লাইভ
-- ProductService::effectivePrice() (doc/10-storefront-order.md §৪)।
CREATE TABLE IF NOT EXISTS `carts` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token`        VARCHAR(64)     NOT NULL COMMENT 'র‍্যান্ডম — কুকিতে/হেডারে থাকে',
  `customer_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'চেকআউট/লগইনের সময় বসে',
  `created_at`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`   BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'গেস্টে সবসময় 0 — Model::create() এর সাধারণ স্ট্যাম্প, প্রজেক্টের সব টেবিলের একই কনভেনশন',
  `updated_at`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cart_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cart_items` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cart_id`     BIGINT UNSIGNED NOT NULL,
  `product_id`  BIGINT UNSIGNED NOT NULL,
  `variant_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = সিম্পল প্রোডাক্ট',
  `qty`         DECIMAL(20,4)   NOT NULL DEFAULT 1.0000,
  `created_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cart_line` (`cart_id`, `product_id`, `variant_id`),
  KEY `ix_cart_items_cart` (`cart_id`),
  CONSTRAINT `fk_cart_item_cart` FOREIGN KEY (`cart_id`)
      REFERENCES `carts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cart_item_product` FOREIGN KEY (`product_id`)
      REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  ৪. অর্ডার
-- =============================================================================

-- হেডার। `status` = App\Enum\OrderStatus (Pending→Confirmed→Processing→
-- Shipped→Delivered, + Cancelled/Returned)। `Pending → Confirmed` অ্যাডমিনের
-- Approve/Reject গেট — doc/10-storefront-order.md §৫।
--
-- দাম/ঠিকানার প্রতিটা কলাম চেকআউটের মুহূর্তের স্ন্যাপশট — পরে
-- delivery_zones.fee বা customers.name বদলালেও পুরোনো অর্ডারের হিসাব/রসিদ
-- অপরিবর্তিত থাকবে (purchase_items এর একই নীতি, 08-purchase.md)।
CREATE TABLE IF NOT EXISTS `orders` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`                VARCHAR(30)     NOT NULL COMMENT 'ORD-000123 — CodeGenerator::next(order, ORD)',
  `customer_id`         BIGINT UNSIGNED NOT NULL,
  `status`              TINYINT         NOT NULL DEFAULT 1 COMMENT 'App\\Enum\\OrderStatus',
  `recipient_name`      VARCHAR(150)    NOT NULL,
  `recipient_phone`     VARCHAR(20)     NOT NULL,
  `shipping_address`    TEXT            NOT NULL,
  `delivery_zone_id`    BIGINT UNSIGNED NOT NULL,
  `delivery_zone_name`  VARCHAR(100)    NOT NULL COMMENT 'স্ন্যাপশট',
  `sub_total`           DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'Σ order_items.line_total',
  `discount_total`      DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'v1 এ সবসময় 0 — কুপন/ভাউচার-কোড পরের ধাপ',
  `delivery_fee`        DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'checkout সময়কার delivery_zones.fee এর স্ন্যাপশট',
  `grand_total`         DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'sub_total − discount_total + delivery_fee',
  `payment_method`      TINYINT         NOT NULL DEFAULT 1 COMMENT 'App\\Enum\\PaymentMethod — 1 COD (ডিফল্ট)',
  `advance_paid`        DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'যাচাই হওয়া order_payments এর যোগফল (ক্যাশড)',
  `note`                VARCHAR(500)    NOT NULL DEFAULT '',
  `placed_at`           INT UNSIGNED    NOT NULL DEFAULT 0,
  `shipped_at`          INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'ভাউচার পোস্টের কার্যকর তারিখ',
  `created_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_code` (`code`),
  KEY `ix_order_customer` (`customer_id`),
  KEY `ix_order_status` (`status`, `placed_at`),
  CONSTRAINT `fk_order_customer` FOREIGN KEY (`customer_id`)
      REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_order_zone` FOREIGN KEY (`delivery_zone_id`)
      REFERENCES `delivery_zones` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- লাইন — প্রোডাক্টের নাম/দাম/cost checkout সময়ের স্ন্যাপশট, `products`/
-- `product_variants` এ পরের পরিবর্তন এখানে প্রতিফলিত হবে না। `cost_price`
-- Shipped এ CostOfGoodsSold ভাউচারের ভিত্তি (doc §৮)।
CREATE TABLE IF NOT EXISTS `order_items` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`      BIGINT UNSIGNED NOT NULL,
  `product_id`    BIGINT UNSIGNED NOT NULL,
  `variant_id`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `product_name`  VARCHAR(191)    NOT NULL COMMENT 'স্ন্যাপশট',
  `variant_name`  VARCHAR(191)    NOT NULL DEFAULT '' COMMENT 'স্ন্যাপশট — "Navy / XL"',
  `sku`           VARCHAR(80)     NOT NULL COMMENT 'স্ন্যাপশট',
  `unit_price`    DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'checkout সময়কার effectivePrice().price স্ন্যাপশট',
  `qty`           DECIMAL(20,4)   NOT NULL DEFAULT 0.0000,
  `line_total`    DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'qty × unit_price',
  `cost_price`    DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'checkout সময়কার purchase_price স্ন্যাপশট',
  PRIMARY KEY (`id`),
  KEY `ix_order_item_order` (`order_id`),
  KEY `ix_order_item_product` (`product_id`, `variant_id`),
  CONSTRAINT `fk_order_item_order` FOREIGN KEY (`order_id`)
      REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_item_product` FOREIGN KEY (`product_id`)
      REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- স্ট্যাটাস অডিট ট্রেইল — কে কখন কোন ধাপে নিয়ে গেছে (Approve/Reject সহ)।
-- `orders.status` শুধু বর্তমান মান রাখে, ইতিহাস এখানে।
CREATE TABLE IF NOT EXISTS `order_status_log` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`     BIGINT UNSIGNED NOT NULL,
  `from_status`  TINYINT         NOT NULL DEFAULT 0,
  `to_status`    TINYINT         NOT NULL,
  `note`         VARCHAR(255)    NOT NULL DEFAULT '',
  `created_at`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_status_log_order` (`order_id`),
  CONSTRAINT `fk_status_log_order` FOREIGN KEY (`order_id`)
      REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  ৫. পেমেন্ট — ম্যানুয়াল bKash/Nagad রেফারেন্স (গেটওয়ে না, doc §৭)
-- =============================================================================

-- কাস্টমার নিজের bKash/Nagad নাম্বার থেকে টাকা পাঠিয়ে TrxID হাতে জমা দেয়;
-- অ্যাডমিন হাতে ভেরিফাই করলে CustomerReceive ভাউচার পোস্ট হয়
-- (Dr MobileBanking / Cr AdvanceFromCustomer — doc §৮)। COD অর্ডারে এই
-- টেবিলে কোনো রো লাগে না।
CREATE TABLE IF NOT EXISTS `order_payments` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`        BIGINT UNSIGNED NOT NULL,
  `method`          TINYINT         NOT NULL COMMENT 'App\\Enum\\PaymentMethod — 2 bKash, 3 Nagad',
  `sender_number`   VARCHAR(20)     NOT NULL DEFAULT '' COMMENT 'কাস্টমার যে নাম্বার থেকে পাঠিয়েছে',
  `transaction_id`  VARCHAR(50)     NOT NULL DEFAULT '' COMMENT 'bKash/Nagad SMS এর TrxID',
  `amount`          DECIMAL(20,4)   NOT NULL DEFAULT 0.0000,
  `status`          TINYINT         NOT NULL DEFAULT 1 COMMENT 'App\\Enum\\PaymentStatus — 1 Pending, 2 Verified, 3 Rejected',
  `note`            VARCHAR(255)    NOT NULL DEFAULT '',
  `verified_by`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `verified_at`     INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_at`      INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`      BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`      INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`      BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_payment_order` (`order_id`),
  CONSTRAINT `fk_payment_order` FOREIGN KEY (`order_id`)
      REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
