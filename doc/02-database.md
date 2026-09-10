# ০২ — ডেটাবেজ

MySQL 8.0 / InnoDB / `utf8mb4_unicode_ci`.

## সবার জন্য একই কনভেনশন

| জিনিস | নিয়ম | কেন |
|---|---|---|
| প্রাইমারি কি | `id BIGINT UNSIGNED AUTO_INCREMENT` | |
| সময় | `INT UNSIGNED` (unix timestamp) | PHP `time()` এর সাথে সরাসরি খাটে, টাইমজোন ঝামেলা নাই |
| টাকা | `DECIMAL(20,4)` | float এ রাউন্ডিং ভুল হয়; ৪ ঘর কারণ ইউনিট প্রাইস/ভ্যাটে ২ ঘরে গোলমাল হয় |
| অডিট | `created_at, created_by, updated_at, updated_by` | `Utility::stampCreate()` / `stampUpdate()` নিজে বসায় |
| স্ট্যাটাস | `isActive TINYINT` (১ = চালু) | রো ডিলিট না করে বন্ধ করা |
| "নাই" | `0` বা `''`, `NULL` নয় (যেখানে সম্ভব) | `IS NULL` চেকের ঝামেলা কমে |

**`company_id` নাই** — সিঙ্গেল স্টোর (সিদ্ধান্ত D-01)।

---

## ফাইল

```
database/
├── schema/001_core.sql          users, api_tokens, code_sequence, settings
├── schema/002_account.sql       হিসাবের ৮টি টেবিল
├── seed/002_account_auto.sql    সিস্টেম চার্ট টেমপ্লেট
└── install.php                  উপরের সবগুলো ক্রম অনুযায়ী চালায় + অ্যাডমিন বানায়
```

`install.php` idempotent — বারবার চালালেও ডেটা নষ্ট হয় না
(`CREATE TABLE IF NOT EXISTS` + `ON DUPLICATE KEY UPDATE`)।

---

## ১. Core টেবিল

### `users`

অ্যাডমিন, স্টাফ, কাস্টমার — সবাই একই টেবিলে, `type` দিয়ে আলাদা।

| কলাম | টাইপ | মানে |
|---|---|---|
| `name` | VARCHAR(150) | |
| `email` / `phone` | VARCHAR, UNIQUE | দুটোর যেকোনোটা দিয়ে লগইন |
| `password` | VARCHAR(255) NULL | `password_hash()`; গেস্ট চেকআউটে NULL |
| `type` | TINYINT | `1` super_admin · `2` staff · `3` customer |
| `last_login` | INT | |

কেন এক টেবিল: কাস্টমার পরে স্টাফ হতে পারে, আর `created_by` সব জায়গায় একই
`users.id` রেফার করে — দুই টেবিল হলে সেটা ভেঙে যেত।

### `api_tokens`

Bearer টোকেন। `token_hash` = `sha256(plainToken)` — **প্লেইন টোকেন DB-তে যায় না**।
`expires_at = 0` মানে কখনো এক্সপায়ার হবে না।

### `code_sequence`

`voucher`, `ledger_entry`, `order` ইত্যাদির অটো নাম্বার।
`CodeGenerator::next('voucher', 'SL')` → `SL-000123`।

`SELECT ... FOR UPDATE` দিয়ে row লক করে নাম্বার নেওয়া হয়, তাই একসাথে অনেক
রিকোয়েস্ট এলেও ডুপ্লিকেট কোড হয় না।

| কলাম | মানে |
|---|---|
| `name` | সিকোয়েন্সের নাম (UNIQUE) |
| `prefix` | ডিফল্ট প্রিফিক্স; কল করার সময় ওভাররাইড করা যায় |
| `next_number` | পরের নাম্বার |
| `padding` | কত ঘরে শূন্য বসবে |

### `settings`

`key_name` → `key_value` (TEXT)। স্টোরের নাম, ফ্রি ডেলিভারির সীমা (৳3000),
COD চার্জ ইত্যাদি এখানে যাবে।

---

## ২. হিসাবের গঠন

তিন লেভেলের চার্ট, তারপর ডাবল-এন্ট্রি ট্রানজেকশন:

```
a_master_account            লেভেল ১ — Assets / Liabilities / Equity / Income / Expenses
      │
      └── a_chart_of_accounts    লেভেল ২ — Current Assets, Sales Revenue, ...
                │
                └── a_ledgers        লেভেল ৩ — Cash in Hand, "রহিম স্টোর", bKash, ...
                          │
                          └── a_ledger_entry ──── a_voucher_entry
                              (প্রতি লাইনে Dr বা Cr)   (ভাউচার হেডার)
```

