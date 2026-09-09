# ০৯ — প্রোডাক্ট/ক্যাটাগরি ছবি ও Purchase-এ বিক্রয় মূল্য

> **স্কোপ:** মূল প্রোডাক্ট পোশাক (শার্ট/প্যান্ট জাতীয়) — তাই একই প্রোডাক্টের
> ২–৩টা রঙ থাকা এবং প্রতিটার নিজস্ব ছবি থাকা এখানে ধরে নেওয়া মূল ব্যবহারের ধরন।
> এই ডক দুটো স্বাধীন কাজ প্ল্যান করেছিল — দুটোই এখন **✅ বিল্ড ও যাচাই করা** (§৭ দেখুন)।
>
> ১. **ছবি** — ক্যাটাগরি ও প্রোডাক্টে প্রকৃত ফাইল আপলোড (schema আগে থেকেই আছে,
>    UI ও ফাইল-হ্যান্ডলিং নাই — [07-catalog.md](07-catalog.md) §৯ এ পেন্ডিং ছিল)।
> ২. **Purchase-এ বিক্রয় মূল্য** — ক্রয় এন্ট্রির প্রতি লাইনে ক্রয়মূল্যের পাশাপাশি
>    বিক্রয়মূল্যও ঐচ্ছিকভাবে দেওয়া যাবে, যাতে প্রতিবার আলাদা করে প্রোডাক্ট এডিটে
>    না গিয়েও কেনার সময়ই বিক্রয়মূল্য ঠিক হয়ে যায়। প্রোডাক্ট এডিট ফর্মে
>    `sale_price` ফিল্ড আগের মতোই থাকবে — চাইলে সেখান থেকেও বদলানো যাবে।

---

## ১. গঠন এক নজরে

```
ছবি
  categories.image (একটাই)                    public/uploads/categories/xxx.webp
  products ──< product_images (গ্যালারি)       public/uploads/products/xxx.webp
                    │ attribute_value_id  →  0 = সাধারণ, নাহলে নির্দিষ্ট Color ভ্যালুর ছবি
                    └ is_primary, sort_order

Purchase-এ বিক্রয় মূল্য
  purchase_items.sale_price (ঐচ্ছিক, 0 = অপরিবর্তিত)
        │
        └──> PurchaseService::applyItems()
                   ├─ CostService::applyPurchase()   → product_variants.purchase_price (weighted-avg, ভ্যারিয়েন্ট-ভিত্তিক, আগের মতোই)
                   └─ sale_price > 0 হলে            → products.sale_price সরাসরি বসে (overwrite, সবসময় প্রোডাক্ট-লেভেলে —
                                                        variant_id যাই হোক, sale_price ভ্যারিয়েন্টে নাই)
```

---

## ২. ছবি — ক্যাটাগরি ও প্রোডাক্ট

### ২.১ এখন কী আছে

| টেবিল | কলাম | অবস্থা |
|---|---|---|
| `categories` | `image VARCHAR(255)` | schema-তে আছে, `CategoryService::save()` ইতিমধ্যে লেখে — কিন্তু অ্যাডমিন ফর্মে (`category/index.php`) কোনো ইনপুট নাই, ফাইল হ্যান্ডলিংও নাই |
| `product_images` | `path`, `alt`, `variant_id`, `is_primary`, `sort_order` | টেবিল ও `ProductService::addImage()`/`deleteImage()` আছে, `/api/v1/products/{id}/images` এন্ডপয়েন্টও আছে — কিন্তু `path` কে স্ট্রিং হিসেবে নেয় (কোথাও থেকে JS দিয়ে বসাতে হতো), প্রোডাক্ট ফর্মে কোনো "ছবি" কার্ডই নাই |

`public/uploads/` ফোল্ডার প্রজেক্টের শুরু থেকেই আছে (`.gitignore` এ
`/public/uploads/*` বাদ দেওয়া, শুধু `.gitkeep` ট্র্যাকড) — অর্থাৎ ফাইল আপলোড
ব্যবস্থাটা প্রথম থেকেই পরিকল্পিত ছিল, এখন শুধু বাস্তবায়ন বাকি।

### ২.২ নতুন — `App\Core\Upload`

```php
Upload::save(array $file, string $folder): string   // ফেরত: '/uploads/categories/6f2a…webp'
Upload::delete(string $path): void
```

নিয়ম:

