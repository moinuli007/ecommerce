# ০৫ — ভাউচার

হিসাব পোস্ট করার একমাত্র দরজা `App\Modules\Account\Services\Voucher`।

> ⚠ `a_voucher_entry` বা `a_ledger_entry` তে **কখনো সরাসরি `INSERT`/`UPDATE` নয়**।
> অর্ডার, পারচেজ, রিটার্ন, রিফান্ড — সব এই সার্ভিসের মধ্য দিয়ে যাবে।
> তাহলেই ট্রায়াল ব্যালেন্স সবসময় মিলবে।

---

## ১. সার্ভিস API

### `Voucher::create()` — সাধারণ ভাউচার

এক ডেবিট + এক ক্রেডিট।

```php
Voucher::create(
    VoucherType $type,
    float       $amount,
    int         $debitLedger,
    int         $creditLedger,
    int         $time,            // unix timestamp — ভাউচারের কার্যকর তারিখ
    string      $note = '',
    string      $reference = ''   // সোর্স ডকুমেন্টের id
): int                            // নতুন voucher id
```

উদাহরণ — কাস্টমার বাকিতে ৳১০০০ এর অর্ডার দিল:

```php
Voucher::create(
    VoucherType::Sale,
    1000.00,
    LedgerAccounts::customerLedger($customer),        // Dr — পাওনা বাড়ল
    LedgerAccounts::systemLedger(AutoLedger::Sales),  // Cr — আয় বাড়ল
    strtotime('today'),
    'Order #1024',
    (string) $order['id']
);
```

### `Voucher::createCompound()` — কম্পাউন্ড ভাউচার

অনেকগুলো লাইন। `SUM(debit)` আর `SUM(credit)` সমান না হলে exception।

```php
Voucher::createCompound(
    VoucherType $type,
    array       $lines,   // [['ledger_id'=>int, 'debit'=>float, 'credit'=>float, 'note'=>string], ...]
    int         $time,
    string      $note = '',
    string      $reference = ''
): int
```

উদাহরণ — ৳১০০০ এর অর্ডার, ৳৬০০ ক্যাশ + ৳৪০০ বাকি:

```php
Voucher::createCompound(VoucherType::Sale, [
    ['ledger_id' => $cash,           'debit' => 600, 'credit' => 0],
    ['ledger_id' => $customerLedger, 'debit' => 400, 'credit' => 0],
    ['ledger_id' => $sales,          'debit' => 0,   'credit' => 1000],
], strtotime('today'), 'Order #1025', '1025');
```

### `Voucher::opening()` — ওপেনিং ব্যালেন্স

কাউন্টার পার্ট সবসময় `AutoLedger::Opening`।

```php
Voucher::opening(
    VoucherType     $type,      // OpeningCustomer | OpeningSupplier | OpeningBalance
    int             $ledgerId,
    float           $amount,
    TransactionType $nature,    // Debit = আমরা পাব · Credit = আমরা দেব
    int             $time,
    string          $reference = '',
    string          $note = 'Opening balance'
): int
```

### `Voucher::update()`

শুধু যেগুলো পাঠাবেন সেগুলোই বদলাবে (`null` = অপরিবর্তিত)।

```php
Voucher::update(
    int    $voucherId,
    ?float $amount = null,
    ?string $note = null,
    ?int   $time = null,
    ?int   $debitLedger = null,
    ?int   $creditLedger = null
): bool
```

**কম্পাউন্ড ভাউচারে `amount`/লেজার বদলানো যাবে না** — ডিলিট করে নতুন করে বানান।
(কোন লাইনে কত বসবে সেটা অনুমান করা অসম্ভব।) আপডেটের শেষে আবার ব্যালেন্স যাচাই হয়।

### `Voucher::delete()` / `deleteByReference()`

```php
Voucher::delete(42);                                       // এক বা
Voucher::delete([42, 43, 44]);                             // অনেকগুলো

// সোর্স ডকুমেন্ট ধরে — অর্ডার ক্যানসেল হলে ওই অর্ডারের সব ভাউচার
Voucher::deleteByReference([VoucherType::Sale, VoucherType::CostOfGoodsSold], '1024');
```