পাশাপাশি তিনটা **টেমপ্লেট** টেবিল (`a_auto_*`) — সিস্টেম নিজে চার্ট বানানোর জন্য।

### `a_auto_master_account` / `a_auto_chart_of_accounts` / `a_auto_ledger`

সিড ডেটা, ইউজার এডিট করে না। **এদের `id` কোডের enum ভ্যালুর সাথে হুবহু মেলে:**

| টেবিল | enum |
|---|---|
| `a_auto_master_account.id` | `App\Enum\MasterAccountType` |
| `a_auto_chart_of_accounts.id` | `App\Enum\AutoChart` |
| `a_auto_ledger.id` | `App\Enum\AutoLedger` |

⚠ নতুন এন্ট্রি **সবসময় শেষে নতুন id দিয়ে** যোগ করবেন। মাঝখানের id বদলালে
পুরোনো লেজারগুলো ভুল অ্যাকাউন্টে চলে যাবে।

`a_auto_ledger` এর `for_income` / `for_expense` ফ্ল্যাগ দুটো তৈরি হওয়া লেজারে কপি হয় —
Income/Expense এন্ট্রি ফর্মের ড্রপডাউনে কোনগুলো দেখাবে সেটা ঠিক করে।

### `a_master_account`

| কলাম | মানে |
|---|---|
| `type` | `MasterAccountType`: 1 Asset · 2 Liability · 3 Equity · 4 Income · 5 Expense |
| `system_ref` | `a_auto_master_account.id`; `0` = ইউজার নিজে বানিয়েছে |

`type` থেকেই অ্যাকাউন্টের **nature** আসে —
Asset/Expense → Debit, Liability/Equity/Income → Credit।
ব্যালেন্সের সাইন এই nature দিয়েই ঠিক হয় (নিচে দেখুন)।

### `a_chart_of_accounts`

| কলাম | মানে |
|---|---|
| `master_account_id` | FK → `a_master_account` |
| `system_chart` | `a_auto_chart_of_accounts.id`; `0` = ম্যানুয়াল |

### `a_ledgers`

আসল কাজের জায়গা — ভাউচার এখানেই ডেবিট/ক্রেডিট হয়।

| কলাম | মানে |
|---|---|
| `chart_of_accounts_id` | FK → `a_chart_of_accounts` |
| `type` | `LedgerType` — লেজারটা কার |
| `reference_id` | `type` অনুযায়ী অর্থ বদলায় (নিচের টেবিল) |
| `system_ledger` | `1` হলে ইউজার ডিলিট করতে পারবে না |
| `for_income` / `for_expense` | এন্ট্রি ফর্মের ড্রপডাউনে দেখাবে কি না |

`UNIQUE (type, reference_id)` — এক কাস্টমারের দুটো লেজার হতে পারবে না।

**`reference_id` এর মানে:**

| `LedgerType` | ভ্যালু | `reference_id` |
|---|---|---|
| `Custom` | 1 | `0` — ম্যানুয়ালি বানানো |
| `Supplier` | 2 | `suppliers.id` |
| `Bank` | 3 | `banks.id` |
| `User` | 4 | `users.id` |
| `AutoLedger` | 5 | `a_auto_ledger.id` (সিস্টেম লেজার) |
| `Customer` | 6 | `customers.id` |
| `Courier` | 7 | `couriers.id` |
| `Gateway` | 8 | `payment_gateways.id` |

### `a_voucher_entry` — ভাউচার হেডার

| কলাম | মানে |
|---|---|
| `code` | `CodeGenerator` — `SL-000123`, `JV-000045` |
| `type` | `VoucherType` (২১টি ধরন, দেখুন [05-voucher.md](05-voucher.md)) |
| `reference` | সোর্স ডকুমেন্ট — Sale এ `orders.id`, Purchase এ `purchases.id` |
| `note` | |
| `time` | ভাউচারের **কার্যকর তারিখ** (`created_at` নয়) |

`reference` কলামে কোন টাইপে কী থাকে সেটা `VoucherType::referenceMeaning()` এ লেখা।

### `a_ledger_entry` — ভাউচার ডিটেইল

