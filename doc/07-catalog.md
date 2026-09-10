# ০৭ — ক্যাটালগ (ক্যাটাগরি · প্রোডাক্ট · ইউনিট · সাইজ)

## গঠন এক নজরে

```
unit_groups ──< units                     Piece / Dozen / Kg … + রূপান্তরের হার
                  │
categories (গাছ)  │
      └──< products ──┬──< product_variants ──< product_variant_values
                      │            "Navy / XL"          ↑
                      └──< product_images                │
                                                          │
attributes ──< attribute_values ──────────────────────────┘
   Size          S · M · L · XL · XXL
   Color         Navy · White · Black …
```

---

## ১. ক্যাটাগরি

গাছ কাঠামো — `parent_id` দিয়ে। রেফারেন্স সাইটের "Collections" মেনু এই গাছ:

```
All Shirts
├── Casual Shirts
└── Half Shirts
    ├── Cuban Half Shirts
    └── Hawaiian Half Shirts
Denim Jeans Pants
Polo T-Shirts
```

### `path` কলামটাই মূল কৌশল

প্রতিটি ক্যাটাগরির `path` এ root থেকে নিজে পর্যন্ত সব id থাকে — `/1/3/4/`।
ফলে **"এই ক্যাটাগরি ও তার সব সাব-ক্যাটাগরির প্রোডাক্ট"** এক LIKE কোয়েরিতেই পাওয়া যায়:

```sql
SELECT id FROM categories WHERE id = ? OR path LIKE '/1/3/%'
```

recursive CTE লাগে না, N+1 হয় না। **`path` সবসময় `CategoryService` বসায়** —
হাতে লিখবেন না। প্যারেন্ট বদলালে সব বংশধরের `path`/`depth` নিজে থেকেই নতুন করে হিসাব হয়।

### সুরক্ষা

| চেষ্টা | ফল |
|---|---|
| নিজেকে নিজের প্যারেন্ট | `ক্যাটাগরি নিজেই নিজের প্যারেন্ট হতে পারবে না।` |
| নিজের বংশধরকে প্যারেন্ট | `নিজের সাব-ক্যাটাগরিকে প্যারেন্ট বানানো যাবে না।` |
| সাব-ক্যাটাগরি আছে এমনটা ডিলিট | `আগে সেগুলো সরান।` |
| প্রোডাক্ট আছে এমনটা ডিলিট | `আগে সেগুলো অন্য ক্যাটাগরিতে নিন।` |

`isFeatured = 1` দিলে হোমপেজের collection কার্ডে দেখাবে (ফেজ ৩)।

---

## ২. ইউনিট

দুই লেভেল — গ্রুপ, তারপর ইউনিট। **একই গ্রুপের ইউনিটগুলোই একে অন্যে রূপান্তরযোগ্য।**

| গ্রুপ | ইউনিট | `conversion` |
|---|---|---|
| Count | **Piece** (বেস) | 1 |
| | Pair | 2 |
| | Dozen | 12 |
| | Box | 24 |
| Weight | **Gram** (বেস) | 1 |
| | Kilogram | 1000 |
| Length | **Meter** (বেস) | 1 |
| | Yard | 0.9144 |

`conversion` = এই ইউনিটে ১ = বেস ইউনিটে কত। প্রতি গ্রুপে **ঠিক একটাই** বেস
(`UnitService` নিজে নিশ্চিত করে — নতুন কোনোটাকে বেস করলে আগেরটার ফ্ল্যাগ নেমে যায়)।

```php
UnitService::convert(1, $dozen, $piece);   // 12
UnitService::toBase(2, $dozen);            // 24
UnitService::fromBase(24, $dozen);         // 2
UnitService::convert(1, $piece, $gram);    // ✗ আলাদা গ্রুপ — exception
```

> **স্টক সবসময় বেস ইউনিটে জমা হয়।** ক্রয়/সমন্বয়ে অন্য ইউনিট দিলে `toBase()`
> দিয়ে রূপান্তর করে তবেই স্টকে বসে।