### পড়ার দিক — `VoucherDetails`

```php
VoucherDetails::byId(42);                              // এক ভাউচার
VoucherDetails::byCode('SL-000123');
VoucherDetails::byReference(VoucherType::Sale, '1024'); // অর্ডারের ভাউচার
VoucherDetails::search([...]);                         // ফিল্টার + পেজিনেশন
VoucherDetails::list([...]);                           // শুধু লিস্ট
```

`search()` এর ফিল্টার: `voucher_id`, `type`, `ledger_id`, `reference`, `user_id`,
`from`, `to`, `code`, `page`, `per_page`। সবগুলোই একক ভ্যালু বা array নিতে পারে।

রিটার্ন শেপ:

```php
[
  'id' => 42, 'code' => 'SL-000123', 'time' => 1785..., 'date' => '2026-07-01',
  'type' => 'Sale', 'type_id' => 8, 'type_code' => 'SL',
  'reference' => '1024', 'note' => 'Order #1024',
  'amount' => 1000.0, 'amount_in_words' => 'One Thousand Taka Only',
  'created_by' => 1, 'created_by_name' => 'Super Admin',
  'is_compound' => false,
  'debit_ledger_id' => 5,  'debit_ledger_name'  => 'রহিম উদ্দিন', 'debit_ledger_code'  => 'CUS-501',
  'credit_ledger_id' => 2, 'credit_ledger_name' => 'Sales',       'credit_ledger_code' => 'AT-SAL',
  'lines' => [ ['ledger_id'=>5, 'ledger_name'=>'...', 'debit'=>1000.0, 'credit'=>0.0, 'note'=>'...'], ... ],
]
```

কম্পাউন্ড ভাউচারে `debit_ledger_*` এ **প্রথম** ডেবিট লাইন বসে — পুরো ছবি `lines` এ।

### স্টেটমেন্ট — `LedgerStatement`

```php
LedgerStatement::statement($ledgerId, '2026-07-01', '2026-07-31');
LedgerStatement::balance($ledgerId, '2026-07-31');
LedgerStatement::trialBalance('2026-07-01', '2026-07-31');
```

`statement()` দেয় ওপেনিং ব্যালেন্স, প্রতি লাইনে running balance, বিপরীত লেজারের নাম
(`particulars`), মোট Dr/Cr, ক্লোজিং। সাইন কনভেনশন [02-database.md](02-database.md) এ।

---

## ২. যেসব নিয়ম সার্ভিস নিজেই প্রয়োগ করে

| নিয়ম | ভাঙলে |
|---|---|
| `SUM(debit) = SUM(credit)` | `ভাউচার ব্যালেন্সড নয় — ডেবিট X, ক্রেডিট Y` |
| মোট পরিমাণ > ০ | `ভাউচারের মোট পরিমাণ শূন্যের বেশি হতে হবে।` |
| অন্তত ২টা লাইন | `ভাউচারে অন্তত দুইটি লাইন লাগবে।` |
| এক লাইনে Dr আর Cr দুটো নয় | `একই লাইনে ডেবিট আর ক্রেডিট দুটোই থাকতে পারবে না।` |
| ঋণাত্মক অঙ্ক নয় | `ডেবিট বা ক্রেডিট ঋণাত্মক হতে পারবে না।` |
| Dr ≠ Cr লেজার (সাধারণ ভাউচারে) | `ডেবিট আর ক্রেডিট একই লেজার হতে পারবে না।` |
| সব লেজার আসলেই আছে | `লেজার পাওয়া যায়নি: 999` |

সবকিছু একটা DB ট্রানজেকশনের ভেতরে — কোনো নিয়ম ভাঙলে **কিছুই সেভ হয় না**।

শূন্য ডেবিট ও শূন্য ক্রেডিটের লাইন নিজে থেকেই বাদ পড়ে (ফর্মে খালি রো থাকলে সমস্যা নাই)।

---

## ৩. VoucherType তালিকা