- `$file` = `$_FILES['image']` এর একটা এন্ট্রি
- অনুমোদিত টাইপ **`finfo` দিয়ে আসল MIME চেক** (এক্সটেনশন না — client-পাঠানো
  filename বিশ্বাস করা যাবে না): `image/jpeg`, `image/png`, `image/webp`
- সর্বোচ্চ সাইজ ২MB (প্রস্তাবিত — `Env` এ কনফিগারযোগ্য রাখা ভালো)
- নতুন র‍্যান্ডম ফাইলনেম (`bin2hex(random_bytes(16))` + আসল এক্সটেনশন) —
  ইউজারের দেওয়া নাম কখনো ফাইলসিস্টেম পাথে ব্যবহার হবে না (path traversal ঠেকাতে)
- ফোল্ডার (`categories/`, `products/`) না থাকলে তৈরি করে দেয়
- **সুরক্ষা:** `public/uploads/` এ কোনোভাবেই `.php` এক্সিকিউট হওয়া চলবে না —
  nginx কনফিগে (`docker/nginx/ecommerce.conf`, এই রিপোর জায়গার বাইরে) একটা
  `location ^~ /uploads/ { location ~ \.php$ { deny all; } }` জাতীয় রুল
  যোগ করা দরকার, বাস্তবায়নের সময় মনে রাখতে হবে।

### ২.৩ ক্যাটাগরি ছবি

একটা ক্যাটাগরিতে একটাই ছবি (ব্যানার/থাম্ব) — মেনুর কার্ডে দেখানোর জন্য।

- অ্যাডমিন ফর্মে (`category/index.php`) একটা ফাইল ইনপুট + ছোট প্রিভিউ + "সরান" বাটন
- নতুন ক্যাটাগরির বেলায় আগে টেক্সট ফিল্ড সেভ করে id পেতে হবে, তারপর ছবি আপলোড
  (প্রোডাক্ট ফর্মে ভ্যারিয়েন্ট বিল্ডারও একই প্যাটার্নে "আগে সেভ করুন" — ধারাবাহিক UX)
- নতুন এন্ডপয়েন্ট:

| Method | URI | কী |
|---|---|---|
| `POST` | `/api/v1/categories/{id}/image` | multipart, ফিল্ড `image` — সেভ করে পুরনোটা (থাকলে) ডিলিট |
| `DELETE` | `/api/v1/categories/{id}/image` | ফাইল ও DB কলাম দুটোই খালি করে |

### ২.৪ প্রোডাক্ট গ্যালারি — রঙ-ভিত্তিক

একই প্রোডাক্টের ২-৩টা রঙ থাকলে প্রতিটা রঙের নিজস্ব ছবি দরকার — কিন্তু সাইজ
বদলালে ছবি বদলায় না (Navy/M আর Navy/XL এর ছবি একই)। তাই ছবি ট্যাগ করা উচিত
**রঙ ভ্যালুতে**, নির্দিষ্ট ভ্যারিয়েন্ট কম্বিনেশনে (`product_variants.id`) না —
নাহলে একই Navy ছবি প্রতিটা সাইজের জন্য আলাদা করে আপলোড করা লাগত।

> **schema পরিবর্তন** (`product_images` টেবিল) — বিল্ড হয়ে গেছে, `003_catalog.sql`
> এ এবং লাইভ dev DB-তে (ALTER দিয়ে) দুই জায়গাতেই:
>
> ```sql
> ALTER TABLE product_images
>   ADD COLUMN attribute_value_id BIGINT UNSIGNED NOT NULL DEFAULT 0
>     COMMENT '0 = সাধারণ ছবি (সব রঙের জন্য); নাহলে Color অ্যাট্রিবিউটের নির্দিষ্ট ভ্যালু'
>     AFTER variant_id,
>   ADD KEY ix_image_attr_value (attribute_value_id);
> ```
>
> পুরনো `variant_id` কলাম থেকে যাবে (ব্যবহার হবে না, ভবিষ্যতে দরকার পড়লে থাকবে)।
> `003_catalog.sql` এর `CREATE TABLE IF NOT EXISTS` idempotent হলেও এটা নতুন
> কলাম যোগ — বিদ্যমান dev DB-তে হাতে `ALTER` চালাতে হবে অথবা টেবিল ড্রপ করে
> `install.php` আবার চালাতে হবে (এখনো লাইভ ডেটা নাই বলে এই সময়েই করা ভালো)।