---

## ৩. অ্যাট্রিবিউট — সাইজ ও রঙ

সিডে দুটো অ্যাট্রিবিউট আসে:

| অ্যাট্রিবিউট | code | type | ভ্যালু |
|---|---|---|---|
| Size | `size` | select | XS · S · M · L · XL · XXL · XXXL · 30 · 32 · 34 · 36 · 38 · 40 · 42 |
| Color | `color` | color | White · Black · Navy · Blue · Sky · Grey · Olive · Maroon · Beige · Green |

সংখ্যার সাইজগুলো (30–42) জিন্স/প্যান্টের কোমরের জন্য। নতুন অ্যাট্রিবিউট
(যেমন Fit, Fabric) `/admin/attributes` থেকেই যোগ করা যায়।

প্রতিটি ভ্যালুর `code` **SKU তে বসে** — `P-00001-M-NVY`। তাই কোড একবার সেট
হলে বদলাবেন না।

> ⚠ যে ভ্যালু কোনো ভ্যারিয়েন্টে ব্যবহৃত হয়েছে সেটা **ডিলিট করা যায় না** —
> তাহলে পুরোনো অর্ডারে "কোন সাইজ ছিল" হারিয়ে যেত। বদলে `isActive = 0`।

---

## ৪. প্রোডাক্ট

### দুই ধরন

| | `has_variant = 0` | `has_variant = 1` |
|---|---|---|
| উদাহরণ | বেল্ট, মগ | শার্ট (M/L/XL × Navy/White) |
| `sale_price` কোথায় | `products` টেবিলে | **`products` টেবিলেই** — ভ্যারিয়েন্টে sale_price নাই, একই প্রোডাক্টের সব রঙ/সাইজ এক দামে বিক্রি হয় |
| `purchase_price`/`offer_price` কোথায় | `products` টেবিলে | প্রতি `product_variants` সারিতে (রঙ/সাইজ ভেদে ক্রয়মূল্য আলাদা হতে পারে) |
| স্টক কোথায় | `products.stock` | প্রতি ভ্যারিয়েন্টে; `products.stock` = যোগফল |

`has_variant` হাতে সেট করতে হয় না — `syncVariants()` নিজে বসায়।

> **স্টক হাতে বসানো যায় না।** `products.stock` / `product_variants.stock` হলো
> `StockService` এর ক্যাশড মান — সত্যের উৎস `stock_ledger`। বাড়ে শুধু **Purchase**
> / **Stock Adjustment** থেকে, কমে **Sale** থেকে। প্রোডাক্ট ফর্মে স্টক read-only।
> পূর্ণ বিবরণ [08-purchase.md](08-purchase.md)।
>
> **`purchase_price`-ও হাতে বসানো যায় না** — একই নিয়ম, একই কারণে। `CostService`
> এটা লেখে (moving weighted-average, Purchase থেকে) — `products.purchase_price`
> (সিম্পল প্রোডাক্ট) আর `product_variants.purchase_price` (ভ্যারিয়েন্ট) দুটোতেই।
> প্রোডাক্ট ফর্মে (এবং ভ্যারিয়েন্ট গ্রিডে) তাই এটাও read-only দেখায় — নতুন
> প্রোডাক্টে `0`, প্রথম Purchase-এর পর আসল দাম বসে (২০২৬-০৯-০৯ চূড়ান্ত সিদ্ধান্ত)।

### দাম