`code` = ভাউচার নাম্বারের প্রিফিক্স। `manual` = ইউজার হাতে বানাতে পারবে কি না —
বাকিগুলো সোর্স ডকুমেন্ট (অর্ডার/পারচেজ) পোস্ট হলে সিস্টেম নিজেই বানায়, তাই
হাতে বানানো ও এডিট করা API লেভেলে আটকানো।

| # | নাম | code | manual | `reference` এ কী থাকে |
|---|---|---|---|---|
| 1 | Opening Customer | `OC` | ✅ | `customers.id` |
| 2 | Opening Supplier | `OS` | ✅ | `suppliers.id` |
| 3 | Opening Balance | `OB` | ✅ | — |
| 4 | Purchase | `PU` | | `purchases.id` |
| 5 | Purchase Return | `PR` | | `purchase_returns.id` |
| 6 | Supplier Payment | `SP` | | `suppliers.id` |
| 7 | Supplier Receive | `SRC` | | `suppliers.id` |
| 8 | Sale | `SL` | | `orders.id` |
| 9 | Sale Return | `SRT` | | `order_returns.id` |
| 10 | Customer Receive | `CR` | | `orders.id` |
| 11 | Customer Refund | `CRF` | | `orders.id` |
| 12 | Shipping Charge | `SH` | | `orders.id` |
| 13 | Sale Discount | `SD` | | `orders.id` |
| 14 | Cost of Goods Sold | `CG` | | `orders.id` |
| 15 | Stock Adjustment | `SA` | | `stock_adjustments.id` |
| 16 | Income | `IN` | ✅ | — |
| 17 | Expense | `EX` | ✅ | — |
| 18 | Contra | `CN` | ✅ | — |
| 19 | Journal | `JV` | ✅ | — |
| 20 | Courier Settlement | `CS` | | `couriers.id` |
| 21 | Gateway Settlement | `GS` | | `payment_gateways.id` |

⚠ **এই নাম্বারগুলো কখনো বদলাবেন না।** নতুন ধরন শেষে ২২, ২৩ … দিয়ে যোগ করবেন।

---

## ৪. প্রতিটি লেনদেনের ডেবিট/ক্রেডিট ম্যাপ

ক্রয় (ফেজ ৪, [08-purchase.md](08-purchase.md)) আর বিক্রয়/অর্ডার
(ফেজ ৩, [10-storefront-order.md](10-storefront-order.md)) দুটোই এই ম্যাপ
অনুযায়ী ✅ বাস্তবায়িত। `AutoLedger::X` মানে `LedgerAccounts::systemLedger(AutoLedger::X)`।

### বিক্রয়

| ঘটনা | Type | ডেবিট | ক্রেডিট |
|---|---|---|---|
| বাকিতে বিক্রি | `Sale` | কাস্টমার লেজার | `Sales` |
| ক্যাশে বিক্রি | `Sale` | `Cash` | `Sales` |
| COD অর্ডার (কুরিয়ারে দেওয়া) | `Sale` | `CodReceivable` | `Sales` |
| শিপিং চার্জ নেওয়া | `ShippingCharge` | কাস্টমার / `CodReceivable` | `ShippingIncome` |

> ⚠ **যেভাবে আসলে বাস্তবায়িত হয়েছে:** এই টেবিলে "শিপিং চার্জ নেওয়া" আলাদা
> `ShippingCharge` টাইপ দেখাচ্ছে, কিন্তু `OrderService::postShipmentVouchers()`
> এ শিপিং লাইনটা **আলাদা ভাউচার না** — একই কম্পাউন্ড `Sale` ভাউচারের তৃতীয়
> লাইন (Dr CodReceivable/AdvanceFromCustomer পুরো grand_total, Cr Sales আর
> Cr ShippingIncome একসাথে)। একই অর্ডারের বিক্রি আর শিপিং সবসময় একসাথে ঘটে
> (checkout-এর দাম দুটোই এক জায়গা থেকে), আলাদা ভাউচার করলে অহেতুক জটিলতা —
> `VoucherType::ShippingCharge` (type 12) তাই এখনো অব্যবহৃত। বিস্তারিত
> [10-storefront-order.md](10-storefront-order.md) §৮।
| ডিসকাউন্ট | `SaleDiscount` | `SalesDiscount` | কাস্টমার লেজার |
| বিক্রির খরচ (স্টক কমা) | `CostOfGoodsSold` | `CostOfGoodsSold` | `Inventory` |
| কাস্টমারের টাকা পাওয়া (ক্যাশ) | `CustomerReceive` | `Cash` | কাস্টমার লেজার |
| কাস্টমারের টাকা পাওয়া (bKash) | `CustomerReceive` | `MobileBanking` | কাস্টমার লেজার |
| গেটওয়েতে পেমেন্ট | `CustomerReceive` | `GatewayReceivable` | কাস্টমার লেজার |
| বিক্রি ফেরত | `SaleReturn` | `SalesReturn` | কাস্টমার লেজার |
| ফেরত পণ্য স্টকে | `SaleReturn` | `Inventory` | `CostOfGoodsSold` |
| কাস্টমারকে রিফান্ড | `CustomerRefund` | কাস্টমার লেজার | `Cash` |

