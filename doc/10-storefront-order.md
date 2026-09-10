# ১০ — স্টোরফ্রন্ট, কার্ট, চেকআউট ও অর্ডার (ফেজ ৩)

> **স্ট্যাটাস:** ✅ বিল্ড ও যাচাই করা। [00-overview.md](00-overview.md) এর
> ফেজ ৩ ("স্টোরফ্রন্ট + কাস্টমার + কার্ট + চেকআউট + অর্ডার, Sale/COGS ভাউচার অটো
> পোস্ট") এই ডকের ডিজাইন অনুযায়ীই বাস্তবায়িত। প্লাস দুটো সংযোজন, যেগুলো আগে
> পরিকল্পনায় ছিল না কিন্তু বিল্ডের সময় দরকার হলো:
>
> ১. **ডেলিভারি জোন** — ঢাকার ভিতরে/বাইরে আলাদা চার্জ (§২)
> ২. **ম্যানুয়াল মোবাইল ব্যাংকিং পেমেন্ট** — bKash/Nagad এর রেফারেন্স নাম্বার হাতে
>    টাইপ করে জমা, অ্যাডমিন হাতে ভেরিফাই করে; পেমেন্ট গেটওয়ে ইন্টিগ্রেশন এখানে
>    নেই — সেটা এখনো ফেজ ৫ এ পড়ে থাকবে, দরকার হলে তখন যোগ হবে (§৭)
>
> বিল্ড ক্রম ও যাচাইয়ের বিস্তারিত §১২ তে।

---

## ০. রেফারেন্স সাইট

[00-overview.md](00-overview.md) এ marlandmanor.store রেফারেন্স হিসেবে ছিল
(প্রোমো বার, নেভিগেশন, হোমপেজ গঠন)। এখন প্রোডাক্ট লিস্টিং/কার্ড ও PDP ডিজাইনের
জন্য দ্বিতীয় রেফারেন্স যোগ হলো:

<https://thekingsmontt.boutique/collections/all> — একই ধরনের মেনজওয়্যার স্টোর
(Shopify, Dawn থিম)। এই ডকের ডিজাইনে যা ধরা হয়েছে:

| জায়গা | কী দেখা গেল |
|---|---|
| **`/collections/all`** | সব প্রোডাক্ট এক গ্রিডে (ক্যাটাগরি ফিল্টার ছাড়া), বাঁয়ে "Filter", ওপরে column-grid টগল |
| **প্রোডাক্ট কার্ড** | ছবি (ওপরে বাঁয়ে লাল "Sale" রিবন যদি অফার চলে), নাম, তারপর দুই লাইনের দাম ব্লক — **Sale price** Tk X.00 / **Regular price** ~~Tk Y.00~~ (অফার না থাকলে খালি এক লাইন দাম) |
| **প্রোডাক্ট ডিটেইল (PDP)** | ছবির গ্যালারি (থাম্বনেইল ১,২,৩…), সাইজ চিপ সিলেক্টর, quantity স্টেপার, "Add to cart" + "Buy it now" দুটো বাটন, বর্ণনা, Size Guide টেবিল, নিচে "You may also like" |

> ⚠ **সতর্কতা — কী কপি হচ্ছে, কী না:** এই রেফারেন্স সাইটের কিছু প্রোডাক্টে
> Regular price অস্বাভাবিক বেশি দেখানো হয়েছে (একটা সোয়েটারে ৳665,000 রেগুলার,
> ৳5,700 সেল) — এটা "fake original price" ধরনের ডিসেপটিভ প্র্যাকটিস, বাংলাদেশের
> ভোক্তা অধিকার সুরক্ষা আইনেও এটা সমস্যাযুক্ত (মিথ্যা মূল্য প্রদর্শন)। **এখানে
> শুধু কার্ড/PDP এর UI-লেআউট কপি হচ্ছে, দাম বানোয়াট করার প্র্যাকটিস না** —
> `offer_price` সবসময় আসল ডিসকাউন্ট হবে, [07-catalog.md](07-catalog.md) এর
> `ProductService::effectivePrice()` লজিক অপরিবর্তিত থাকছে (§১ এর ডায়াগ্রাম)।

`/collections/all` স্টোরফ্রন্টে ম্যাপ হবে `category` প্যারামিটার ছাড়া
`GET /storefront/products` কলে (§৬)। নির্দিষ্ট কালেকশন হলে
`?category=<slug>` যোগ হবে (আগে থেকেই থাকা ক্যাটাগরি `path` LIKE কোয়েরি,
[07-catalog.md](07-catalog.md) §১ — সাব-ক্যাটাগরির প্রোডাক্টও চলে আসে)।

---

## ১. গঠন এক নজরে

```
delivery_zones                               ঢাকার ভিতরে / বাইরে — fee, free_delivery_threshold
      │
      └──< orders ──┬──< order_items          product/variant স্ন্যাপশট (নাম, দাম, sku, cost)
                     ├──< order_payments       bKash/Nagad রেফারেন্স, ম্যানুয়াল ভেরিফাই
                     └──< order_status_log     Pending→Confirmed→Processing→Shipped→Delivered

customers ──< orders
      │
      └── ledger_id → a_ledgers (Accounts Receivable, lazy তৈরি — SupplierService এর মতোই)

carts (guest-friendly, টোকেন কুকি) ──< cart_items ──> products / product_variants
                                                         (দাম কখনো কার্টে জমা হয় না —
                                                          সবসময় লাইভ effectivePrice())

status → Shipped   ──> Voucher::createCompound(Sale)        Dr CodReceivable/AdvanceFromCustomer,
                                                              Cr Sales, Cr ShippingIncome
                   ──> Voucher::create(CostOfGoodsSold)      Dr COGS, Cr Inventory
                   ──> StockService::move(−qty, Sale) প্রতি লাইনে

order_payments verified (bKash/Nagad, Shipped-এর আগে)
                   ──> Voucher::create(CustomerReceive)      Dr MobileBanking, Cr AdvanceFromCustomer

status → Returned  ──> Voucher::create(SaleReturn) — টাকা ফেরত (Dr SalesReturn / Cr CodReceivable বা AdvanceFromCustomer)
                   ──> Voucher::create(SaleReturn) — স্টক ফেরত (Dr Inventory / Cr CostOfGoodsSold)
                   ──> StockService::move(+qty, SaleReturn) প্রতি লাইনে
```

`Pending`/`Confirmed`/`Processing`/`Cancelled` — এই স্ট্যাটাসগুলোতে **কোনো
ভাউচার তৈরি হয় না**। যতক্ষণ পণ্য গুদাম থেকে বের হয়নি, ততক্ষণ হিসাবে কিছু বসবে না
— অর্ডার বাতিল হলে কিছু রিভার্স করারও দরকার পড়ে না (§৫, §৮)।

---

## ২. ডেলিভারি জোন (নতুন)

### কেন টেবিল, হার্ডকোড নয়

"ঢাকার ভিতরে ৳৭০, বাইরে ৳১৩০" — এখন দুইটা জোনই লাগবে, কিন্তু কোড/স্কিমাতে
`if ($district === 'Dhaka')` লিখলে নতুন জোন (যেমন "ঢাকা সাব-আরবান" বা
"চট্টগ্রাম মেট্রো") পরে যোগ করতে কোড বদলাতে হতো। তাই ডেটা-চালিত টেবিল —
অ্যাডমিন প্যানেল থেকেই জোন/ফি বদলানো ও নতুন জোন যোগ করা যাবে, কোনো ডিপ্লয়
লাগবে না।

### `delivery_zones`

```sql
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

INSERT INTO `delivery_zones` (`name`, `fee`, `free_delivery_threshold`, `is_default`, `sort_order`) VALUES
  ('ঢাকার ভিতরে (Inside Dhaka)',  70.0000,  3000.0000, 1, 1),
  ('ঢাকার বাইরে (Outside Dhaka)', 130.0000,     0.0000, 0, 2);
```

`free_delivery_threshold = 3000` — [00-overview.md](00-overview.md) এর প্রোমো
বার নোটের "Free Delivery on Orders Over ৳3,000" এর সাথে মিলিয়ে ডিফল্ট সিড।
বাইরের জোনে ফ্রি ডেলিভারি ডিফল্টে বন্ধ (`0`), অ্যাডমিন চাইলে চালু করতে পারবে।

### স্টোরফ্রন্টে সিলেকশন

জোন নির্বাচন **রেডিও বাটনে দুইটা অপশন** — জেলা ড্রপডাউন বা পোস্টকোড ম্যাপিং না
(v1 এ ওভার-ইঞ্জিনিয়ারিং, রেফারেন্স সাইটগুলোও এভাবেই করে)। চেকআউট ফর্মে:

```
ডেলিভারি এলাকা:  ( ) ঢাকার ভিতরে — ৳70   ( • ) ঢাকার বাইরে — ৳130
```

`GET /storefront/delivery-zones` কল করে রেডিও লিস্ট বানাবে; `is_default = 1`
জোন প্রি-সিলেক্টেড থাকবে। sub_total ইতিমধ্যে threshold পার করলে ফ্রন্টএন্ডে
"ফ্রি ডেলিভারি!" দেখানো যাবে, আসল হিসাব সবসময় সার্ভার সাইডে চেকআউটের সময়
(§৫ — `delivery_fee` কলাম)।

### অর্ডারে স্ন্যাপশট

`orders.delivery_fee` আর `orders.delivery_zone_name` চেকআউটের মুহূর্তে
`delivery_zones` থেকে কপি হয়ে বসে — পরে অ্যাডমিন ফি বাড়ালে/কমালে পুরোনো
অর্ডারের হিসাব বদলাবে না (ঠিক `purchase_items` এর মতো স্ন্যাপশট নীতি,
[08-purchase.md](08-purchase.md))।

---

## ৩. কাস্টমার

```sql
CREATE TABLE IF NOT EXISTS `customers` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`          BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = গেস্ট (অ্যাকাউন্ট খোলেনি); নইলে FK → users.id',
  `name`             VARCHAR(150)    NOT NULL,
  `phone`            VARCHAR(20)     NOT NULL COMMENT 'প্রধান পরিচয় — ডিডুপ এই দিয়ে',
  `email`            VARCHAR(150)    NOT NULL DEFAULT '',
  `ledger_id`        BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'FK → a_ledgers, lazy — LedgerAccounts::customerLedger()',
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
```

**গেস্ট চেকআউট ডিফল্ট** — অ্যাকাউন্ট বানানো বাধ্যতামূলক না (COD-নির্ভর
বাজারে লগইন-বাধ্যতা মানেই কার্ট abandonment বাড়া)। চেকআউটে `phone` দিয়ে
`customers` টেবিলে খোঁজা হয় — মিললে সেই কাস্টমারেই অর্ডার যোগ হয় (পুরোনো
অর্ডার/লেজার ইতিহাস ধরে রাখতে), না মিললে নতুন রো তৈরি। পরে কেউ ওই ফোন
নাম্বার দিয়ে রেজিস্টার করলে `user_id` বসিয়ে সেই একই `customers` রো টাই হয়ে
যাবে — দুইটা আলাদা কাস্টমার/লেজার তৈরি হবে না। `ledger_id` ঠিক
`suppliers.ledger_id` এর মতোই lazy তৈরি হয় ([08-purchase.md](08-purchase.md)
§২), প্রথম Sale ভাউচার পোস্টের সময়।

---

## ৪. কার্ট

```sql
CREATE TABLE IF NOT EXISTS `carts` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token`        VARCHAR(64)     NOT NULL COMMENT 'র‍্যান্ডম — কুকিতে/হেডারে থাকে, লগইন ছাড়াই কার্ট চেনার উপায়',
  `customer_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'চেকআউট/লগইনের সময় বসে',
  `created_at`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`   BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'গেস্টে সবসময় 0 — Model::create() এর সাধারণ স্ট্যাম্প',
  `updated_at`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cart_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cart_items` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cart_id`     BIGINT UNSIGNED NOT NULL,
  `product_id`  BIGINT UNSIGNED NOT NULL,
  `variant_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `qty`         DECIMAL(20,4)   NOT NULL DEFAULT 1.0000,
  `created_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cart_line` (`cart_id`, `product_id`, `variant_id`),
  KEY `ix_cart_items_cart` (`cart_id`),
  CONSTRAINT `fk_cart_item_cart` FOREIGN KEY (`cart_id`)
      REFERENCES `carts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**দাম কার্টে কখনো জমা থাকে না** — `GET /storefront/cart` প্রতিবার
`ProductService::effectivePrice()` দিয়ে লাইভ দাম হিসাব করে দেখায়। ফলে
প্রোডাক্টে অফার শুরু/শেষ হলে কার্টে বসে থাকা পুরোনো আইটেমেও সাথে সাথে
নতুন দাম দেখাবে — এটাই কাস্টমারের প্রত্যাশা।

`token` প্রথম `GET/POST /storefront/cart*` কলেই সার্ভার বানিয়ে
httpOnly কুকিতে (`cart_token`) বসিয়ে দেয়; মোবাইল অ্যাপের জন্য একই টোকেন
`X-Cart-Token` হেডারেও নেওয়া যাবে। `guard: guest` — লগইন লাগে না।
চেকআউট সফল হলে কার্ট খালি হয়ে যায় (`cart_items` ডিলিট, `carts` রো থেকে যায়
পরের কেনাকাটার জন্য পুনর্ব্যবহারযোগ্য)।

> লগইন করা কাস্টমারের কার্ট ডিভাইস পাল্টালে হারিয়ে যাবে (guest token
> ব্রাউজার-নির্দিষ্ট) — ক্রস-ডিভাইস কার্ট মার্জ v1 স্কোপে নেই, দরকার হলে
> পরের ধাপে।

---

## ৫. অর্ডার

### `orders`

```sql
CREATE TABLE IF NOT EXISTS `orders` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`                VARCHAR(30)     NOT NULL COMMENT 'ORD-000123 — CodeGenerator::next(order, ORD)',
  `customer_id`         BIGINT UNSIGNED NOT NULL,
  `status`              TINYINT         NOT NULL DEFAULT 1 COMMENT 'App\\Enum\\OrderStatus',
  `recipient_name`      VARCHAR(150)    NOT NULL,
  `recipient_phone`     VARCHAR(20)     NOT NULL,
  `shipping_address`    TEXT            NOT NULL,
  `delivery_zone_id`    BIGINT UNSIGNED NOT NULL,
  `delivery_zone_name`  VARCHAR(100)    NOT NULL COMMENT 'স্ন্যাপশট — পরে জোনের নাম বদলালেও পুরোনো অর্ডারে যা ছিল তাই থাকবে',
  `sub_total`           DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'Σ order_items.line_total',
  `discount_total`      DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'v1 এ সবসময় 0 — কুপন/ভাউচার কোড পরের ধাপ',
  `delivery_fee`        DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'checkout সময়কার delivery_zones.fee এর স্ন্যাপশট',
  `grand_total`         DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'sub_total − discount_total + delivery_fee',
  `payment_method`      TINYINT         NOT NULL DEFAULT 1 COMMENT 'App\\Enum\\PaymentMethod — 1 COD (ডিফল্ট)',
  `advance_paid`        DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'যাচাই হওয়া order_payments এর যোগফল (ক্যাশড)',
  `note`                VARCHAR(500)    NOT NULL DEFAULT '',
  `placed_at`           INT UNSIGNED    NOT NULL DEFAULT 0,
  `shipped_at`          INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'ভাউচার পোস্টের কার্যকর তারিখ, §৮',
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
```

### `order_items`

```sql
CREATE TABLE IF NOT EXISTS `order_items` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`      BIGINT UNSIGNED NOT NULL,
  `product_id`    BIGINT UNSIGNED NOT NULL,
  `variant_id`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `product_name`  VARCHAR(191)    NOT NULL COMMENT 'স্ন্যাপশট',
  `variant_name`  VARCHAR(191)    NOT NULL DEFAULT '' COMMENT 'স্ন্যাপশট — "Navy / XL"',
  `sku`           VARCHAR(80)     NOT NULL COMMENT 'স্ন্যাপশট',
  `unit_price`    DECIMAL(20,4)   NOT NULL COMMENT 'checkout সময়কার effectivePrice().price স্ন্যাপশট',
  `qty`           DECIMAL(20,4)   NOT NULL,
  `line_total`    DECIMAL(20,4)   NOT NULL COMMENT 'qty × unit_price',
  `cost_price`    DECIMAL(20,4)   NOT NULL DEFAULT 0.0000 COMMENT 'checkout সময়কার purchase_price স্ন্যাপশট — COGS ভাউচারের ভিত্তি',
  PRIMARY KEY (`id`),
  KEY `ix_order_item_order` (`order_id`),
  CONSTRAINT `fk_order_item_order` FOREIGN KEY (`order_id`)
      REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

প্রোডাক্টের নাম/দাম পরে বদলাতে পারে (এডিট, নতুন Purchase-এ `sale_price`
ওভাররাইট — [09-media-and-purchase-pricing.md](09-media-and-purchase-pricing.md))
কিন্তু **পুরোনো অর্ডারের রসিদ কখনো বদলাবে না** — তাই `product_name`,
`variant_name`, `sku`, `unit_price`, `cost_price` সব চেকআউটের মুহূর্তে
স্ন্যাপশট হয়ে বসে, `products`/`product_variants` টেবিলে পরের প্রতিটা লাইভ রিড
না।

### `order_status_log`

```sql
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
```

কুরিয়ার-নির্ভর ব্যবসায় "কে কখন স্ট্যাটাস বদলাল" জানা জরুরি (গ্রাহকের ফোন কল,
ডিসপিউট) — তাই প্রতিটা ট্রানজিশন লগ হয়, `orders.status` শুধু বর্তমান মান।

### `App\Enum\OrderStatus`

| # | নাম | মানে |
|---|---|---|
| 1 | `Pending` | অর্ডার এসেছে, এখনো ফোনে কনফার্ম হয়নি |
| 2 | `Confirmed` | ফোনে ঠিকানা/পণ্য কনফার্ম হয়েছে |
| 3 | `Processing` | প্যাকিং হচ্ছে |
| 4 | `Shipped` | কুরিয়ারে দেওয়া হয়েছে — **স্টক কমে, ভাউচার পোস্ট হয় (§৮)** |
| 5 | `Delivered` | কুরিয়ার ডেলিভারি করেছে (COD ক্যাশ তখনো কুরিয়ারের কাছে — সেটেলমেন্ট আলাদা ধাপ, [05-voucher.md](05-voucher.md) §৪ "আদায়/সেটেলমেন্ট") |
| 6 | `Cancelled` | শুধু `Shipped` এর **আগে** অনুমোদিত — কোনো ভাউচার ছোঁয় না |
| 7 | `Returned` | শুধু `Shipped`/`Delivered` এর **পরে** অনুমোদিত — `SaleReturn` ভাউচার + স্টক ফেরত |

### বৈধ ট্রানজিশন

```
Pending ──> Confirmed ──> Processing ──> Shipped ──> Delivered
   │            │              │            │            │
   └──────► Cancelled ◄────────┘            └──► Returned ◄┘
```

`OrderService::changeStatus()` এই গ্রাফের বাইরের কোনো লাফ আটকাবে —
যেমন `Pending` থেকে সরাসরি `Shipped` করা যাবে না, প্রতিটা ধাপ পার হতে হবে
(exception: `"Pending থেকে সরাসরি Shipped করা যায় না।"`)।

**bKash/Nagad অর্ডারে অতিরিক্ত শর্ত:** `payment_method != COD` হলে
`Confirmed` এর পরে যেতে (`Processing`/`Shipped`) একটা `Verified`
`order_payments` রো লাগবে যার `amount ≥ orders.grand_total` (§৭) — টাকা
হাতে না এসে অপ্রয়োজনে পণ্য পাঠানো যাবে না।

### অর্ডার অনুমোদন (Approval) — COD এর একমাত্র গেট

অর্ডার এলেই `Pending` — অর্থাৎ কেউ এখনো ফোনে কথা বলে ঠিকানা/পণ্য নিশ্চিত
করেনি। বাংলাদেশে COD অর্ডারে ভুয়া নাম্বার, ভুল ঠিকানা, বা শেষমুহূর্তে
মত-বদল খুবই সাধারণ — প্যাকিং/কুরিয়ারে দেওয়ার আগে একজন মানুষ ফোনে
যাচাই না করলে রিটার্ন-লস (কুরিয়ার চার্জ দুই দিকেই, প্যাকিং খরচ) অনেক বেড়ে
যায়। তাই `Pending → Confirmed` ধাপটা সাধারণ একটা স্ট্যাটাস-বদল না —
এটাই **অ্যাডমিনের অর্ডার-অনুমোদনের জায়গা**, আর এটাই **COD অর্ডারের একমাত্র/
চূড়ান্ত ফ্রড-বিরোধী গেট** (bKash/Nagad এ এর ওপরেও পেমেন্ট-ভেরিফাই গেট আছে,
ওপরের অনুচ্ছেদ)।

**অ্যাডমিনে আলাদা "Pending Orders" সারি/queue** — নতুন অর্ডার এলেই এখানে
জমা হয়, তারিখ-ক্রমে (আগে যেটা এসেছে আগে কল)। প্রতিটা রো তে দুটো বাটন:

| বাটন | ফল |
|---|---|
| **Approve** | `Pending → Confirmed`। COD অর্ডারে এরপর সরাসরি Processing/Shipped এ নেওয়া যায় — আর কোনো গেট নাই |
| **Reject** | `Pending → Cancelled`, কারণ **বাধ্যতামূলক note** সহ (ফোনে পাওয়া যায়নি / ভুল নাম্বার / কাস্টমার নিজেই বাতিল করেছে) — কোনো ভাউচার/স্টক ছোঁয় না (§৮ "Cancelled — কোনো ভাউচার না") |

`OrderService::approve()` / `reject()` শুধু `Pending` থেকেই কাজ করে —
`Confirmed`/`Processing` হয়ে যাওয়া অর্ডারে এই দুটো বাটন দেখানোর দরকার নাই
(তখন অন্য ট্রানজিশন — Cancel আলাদাভাবে অনুমোদিত থাকলে সেটা §৫ এর সাধারণ
গ্রাফ দিয়েই, নতুন কিছু না)।

---

## ৬. HTTP API — স্টোরফ্রন্ট (`guard: guest`)

erp_saas / ecommerce এর সাধারণ রেসপন্স ফরম্যাট
([03-response-format.md](03-response-format.md)) — সব `{status, m, ...}`।

### ক্যাটালগ (বিদ্যমান Catalog মডিউলেই নতুন guest এন্ডপয়েন্ট)

| Method | URI | কী | নোট |
|---|---|---|---|
| `GET` | `/api/v1/storefront/categories` | পুরো গাছ, শুধু পাবলিক কলাম | `CategoryApi::publicTree()` — `CategoryService::tree(true)` রিইউজ, আউটপুটে শুধু `id,name,slug,image,isFeatured,children` (রিকার্সিভ) |
| `GET` | `/api/v1/storefront/categories/{slug}` | একটা + ব্রেডক্রাম্ব | `CategoryApi::publicShow()` |
| `GET` | `/api/v1/storefront/products` | ফিল্টার: `category` (slug), `q`, `sort` (`newest`\|`price_asc`\|`price_desc`), `isFeatured`, `isNew`, `on_sale`, `page`, `per_page` | `ProductApi::publicIndex()` — `purchase_price` বাদ, exact `stock` এর বদলে বুলিয়ান `in_stock` |
| `GET` | `/api/v1/storefront/products/{slug}` | পূর্ণ ডিটেইল — ভ্যারিয়েন্ট, ছবি (রঙ-ভিত্তিক), `effectivePrice()` | `ProductApi::publicShowBySlug()` — `purchase_price`/`stock_alert`/exact stock বাদ, প্রোডাক্ট ও প্রতি ভ্যারিয়েন্টে `in_stock` বুলিয়ান |

`price`, `regular_price`, `on_offer`, `discount_percent` — এই চারটা কি
[07-catalog.md](07-catalog.md) এর `effectivePrice()` থেকে ইতিমধ্যেই প্রতিটা
প্রোডাক্ট/ভ্যারিয়েন্ট রো তে বসানো আছে (`ProductService::hydrate()`/`decorate()`)
— প্রোডাক্ট কার্ডের "Sale price / Regular price" ব্লক এই চারটা ফিল্ড দিয়েই
বানানো যাবে, নতুন কোনো ক্যালকুলেশন লাগবে না।

**`sort`/`on_sale` — `ProductService::search()` এ নতুন ফিল্টার** (backward-compatible,
অ্যাডমিন লিস্টিং এই দুটো না পাঠালে আগের আচরণই থাকে): `on_sale` SQL এ
`offer_price > 0 AND offer_price < sale_price AND (offer_start/end রেঞ্জে)`
শর্ত যোগ করে (`effectivePrice()`/`offerRunning()` এর একই যুক্তি, pagination এর
`total` ঠিক রাখতে post-filter না, SQL `WHERE`)। `sort` অর্ডার করে `sale_price`
কলাম দিয়ে (offer-aware effective price দিয়ে না — সহজ আনুমানিক, যথেষ্ট v1 এ)।

**`ProductApi::publicListResponse(array $filters)` — Request থেকে আলাদা করা**:
`publicIndex()` (আসল HTTP এন্ডপয়েন্ট) এটাকে `Request::string()`/`int()` দিয়ে
পড়া ফিল্টার পাঠিয়ে কল করে। কিন্তু `Request::all()` এক রিকোয়েস্টে **একবারই**
ক্যাশ হয় ([03-response-format.md](03-response-format.md) এর ডিবাগ নোটের সাথে
সম্পর্কিত internal ব্যাপার) — হোমপেজে "New Arrivals" আর "Bestsellers" দুটো
সেকশন **একই রিকোয়েস্টে** আলাদা ফিল্টারে (`isNew=1` vs `isFeatured=1`) লাগে,
যেটা `Request` পড়া কোনো মেথড দিয়ে দ্বিতীয়বার সম্ভব না। তাই `publicListResponse()`
ফিল্টার আর্গুমেন্ট হিসেবে নেয় (Request ছোঁয় না), আর `ProductApi::section(array $filters)`
(শুধু প্রোডাক্ট অ্যারে ফেরত দেয়, pagination মেটা ছাড়া) `StorefrontController::home()`
সরাসরি কল করে — এই বাগটা নিজে বিল্ড করার সময় ধরা পড়েছিল, ঠিক করা হয়েছে।

### ডেলিভারি

| Method | URI | কী |
|---|---|---|
| `GET` | `/api/v1/storefront/delivery-zones` | সক্রিয় জোন লিস্ট (`id, name, fee, free_delivery_threshold`) — চেকআউট রেডিও বানাতে |

`DeliveryZoneApi::publicIndex()` — `index()` (অ্যাডমিন, §৯) থেকে ইচ্ছাকৃতভাবে
আলাদা মেথড, সবসময় `active_only = 1` জোর করে দেয়; ক্লায়েন্ট প্যারামিটার দিয়ে
নিষ্ক্রিয় জোন দেখতে পারবে না।

### কার্ট (টোকেন কুকি/হেডার দিয়ে চেনে, §৪)

| Method | URI | Body | কী |
|---|---|---|---|
| `GET` | `/api/v1/storefront/cart` | — | লাইভ দাম সহ পুরো কার্ট + `sub_total` |
| `POST` | `/api/v1/storefront/cart/items` | `{product_id, variant_id?, qty}` | যোগ/qty বাড়ানো (একই লাইন থাকলে merge) |
| `PUT` | `/api/v1/storefront/cart/items/{id}` | `{qty}` | qty বদল (`0` = ডিলিট) |
| `DELETE` | `/api/v1/storefront/cart/items/{id}` | — | এক লাইন সরানো |
| `DELETE` | `/api/v1/storefront/cart` | — | পুরো কার্ট খালি |

### চেকআউট ও অর্ডার

| Method | URI | Body | কী |
|---|---|---|---|
| `POST` | `/api/v1/storefront/checkout` | নিচে দেখুন | কার্ট থেকে অর্ডার তৈরি |
| `GET` | `/api/v1/storefront/orders/{code}?phone=` | — | গেস্ট অর্ডার ট্র্যাকিং — কোড + ফোন দুটোই মিললে দেখাবে (লগইন লাগে না, কিন্তু কোড অনুমান-প্রতিরোধী হতে দুটো চাওয়া হয়) |

**চেকআউট পেলোড:**

```json
POST /api/v1/storefront/checkout
{
  "recipient_name": "রহিম উদ্দিন",
  "recipient_phone": "017XXXXXXXX",
  "shipping_address": "বাড়ি ১২, রোড ৫, ধানমন্ডি, ঢাকা",
  "delivery_zone_id": 1,
  "payment_method": 1,
  "note": "সন্ধ্যার পর কল দিয়ে আসবেন"
}
```

`payment_method` bKash/Nagad (2/3) হলে অতিরিক্ত লাগবে:

```json
{ "payment_method": 2, "sender_number": "018XXXXXXXX", "transaction_id": "9G7H2K4L1M" }
```

সফল রেসপন্স:

```json
{ "status": 1, "m": [["s", "Order placed — ORD-000045."]],
  "order": { "code": "ORD-000045", "grand_total": 6370.0, "status": 1, ... } }
```

সার্ভার-সাইড ধাপ (`OrderService::checkout()`, পুরোটা `DB::transaction()`):

1. কার্ট খালি না — খালি হলে `"Your cart is empty."`
2. প্রতি লাইনে স্টক আছে কি না চেক (`StockService::onHand() ≥ qty`), না
   থাকলে `"<name> is out of stock."`
3. `customers` এ `phone` দিয়ে খোঁজা/তৈরি (§৩)
4. প্রতি লাইনে `ProductService::effectivePrice()` দিয়ে **এই মুহূর্তের** দাম
   বের করে `order_items` স্ন্যাপশট বসানো (কার্টে যা দেখানো হয়েছিল সেটা না —
   race condition এড়াতে চেকআউটেই আবার হিসাব)
5. `delivery_zones` থেকে fee কপি, threshold চেক করে ফ্রি হলে `0`
6. হেডার ইনসার্ট, `code = CodeGenerator::next('order', 'ORD')`,
   `status = Pending`, প্রতি লাইন `order_items` এ (স্ন্যাপশট, audit কলাম নাই — §১১ O-13)
7. `order_status_log` এ `0 → Pending` রো ("Order placed") — এখান থেকেই অডিট ট্রেইল শুরু
8. bKash/Nagad হলে `order_payments` এ `status = Pending` রো (এখনো ভাউচার
   পোস্ট হয় না — ভেরিফাইয়ের অপেক্ষায়, §৭)
9. কার্ট খালি করা (`cart_items` ডিলিট)

> **স্টক এখনো কমে না এই ধাপে** — শুধু availability চেক হয়। আসল
> `StockService::move()` হয় `Shipped` এ যাওয়ার সময় (§৮) — কারণ `Pending`
> অবস্থায় বাতিল হওয়া অর্ডারও অনেক (ভুয়া অর্ডার, ফোনে না পাওয়া), প্রতিটাতে
> স্টক আগেই কমানো-বাড়ানো অপ্রয়োজনীয় ওঠানামা তৈরি করত।

---

## ৭. পেমেন্ট (ম্যানুয়াল রেফারেন্স)

### কেন গেটওয়ে না, রেফারেন্স-ভিত্তিক

bKash/Nagad এর অফিসিয়াল মার্চেন্ট API (PGW) এর জন্য ব্যবসায়িক অ্যাকাউন্ট,
অ্যাপ্রুভাল, সেটেলমেন্ট চুক্তি লাগে — এখনই সেটআপ নাই। তাই v1: কাস্টমার
নিজের **পার্সোনাল** bKash/Nagad নাম্বার থেকে দোকানের নাম্বারে টাকা পাঠায়,
SMS-এ পাওয়া Transaction ID (TrxID) হাতে চেকআউট ফর্মে টাইপ করে। অ্যাডমিন
নিজের bKash/Nagad অ্যাপ/ডায়াল খুলে TrxID মিলিয়ে **হাতে ভেরিফাই** করে।
[00-overview.md](00-overview.md) এ যে "bKash/Nagad আর গেটওয়ে ফি" ফেজ প্ল্যানে
ছিল, সেই আসল গেটওয়ে ইন্টিগ্রেশন (`payment_gateways`, `GatewaySettlement`
ভাউচার) ফেজ ৫ এই থাকছে — এটা তার হালকা, ম্যানুয়াল পূর্বসূরি।

### `order_payments`

```sql
CREATE TABLE IF NOT EXISTS `order_payments` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`        BIGINT UNSIGNED NOT NULL,
  `method`          TINYINT         NOT NULL COMMENT 'App\\Enum\\PaymentMethod — 2 bKash, 3 Nagad (COD এর জন্য রো লাগে না)',
  `sender_number`   VARCHAR(20)     NOT NULL DEFAULT '' COMMENT 'কাস্টমার যে নাম্বার থেকে পাঠিয়েছে',
  `transaction_id`  VARCHAR(50)     NOT NULL DEFAULT '' COMMENT 'bKash/Nagad SMS এর TrxID',
  `amount`          DECIMAL(20,4)   NOT NULL,
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
```

### `App\Enum\PaymentMethod` / `PaymentStatus`

| PaymentMethod | # | | PaymentStatus | # |
|---|---|---|---|---|
| `Cod` | 1 | | `Pending` | 1 |
| `Bkash` | 2 | | `Verified` | 2 |
| `Nagad` | 3 | | `Rejected` | 3 |

**v1 সরলীকরণ (সিদ্ধান্ত O-04, §১১):** অর্ডার হয় পুরোপুরি COD, নয়তো
পুরোপুরি অগ্রিম bKash/Nagad — আংশিক অগ্রিম + বাকিটা COD এই ডকে নেই। ব্যবসা
বড় হলে `order_payments` স্কিমা এমনিতেই একাধিক রো নিতে পারে
(`SUM(amount) < grand_total` = আংশিক), তাই স্কিমা বদলাতে হবে না, শুধু
`OrderService` এর ভ্যালিডেশন শিথিল করলেই হবে।

### অ্যাডমিন ভেরিফাই ফ্লো

| Method | URI | Body | কী |
|---|---|---|---|
| `GET` | `/api/v1/orders/{id}/payments` | — | ওই অর্ডারের সব পেমেন্ট রো |
| `POST` | `/api/v1/order-payments` | `{order_id, method, sender_number?, transaction_id?, amount}` | অ্যাডমিন হাতেও রেকর্ড করতে পারবে (যেমন ফোনে কনফার্ম করে) |
| `PUT` | `/api/v1/order-payments/{id}/verify` | — | `status = Verified`, `verified_by/at` বসে, **`CustomerReceive` ভাউচার পোস্ট হয়** (§৮), `orders.advance_paid` বাড়ে |
| `PUT` | `/api/v1/order-payments/{id}/reject` | `{note?}` | `status = Rejected` — ভাউচার তৈরি হয় না; কাস্টমারকে জানাতে হবে TrxID ভুল/না মেলা |

`verify` এ TrxID ডুপ্লিকেট চেক থাকবে (`UNIQUE (method, transaction_id)`
যোগ করা যায় যদি একই TrxID দুইবার ভুলে/জালিয়াতিতে জমা পড়া ঠেকাতে হয় —
v1 এ ঐচ্ছিক, ম্যানুয়াল ভেরিফায়ারই আসল রক্ষাকবচ)।

---

## ৮. হিসাবে সংযোগ

ভাউচারের ডেবিট/ক্রেডিট **নাম** [05-voucher.md](05-voucher.md) §৪ এ আগে থেকেই
লেখা আছে — এই সেকশন শুধু **কখন** (কোন স্ট্যাটাস ট্রানজিশনে) সেগুলো পোস্ট হয়
সেটা অর্ডার-নির্দিষ্টভাবে বলে দেয়, প্লাস একটা নতুন মিসিং কেস (advance
payment) যোগ করে।

### bKash/Nagad অগ্রিম ভেরিফাই হলে (Shipped এর আগে)

`AutoLedger::AdvanceFromCustomer` (id 21, Current Liabilities) — এতদিন
enum-এ সংজ্ঞায়িত থাকলেও কোথাও ব্যবহার হয়নি ([app/Enum/AutoLedger.php](../app/Enum/AutoLedger.php))।
এই ঠিক এই দরকারে বানানো — অর্ডার তৈরির **আগেই** টাকা এসে গেলে সেটা কাস্টমারের
Accounts Receivable লেজারে না বসিয়ে আলাদা দায় (liability) হিসেবে রাখা হয়,
কারণ তখনো কোনো Sale ভাউচার নেই যার বিপরীতে বসবে:

```php
Voucher::create(
    VoucherType::CustomerReceive,
    $payment['amount'],
    LedgerAccounts::systemLedger(AutoLedger::MobileBanking), // Dr
    LedgerAccounts::systemLedger(AutoLedger::AdvanceFromCustomer), // Cr
    time(),
    'Advance ' . $payment['method_label'] . ' — ' . $order['code'],
    (string) $order['id']
);
```

### `Shipped` এ যাওয়ার সময়

```php
$dr = $order['payment_method'] === PaymentMethod::Cod
    ? AutoLedger::CodReceivable
    : AutoLedger::AdvanceFromCustomer;   // অগ্রিম দিয়ে থাকলে সেই দায় ক্লিয়ার হয়

Voucher::createCompound(VoucherType::Sale, [
    ['ledger_id' => LedgerAccounts::systemLedger($dr), 'debit' => $order['grand_total'], 'credit' => 0],
    ['ledger_id' => LedgerAccounts::systemLedger(AutoLedger::Sales),
        'debit' => 0, 'credit' => $order['sub_total'] - $order['discount_total']],
    ['ledger_id' => LedgerAccounts::systemLedger(AutoLedger::ShippingIncome),
        'debit' => 0, 'credit' => $order['delivery_fee']],
], $order['shipped_at'], 'Order ' . $order['code'], (string) $order['id']);

Voucher::create(
    VoucherType::CostOfGoodsSold,
    array_sum(array_map(fn ($i) => $i['cost_price'] * $i['qty'], $items)),
    LedgerAccounts::systemLedger(AutoLedger::CostOfGoodsSold), // Dr
    LedgerAccounts::systemLedger(AutoLedger::Inventory),       // Cr
    $order['shipped_at'], 'COGS ' . $order['code'], (string) $order['id']
);

foreach ($items as $item) {
    StockService::move(
        $item['product_id'], $item['variant_id'], -$item['qty'],
        StockChangeType::Sale, $item['id'], $order['shipped_at']
    );
}
```

`discount_total = 0` থাকলে (v1 ডিফল্ট) Sales লাইনে পুরো `sub_total`ই যায়;
কুপন/ভাউচার-কোড ফিচার পরে এলে `SaleDiscount` (type 13, Dr SalesDiscount /
Cr একই ডেবিট লেজার) এই compound এই একটা তৃতীয় লাইন হিসেবে যোগ হবে —
স্কিমার `discount_total` কলাম সেই দিনের জন্যই এখন থেকে রাখা।

### `Returned` এ যাওয়ার সময়

```php
Voucher::create(VoucherType::SaleReturn, $order['grand_total'],
    LedgerAccounts::systemLedger(AutoLedger::SalesReturn), // Dr
    LedgerAccounts::systemLedger($dr), // Cr — একই লেজার যেখানে Sale এ Dr হয়েছিল
    time(), 'Return ' . $order['code'], (string) $order['id']);

Voucher::create(VoucherType::SaleReturn, $totalCost,
    LedgerAccounts::systemLedger(AutoLedger::Inventory),       // Dr
    LedgerAccounts::systemLedger(AutoLedger::CostOfGoodsSold), // Cr
    time(), 'Return stock ' . $order['code'], (string) $order['id']);

foreach ($items as $item) {
    StockService::move(..., +$item['qty'], StockChangeType::SaleReturn, ...);
}
```

### `Cancelled` — কোনো ভাউচার না

`Shipped` এর আগে বাতিল মানে স্টক কমেনি, বিক্রিও হয়নি হিসাবের ভাষায় —
`Voucher::deleteByReference()` কল করারও দরকার নাই, কারণ কিছু তৈরিই হয়নি।
bKash/Nagad অগ্রিম নেওয়া থাকলে সেটা আলাদাভাবে `CustomerRefund` দিয়ে
ফেরত দিতে হবে (Dr `AdvanceFromCustomer` / Cr `MobileBanking` বা `Cash`) —
এই ডকের স্কোপের বাইরে না হলেও v1 এ অ্যাডমিন ম্যানুয়াল ভাউচার দিয়েও সারতে
পারে (`VoucherType::CustomerRefund` ইতিমধ্যেই manual না, তাই এটাও
`OrderService`/`PaymentApi` তেই একটা ছোট এন্ডপয়েন্ট — বিস্তারিত ডিজাইন
পরের ধাপে, এখন শুধু জায়গা রাখা হলো)।

---

## ৯. HTTP API — অ্যাডমিন (`guard: admin`)

| Method | URI | কী |
|---|---|---|
| `GET`/`POST`/`PUT`/`DELETE` | `/api/v1/delivery-zones[/{id}]` | জোন CRUD — `name`, `fee`, `free_delivery_threshold`, `is_default?`, `sort_order?`; ডিলিট হয় না কোনো অর্ডার এই জোন ব্যবহার করলে |
| `GET` | `/api/v1/orders` | ফিল্টার: `status`, `customer_id`, `payment_method`, `from`, `to`, `code`, `page`, `per_page` |
| `GET` | `/api/v1/orders/pending` | শুধু `Pending`, পুরোনোটা আগে (পেজিনেশন ছাড়া — ছোট queue) — `/admin/orders/pending` এই কল করে |
| `GET` | `/api/v1/orders/{id}` | পূর্ণ — আইটেম, পেমেন্ট, স্ট্যাটাস লগ, `status_label`, `next_states` (কোন বাটন দেখাবে সেটা এনাম থেকেই ঠিক হয়) |
| `POST` | `/api/v1/orders/{id}/approve` | `{note?}` — **শুধু `Pending` থেকে**, `→ Confirmed` (§৫ "অর্ডার অনুমোদন") |
| `POST` | `/api/v1/orders/{id}/reject` | `{note}` (বাধ্যতামূলক) — **শুধু `Pending` থেকে**, `→ Cancelled`, কোনো ভাউচার/স্টক ছোঁয় না |
| `PUT` | `/api/v1/orders/{id}/status` | `{status, note?}` — `Confirmed` এর পরের ধাপগুলোর জন্য (Processing/Shipped/Delivered/Returned) — ট্রানজিশন যাচাই (§৫) + ভাউচার পোস্ট (§৮) |
| `GET` | `/api/v1/orders/{id}/payments` | §৭ |
| `POST` | `/api/v1/order-payments` | §৭ |
| `PUT` | `/api/v1/order-payments/{id}/verify` | §৭ |
| `PUT` | `/api/v1/order-payments/{id}/reject` | §৭ |
| `GET`/`POST`/`PUT`/`DELETE` | `/api/v1/customers[/{id}]` | পরিকল্পনার চেয়ে বেশি — শুধু GET না, পুরো CRUD (Supplier এর প্যাটার্নে) যাতে অ্যাডমিন ফোন-অর্ডার কাস্টমার হাতেও যোগ/এডিট করতে পারে, opening balance দিলে `OpeningCustomer` ভাউচার পোস্ট হয়; `GET .../{id}` এ প্রোফাইল + `ledger_balance` (`LedgerStatement::balance()`) — অর্ডার হিস্টোরি এখনো নাই (`orders` ফিল্ড খালি রাখা আছে) |

`approve`/`reject` ইচ্ছাকৃতভাবে `status` এন্ডপয়েন্ট থেকে আলাদা —
জেনেরিক `PUT .../status` এ `{status: 2}` পাঠিয়েও একই কাজ হতো, কিন্তু
এটা admin UI তে ভুল-চাপে বাতিল/অনুমোদন হয়ে যাওয়ার ঝুঁকি রাখে (dropdown
থেকে ভুল ভ্যালু), যেখানে এই দুটো এন্ডপয়েন্ট শুধু নির্দিষ্ট দুটো বড়,
স্বতন্ত্র বাটনের (§৫) পেছনে বসবে — **Reject এ `note` বাধ্যতামূলক**
(কেন বাতিল সবসময় লেখা থাকা উচিত, রিপোর্ট/ট্রেন্ড দেখতে কাজে লাগবে)।

`DELETE /orders/{id}` **নাই** — অর্ডার হার্ড-ডিলিট হয় না, শুধু
`Cancelled`/`Returned` স্ট্যাটাসে যায় (ঠিক ভ্যারিয়েন্টের মতোই,
[07-catalog.md](07-catalog.md) — ইতিহাস কখনো হারানো যাবে না)।

### অ্যাডমিন পেজ

| পেজ | কী করা যায় |
|---|---|
| `/admin/orders/pending` | **অ্যাডমিনের হোম-বেস** — শুধু `Pending` অর্ডার, পুরোনোটা আগে; প্রতিটা রো তে ফোন/ঠিকানা/আইটেম + বড় **Approve**/**Reject** বাটন |
| `/admin/orders` | সব অর্ডার, ফিল্টার (স্ট্যাটাস, পেমেন্ট মেথড, তারিখ, কোড) |
| `/admin/orders/{id}` | ডিটেইল — আইটেম, ডেলিভারি ঠিকানা, পেমেন্ট (bKash/Nagad হলে ভেরিফাই/রিজেক্ট বাটন এখানেই), স্ট্যাটাস টাইমলাইন, `next_states` থেকে গড়া পরের ধাপের বাটন |
| `/admin/delivery-zones`, `.../create`, `.../{id}/edit` | জোন তালিকা + ফি/থ্রেশহোল্ড/ডিফল্ট এডিট (Supplier এর প্যাটার্নে list+form আলাদা পেজ) |
| `/admin/customers`, `.../create`, `.../{id}/edit` | তালিকা (সার্চ), এডিট পেজে লেজার ব্যালেন্স দেখা যায় |

---

## ১০. মডিউল কাঠামো — ✅ বিল্ড হয়ে গেছে

```
app/Modules/Sale/
├── Models/       Customer · Cart · CartItem · DeliveryZone
│                 Order · OrderItem · OrderStatusLog · OrderPayment
├── Services/     CustomerService     — save() / findOrCreateByPhone() / delete() / list()
│                 CartService         — resolveCart() / details() / addItem() / updateItemQty() / removeItem() / clear()
│                 DeliveryZoneService — save() / delete() / list()
│                 OrderService        — checkout() / approve() / reject() / changeStatus() / pendingQueue()
│                                       / list() / details() / detailsByCodeAndPhone() + ভাউচার পোস্ট (private)
│                 PaymentService      — record() / verify() / reject()
├── Controllers/  StorefrontController — home/collection/product/cart/checkout/trackOrder (পাবলিক পেজ, একটাই কন্ট্রোলার
│                                        Catalog+Sale দুই মডিউলের Api কল করে — পাবলিক সাইট কোনো একটা মডিউলের না)
│                 OrderController · DeliveryZoneController · CustomerController (অ্যাডমিন পেজ)
├── Api/          CartApi · CartTokenResolver (কুকি/হেডার থেকে token রেজলভ — CartApi ও CheckoutApi দুটোতেই লাগে)
│                 CheckoutApi · OrderApi · PaymentApi · DeliveryZoneApi · CustomerApi
└── Views/
    ├── storefront/  home · collection · product · cart · checkout · track · not-found · _product-card (partial)
    ├── order/       pending-list · list · show
    ├── delivery-zone/ list · form
    └── customer/    list · form
```

`resources/views/layouts/storefront.php` — পাবলিক সাইটের লেআউট (প্রোমো বার,
নেভ, কার্ট ব্যাজ, ফুটার), ঠিক `layouts/admin.php` এর মতোই কোনো নির্দিষ্ট মডিউলের
না, `resources/views/layouts/` এ।

Catalog মডিউলে যোগ হয়েছে (নতুন মডিউল না — বিদ্যমান ক্লাসেই মেথড, যেমন
পরিকল্পনা করা হয়েছিল):

```
app/Modules/Catalog/Api/CategoryApi.php    + publicTree() / publicShow()
app/Modules/Catalog/Api/ProductApi.php     + publicIndex() / publicListResponse() / section() / publicShowBySlug()
app/Modules/Catalog/Services/ProductService.php  + search() এ sort/on_sale ফিল্টার (applySort() প্রাইভেট মেথড)
```

`database/schema/005_order.sql` — এই ডকের সব টেবিল (§২–৭), `App\Enum\OrderStatus`
/ `PaymentMethod` / `PaymentStatus` নতুন এনাম ফাইল (`canTransitionTo()`/`nextStates()`
সহ — §৫ এর গ্রাফটাই এনামে এনকোড করা), `App\Enum\StockChangeType::Sale`
(value 2, আগে থেকেই সংজ্ঞায়িত, [08-purchase.md](08-purchase.md) §৩ — "ফেজ ৫"
কমেন্টটা এই ফেজেই বাস্তবায়িত হলো)।

`app/Core/Menu.php` — নতুন "Sale" গ্রুপ: Pending Orders, Orders, Customers,
Delivery Zones।

---

## ১১. সিদ্ধান্ত

| # | সিদ্ধান্ত | কেন |
|---|---|---|
| O-01 | ডেলিভারি জোন ডেটা-চালিত টেবিল, হার্ডকোড ২টা `if` না | নতুন জোন/ফি বদল ডিপ্লয় ছাড়াই অ্যাডমিন থেকে |
| O-02 | জোন সিলেকশন v1-এ ২-অপশন রেডিও, জেলা ড্রপডাউন না | ওভার-ইঞ্জিনিয়ারিং এড়ানো — রেফারেন্স সাইটগুলোও এভাবেই করে |
| O-03 | গেটওয়ে ইন্টিগ্রেশন না — TrxID হাতে টাইপ, অ্যাডমিন হাতে ভেরিফাই | মার্চেন্ট অ্যাকাউন্ট/অ্যাপ্রুভাল এখনো নাই; গেটওয়ে ফেজ ৫ এ |
| O-04 | v1-এ অর্ডার হয় পুরো COD নয়তো পুরো অগ্রিম bKash/Nagad — আংশিক না | স্কোপ ছোট রাখা; স্কিমা (একাধিক `order_payments` রো) আংশিক পেমেন্টের জন্য প্রস্তুত, শুধু ভ্যালিডেশন পরে শিথিল করলেই চলবে |
| O-05 | bKash/Nagad অগ্রিম আগে `AdvanceFromCustomer` (liability) এ বসে, Sale ভাউচারের সময় সেটাই Dr হয়ে ক্লিয়ার হয় | Sale ভাউচার তৈরির আগে টাকা এলে সেটা Accounts Receivable এ বসানো ভুল (তখনো কোনো বিক্রিই নেই হিসাবের ভাষায়); এনাম আগে থেকেই ছিল, ব্যবহার শুরু হলো |
| O-06 | স্টক/ভাউচার `Pending`-এ না, `Shipped`-এ পোস্ট হয় | `Pending`/`Confirmed` এ বাতিল হওয়া অর্ডার অনেক — প্রতিটায় স্টক ওঠানামা আর ভাউচার তৈরি-রিভার্স করা অপ্রয়োজনীয় |
| O-07 | `order_items` এ নাম/দাম/cost সব স্ন্যাপশট, লাইভ `products` রেফারেন্স না | রসিদ কখনো বদলাবে না, এমনকি প্রোডাক্ট পরে এডিট/ডিলিট হলেও — `purchase_items` এর একই নীতি |
| O-08 | গেস্ট-ফার্স্ট চেকআউট, `customers.phone` দিয়ে ডিডুপ | COD-নির্ভর বাজারে লগইন-বাধ্যতা মানে কার্ট abandonment; ফোন নাম্বার প্রাকৃতিক ইউনিক আইডি |
| O-09 | কার্টে দাম জমা থাকে না, সবসময় লাইভ `effectivePrice()` | অফার শুরু/শেষ হলে কার্টে সাথে সাথে প্রতিফলিত হবে; চেকআউটেই আসল স্ন্যাপশট নেয় |
| O-10 | রেফারেন্স সাইটের "fake original price" প্যাটার্ন কপি হবে না — শুধু কার্ড/PDP লেআউট | ভোক্তা অধিকার আইন সমস্যা; `offer_price` সবসময় genuine discount |
| O-11 | `Pending → Confirmed` একটা আলাদা **Approve/Reject** এন্ডপয়েন্ট জোড়া, জেনেরিক status-বদল না; এটাই COD অর্ডারের একমাত্র ফ্রড-বিরোধী গেট | COD এ ভুয়া/ভুল-নাম্বার অর্ডার সাধারণ — প্যাকিং/কুরিয়ারের আগে ফোনে মানুষ-যাচাই ছাড়া রিটার্ন-লস বাড়ে; ব্যবহারকারীর সরাসরি অনুরোধ (২০২৬-০৯-১০) |
| O-12 | Reject এ `note` বাধ্যতামূলক, Approve এ ঐচ্ছিক | বাতিলের কারণ সবসময় ট্র্যাক করা দরকার (রিপোর্টিং/প্যাটার্ন দেখতে — কোন এলাকায় ভুয়া অর্ডার বেশি ইত্যাদি) |
| O-13 | `carts`/`cart_items`/`order_payments` এ চারটাই audit কলাম (`created/updated_at/by`), কিন্তু `order_items`/`order_status_log` এ audit কলামই নাই — insert `DB::insert()` দিয়ে সরাসরি, `Model::create()` দিয়ে না | `Model::create()`/`updateById()` সবসময় চারটা কলাম স্ট্যাম্প করতে চায় — যে টেবিল বদলাতে পারে (cart, payment) তার দরকার আছে, যেটা শুধু append-only স্ন্যাপশট/লগ তার দরকার নাই (`purchase_items`/`stock_ledger` এর একই প্যাটার্ন) — বিল্ডের সময় "Unknown column" এরর দিয়ে ধরা পড়েছিল, schema-তে ঠিক করা হয়েছে |
| O-14 | `ProductApi::publicListResponse(array $filters)` ফিল্টার আর্গুমেন্ট নেয়, `Request` পড়ে না; `publicIndex()` (HTTP) আর `section()` (হোমপেজ ব্লক) দুটোই এটাকে কল করে | হোমপেজে "New Arrivals"/"Bestsellers" একই রিকোয়েস্টে ভিন্ন ফিল্টারে লাগে — `Request::all()` এক রিকোয়েস্টে একবারই মূল্যায়িত হয়, তাই Request-নির্ভর মেথড দ্বিতীয়বার ভিন্ন ফিল্টারে কল করা যেত না |
| O-15 | প্রোডাক্ট কার্ডের CSS partial-এ (`_product-card.php`) না, লেআউটে (`storefront.php`) একবার | partial একটা লিস্টিং পেজে N বার রেন্ডার হয় (প্রতি প্রোডাক্টে একবার) — `<style>` ভেতরে রাখলে পেজে N বার ডুপ্লিকেট হতো (বিল্ডের সময় ধরা পড়েছিল — ৩-৪টা প্রোডাক্টে ৫২টা `p-card` স্ট্রিং!) |
| O-16 | কাস্টমার/ডেলিভারি-জোনের অ্যাডমিন API পরিকল্পনার চেয়ে বেশি — শুধু GET না, পুরো CRUD | Supplier-এর মতোই অ্যাডমিন হাতে ফোন-অর্ডার কাস্টমার/নতুন জোন যোগ করতে পারবে, না হলে ছোট সংশোধনের জন্যও DB-তে হাত দিতে হতো |

---

## ১২. বিল্ড ক্রম — ✅ সম্পূর্ণ

প্রস্তাবিত ক্রম অনুযায়ীই বিল্ড হয়েছে ([08-purchase.md](08-purchase.md) এর ধরনে):

1. ✅ `schema/005_order.sql` + `OrderStatus`/`PaymentMethod`/`PaymentStatus` enum
2. ✅ **DeliveryZone** — মডেল/সার্ভিস/CRUD
3. ✅ **Customer** — dedup + lazy ledger (Supplier প্যাটার্ন কপি) + পূর্ণ অ্যাডমিন CRUD
4. ✅ **Cart** — টোকেন কুকি (`CartTokenResolver`), লাইভ pricing
5. ✅ Catalog এ `publicTree()`/`publicShow()`/`publicIndex()`/`publicShowBySlug()` — storefront ব্রাউজিং
6. ✅ **OrderService::checkout()** — কার্ট → অর্ডার, স্টক এখনো না কমা
7. ✅ **OrderService::approve()/reject()** + `changeStatus()` + ভাউচার পোস্ট (§৮)
8. ✅ **PaymentService** — রেকর্ড/ভেরিফাই/রিজেক্ট
9. ✅ অ্যাডমিন ভিউ — `/admin/orders/pending` (Approve/Reject), অর্ডার লিস্ট/ডিটেইল, পেমেন্ট ভেরিফাই, ডেলিভারি জোন/কাস্টমার CRUD
10. ✅ স্টোরফ্রন্ট ভিউ — হোম, কালেকশন গ্রিড (§০ রেফারেন্স), PDP (সাইজ/রঙ সিলেক্টর), কার্ট, চেকআউট, অর্ডার ট্র্যাকিং

### যাচাই

প্রতিটা ধাপে `install.php` (idempotent) + ট্রায়াল ব্যালেন্স (`SUM(debit) =
SUM(credit)`) চেক করা হয়েছে ([08-purchase.md](08-purchase.md) এর মতোই), প্লাস
আসল HTTP রিকোয়েস্ট দিয়ে (Api ক্লাস সরাসরি কল করে, cookie/session সহ curl দিয়ে)
পুরো কাস্টমার জার্নি এন্ড-টু-এন্ড:

- **COD অর্ডার** — checkout → approve → Processing → Shipped (Sale compound
  ভাউচার Dr CodReceivable / Cr Sales + ShippingIncome, ঠিক অঙ্কে; COGS ভাউচার;
  স্টক সঠিক পরিমাণে কমেছে) → Delivered → Returned (টাকা ও স্টক দুটোই ফেরত,
  ট্রায়াল ব্যালেন্স তখনো balanced)
- **bKash অগ্রিম অর্ডার** — payment ভেরিফাই না করে Processing-এ যাওয়ার চেষ্টা
  আটকে গেছে; ভেরিফাই করার পর `CustomerReceive` (Dr MobileBanking / Cr
  AdvanceFromCustomer) পোস্ট হয়ে `Shipped`-এ `AdvanceFromCustomer` ঠিক নেট
  শূন্যে মিলেছে (O-05)
- **Reject flow** — note ছাড়া আটকে গেছে, note দিয়ে Cancelled, কোনো ভাউচার/স্টক
  ছোঁয়নি
- **স্টোরফ্রন্ট** — হোম/কালেকশন/PDP/কার্ট/চেকআউট/ট্র্যাকিং সবগুলো আসল ব্রাউজার
  সেশনের মতো (কুকি persist করে) টেস্ট করা — ভ্যারিয়েন্ট সিলেক্ট করে দাম বদল,
  কার্টে merge, ফ্রি-ডেলিভারি থ্রেশহোল্ড, ভুল ফোনে ট্র্যাকিং না পাওয়া — সব ঠিক
- প্রতি ধাপের টেস্ট ডেটা শেষে DB থেকে মুছে পরিষ্কার রাখা হয়েছে (শুধু ডেমো
  ক্যাটাগরি/প্রোডাক্ট/একটা Pending অর্ডার ইচ্ছাকৃতভাবে রাখা — যাতে অ্যাডমিন
  Approve ফ্লো সরাসরি টেস্ট করা যায়)