| কলাম | মানে |
|---|---|
| `voucher_id` | FK → `a_voucher_entry`, **ON DELETE CASCADE** |
| `ledger_id` | FK → `a_ledgers`, **ON DELETE RESTRICT** (লেনদেন থাকলে লেজার মোছা যাবে না) |
| `debit` / `credit` | একটায় ভ্যালু থাকলে অন্যটা `0` |
| `time` | ভাউচারের `time` এর কপি — লেজার রিপোর্টে join এড়াতে |

**অপরিবর্তনীয় নিয়ম:** একটা ভাউচারের সব লাইনের `SUM(debit) = SUM(credit)`।
`Voucher::assertBalanced()` এটা প্রতিবার যাচাই করে; না মিললে exception, কিছুই সেভ হয় না।

সাধারণ ভাউচারে ২টা লাইন, কম্পাউন্ড ভাউচারে যত খুশি।

---

## ব্যালেন্সের সাইন

দুটো আলাদা সংখ্যা, গুলিয়ে ফেলবেন না:

| নাম | সূত্র | মানে |
|---|---|---|
| `raw_balance` | `SUM(debit) − SUM(credit)` | সবসময় ডেবিট-পজিটিভ |
| `balance` | nature অনুযায়ী সাইন ঠিক করা | পজিটিভ = অ্যাকাউন্টের স্বাভাবিক দিক |

`LedgerStatement` দ্বিতীয়টা দেয়। ফলে:

- কাস্টমার লেজারে `balance` পজিটিভ → **সে আমাদের টাকা দেবে**
- সাপ্লায়ার লেজারে `balance` পজিটিভ → **আমরা তাকে টাকা দেব**
- Sales লেজারে `balance` পজিটিভ → **বিক্রি হয়েছে** (যদিও raw নেগেটিভ)

`side` ফিল্ডে `Dr` / `Cr` থাকে, খালি স্ট্রিং মানে ব্যালেন্স শূন্য।

---

## সূচক (index)

যেগুলো ইচ্ছাকৃতভাবে দেওয়া হয়েছে:

| টেবিল | ইনডেক্স | কেন |
|---|---|---|
| `a_voucher_entry` | `(type, reference)` | "এই অর্ডারের ভাউচার আছে কি?" — খুব ঘন কোয়েরি |
| `a_voucher_entry` | `(time)` | তারিখ রেঞ্জ রিপোর্ট |
| `a_ledger_entry` | `(ledger_id, time)` | লেজার স্টেটমেন্ট — সবচেয়ে ভারী কোয়েরি |
| `a_ledgers` | `UNIQUE (type, reference_id)` | ডুপ্লিকেট পার্টি-লেজার ঠেকানো |

---

---

## ৩. ক্যাটালগ টেবিল (ফেজ ২)

`database/schema/003_catalog.sql` — ৯টি টেবিল। পূর্ণ ব্যাখ্যা [07-catalog.md](07-catalog.md) এ।

| টেবিল | কী রাখে | মনে রাখার মতো |
|---|---|---|
| `unit_groups` | Count / Weight / Length | একই গ্রুপের ইউনিটই রূপান্তরযোগ্য |
| `units` | Piece, Dozen, Kg … | `conversion` = ১ এই ইউনিট → কত বেস; প্রতি গ্রুপে একটাই `is_base` |
| `categories` | ক্যাটাগরি গাছ | **`path`** = `/1/3/4/` — এক LIKE কোয়েরিতে পুরো শাখা |
| `attributes` | Size, Color | `code` কোডে রেফার করার জন্য |
| `attribute_values` | S · M · L · XL · XXL / Navy · White … | `code` SKU তে বসে |
| `products` | মূল প্রোডাক্ট | `has_variant` ঠিক করে দাম/স্টক কোথায় |
| `product_variants` | "Navy / XL" | **`signature`** = সাজানো value id, ডুপ্লিকেট আটকায় |
| `product_variant_values` | ভ্যারিয়েন্ট ↔ ভ্যালু | `product_id` ইচ্ছাকৃতভাবে ডুপ্লিকেট (ফিল্টার দ্রুত হয়) |
| `product_images` | গ্যালারি | `variant_id > 0` হলে ওই ভ্যারিয়েন্টের ছবি |

দুটো নিয়ম কখনো ভাঙবেন না:

1. **`categories.path` হাতে লিখবেন না** — `CategoryService` বসায়, প্যারেন্ট বদলালে
   পুরো শাখা নতুন করে হিসাব করে।
2. **ভ্যারিয়েন্ট হার্ড-ডিলিট নয়** — `isActive = 0`। মুছে ফেললে পুরোনো অর্ডারে
   "কোন সাইজ ছিল" হারিয়ে যাবে।