অ্যাডমিন প্রোডাক্ট ফর্মে নতুন "ছবি" কার্ড (ভ্যারিয়েন্ট কার্ডের কাছেই):

- ড্রপডাউন **"কোন রঙ (ঐচ্ছিক)"** — প্রোডাক্টে বর্তমানে যে Color ভ্যালুগুলো
  ব্যবহৃত (§৩ এর মতো `selected_values[color_attribute_id]`), প্লাস "— সাধারণ —"
- ফাইল ইনপুট + "আপলোড" বাটন
- নিচে থাম্বনেইল গ্রিড — প্রতিটায় "প্রধান করুন" ও "ডিলিট" বাটন, রঙের নাম লেবেল
- নতুন প্রোডাক্টে (এখনো সেভ হয়নি) কার্ডটা লুকানো থাকবে — ক্যাটাগরির মতোই "সেভ
  করার পর ছবি যোগ করা যাবে"

এন্ডপয়েন্ট পরিবর্তন:

| Method | URI | পরিবর্তন |
|---|---|---|
| `POST` | `/api/v1/products/{id}/images` | এখন multipart — ফিল্ড `image` (ফাইল), `attribute_value_id?`, `alt?`, `is_primary?`। আগের JSON `path` ইনপুট বাদ |
| `DELETE` | `/api/v1/images/{id}` | অপরিবর্তিত — ফাইলও ডিস্ক থেকে মুছবে (`Upload::delete()`) |

`ProductService::addImage()` / `hydrate()` এ `path` এর জায়গায়/পাশাপাশি
`attribute_value_id` আসবে; `details()`/`decorate()` এর আউটপুটে প্রতি ছবিতে
`color_value_id` ও `color_name` যোগ হবে যাতে স্টোরফ্রন্টে "রঙ বাছলে ছবি বদলায়"
সহজে বানানো যায় (ফেজ ৩)।

---

## ৩. Purchase-এ বিক্রয় মূল্য (`sale_price`)

### নিয়ম

- **`sale_price` সবসময় প্রোডাক্ট-লেভেলে** (`products.sale_price`) — `product_variants`
  এ `sale_price` কলামই নাই (সিদ্ধান্ত ২০২৬-০৯-০৯, নিচে M-04)। একই প্রোডাক্টের
  সব রঙ/সাইজ এক দামে বিক্রি হয়; শুধু `purchase_price` (cost) আর `stock`
  ভ্যারিয়েন্ট-ভিত্তিক থাকে, কারণ ব্যাচ/রঙ ভেদে কেনা দাম সত্যিই আলাদা হতে পারে।
- `purchase_items.sale_price` — ঐচ্ছিক, `0` মানে "বিক্রয়মূল্য অপরিবর্তিত থাক"
- `> 0` দিলে সেভের সময় সরাসরি প্রোডাক্টের `sale_price` কলামে বসে যায় —
  **শেষেরটাই থাকে (overwrite)**, `purchase_price` এর মতো moving weighted-average
  **না** — কারণ এটা costing না, বিক্রয়-সিদ্ধান্ত। লাইনটা কোন ভ্যারিয়েন্টের
  (কোন রঙ/সাইজ) সেটা কোনো তফাত করে না — `variant_id` থাকুক বা না থাকুক, সবসময়
  প্রোডাক্টেই বসে।
- প্রোডাক্ট এডিট ফর্মে `sale_price` ফিল্ড আগের মতোই থাকে (দাম কার্ডে, প্রোডাক্ট-লেভেলে),
  স্বাধীনভাবে বদলানো যায় — Purchase এন্ট্রি একটা সুবিধাজনক শর্টকাট মাত্র, একমাত্র পথ না
- `purchase_price` এর নিয়ম অপরিবর্তিত ([08-purchase.md](08-purchase.md) §৫) — এটা এখনো ভ্যারিয়েন্ট-ভিত্তিক

### schema — `database/schema/004_purchase.sql`

```sql
ALTER TABLE purchase_items
  ADD COLUMN sale_price DECIMAL(20,4) NOT NULL DEFAULT 0.0000
    COMMENT '0 = অপরিবর্তিত; >0 হলে সেভের সময় প্রোডাক্ট/ভ্যারিয়েন্টের sale_price ওভাররাইট হয়'
    AFTER unit_price;
```

