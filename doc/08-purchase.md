# ০৮ — পারচেস ও স্টক (ফেজ ৪)

> **মূল নিয়ম:** প্রোডাক্টে স্টক হাতে বসানো যায় না। স্টক বাড়ে শুধু **Purchase**
> (এবং Stock Adjustment / ওপেনিং) থেকে, কমে **Sale / Sale Return নয় এমন**
> আউট-মুভমেন্ট থেকে। `products.stock` / `product_variants.stock` হলো ক্যাশ করা
> মান — সত্যের উৎস `stock_ledger`।

রেফারেন্স বাস্তবায়ন: `panacea` এর `operation/purchase` + `class/somiti.php`
(weighted-average cost, signed stock log, save-এর সময় ভাউচার)। এখানে সেই
business logic-ই ecommerce-এর মডিউল/`DB`/`Voucher` স্টাইলে।

---

## ১. গঠন এক নজরে

```
suppliers ──< purchases ──< purchase_items
                  │               │  (product_id, variant_id, unit_id, qty, unit_price)
                  │               └──> StockService::move(+qty)  ──> stock_ledger (change_type = Purchase)
                  │                                                        │
                  │                                              cached: products.stock / product_variants.stock
                  │
                  └──> Voucher::create(Purchase)  ── Dr Inventory (7) / Cr Supplier ledger

stock_adjustments ──< stock_adjustment_items ──> stock_ledger (Opening / Adjust)
purchase_returns  ──< purchase_return_items  ──> stock_ledger (PurchaseReturn, -qty)
```

---

## ২. নতুন টেবিল — `database/schema/004_purchase.sql`

সব টেবিলে প্রজেক্টের সাধারণ কনভেনশন ([02-database.md](02-database.md)):
`id BIGINT UNSIGNED`, সময় `INT UNSIGNED`, টাকা `DECIMAL(20,4)`, স্টক/qty
`DECIMAL(20,4)`, অডিট `created_at/by, updated_at/by`, `isActive TINYINT`।

### `suppliers`

| কলাম | মানে |
|---|---|
| `code` | `SUP-000123` — `CodeGenerator` |
| `name`, `phone`, `email`, `address` | |
| `opening_balance` | ইনস্টলের সময়কার বকেয়া; `VoucherType::OpeningSupplier` দিয়ে পোস্ট হয় |
| `ledger_id` | FK → `a_ledgers` (এই সাপ্লায়ারের লেজার); `LedgerAccounts::supplierLedger()` বসায় |
| `isActive` | |

### `purchases` — হেডার

