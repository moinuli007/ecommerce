# ০৪ — রাউটিং

রাউট টেবিল সম্পূর্ণ কোডে, `routes/*.php` এ (সিদ্ধান্ত D-02 — erp_saas এর `module`
টেবিল ভিত্তিক রাউটিং এখানে নাই)।

লোড হওয়ার ক্রম **গুরুত্বপূর্ণ** (`bootstrap.php`):

```
routes/api.php  →  routes/admin.php  →  routes/web.php
```

`web.php` এ `/` এর মতো ব্রড রাউট থাকে, তাই সেটা সবার শেষে।

## রাউট লেখা

```php
Router::group(['prefix' => '/api/v1', 'json' => true], function (): void {
    Router::group(['guard' => 'admin'], function (): void {
        Router::get('/vouchers',      [VoucherApi::class, 'index'], ['name' => 'api.voucher.index']);
        Router::get('/vouchers/{id}', [VoucherApi::class, 'show'],  ['name' => 'api.voucher.show']);
    });
});
```

| অপশন | মানে |
|---|---|
| `prefix` | গ্রুপের URL প্রিফিক্স (নেস্ট করলে জোড়া লাগে) |
| `guard` | `guest` · `auth` · `admin` · `customer` (ডিফল্ট `guest`) |
| `json` | `true` হলে হ্যান্ডলারের রিটার্ন array JSON হয়ে যাবে |
| `name` | `Router::url('api.voucher.show', ['id' => 7])` এর জন্য |

হ্যান্ডলার সবসময় `[ClassName::class, 'staticMethod']` — কোনো কন্ট্রোলার
ইনস্ট্যান্স তৈরি হয় না (সিদ্ধান্ত D-05)।

### `{param}`

`/vouchers/{id}` → `Request::paramInt('id')` / `Request::param('code')`।

⚠ **ক্রম গুরুত্বপূর্ণ:** প্রথম যেটা ম্যাচ করে সেটাই চলে। তাই স্থির পাথ আগে,
প্লেসহোল্ডার পরে:

```php
Router::get('/vouchers/form-data', ...);   // ✅ আগে
Router::get('/vouchers/{id}',      ...);   // ✅ পরে — নাহলে "form-data" কে id ভাববে
```

### `guard` কী করে

| guard | লগইন নাই | লগইন আছে, ভুল টাইপ |
|---|---|---|
| `guest` | চলবে | চলবে |
| `auth` | 401 | চলবে |
| `admin` | 401 | 403 |
| `customer` | 401 | 403 |

JSON রাউটে 401/403 রেসপন্স `status: 0` + `m` সহ যায়।

পেজ রাউটে 401 হলে **যে এলাকা থেকে এসেছে সেই এলাকার** লগইন পেজে যাবে —
`/admin/*` → `/admin/login`, স্টোরফ্রন্ট → `/login`। সাথে
`?next=<মূল পাথ>` যায়, লগইনের পর ঠিক ওখানেই ফিরে আসে।

`next` শুধু নিজের সাইটের ভেতরের পাথ হতে পারে (`LoginController::safeNext()`) —
`//evil.com` বা `https://evil.com` দিয়ে বাইরে পাঠানো (open redirect) ব্লকড।

### ব্রাউজার ফর্ম থেকে PUT/DELETE

HTML ফর্ম শুধু GET/POST পারে। তাই POST-এ `_method=DELETE` ফিল্ড পাঠালে
Router সেটাকে DELETE হিসেবে ধরবে।

---

## বর্তমান রাউট টেবিল (ফেজ ১)

`Router::all()` থেকে সরাসরি নেওয়া — নতুন রাউট যোগ করলে এই টেবিল আপডেট করবেন।

### API — `/api/v1/*` (রেসপন্স JSON)

