-- =============================================================================
--  001_core.sql — Core টেবিল (user, token, sequence, setting)
--  Engine: MySQL 8.0 / InnoDB / utf8mb4
--
--  কনভেনশন (পুরো প্রজেক্টে একই):
--   * সব টাইমস্ট্যাম্প  : INT UNSIGNED (unix timestamp, PHP time()/strtotime())
--   * সব টাকার অঙ্ক    : DECIMAL(20,4)
--   * প্রতিটি টেবিলে   : created_at, created_by, updated_at, updated_by
--   * সফট-স্ট্যাটাস     : isActive TINYINT (1 = চালু, 0 = বন্ধ)
--   * সিঙ্গেল স্টোর     : কোনো company_id নাই (Decision D-01, doc/01-architecture.md)
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- users — admin, staff এবং customer সবাই এখানেই থাকে, `type` দিয়ে আলাদা
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(150)    NOT NULL,
  `email`       VARCHAR(150)        NULL,
  `phone`       VARCHAR(30)         NULL,
  `password`    VARCHAR(255)        NULL COMMENT 'password_hash(), গেস্ট কাস্টমারের জন্য NULL',
  `type`        TINYINT         NOT NULL DEFAULT 3 COMMENT '1=super_admin, 2=staff, 3=customer',
  `avatar`      VARCHAR(255)        NULL,
  `last_login`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `isActive`    TINYINT         NOT NULL DEFAULT 1,
  `created_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_phone` (`phone`),
  KEY `ix_users_type` (`type`, `isActive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- api_tokens — Bearer টোকেন (মোবাইল/এক্সটার্নাল ক্লায়েন্ট)
-- ব্রাউজার থেকে এলে PHP session ব্যবহার হয়, টোকেন লাগে না।
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_tokens` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `name`        VARCHAR(100)    NOT NULL DEFAULT '' COMMENT 'device / client নাম',
  `token_hash`  CHAR(64)        NOT NULL COMMENT 'hash(sha256, plainToken)',
  `abilities`   VARCHAR(255)    NOT NULL DEFAULT '*',
  `last_used`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `expires_at`  INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT '0 = কখনো এক্সপায়ার হবে না',
  `created_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_api_tokens_hash` (`token_hash`),
  KEY `ix_api_tokens_user` (`user_id`),
  CONSTRAINT `fk_api_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- code_sequence — অটো কোড জেনারেটর (voucher, ledger entry, order, invoice ...)
-- CodeGenerator::next('voucher') এই টেবিল থেকেই নাম্বার নেয় (row lock সহ)।
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `code_sequence` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(50)     NOT NULL COMMENT 'voucher | ledger_entry | order ...',
  `prefix`       VARCHAR(20)     NOT NULL DEFAULT '',
  `next_number`  BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `padding`      TINYINT         NOT NULL DEFAULT 6,
  `created_at`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_code_sequence_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `code_sequence` (`name`, `prefix`, `next_number`, `padding`) VALUES
  ('voucher',       'V',   1, 6),
  ('ledger_entry',  'LE',  1, 8),
  ('ledger',        'LG',  1, 5),
  ('chart',         'CA',  1, 4),
  ('master',        'MA',  1, 3)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- -----------------------------------------------------------------------------
-- settings — key/value কনফিগ (স্টোর নাম, ফ্রি ডেলিভারি লিমিট, COD চার্জ ...)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key_name`    VARCHAR(100)    NOT NULL,
  `key_value`   TEXT                NULL,
  `group_name`  VARCHAR(50)     NOT NULL DEFAULT 'general',
  `created_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