### আদায় / সেটেলমেন্ট

| ঘটনা | Type | ডেবিট | ক্রেডিট |
|---|---|---|---|
| কুরিয়ার COD টাকা দিল | `CourierSettlement` | `Cash` / `Bank` | `CodReceivable` |
| কুরিয়ারের ডেলিভারি চার্জ | `CourierSettlement` | `DeliveryExpense` | `CodReceivable` |
| গেটওয়ে টাকা দিল | `GatewaySettlement` | `Bank` | `GatewayReceivable` |
| গেটওয়ে ফি কাটল | `GatewaySettlement` | `GatewayFee` | `GatewayReceivable` |

> কুরিয়ার সেটেলমেন্ট সাধারণত **কম্পাউন্ড ভাউচার** — এক ব্যাচে অনেক অর্ডারের
> টাকা আসে, সাথে চার্জ কাটা হয়। `createCompound()` ব্যবহার করবেন।

### ক্রয় ও স্টক

| ঘটনা | Type | ডেবিট | ক্রেডিট |
|---|---|---|---|
| বাকিতে ক্রয় | `Purchase` | `Inventory` | সাপ্লায়ার লেজার |
| ক্যাশে ক্রয় | `Purchase` | `Inventory` | `Cash` |
| সাপ্লায়ারকে পেমেন্ট | `SupplierPayment` | সাপ্লায়ার লেজার | `Cash` / `Bank` |
| ক্রয় ফেরত | `PurchaseReturn` | সাপ্লায়ার লেজার | `Inventory` |
| সাপ্লায়ার থেকে টাকা ফেরত | `SupplierReceive` | `Cash` | সাপ্লায়ার লেজার |
| স্টক কমে গেছে (নষ্ট/হারানো) | `StockAdjustment` | `StockAdjustment` | `Inventory` |
| স্টক বেড়েছে (গণনায় বেশি) | `StockAdjustment` | `Inventory` | `StockAdjustment` |

### সাধারণ

| ঘটনা | Type | ডেবিট | ক্রেডিট |
|---|---|---|---|
| খরচ (ক্যাশে) | `Expense` | খরচের লেজার (`for_expense=1`) | `Cash` |
| আয় (ক্যাশে) | `Income` | `Cash` | আয়ের লেজার (`for_income=1`) |
| ক্যাশ → ব্যাংক | `Contra` | `Bank` | `Cash` |
| ব্যাংক → ক্যাশ | `Contra` | `Cash` | `Bank` |
| ভ্যাট আদায় | `Sale` | কাস্টমার লেজার | `VatPayable` |
| কাস্টমার ওপেনিং (আমরা পাব) | `OpeningCustomer` | কাস্টমার লেজার | `Opening` |
| সাপ্লায়ার ওপেনিং (আমরা দেব) | `OpeningSupplier` | `Opening` | সাপ্লায়ার লেজার |

---

## ৫. HTTP API

সব এন্ডপয়েন্টে `guard: admin`। রেসপন্স ফরম্যাট [03-response-format.md](03-response-format.md)।

### `GET /api/v1/vouchers`