| Method | URI | guard | হ্যান্ডলার |
|---|---|---|---|
| `POST` | `/api/v1/auth/login` | guest | `Auth\Api\AuthApi::login` |
| `POST` | `/api/v1/auth/logout` | auth | `Auth\Api\AuthApi::logout` |
| `GET` | `/api/v1/auth/me` | auth | `Auth\Api\AuthApi::me` |
| `GET` | `/api/v1/dashboard` | admin | `Dashboard\Api\DashboardApi::index` |
| `GET` | `/api/v1/vouchers` | admin | `Account\Api\VoucherApi::index` |
| `GET` | `/api/v1/vouchers/form-data` | admin | `Account\Api\VoucherApi::formData` |
| `GET` | `/api/v1/vouchers/code/{code}` | admin | `Account\Api\VoucherApi::showByCode` |
| `GET` | `/api/v1/vouchers/{id}` | admin | `Account\Api\VoucherApi::show` |
| `POST` | `/api/v1/vouchers` | admin | `Account\Api\VoucherApi::store` |
| `POST` | `/api/v1/vouchers/opening` | admin | `Account\Api\VoucherApi::storeOpening` |
| `PUT` | `/api/v1/vouchers/{id}` | admin | `Account\Api\VoucherApi::update` |
| `DELETE` | `/api/v1/vouchers/{id}` | admin | `Account\Api\VoucherApi::destroy` |
| `GET` | `/api/v1/ledgers` | admin | `Account\Api\LedgerApi::index` |
| `GET` | `/api/v1/ledgers/tree` | admin | `Account\Api\LedgerApi::tree` |
| `GET` | `/api/v1/ledgers/options` | admin | `Account\Api\LedgerApi::options` |
| `POST` | `/api/v1/ledgers` | admin | `Account\Api\LedgerApi::store` |
| `GET` | `/api/v1/ledgers/{id}/statement` | admin | `Account\Api\LedgerApi::statement` |
| `GET` | `/api/v1/ledgers/{id}/balance` | admin | `Account\Api\LedgerApi::balance` |
| `GET` | `/api/v1/reports/trial-balance` | admin | `Account\Api\LedgerApi::trialBalance` |

#### ক্যাটালগ (সবগুলোতে `guard: admin`) — বিস্তারিত [07-catalog.md](07-catalog.md)

| Method | URI | হ্যান্ডলার |
|---|---|---|
| `GET` | `/api/v1/categories` | `Catalog\Api\CategoryApi::index` |
| `GET` | `/api/v1/categories/{id}` | `Catalog\Api\CategoryApi::show` |
| `POST` | `/api/v1/categories` | `Catalog\Api\CategoryApi::store` |
| `PUT` | `/api/v1/categories/{id}` | `Catalog\Api\CategoryApi::update` |
| `DELETE` | `/api/v1/categories/{id}` | `Catalog\Api\CategoryApi::destroy` |
| `GET` | `/api/v1/units` | `Catalog\Api\UnitApi::index` |
| `GET` | `/api/v1/units/convert` | `Catalog\Api\UnitApi::convert` |
| `POST` | `/api/v1/units` | `Catalog\Api\UnitApi::store` |
| `DELETE` | `/api/v1/units/{id}` | `Catalog\Api\UnitApi::destroy` |
| `POST` | `/api/v1/unit-groups` | `Catalog\Api\UnitApi::storeGroup` |
| `DELETE` | `/api/v1/unit-groups/{id}` | `Catalog\Api\UnitApi::destroyGroup` |
| `GET` | `/api/v1/attributes` | `Catalog\Api\AttributeApi::index` |
| `POST` | `/api/v1/attributes` | `Catalog\Api\AttributeApi::store` |
| `DELETE` | `/api/v1/attributes/{id}` | `Catalog\Api\AttributeApi::destroy` |
| `POST` | `/api/v1/attribute-values` | `Catalog\Api\AttributeApi::storeValue` |
| `DELETE` | `/api/v1/attribute-values/{id}` | `Catalog\Api\AttributeApi::destroyValue` |
| `GET` | `/api/v1/products` | `Catalog\Api\ProductApi::index` |
| `GET` | `/api/v1/products/form-data` | `Catalog\Api\ProductApi::formData` |
| `GET` | `/api/v1/products/slug/{slug}` | `Catalog\Api\ProductApi::showBySlug` |
| `GET` | `/api/v1/products/{id}` | `Catalog\Api\ProductApi::show` |
| `POST` | `/api/v1/products` | `Catalog\Api\ProductApi::store` |
| `PUT` | `/api/v1/products/{id}` | `Catalog\Api\ProductApi::update` |
| `DELETE` | `/api/v1/products/{id}` | `Catalog\Api\ProductApi::destroy` |
| `POST` | `/api/v1/products/{id}/variants` | `Catalog\Api\ProductApi::syncVariants` |
| `POST` | `/api/v1/products/{id}/images` | `Catalog\Api\ProductApi::addImage` |
| `PUT` | `/api/v1/variants/{id}` | `Catalog\Api\ProductApi::updateVariant` |
| `DELETE` | `/api/v1/images/{id}` | `Catalog\Api\ProductApi::deleteImage` |

