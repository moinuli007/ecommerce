-- =============================================================================
--  003_catalog.sql — ক্যাটালগ মডিউল
--
--  গঠন:
--
--      unit_groups ──< units                    (Piece, Dozen, Kg … + conversion)
--
--      categories (self-parent, গাছ)
--            └──< products
--                    ├──< product_variants ──< product_variant_values
--                    │            (Size=XL, Color=Navy এর নির্দিষ্ট কম্বিনেশন)
--                    └──< product_images
--
--      attributes ──< attribute_values          (Size: S/M/L/XL/XXL, Color: …)
--             └── product_variant_values দিয়ে ভ্যারিয়েন্টে যুক্ত
--
--  দুই ধরনের প্রোডাক্ট:
--    has_variant = 0 → দাম/স্টক `products` টেবিলেই (যেমন একটা বেল্ট)
--    has_variant = 1 → দাম/স্টক প্রতি `product_variants` সারিতে (শার্ট: M/L/XL)
--
--  ⚠ ভ্যারিয়েন্ট কখনো হার্ড-ডিলিট হয় না — অর্ডার/স্টকের ইতিহাস ভেঙে যাবে।
--    বাদ দিতে হলে isActive = 0।
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
--  ১. ইউনিট
-- =============================================================================

-- একই গ্রুপের ইউনিটগুলো একে অন্যে রূপান্তরযোগ্য (Piece ↔ Dozen)।
CREATE TABLE IF NOT EXISTS `unit_groups` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100)    NOT NULL,
  `sort_order`  INT             NOT NULL DEFAULT 0,
  `isActive`    TINYINT         NOT NULL DEFAULT 1,
  `created_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_unit_group_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- `conversion` = এই ইউনিটে ১ = বেস ইউনিটে কত।
--   Piece  (base)  conversion = 1
--   Dozen         conversion = 12   → ১ ডজন = ১২ পিস
-- প্রতি গ্রুপে ঠিক একটাই `is_base = 1` থাকবে (UnitService যাচাই করে)।
CREATE TABLE IF NOT EXISTS `units` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_group_id`  BIGINT UNSIGNED NOT NULL,
  `name`           VARCHAR(100)    NOT NULL,
  `code`           VARCHAR(20)     NOT NULL COMMENT 'pcs, dzn, kg …',
  `conversion`     DECIMAL(20,6)   NOT NULL DEFAULT 1.000000,
  `is_base`        TINYINT         NOT NULL DEFAULT 0,
  `sort_order`     INT             NOT NULL DEFAULT 0,
  `isActive`       TINYINT         NOT NULL DEFAULT 1,
  `created_at`     INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`     INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_unit_code` (`code`),
  KEY `ix_unit_group` (`unit_group_id`, `isActive`),
  CONSTRAINT `fk_unit_group` FOREIGN KEY (`unit_group_id`)
      REFERENCES `unit_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  ২. ক্যাটাগরি (গাছ)
-- =============================================================================

-- `parent_id = 0` মানে টপ লেভেল। রেফারেন্স সাইটের "Collections" মেনু এই গাছ।
-- `path` হলো root থেকে নিজের পর্যন্ত id গুলো — "/1/4/9/" ফরম্যাটে।
-- এতে "এই ক্যাটাগরি ও তার সব সাব-ক্যাটাগরির প্রোডাক্ট" এক LIKE কোয়েরিতেই পাওয়া যায়,
-- recursive CTE ছাড়াই। CategoryService সেভের সময় path নিজে বসায়।
CREATE TABLE IF NOT EXISTS `categories` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `path`         VARCHAR(255)    NOT NULL DEFAULT '' COMMENT '/1/4/9/ — ancestor ids',
  `depth`        TINYINT         NOT NULL DEFAULT 0,
  `name`         VARCHAR(150)    NOT NULL,
  `slug`         VARCHAR(180)    NOT NULL,
  `description`  TEXT                NULL,
  `image`        VARCHAR(255)    NOT NULL DEFAULT '',
  `sort_order`   INT             NOT NULL DEFAULT 0,
  `isFeatured`   TINYINT         NOT NULL DEFAULT 0 COMMENT 'হোমপেজের collection কার্ডে দেখাবে',
  `isActive`     TINYINT         NOT NULL DEFAULT 1,
  `created_at`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_category_slug` (`slug`),
  KEY `ix_category_parent` (`parent_id`, `isActive`, `sort_order`),
  KEY `ix_category_path` (`path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  ৩. অ্যাট্রিবিউট (Size, Color …)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `attributes` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100)    NOT NULL COMMENT 'Size, Color',
  `code`        VARCHAR(30)     NOT NULL COMMENT 'size, color — কোডে রেফার করার জন্য',
  `type`        VARCHAR(20)     NOT NULL DEFAULT 'select' COMMENT 'select | color',
  `sort_order`  INT             NOT NULL DEFAULT 0,
  `isActive`    TINYINT         NOT NULL DEFAULT 1,
  `created_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attribute_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- S, M, L, XL, XXL … / Navy, White, Black …
CREATE TABLE IF NOT EXISTS `attribute_values` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attribute_id`  BIGINT UNSIGNED NOT NULL,
  `value`         VARCHAR(100)    NOT NULL COMMENT 'দেখানোর নাম — XL',
  `code`          VARCHAR(50)     NOT NULL COMMENT 'SKU তে বসে — XL',
  `color_hex`     VARCHAR(7)      NOT NULL DEFAULT '' COMMENT 'type=color হলে সোয়াচের রঙ',
  `sort_order`    INT             NOT NULL DEFAULT 0,
  `isActive`      TINYINT         NOT NULL DEFAULT 1,
  `created_at`    INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`    INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attr_value` (`attribute_id`, `code`),
  KEY `ix_attr_value_sort` (`attribute_id`, `sort_order`)
  , CONSTRAINT `fk_attr_value_attr` FOREIGN KEY (`attribute_id`)
      REFERENCES `attributes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  ৪. প্রোডাক্ট
-- =============================================================================

-- দাম নিয়ে নিয়ম:
--   sale_price   — স্বাভাবিক বিক্রয় মূল্য (regular price)
--   offer_price  — অফার চলাকালীন মূল্য; 0 হলে অফার নাই
--   offer_start / offer_end — 0 হলে সীমা নাই (চিরকাল চলবে)
--   কার্যকর দাম বের করতে সবসময় ProductService::effectivePrice() ব্যবহার করবেন।
CREATE TABLE IF NOT EXISTS `products` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id`        BIGINT UNSIGNED NOT NULL,
  `unit_id`            BIGINT UNSIGNED NOT NULL,
  `name`               VARCHAR(191)    NOT NULL,
  `slug`               VARCHAR(220)    NOT NULL,
  `sku`                VARCHAR(60)     NOT NULL,
  `short_description`  VARCHAR(500)    NOT NULL DEFAULT '',
  `description`        TEXT                NULL,
  `purchase_price`     DECIMAL(20,4)   NOT NULL DEFAULT 0.0000,
  `sale_price`         DECIMAL(20,4)   NOT NULL DEFAULT 0.0000,
  `offer_price`        DECIMAL(20,4)   NOT NULL DEFAULT 0.0000,
  `offer_start`        INT UNSIGNED    NOT NULL DEFAULT 0,
  `offer_end`          INT UNSIGNED    NOT NULL DEFAULT 0,
  `stock`              DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'has_variant=0 হলে এখানে; নাহলে ভ্যারিয়েন্টের যোগফল',
  `stock_alert`        DECIMAL(20,4)   NOT NULL DEFAULT 0.0000,
  `weight`             DECIMAL(12,3)   NOT NULL DEFAULT 0.000 COMMENT 'গ্রামে — কুরিয়ার চার্জের জন্য',
  `has_variant`        TINYINT         NOT NULL DEFAULT 0,
  `isFeatured`         TINYINT         NOT NULL DEFAULT 0 COMMENT 'Bestsellers সেকশনে',
  `isNew`              TINYINT         NOT NULL DEFAULT 0 COMMENT 'New Arrivals সেকশনে',
  `isActive`           TINYINT         NOT NULL DEFAULT 1,
  `sort_order`         INT             NOT NULL DEFAULT 0,
  `meta_title`         VARCHAR(191)    NOT NULL DEFAULT '',
  `meta_description`   VARCHAR(300)    NOT NULL DEFAULT '',
  `created_at`         INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`         BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`         INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`         BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_slug` (`slug`),
  UNIQUE KEY `uq_product_sku` (`sku`),
  KEY `ix_product_category` (`category_id`, `isActive`, `sort_order`),
  KEY `ix_product_unit` (`unit_id`),
  KEY `ix_product_flags` (`isActive`, `isFeatured`, `isNew`),
  CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`)
      REFERENCES `categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_product_unit` FOREIGN KEY (`unit_id`)
      REFERENCES `units` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- একটা নির্দিষ্ট কম্বিনেশন — যেমন "Navy / XL"।