### `PurchaseService::applyItems()` এ নতুন ধাপ

`CostService::applyPurchase()` ও `StockService::move()` এর পরে, প্রতি লাইনে:

```php
// variant_id নির্বিশেষে সবসময় প্রোডাক্টে বসে — sale_price ভ্যারিয়েন্টে নাই
if ($line['sale_price'] > 0) {
    Product::updateById($line['product_id'], ['sale_price' => $line['sale_price']]);
}
```

`validate()` এ `sale_price` যোগ — শুধু `≥ 0` চেক, `purchase_price` এর সাথে
কোনো তুলনা (যেমন sale ≥ cost) **বাধ্যতামূলক না** — ছাড়/লস-লিডার ইচ্ছাকৃত
হতে পারে।

### Edit / Delete — sale_price রিভার্স হয় না

`purchase_price` আর stock, edit/delete এ reverse+re-apply হয় ([08-purchase.md](08-purchase.md)
§৮) — কারণ ওগুলো ট্রানজেকশনাল সত্য (কতটা স্টক এলো, কী রেটে)। কিন্তু
`sale_price` একটা সাধারণ "সর্বশেষ মান" write — কোনো ঐতিহাসিক লেজার নাই এটার
জন্য। তাই একটা Purchase এডিট/ডিলিট করলে সেই সময় বসানো `sale_price` **ফিরিয়ে
নেওয়া হয় না** (কেউ ইতিমধ্যে ওই দামে বিক্রি দেখেছে হয়তো)। এটা একটা সচেতন সীমাবদ্ধতা
(§৮ এর P-07)।

### UI — `purchase/form.php`

লাইন-বিল্ডারে (`.builder`) `b_price` এর পাশে নতুন `b_sale_price` ইনপুট
("বিক্রয় মূল্য, ঐচ্ছিক") — প্রোডাক্ট/ভ্যারিয়েন্ট বাছলে বর্তমান `sale_price`
দিয়ে প্রি-ফিল হবে (যেমন এখন `unit_price` টা `purchase_price` দিয়ে হয়)। কার্ট
টেবিলে একটা কলাম বাড়বে, সাবমিট পেলোডের প্রতি আইটেমে `sale_price` যোগ হবে।

---

## ৪. পরিবর্তিত/নতুন ফাইল

| ফাইল | পরিবর্তন |
|---|---|
| `app/Core/Upload.php` | **নতুন** — `save()`/`delete()`, MIME যাচাই, র‍্যান্ডম ফাইলনেম |
| `database/schema/003_catalog.sql` | `product_images.attribute_value_id` কলাম |
| `database/schema/004_purchase.sql` | `purchase_items.sale_price` কলাম |
| `app/Modules/Catalog/Services/CategoryService.php` | ইমেজ আপলোড/ডিলিট মেথড (Upload হেল্পার কল) |
| `app/Modules/Catalog/Services/ProductService.php` | `addImage()` এ `attribute_value_id`, ফাইল আপলোড ইন্টিগ্রেশন; `hydrate()`/`decorate()` আউটপুটে রঙের নাম |
| `app/Modules/Catalog/Api/CategoryApi.php` | `POST`/`DELETE /categories/{id}/image` (multipart) |
| `app/Modules/Catalog/Api/ProductApi.php` | `POST /products/{id}/images` multipart-এ বদল |
| `app/Modules/Catalog/Views/category/index.php` | ছবি ইনপুট + প্রিভিউ |
| `app/Modules/Catalog/Views/product/form.php` | নতুন "ছবি" কার্ড (রঙ ড্রপডাউন + আপলোড + গ্রিড) |
| `app/Modules/Purchase/Services/PurchaseService.php` | `validate()`/`applyItems()` এ `sale_price` |
| `app/Modules/Purchase/Views/purchase/form.php` | লাইন-বিল্ডার ও কার্টে বিক্রয় মূল্য কলাম |
| `doc/07-catalog.md` | §৯ এর "ছবি আপলোড" বুলেট এই ডকে লিংক করে সরানো |
| `doc/08-purchase.md` | §২ এর `purchase_items` টেবিলে `sale_price` সারি + এই ডকে লিংক |

---

## ৫. আসন্ন — ইউনিট টেস্ট