### Admin — `/admin/*` (রেসপন্স HTML)

| Method | URI | guard | হ্যান্ডলার |
|---|---|---|---|
| `GET` | `/admin/login` | guest | `Auth\Controllers\LoginController::form` |
| `POST` | `/admin/login` | guest | `Auth\Controllers\LoginController::submit` |
| `GET`/`POST` | `/admin/logout` | guest | `Auth\Controllers\LoginController::logout` |
| `GET` | `/admin` | admin | `Dashboard\Controllers\DashboardController::index` |
| `GET` | `/admin/profile` | admin | `Auth\Controllers\ProfileController::show` |
| `GET` | `/admin/products` | admin | `Catalog\Controllers\CatalogController::products` |
| `GET` | `/admin/products/create` | admin | `Catalog\Controllers\CatalogController::productCreate` |
| `GET` | `/admin/products/{id}/edit` | admin | `Catalog\Controllers\CatalogController::productEdit` |
| `GET` | `/admin/categories` | admin | `Catalog\Controllers\CatalogController::categories` |
| `GET` | `/admin/attributes` | admin | `Catalog\Controllers\CatalogController::attributes` |
| `GET` | `/admin/units` | admin | `Catalog\Controllers\CatalogController::units` |
| `GET` | `/admin/vouchers` | admin | `Account\Controllers\VoucherController::index` |
| `GET` | `/admin/vouchers/entry` | admin | `Account\Controllers\VoucherController::entry` |
| `GET` | `/admin/vouchers/{id}` | admin | `Account\Controllers\VoucherController::show` |
| `GET` | `/admin/ledgers` | admin | `Account\Controllers\LedgerController::index` |
| `GET` | `/admin/ledgers/{id}/statement` | admin | `Account\Controllers\LedgerController::statement` |
| `GET` | `/admin/reports/trial-balance` | admin | `Account\Controllers\LedgerController::trialBalance` |

### Web — স্টোরফ্রন্ট

| Method | URI | guard | হ্যান্ডলার |
|---|---|---|---|
| `GET` | `/` | guest | প্লেসহোল্ডার (ফেজ ২) |

ফেজ ২–৩ এ যোগ হবে: `/collections/{slug}`, `/products/{slug}`, `/cart`,
`/checkout`, `/account/orders`।

---

## নতুন মডিউলের রাউট যোগ করার নিয়ম

১. `app/Modules/<Module>/Api/<X>Api.php` — payload array ফেরত দেওয়া static মেথড
২. `routes/api.php` এ HTTP রাউট (`json: true`)
৩. দরকার হলে `app/Modules/<Module>/Controllers/<X>Controller.php` —
   **একই Api মেথড কল করে** ভিউ রেন্ডার করবে (সিদ্ধান্ত D-04)
৪. `routes/admin.php` বা `routes/web.php` এ পেজ রাউট
৫. এই ডকের টেবিল আপডেট