| কলাম | মানে |
|---|---|
| `purchase_price` | moving weighted-average ক্রয়মূল্য (COGS হিসাবের ভিত্তি) — **শুধু `CostService` লেখে, ফর্মে read-only**; ভ্যারিয়েন্ট থাকলে প্রতি ভ্যারিয়েন্টে আলাদা |
| `sale_price` | স্বাভাবিক বিক্রয় মূল্য (regular price) — **শুধু `products` টেবিলে**, `product_variants` এ এই কলাম নাই। একই প্রোডাক্টের সব রঙ/সাইজ এক দামে বিক্রি হয় (সিদ্ধান্ত, ২০২৬-০৯-০৯) |
| `offer_price` | অফার মূল্য; `0` = অফার নাই; ভ্যারিয়েন্টে আলাদা অফার দেওয়া যায় (যেমন নির্দিষ্ট রঙে ক্লিয়ারেন্স) |
| `offer_start` / `offer_end` | `0` = সীমা নাই |

**কার্যকর দাম সবসময় `ProductService::effectivePrice()` দিয়ে বের করবেন** —
কোথাও `sale_price` সরাসরি পড়বেন না।

```php
ProductService::effectivePrice($product, $variant);
// [
//   'price' => 899.0, 'regular' => 1090.0, 'on_offer' => true,
//   'discount' => 191.0, 'discount_percent' => 17.5
// ]
```

নিয়ম: অফার তখনই চালু যখন `offer_price > 0`, `offer_price < regular`, এবং
আজকের তারিখ সময়সীমার ভেতরে। `offer_end` **inclusive** — ওই দিনের শেষ সেকেন্ড পর্যন্ত।

`sale_price` সবসময় প্রোডাক্টের — ভ্যারিয়েন্টে নিজস্ব `sale_price` নাই। ভ্যারিয়েন্টে
শুধু `offer_price` আলাদা হতে পারে; **অফারের সময়সীমা কেবল প্রোডাক্টে** থাকে।

---

## ৫. ভ্যারিয়েন্ট — কীভাবে তৈরি হয়

ভ্যারিয়েন্ট হাতে বানাতে হয় না। যে সাইজ/রঙগুলো আছে সেগুলো বেছে দিলে
**সব কম্বিনেশন (cartesian product)** নিজে থেকেই তৈরি হয়:

```php
ProductService::syncVariants($productId, [
    2 => [23, 21],       // Color: Navy, White
    1 => [3, 4, 5],      // Size:  M, L, XL
]);
// → ২ × ৩ = ৬টা ভ্যারিয়েন্ট
// ['created' => 6, 'kept' => 0, 'deactivated' => 0, 'total' => 6]
```

প্রতিটি ভ্যারিয়েন্ট পায়:

| | কীভাবে |
|---|---|
| `signature` | ভ্যালু id গুলো সংখ্যাক্রমে সাজিয়ে — `"3-23"` |
| `name` | অ্যাট্রিবিউটের ক্রমে ভ্যালু — `"M / Navy"` |
| `sku` | প্রোডাক্টের SKU + ভ্যালু কোড — `"P-00001-M-NVY"` |
| `purchase_price` | প্রোডাক্টের (তখনকার) দাম কপি হয়ে আসে; এরপর শুধু `CostService`/Purchase বদলায়, গ্রিডে read-only |
| `offer_price` | প্রোডাক্টের দাম কপি হয়ে আসে, পরে গ্রিডে আলাদা করে বদলানো যায় |
| `sale_price` | **নাই** — বিক্রয় মূল্য সবসময় প্রোডাক্টের, ভ্যারিয়েন্টে কপি হয় না |

### আবার চালালে কী হয়

`signature` দিয়ে মিলিয়ে দেখা হয়, তাই **আগের ভ্যারিয়েন্ট নষ্ট হয় না**:

| অবস্থা | ফল |
|---|---|
| আগেও ছিল, এখনো আছে | দাম/স্টক অক্ষত, শুধু `isActive = 1` |
| নতুন কম্বিনেশন | তৈরি হয় |
| আগে ছিল, এখন বাদ | **`isActive = 0`** — মুছে যায় না |
| আবার ফিরিয়ে আনা | আগের দাম/স্টক সহ আবার চালু |

শেষ নিয়মটাই সবচেয়ে গুরুত্বপূর্ণ — XL বাদ দিয়ে আবার ফেরত আনলে ওর পুরোনো
স্টক আর অর্ডারের ইতিহাস অক্ষত থাকে।