এই ডকের বাইরে, ব্যবহারকারী সামনে ইউনিট টেস্ট চালাবেন বলে জানিয়েছেন। এখনই
টেস্ট হারনেস বসানো এই কাজের স্কোপে না, কিন্তু ভবিষ্যতে শুরু করার সবচেয়ে সহজ
জায়গাগুলো — এগুলো ইতিমধ্যে DB ছাড়া টেস্টযোগ্য pure function/static method:

- `UnitService::convert()` / `toBase()` / `fromBase()`
- `ProductService::effectivePrice()` / `offerRunning()` (অফার তারিখ/দাম লজিক)
- `ProductService::cartesian()` / `signature()` (ভ্যারিয়েন্ট জেনারেশন — যদিও এখন `private`, টেস্টের জন্য দরকার হলে `syncVariants()` এর মধ্য দিয়ে ইন্টিগ্রেশন টেস্ট)
- `CostService` এর weighted-average সূত্র ([08-purchase.md](08-purchase.md) §৫)

DB-নির্ভর অংশগুলোর (Purchase/Product সেভ ফ্লো) জন্য টেস্ট DB/ফিক্সচার লাগবে —
সেটা তখন আলাদা আলোচনা।

---

## ৬. সিদ্ধান্ত

| # | সিদ্ধান্ত | কেন |
|---|---|---|
| M-01 | ছবি ট্যাগ হয় **রঙ ভ্যালুতে** (`attribute_value_id`), নির্দিষ্ট ভ্যারিয়েন্ট কম্বিনেশনে না | একই রঙের সব সাইজ একই ছবি শেয়ার করে — সাইজ-বাই-সাইজ ডুপ্লিকেট আপলোড লাগবে না |
| M-02 | ক্যাটাগরিতে একটাই ছবি, প্রোডাক্টে গ্যালারি (একাধিক) | ক্যাটাগরি ছবি শুধু মেনু/ব্যানারে; প্রোডাক্টে একাধিক অ্যাঙ্গেল/রঙ দরকার |
| M-03 | MIME `finfo` দিয়ে যাচাই, র‍্যান্ডম ফাইলনেম, `.php` এক্সিকিউশন uploads ফোল্ডারে বন্ধ | Unrestricted file upload (OWASP) ঠেকাতে — ইউজারের filename/extension বিশ্বাসযোগ্য না |
| P-07 | Purchase লাইনে ঐচ্ছিক `sale_price` — overwrite, weighted-average না | এটা costing না, বিক্রয়-সিদ্ধান্ত; শেষ যা ঠিক করা হয়েছে সেটাই থাকা স্বাভাবিক |
| P-08 | Purchase edit/delete এ `sale_price` রিভার্স হয় না | কোনো ঐতিহাসিক লেজার নাই এটার জন্য (purchase_price/stock এর মতো); ফিরিয়ে নিলে ইতিমধ্যে দেখানো দাম উল্টে যেত |
| P-09 | প্রোডাক্ট এডিট ফর্মে `sale_price` ফিল্ড থেকে যায়, restrict হয় না | Purchase এন্ট্রি একটা শর্টকাট — একমাত্র পথ বানালে ছোট দাম-সংশোধনের জন্যও পুরো purchase এডিট করা লাগত |
| M-04 | `sale_price` **শুধু `products` টেবিলে** — `product_variants` থেকে কলামটাই বাদ (২০২৬-০৯-০৯, ব্যবহারকারীর কনফার্মেশনে) | পোশাকে রঙ/সাইজ বদলালে বিক্রয়মূল্য বদলায় না, শুধু ক্রয়মূল্য/স্টক ব্যাচ-ভিত্তিক আলাদা হয়; দুটো আলাদা sale_price (variant vs product) রাখলে কোনটা "আসল" দাম সেটা নিয়ে বিভ্রান্তি হতো — যেমনটা ব্যবহারকারী প্রোডাক্ট এডিট পেজের গ্রিড দেখে হয়েছিল |

---

## ৭. বিল্ড ক্রম — ✅ সম্পূর্ণ