| কলাম | মানে |
|---|---|
| `code` | `PU-000123` — `CodeGenerator::next('purchase', 'PU')` |
| `supplier_id` | FK → `suppliers` |
| `supplier_invoice_no` | সাপ্লায়ারের বিল নং |
| `invoice_date` | বিলের কার্যকর তারিখ (unix) — ভাউচার ও stock_ledger এই সময় নেয় |
| `challan_no`, `po_no` | ঐচ্ছিক রেফারেন্স |
| `sub_total` | Σ `purchase_items.line_total` |
| `discount` | invoice-লেভেল ডিসকাউন্ট |
| `total` | `sub_total − discount` (VAT/AIT নাই — [সিদ্ধান্ত](#১৪-সিদ্ধান্ত)) |
| `status` | `1` = posted (ড্রাফট আপাতত নাই) |
| `note` | |

### `purchase_items` — লাইন

| কলাম | মানে |
|---|---|
| `purchase_id` | FK → `purchases`, ON DELETE CASCADE |
| `product_id` | FK → `products` |
| `variant_id` | `0` = সিম্পল প্রোডাক্ট; নইলে FK → `product_variants` |
| `unit_id` | যে ইউনিটে কেনা হলো (FK → `units`) |
| `qty` | ইনপুট ইউনিটে পরিমাণ |
| `qty_base` | বেস ইউনিটে রূপান্তরিত (`qty × units.conversion`) — স্টকে এটাই যায় |
| `unit_price` | প্রতি **ইনপুট ইউনিটে** ল্যান্ডেড ক্রয়মূল্য |
| `line_total` | `qty × unit_price` |

### `stock_ledger` — স্টক খতিয়ান (panacea `product_stock_log`)

| কলাম | মানে |
|---|---|
| `product_id` | |
| `variant_id` | `0` = সিম্পল |
| `change_type` | `App\Enum\StockChangeType` (নিচে) |
| `reference_id` | সোর্স রো-র id — `purchase_items.id`, `stock_adjustment_items.id`, ভবিষ্যতে `order_items.id` … |
| `qty` | **সাইনড, বেস ইউনিটে** — ইন `+`, আউট `−` (সাইন `change_type` অনুযায়ী বসে) |
| `rate` | ওই মুভমেন্টের প্রতি-বেস-ইউনিট খরচ (valuation রিপোর্টের জন্য) |
| `action_time` | কার্যকর তারিখ — "এই তারিখ পর্যন্ত স্টক কত" হিসাবের ভিত্তি |
| `entry_time` | রো তৈরির সময় |

ইনডেক্স: `(product_id, variant_id, action_time)`, `(change_type, reference_id)`।

### `stock_adjustments` + `stock_adjustment_items`

ওপেনিং স্টক, নষ্ট/হারানো, ফিজিক্যাল গণনা-মিলকরণ। `reason TINYINT`
(`1` Opening · `2` Damage · `3` Count correction)। পোস্ট হলে `stock_ledger`-এ
`Opening` বা `Adjust` রো + `VoucherType::StockAdjustment` ভাউচার
(Dr/Cr Inventory ↔ Stock Adjustment ledger, লাভ/ক্ষতির দিক অনুযায়ী)।

### `purchase_returns` + `purchase_return_items`

`purchase_id` রেফার করে (কোন কেনা থেকে ফেরত)। পোস্ট হলে `stock_ledger`-এ
`PurchaseReturn` (`−qty`) + `VoucherType::PurchaseReturn` ভাউচার
(Dr Supplier / Cr Inventory)।

---

## ৩. `App\Enum\StockChangeType`

panacea `ST_CH_*` এর সাবসেট। ভ্যালু কখনো বদলাবে না, নতুন শেষে যোগ হবে।

| case | value | সাইন | সোর্স |
|---|---|---|---|
| `Purchase` | 1 | `+` | `purchase_items` |
| `Sale` | 2 | `−` | `order_items` (ফেজ ৫) |
| `SaleReturn` | 3 | `+` | `order_return_items` (ফেজ ৫) |
| `PurchaseReturn` | 4 | `−` | `purchase_return_items` |
| `Opening` | 5 | `+` | `stock_adjustment_items` |
| `Adjust` | 6 | `+/−` | `stock_adjustment_items` |

---

## ৪. স্টক কীভাবে জমা হয় — কলাম + খতিয়ান (ক্যাশড)

`StockService` একমাত্র জায়গা যেখানে স্টক বদলায়।

```php
StockService::move(
    int $productId,
    int $variantId,          // 0 = simple
    float $qtyBase,          // signed, base unit
    StockChangeType $type,
    int $referenceId,
    int $actionTime,
    float $rate = 0.0
): void
```

`move()` যা করে:

1. `stock_ledger`-এ এক রো ইনসার্ট (সাইন `type` অনুযায়ী)।
2. ক্যাশড স্টক নতুন করে হিসাব করে বসায় —
   `onHand = SUM(qty) FROM stock_ledger WHERE product_id=? AND variant_id=? AND action_time <= +5yr`
   (panacea `productClosingStock` + `update_product_closing_stock`)।
   - সিম্পল হলে → `products.stock`
   - ভ্যারিয়েন্ট হলে → `product_variants.stock`, এবং `products.stock` = ওই
     প্রোডাক্টের সব ভ্যারিয়েন্টের যোগফল ([07-catalog.md](07-catalog.md))।

স্টক সবসময় **বেস ইউনিটে** জমা — অন্য ইউনিটে কিনলে `qty_base` আগে বের করে তবেই।

> রিপোর্ট: "১লা তারিখে স্টক কত" = `SUM(qty) WHERE action_time <= ওই তারিখ`।
> ক্যাশড কলাম শুধু দ্রুত লিস্টিং/ফিল্টারের জন্য, হিসাবের ভিত্তি খতিয়ান।

---

## ৫. Weighted-average cost

প্রতি **স্টক-ইন লাইনে** (Purchase, Opening, বা বাড়তি Adjust) `products` /
`product_variants` এর `purchase_price` নতুন করে হিসাব হয় (panacea
`purchaseAdd.php` line 112):

```
new_cost = ( (old_stock × old_cost) + (qty_base × rate_base) )
           / (old_stock + qty_base)
```

- `rate_base` = প্রতি বেস ইউনিটে ল্যান্ডেড মূল্য = `line_total ÷ qty_base`
- `old_stock ≤ 0` (বা `new_stock ≤ 0`) হলে `new_cost = rate_base`
- ফলাফল `purchase_price` কলামে (৪ ঘর রাউন্ড) — ভবিষ্যতে COGS-এর ভিত্তি
- আউট-মুভমেন্ট (Sale, Purchase Return, ঘাটতি Adjust) cost বদলায় না
- `CostService::applyPurchase()` — move এর **আগে** (old stock লাগে)
- `CostService::rebuild()` — edit/delete এর পর সব `qty > 0` রো `action_time`
  ক্রমে চালিয়ে নতুন করে হিসাব
- প্রতিবার পরিবর্তন `product_price_log` এ:
  `product_id, variant_id, purchase_price, source_type, reference_id, created_at, created_by`

---

## ৬. Purchase save ফ্লো — `PurchaseService::create($data): int`

পুরোটা `DB::transaction()` এর ভেতরে; যেকোনো ধাপে ব্যর্থ হলে সব rollback।

1. **ভ্যালিডেশন** — সাপ্লায়ার আছে ও `isActive`; অন্তত এক লাইন; প্রতি লাইনে
   `qty > 0`, `unit_price ≥ 0`, `product`/`variant` মেলে ও `isActive`;
   `discount` ≥ 0 এবং `≤ sub_total`।
2. **হেডার ইনসার্ট** — `purchases`; `code = CodeGenerator::next('purchase', 'PU')`।
3. **প্রতি লাইনে:**
   a. `qty_base` = `qty × units.conversion`; `line_total` = `qty × unit_price`
   b. `purchase_items` ইনসার্ট
   c. `StockService::move(product, variant, +qty_base, Purchase, item_id, invoice_date, unit_price_base)`
   d. `CostService::applyPurchase(product_id, variant_id, qty_base, rate_base, item_id)` → নতুন `purchase_price` + `product_price_log`
4. **হেডার আপডেট** — `sub_total = Σ line_total`, `total = sub_total − discount`।
5. **ভাউচার** ([05-voucher.md](05-voucher.md)):

```php
Voucher::create(
    VoucherType::Purchase,                               // = 4, reference = purchases.id
    $total,
    LedgerAccounts::systemLedger(AutoLedger::Inventory), // Dr — 7
    LedgerAccounts::supplierLedger($supplier),           // Cr — সাপ্লায়ারের পাওনা বাড়ল
    $invoiceDate,
    'Purchase ' . $code,
    (string) $purchaseId
);
```

> **discount:** v1-এ line `unit_price` = ল্যান্ডেড মূল্য (line-লেভেল ছাড় ওখানেই
> ধরে নাও)। invoice-লেভেল `discount` শুধু `total` তথা supplier payable কমায় —
> weighted-average line rate থেকেই হয়, তাই বড় invoice-ছাড় হলে সেটা line
> price-এ পুশ করাই ভালো। প্রো-রাটা ল্যান্ডেড-কস্ট বণ্টন পরের উন্নতি।

---

## ৭. হিসাব — perpetual inventory

| ঘটনা | Debit | Credit | VoucherType |
|---|---|---|---|
| Purchase | Inventory (7) | Supplier ledger | `Purchase` (4) |
| Purchase Return | Supplier ledger | Inventory (7) | `PurchaseReturn` (5) |
| Sale (ফেজ ৫) | COGS (15) | Inventory (7) | `CostOfGoodsSold` (14) |
| Stock Adjustment — ঘাটতি | Stock Adjustment (16) | Inventory (7) | `StockAdjustment` (15) |
| Stock Adjustment — বাড়তি / Opening | Inventory (7) | Stock Adjustment (16) | `StockAdjustment` (15) |
| Supplier payment | Supplier ledger | Cash/Bank | `SupplierPayment` (6) |

`AutoLedger::Purchase` (13) perpetual-এ ব্যবহার হয় না (periodic method-এর জন্য
সিডে রাখা আছে)।

---

## ৮. Edit / Delete

panacea `update.php` এর সরল রূপ — **reverse তারপর re-apply**:

- **Edit:** পুরনো `stock_ledger` রো (`change_type=Purchase, reference_id IN item ids`)
  ডিলিট → আক্রান্ত প্রোডাক্টের ক্যাশড স্টক ও `purchase_price` rebuild →
  `Voucher::deleteByReference(VoucherType::Purchase, purchaseId)` → নতুন ডেটায়
  ধাপ ২–৫ আবার।
- **Delete:** একই reverse; হেডার+লাইন ডিলিট; ভাউচার `deleteByReference`।
- **রক্ষাকবচ:** কোনো লাইনের প্রোডাক্ট যদি ইতিমধ্যে বিক্রি হয়ে স্টক ঋণাত্মক করে
  ফেলে — edit/delete আটকে দাও (message সহ)।

`CostService::rebuild(product, variant)` — ওই আইটেমের সব ইন-মুভমেন্ট
`action_time` ক্রমে চালিয়ে weighted-average আবার হিসাব করে।

---

## ৯. Catalog মডিউলে পরিবর্তন

| ফাইল | পরিবর্তন |
|---|---|
| `app/Modules/Catalog/Views/product/form.php` (`stock` ইনপুট — সিম্পল ~২৫০, ভ্যারিয়েন্ট গ্রিড ~১৯৯) | ইনপুট বাদ; বদলে read-only "বর্তমান স্টক" (`StockService::onHand`)। `stock_alert` থাকবে |
| একই ফাইল, `purchase_price` (~১০২, ~১৯৩) | প্রথম মুভমেন্টের আগে editable (opening cost); পরে read-only |
| `app/Modules/Catalog/Services/ProductService.php` (`stock` লেখা — ~১০৪, ~২৬৩, ~২৯৪) | save/update-এ `stock` কখনো লিখবে না; create-এ `stock = 0`। `product_variants.stock` যোগফল-সিঙ্ক `StockService`-এ সরে যাবে |
| `ProductApi` / `VariantApi` PUT/POST | `stock` ফিল্ড উপেক্ষা (payload-এ থাকলেও) |
| `doc/07-catalog.md` স্টক অংশ (~৮৪, ~১১৭) | নতুন নিয়মে হালনাগাদ, এই ডকে লিংক |

---

## ১০. ওপেনিং স্টক

প্রোডাক্ট ফর্মে নয়। দুটো পথ:

1. **Stock Adjustment** `reason = Opening` — প্রোডাক্ট + qty + rate দিয়ে; স্টক ও
   Inventory মূল্য দুটোই বসে।
2. প্রথম **Purchase** — স্বাভাবিক কেনা হিসেবেই।

---

## ১১. মডিউল কাঠামো — `app/Modules/Purchase/`

```
Models/       Supplier · Purchase · PurchaseItem · StockLedger
              StockAdjustment · StockAdjustmentItem · PurchaseReturn · PurchaseReturnItem
Services/     PurchaseService   — create / update / delete
              StockService      — move / onHand / rebuildCached
              CostService       — applyPurchase / rebuild (weighted-average)
              SupplierService   — CRUD + opening ভাউচার
              StockAdjustmentService · PurchaseReturnService
Controllers/  SupplierController · PurchaseController · StockController
Api/          SupplierApi · PurchaseApi · StockApi        (/api/v1/...)
Views/        supplier/{list,form} · purchase/{list,form,show}
              stock/{ledger,adjustment-form,adjustment-list}
```

---

## ১২. রুট

`routes/admin.php` (UI) + `routes/api.php` (JSON) — প্রতিটির জোড়া:

| Admin | API |
|---|---|
| `/admin/suppliers`, `.../create`, `.../{id}/edit` | `/api/v1/suppliers` (GET/POST/PUT/DELETE) |
| `/admin/purchases`, `.../create`, `.../{id}`, `.../{id}/edit` | `/api/v1/purchases` (GET/POST/PUT/DELETE) + `/purchases/form-data` |
| `/admin/stock/adjustments`, `.../create`, `.../{id}/edit` | `/api/v1/stock/adjustments` (GET/POST/PUT/DELETE) |
| `/admin/stock/returns`, `.../create` | `/api/v1/stock/returns` (GET/POST/DELETE) |
| — | `/api/v1/stock/ledger?product_id=&variant_id=` (GET) |

---

## ১৩. বিল্ড ক্রম — ✅ সম্পূর্ণ

1. ✅ `schema/004_purchase.sql` + `seed/004_purchase.sql` + `StockChangeType` enum
2. ✅ **Supplier** — মডেল/সার্ভিস/CRUD/ভিউ + opening ভাউচার
3. ✅ **StockService** + `stock_ledger`; `ProductService`/form থেকে স্টক ইনপুট বাদ (create → 0)
4. ✅ **PurchaseService::create** + `CostService` + ভাউচার + purchase form/list/show
5. ✅ Purchase **edit/delete** (reverse + re-apply) + ঋণাত্মক-স্টক রক্ষাকবচ
6. ✅ **Stock Adjustment** (Opening সহ) + **Purchase Return**
7. ✅ `doc/07-catalog.md` হালনাগাদ; এই ডক ফাইনাল

প্রতি ধাপ শেষে যাচাই হয়েছে: `install.php` চলে, ট্রায়াল ব্যালেন্স মেলে
(`SUM(debit) = SUM(credit)`), `products.stock = SUM(stock_ledger.qty)`,
weighted-average cost ঠিক থাকে।

### মডিউল ফাইল

```
app/Modules/Purchase/
├── Models/       Supplier · Purchase · PurchaseItem · StockLedger
│                 StockAdjustment · PurchaseReturn
├── Services/     SupplierService · StockService · CostService · PurchaseService
│                 StockAdjustmentService · PurchaseReturnService
├── Controllers/  SupplierController · PurchaseController · StockController
├── Api/          SupplierApi · PurchaseApi · StockApi
└── Views/        supplier/{list,form} · purchase/{list,form,show}
                  stock/{adjustment-list,adjustment-form,return-list,return-form}
```
`app/Enum/StockChangeType.php` · `database/schema/004_purchase.sql` ·
`database/seed/004_purchase.sql`

---

## ১৪. সিদ্ধান্ত

| # | সিদ্ধান্ত | কেন |
|---|---|---|
| P-01 | স্টক হাতে বসানো নিষেধ — সব Purchase/Adjustment থেকে | ইনভেন্টরি ও হিসাব সবসময় মিলবে |
| P-02 | ক্যাশড কলাম + `stock_ledger` (সত্যের উৎস খতিয়ান) | দ্রুত রিড + তারিখভিত্তিক রিপোর্ট, দুটোই |
| P-03 | **Perpetual** inventory — Purchase: Dr Inventory / Cr Supplier | enum-এ `CostOfGoodsSold` আছে, রিয়েল-টাইম লাভ দরকার |
| P-04 | Cost = moving **weighted-average** (`purchase_price` কলামে) | panacea-এর সাথে এক, সহজ, যথেষ্ট |
| P-05 | **VAT/AIT নাই** — কলামও নাই, `total = sub_total − discount` | এই স্টোরে ক্রয়ে লাগে না |
| P-06 | রেফারেন্স: `panacea/operation/purchase` business logic | পরীক্ষিত প্যাটার্ন |