---

## ৬. HTTP API

সব এন্ডপয়েন্টে `guard: admin` — স্টোরফ্রন্টের পাবলিক এন্ডপয়েন্ট
(`guard: guest`) আলাদা, একই `CategoryApi`/`ProductApi` ক্লাসে
`publicTree()`/`publicShow()`/`publicIndex()`/`publicShowBySlug()` মেথডে,
দেখুন [10-storefront-order.md](10-storefront-order.md) §৬।

### ক্যাটাগরি

| Method | URI | কী |
|---|---|---|
| `GET` | `/api/v1/categories` | পুরো গাছ + ড্রপডাউন options + featured |
| `GET` | `/api/v1/categories/{id}` | একটা + ব্রেডক্রাম্ব + children |
| `POST` | `/api/v1/categories` | `name`, `parent_id?`, `slug?`, `sort_order?`, `isFeatured?` |
| `PUT` | `/api/v1/categories/{id}` | একই ফিল্ড |
| `DELETE` | `/api/v1/categories/{id}` | |

### ইউনিট

| Method | URI | কী |
|---|---|---|
| `GET` | `/api/v1/units` | গ্রুপসহ তালিকা + ড্রপডাউন options |
| `GET` | `/api/v1/units/convert?quantity=&from=&to=` | রূপান্তর |
| `POST` | `/api/v1/units` | `unit_group_id`, `name`, `code`, `conversion`, `is_base?` |
| `DELETE` | `/api/v1/units/{id}` | |
| `POST` | `/api/v1/unit-groups` | `name`, `sort_order?` |
| `DELETE` | `/api/v1/unit-groups/{id}` | |

### অ্যাট্রিবিউট

| Method | URI | কী |
|---|---|---|
| `GET` | `/api/v1/attributes` | সব অ্যাট্রিবিউট, ভ্যালু সহ |
| `POST` | `/api/v1/attributes` | `name`, `code?`, `type?` (`select`\|`color`) |
| `DELETE` | `/api/v1/attributes/{id}` | |
| `POST` | `/api/v1/attribute-values` | `attribute_id`, `value`, `code?`, `color_hex?` |
| `DELETE` | `/api/v1/attribute-values/{id}` | |

### প্রোডাক্ট

| Method | URI | কী |
|---|---|---|
| `GET` | `/api/v1/products` | ফিল্টার: `category_id` (গাছসহ), `q`, `isActive`, `isFeatured`, `isNew`, `page`, `per_page` |
| `GET` | `/api/v1/products/form-data` | ক্যাটাগরি + ইউনিট + অ্যাট্রিবিউট ড্রপডাউন |
| `GET` | `/api/v1/products/{id}` | পূর্ণ তথ্য — ভ্যারিয়েন্ট, ছবি, ক্যাটাগরি পথ |
| `GET` | `/api/v1/products/slug/{slug}` | স্টোরফ্রন্টের জন্য |
| `POST` | `/api/v1/products` | নিচে ফিল্ড তালিকা |
| `PUT` | `/api/v1/products/{id}` | |
| `DELETE` | `/api/v1/products/{id}` | |
| `POST` | `/api/v1/products/{id}/variants` | `selection` — নিচে দেখুন |
| `PUT` | `/api/v1/variants/{id}` | `offer_price`, `barcode?` (`purchase_price`/`sale_price` নাই — আগেরটা শুধু Purchase থেকে আসে, পরেরটা প্রোডাক্টের দাম কার্ড থেকে বদলান) |
| `POST` | `/api/v1/products/{id}/images` | multipart — `image` (ফাইল), `attribute_value_id?` (রঙ, 0=সাধারণ), `alt?`, `is_primary?` — [09-media-and-purchase-pricing.md](09-media-and-purchase-pricing.md) |
| `PUT` | `/api/v1/images/{id}` | `is_primary?`, `alt?`, `attribute_value_id?` (নতুন ফাইল না) |
| `DELETE` | `/api/v1/images/{id}` | |