---

## ৪. ক্রয় ও স্টক টেবিল (ফেজ ৪)

`database/schema/004_purchase.sql` — ৯টি টেবিল। পূর্ণ ব্যাখ্যা [08-purchase.md](08-purchase.md) এ।

| টেবিল | কী রাখে | মনে রাখার মতো |
|---|---|---|
| `suppliers` | সাপ্লায়ার | `ledger_id` = Accounts Payable লেজারের ক্যাশ (lazy) |
| `purchases` / `purchase_items` | ক্রয়ের হেডার / লাইন | `total = sub_total − discount` (VAT/AIT নাই) |
| `stock_ledger` | **স্টকের সত্যের উৎস** | `qty` সাইনড, বেস ইউনিটে; append-only |
| `stock_adjustments` / `_items` | ওপেনিং / নষ্ট / গণনা | `qty` সাইনড; reason ১=Opening ২=Damage ৩=Count |
| `purchase_returns` / `_items` | ক্রয় ফেরত | `purchase_id` রেফার করে |
| `product_price_log` | weighted-avg cost বদলের ইতিহাস | append-only |

`products.stock` / `product_variants.stock` হাতে বসে না — `StockService` এর
ক্যাশড মান, `stock_ledger` থেকে হিসাব। `purchase_price` = moving weighted-average,
`CostService` বসায়।

---

## ৫. স্টোরফ্রন্ট ও অর্ডার টেবিল (ফেজ ৩)

`database/schema/005_order.sql` — ৮টি টেবিল। পূর্ণ ব্যাখ্যা [10-storefront-order.md](10-storefront-order.md) এ।

| টেবিল | কী রাখে | মনে রাখার মতো |
|---|---|---|
| `delivery_zones` | ঢাকার ভিতরে/বাইরে | ডেটা-চালিত — নতুন জোন অ্যাডমিন থেকেই যোগ হয় |
| `customers` | কাস্টমার | `phone` দিয়ে গেস্ট-ডিডুপ; `ledger_id` = Accounts Receivable লেজারের ক্যাশ (lazy, suppliers এর একই প্যাটার্ন) |
| `carts` / `cart_items` | গেস্ট কার্ট | `token` (কুকি) দিয়ে চেনা; দাম এখানে জমা থাকে **না** — সবসময় লাইভ `effectivePrice()` |
| `orders` | অর্ডার হেডার | `status` = `OrderStatus`; ঠিকানা/দাম/জোন-নাম সব checkout-মুহূর্তের স্ন্যাপশট |
| `order_items` | অর্ডার লাইন | প্রোডাক্ট নাম/দাম/cost স্ন্যাপশট — audit কলাম নাই (`purchase_items` এর একই প্যাটার্ন, অপরিবর্তনীয় লাইন) |
| `order_status_log` | স্ট্যাটাস অডিট ট্রেইল | append-only — `created_at`/`created_by` আছে, `updated_at`/`by` নাই (`stock_ledger` এর প্যাটার্ন) |
| `order_payments` | ম্যানুয়াল bKash/Nagad রেফারেন্স | `status` = `PaymentStatus`; verify হলে `CustomerReceive` ভাউচার পোস্ট হয় |

⚠ **audit কলামের নিয়ম** — এই ফেজে একটা ভুল থেকে শেখা: `Model::create()`/`updateById()`
সবসময় চারটা কলামই (`created_at/by`, `updated_at/by`) স্ট্যাম্প করতে চায়। যে টেবিল
সত্যিই বদলাতে পারে (এখানে `carts`, `cart_items`, `order_payments`) তার চারটাই লাগবে;
যেটা শুধু append-only লগ/স্ন্যাপশট (`order_items`, `order_status_log`) সেখানে
`Model::create()` না, সরাসরি `DB::insert()` ব্যবহার করতে হবে — নাহলে "Unknown column"
এরর।

ফেজ ৫ এ যোগ হবে (গেটওয়ে, কুরিয়ার সেটেলমেন্ট, রিটার্ন/রিফান্ড আলাদা ফর্ম):

```
shipments, couriers, payment_gateways, refunds, returns
```

`orders`, `purchases` ইত্যাদির সাথে হিসাবের যোগসূত্র `a_voucher_entry.reference` —
আলাদা কোনো FK নয়, কারণ এক অর্ডার থেকে একাধিক ভাউচার (Sale, COGS, Shipping) তৈরি হয়।