1. ✅ `App\Core\Upload` + `public/uploads/{categories,products}/` রানটাইমে তৈরি
2. ✅ `product_images.attribute_value_id` কলাম (ALTER, লাইভ dev DB-তেও চালানো হয়েছে) + `003_catalog.sql` হালনাগাদ
3. ✅ ক্যাটাগরি ছবি — `/image` এন্ডপয়েন্ট + ফর্ম UI (প্রিভিউ, আপলোড, সরান)
4. ✅ প্রোডাক্ট গ্যালারি — multipart এন্ডপয়েন্ট + "ছবি" কার্ড UI (রঙ ড্রপডাউন, প্রধান/ডিলিট)
5. ✅ `purchase_items.sale_price` কলাম + `004_purchase.sql` হালনাগাদ
6. ✅ `PurchaseService` — `sale_price` ভ্যালিডেশন + `applyItems()` write (+ `details()`/`formData()` তে এক্সপোজ)
7. ✅ `purchase/form.php` + `purchase/show.php` — বিক্রয় মূল্য কলাম
8. ✅ `doc/07-catalog.md` / `doc/08-purchase.md` ক্রস-লিংক হালনাগাদ, এই ডক ফাইনাল
9. ✅ যাচাই — `php -l` সব ফাইল, `install.php` (idempotent), API দিয়ে end-to-end:
   ক্যাটাগরি ছবি আপলোড→রিপ্লেস→ডিলিট (পুরনো ফাইল ঠিকমতো মোছে), প্রোডাক্ট গ্যালারি
   (Navy-ট্যাগড + সাধারণ ছবি, প্রধান বদল, ডিলিট), Purchase-এ `sale_price` দিলে
   ভ্যারিয়েন্টের দাম বদলায় আর ফাঁকা রাখলে অক্ষত থাকে, `purchase_price`
   weighted-average ঠিক থাকে, trial balance ব্যালেন্সড থাকে delete-এর পরও।
   টেস্ট ডেটা DB থেকে পরিষ্কার করা হয়েছে।

> **dev গোচা:** `public/uploads/` বাইন্ড-মাউন্টেড, host user (uid 1000) এর মালিকানায় —
> php-fpm (`www-data`) সাবফোল্ডার বানাতে পারছিল না (`mkdir(): Permission denied`)।
> `chmod -R 777 public/uploads` দিয়ে ঠিক করা হয়েছে — CLAUDE.md এ গোচা হিসেবে টুকে রাখা।

### ১০. সংশোধন (২০২৬-০৯-০৯) — `sale_price` ভ্যারিয়েন্ট থেকে সরানো

প্রোডাক্ট এডিট পেজে ব্যবহারকারী দেখলেন প্রতিটা ভ্যারিয়েন্টের (White, Black …)
নিজস্ব আলাদা "Sale" ফিল্ড আছে — যা তাঁর প্রত্যাশার সাথে মেলেনি। কনফার্ম করা
হয়েছে: **সেল প্রাইজ পুরো প্রোডাক্টে একটাই**, রঙ/সাইজ বদলালে বদলায় না (M-04)।

পরিবর্তন:
- `database/schema/003_catalog.sql` + লাইভ dev DB — `product_variants.sale_price`
  কলাম **ড্রপ করা হয়েছে** (`ALTER TABLE product_variants DROP COLUMN sale_price`)
- `ProductService::syncVariants()` — নতুন ভ্যারিয়েন্টে আর sale_price কপি হয় না
- `ProductService::updateVariant()` — গ্রিড থেকে বদলানো যায় এমন ফিল্ড এখন শুধু
  `purchase_price`, `offer_price`
- `ProductService::effectivePrice()` — `regular` সবসময় `$product['sale_price']`
  (ভ্যারিয়েন্ট fallback বাদ)
- `product/form.php` — ভ্যারিয়েন্ট গ্রিড থেকে "Sale" কলাম বাদ, একটা নোটিস: "Sale
  price is set once for the whole product…"
- `PurchaseService::applyItems()` — `sale_price > 0` হলে **সবসময়** `Product::updateById`,
  `variant_id` চেক বাদ (§৩ দেখুন)
- `PurchaseApi::formData()` / `purchase/form.php` — ভ্যারিয়েন্ট ড্রপডাউনে আর
  sale_price থাকে না; বিক্রয়মূল্য প্রি-ফিল সবসময় প্রোডাক্টের `sale_price` থেকে,
  ভ্যারিয়েন্ট বদলালে বদলায় না (শুধু Purchase Price বদলায়)
- `offer_price` অপরিবর্তিত — ভ্যারিয়েন্টে আলাদা অফার থাকা বৈধ, শুধু `sale_price` না