**প্রোডাক্ট সেভের ফিল্ড:** `name`*, `category_id`*, `unit_id`*, `slug?`, `sku?`
(খালি হলে `P-00001` সিরিজ), `short_description?`, `description?`,
`sale_price?`, `offer_price?`, `offer_start?`, `offer_end?`
(`YYYY-MM-DD`), `stock_alert?`, `weight?` (গ্রাম), `isActive?`,
`isNew?`, `isFeatured?`, `sort_order?`, `meta_title?`, `meta_description?`

**ভ্যারিয়েন্ট জেনারেট:**

```json
POST /api/v1/products/12/variants
{ "selection": { "2": [23, 21], "1": [3, 4, 5] } }
```

উত্তর:

```json
{ "status": 1,
  "m": [["s", "6 টি ভ্যারিয়েন্ট — নতুন 6, আগের 0, নিষ্ক্রিয় 0।"]],
  "summary": { "created": 6, "kept": 0, "deactivated": 0, "total": 6 },
  "product": { … } }
```

---

## ৭. অ্যাডমিন পেজ

| পেজ | কী করা যায় |
|---|---|
| `/admin/products` | লিস্ট, ফিল্টার (নাম/SKU, ক্যাটাগরি, অবস্থা), ছবি-থাম্ব, দাম (অফার হলে কাটা দাম + %), স্টক (কম হলে সতর্কতা) |
| `/admin/products/create` | নতুন প্রোডাক্ট ফর্ম |
| `/admin/products/{id}/edit` | এডিট + **ভ্যারিয়েন্ট বিল্ডার** + ভ্যারিয়েন্টের দাম/স্টক গ্রিড |
| `/admin/categories` | গাছ + পাশে যোগ/এডিট ফর্ম |
| `/admin/attributes` | সাইজ/রঙের ভ্যালু চিপ হিসেবে, ইনলাইন যোগ/ডিলিট |
| `/admin/units` | গ্রুপ অনুযায়ী ইউনিট + ইনলাইন যোগ |

ভ্যারিয়েন্ট বিল্ডারে অপশন বাছলে উপরে লাইভ গুনতি দেখায় — `2 × 3 = 6 টি ভ্যারিয়েন্ট`।

সব পেজের সেভ/ডিলিট শেয়ার্ড `api()` হেল্পার দিয়ে সরাসরি HTTP API-তে যায়
(লেআউটে ডিফাইন করা, দেখুন [06-admin-ui.md](06-admin-ui.md))।

---

## ৮. ডেমো ডেটা

রেফারেন্স সাইটের ক্যাটাগরি গাছ চাইলে:

```bash
php database/install.php --demo
```

`database/demo/*.sql` স্বাভাবিক ইনস্টলে চলে না — শুধু `--demo` দিলে।

---

## ৯. ফেজ ৩ — ✅ যুক্ত হয়ে গেছে

- স্টোরফ্রন্ট পাবলিক এন্ডপয়েন্ট (`/collections/{slug}`, `/products/{slug}`)
- কার্ট ও চেকআউট — ভ্যারিয়েন্ট ধরে
- অর্ডার `Shipped` হলে `Sale` + `CostOfGoodsSold` ভাউচার
  (ম্যাপিং [05-voucher.md](05-voucher.md) এ)

পূর্ণ ডিজাইন ও বিল্ড বিস্তারিত [10-storefront-order.md](10-storefront-order.md) এ।

> ছবি আপলোড (ক্যাটাগরি + প্রোডাক্ট গ্যালারি, রঙ-ভিত্তিক ট্যাগিং) ফেজ ৩ এর
> অপেক্ষা না করে আগেই প্ল্যান করা হয়েছিল — দেখুন [09-media-and-purchase-pricing.md](09-media-and-purchase-pricing.md)।