-- `signature` হলো attribute_value_id গুলো সাজিয়ে জোড়া দেওয়া স্ট্রিং ("3-11"),
-- এতে একই কম্বিনেশন দুবার তৈরি হওয়া UNIQUE কি দিয়েই আটকানো যায়।
CREATE TABLE IF NOT EXISTS `product_variants` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`      BIGINT UNSIGNED NOT NULL,
  `signature`       VARCHAR(191)    NOT NULL DEFAULT '',
  `name`            VARCHAR(191)    NOT NULL DEFAULT '' COMMENT 'Navy / XL',
  `sku`             VARCHAR(80)     NOT NULL,
  `barcode`         VARCHAR(80)     NOT NULL DEFAULT '',
  `purchase_price`  DECIMAL(20,4)   NOT NULL DEFAULT 0.0000,
  `sale_price`      DECIMAL(20,4)   NOT NULL DEFAULT 0.0000,
  `offer_price`     DECIMAL(20,4)   NOT NULL DEFAULT 0.0000,
  `stock`           DECIMAL(20,4)   NOT NULL DEFAULT 0.0000,
  `image`           VARCHAR(255)    NOT NULL DEFAULT '',
  `sort_order`      INT             NOT NULL DEFAULT 0,
  `isActive`        TINYINT         NOT NULL DEFAULT 1,
  `created_at`      INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`      BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`      INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`      BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_variant_sku` (`sku`),
  UNIQUE KEY `uq_variant_signature` (`product_id`, `signature`),
  KEY `ix_variant_product` (`product_id`, `isActive`),
  CONSTRAINT `fk_variant_product` FOREIGN KEY (`product_id`)
      REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ভ্যারিয়েন্ট ↔ অ্যাট্রিবিউট ভ্যালু (একটা ভ্যারিয়েন্টে প্রতি অ্যাট্রিবিউটে একটাই ভ্যালু)
CREATE TABLE IF NOT EXISTS `product_variant_values` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `variant_id`          BIGINT UNSIGNED NOT NULL,
  `product_id`          BIGINT UNSIGNED NOT NULL COMMENT 'ফিল্টারিং সহজ করতে ডুপ্লিকেট রাখা',
  `attribute_id`        BIGINT UNSIGNED NOT NULL,
  `attribute_value_id`  BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_variant_attribute` (`variant_id`, `attribute_id`),
  KEY `ix_pvv_product` (`product_id`, `attribute_value_id`),
  KEY `ix_pvv_value` (`attribute_value_id`),
  CONSTRAINT `fk_pvv_variant` FOREIGN KEY (`variant_id`)
      REFERENCES `product_variants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pvv_attribute` FOREIGN KEY (`attribute_id`)
      REFERENCES `attributes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pvv_value` FOREIGN KEY (`attribute_value_id`)
      REFERENCES `attribute_values` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- প্রোডাক্ট গ্যালারি। `variant_id > 0` হলে ছবিটা ওই ভ্যারিয়েন্টের (যেমন Navy রঙের ছবি)।
CREATE TABLE IF NOT EXISTS `product_images` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`  BIGINT UNSIGNED NOT NULL,
  `variant_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `path`        VARCHAR(255)    NOT NULL,
  `alt`         VARCHAR(191)    NOT NULL DEFAULT '',
  `is_primary`  TINYINT         NOT NULL DEFAULT 0,
  `sort_order`  INT             NOT NULL DEFAULT 0,
  `created_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_image_product` (`product_id`, `sort_order`),
  KEY `ix_image_variant` (`variant_id`),
  CONSTRAINT `fk_image_product` FOREIGN KEY (`product_id`)
      REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