ফিল্টার: `type[]`, `ledger_id[]`, `reference`, `user_id[]`, `from`, `to`, `code`,
`page`, `per_page` (ডিফল্ট ৫০, সর্বোচ্চ ৫০০, `0` = সব)।

```json
{ "status": 1, "m": [], "vouchers": [ ... ],
  "pagination": { "total": 137, "page": 1, "per_page": 50, "pages": 3 } }
```

### `GET /api/v1/vouchers/{id}` · `GET /api/v1/vouchers/code/{code}`

```json
{ "status": 1, "m": [], "voucher": { ... } }
```

### `GET /api/v1/vouchers/form-data`

এন্ট্রি ফর্মের ড্রপডাউন — `voucher_types` (শুধু manual), `ledgers`, `natures`, `today`।

### `POST /api/v1/vouchers`

সাধারণ ভাউচার:

```json
{ "type": 19, "amount": 500, "debit_ledger": 3, "credit_ledger": 1,
  "date": "2026-07-10", "note": "অফিস ভাড়া", "reference": "" }
```

কম্পাউন্ড ভাউচার (`amount`/`debit_ledger` এর বদলে `lines`):

```json
{ "type": 19, "date": "2026-07-10", "note": "কুরিয়ার সেটেলমেন্ট",
  "lines": [
    { "ledger_id": 1, "debit": 4800, "credit": 0 },
    { "ledger_id": 9, "debit": 200,  "credit": 0, "note": "ডেলিভারি চার্জ" },
    { "ledger_id": 6, "debit": 0,    "credit": 5000 }
  ] }
```

সফল হলে `voucher_id` আর পুরো `voucher` অবজেক্ট ফেরত আসে।

manual নয় এমন type পাঠালে:
`{"status":0,"m":[["e","Sale ভাউচার হাতে তৈরি করা যায় না — এটি সিস্টেম নিজেই তৈরি করে।"]]}`

### `POST /api/v1/vouchers/opening`

```json
{ "type": 1, "ledger_id": 5, "amount": 2500, "nature": 1,
  "date": "2026-06-30", "reference": "501" }
```

`nature`: `1` = Debit (আমরা পাব) · `2` = Credit (আমরা দেব)।

### `PUT /api/v1/vouchers/{id}` · `DELETE /api/v1/vouchers/{id}`

শুধু manual ভাউচারে কাজ করে। DELETE এ অ্যাডমিন হতে হবে।

### লেজার ও রিপোর্ট

| এন্ডপয়েন্ট | কী দেয় |
|---|---|
| `GET /api/v1/ledgers` | ফিল্টার সহ লেজার লিস্ট (`chart_id`, `type`, `for_income`, `for_expense`, `search`) |
| `GET /api/v1/ledgers/tree` | master → chart → ledger পুরো গাছ |
| `GET /api/v1/ledgers/options` | ড্রপডাউনের ফ্ল্যাট লিস্ট |
| `POST /api/v1/ledgers` | ম্যানুয়াল লেজার (`name`, `chart_id`, `code?`, `for_income?`, `for_expense?`) |
| `GET /api/v1/ledgers/{id}/statement?from=&to=` | পূর্ণ স্টেটমেন্ট |
| `GET /api/v1/ledgers/{id}/balance?upto=` | শুধু ব্যালেন্স |
| `GET /api/v1/reports/trial-balance?from=&to=` | ট্রায়াল ব্যালেন্স + `is_balanced` |

---

## ৬. অ্যাডমিন পেজ

| পেজ | কী |
|---|---|
| `/admin/vouchers` | লিস্ট + ফিল্টার (ধরন, তারিখ, কোড) |
| `/admin/vouchers/entry` | এন্ট্রি ফর্ম — সরাসরি `POST /api/v1/vouchers` এ পাঠায় |
| `/admin/vouchers/{id}` | ডিটেইল + সব লাইন + কথায় অঙ্ক |
| `/admin/ledgers` | চার্ট অব অ্যাকাউন্টস ট্রি |
| `/admin/ledgers/{id}/statement` | লেজার স্টেটমেন্ট |
| `/admin/reports/trial-balance` | ট্রায়াল ব্যালেন্স (না মিললে লাল সতর্কতা) |
