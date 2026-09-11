# ১১ — কাস্টমার সেলফ-সার্ভিস অ্যাকাউন্ট (ফেজ ৩.৫)

> **স্ট্যাটাস:** ✅ বিল্ড ও যাচাই করা — এই ডকের ডিজাইন অনুযায়ীই বাস্তবায়িত।
> রাউট নামকরণে একটাই বিচ্যুতি: `/account/*` প্রিফিক্সের বদলে flat
> `/login`/`/register`/`/profile`/`/logout` (§৮, C-11) — বিল্ডের সময়
> ধরা পড়ল `Router::deny()` আগে থেকেই `/login` হার্ডকোড করে রেখেছিল।

## ০. কেন আলাদা ফেজ

[10-storefront-order.md](10-storefront-order.md) §৩ এ `customers` টেবিল
ডিজাইনের সময়ই `user_id` কলাম রাখা হয়েছিল ("0 = গেস্ট, নইলে FK → users.id")
আর স্পষ্ট লেখা ছিল — "পরে কেউ ওই ফোন নাম্বার দিয়ে রেজিস্টার করলে `user_id`
বসিয়ে সেই একই `customers` রো টাই হয়ে যাবে"। কিন্তু ফেজ ৩ এ শুধু **গেস্ট
চেকআউট** বিল্ড হয়েছিল — রেজিস্ট্রেশন/লগইন/প্রোফাইল/ডিলিটের কোনো UI বা API
তখন তৈরি হয়নি (`AuthApi` এ শুধু admin-focused `login()`/`logout()`/`me()`
ছিল, `register()` কখনো ছিল না)। এই ফেজ সেই প্রতিশ্রুতিটা পূরণ করে।

**স্কিমা আগে থেকেই প্রস্তুত** — `users.type = 3` (customer), `users.password`
nullable, `customers.user_id` — সব [001_core.sql](../database/schema/001_core.sql)
আর `customers` এ ফেজ ৩ থেকেই আছে। এই ফেজে **কোনো নতুন টেবিল/কলাম লাগে না**,
শুধু অ্যাপ্লিকেশন লেয়ার (Api/Controller/View/route)।

`App\Core\Auth` এও `TYPE_CUSTOMER`/`isCustomer()` এবং `Router::guard()` এ
`'customer'` কেসও আগে থেকেই বসানো ছিল (ফেজ ১, ব্যবহার শুরু হয়নি) — এই ফেজে
প্রথমবার আসলে ব্যবহার হচ্ছে।

---

## ১. কাঠামো এক নজরে

```
users (type=3, password হ্যাশ)  ──┐
                                   │ user_id (রেজিস্ট্রেশনে বসে)
                                   ▼
customers (phone দিয়ে ডিডুপ) ──< orders / a_ledgers

guard: guest    → রেজিস্ট্রেশন, লগইন ফর্ম
guard: auth     → /me, প্রোফাইল আপডেট, পাসওয়ার্ড বদল (যেকোনো লগইন ইউজার —
                   API ইচ্ছাকৃতভাবে admin/staff দিয়েও কল করা যায়, যদিও এই
                   ফেজে admin UI তে ফর্ম বসানো স্কোপে নেই, নিচে C-06)
guard: customer → অ্যাকাউন্ট ডিলিট (শুধু কাস্টমারের নিজের ওপর প্রযোজ্য একশন)
```

দুটো টেবিল ইচ্ছাকৃতভাবেই আলাদা থাকছে — `users` = লগইন-পরিচয়
(email/phone/password), `customers` = হিসাব-পক্ষ (ledger, order দেদুপ,
opening balance)। একজন কাস্টমারের প্রোফাইল বদলালে **দুটো রো-ই** সিঙ্কে
আপডেট হবে (§৫)।

---

## ২. রেজিস্ট্রেশন

`POST /api/v1/auth/register` (guard: guest) — ইনপুট: `name`, `phone`,
`email?`, `password`, `password_confirmation`।

**ফোনই একমাত্র ডিডুপ কি** — [10-storefront-order.md](10-storefront-order.md)
এর O-08 এর ("ফোন নাম্বার প্রাকৃতিক ইউনিক আইডি") ঠিক ধারাবাহিকতা, ইমেইল না
(ইমেইল ঐচ্ছিক, `customers.email` এও তাই)।

ধাপ (`AuthApi::register()`, `DB::transaction()`):

1. `phone` দিয়ে `users` এ খোঁজা:
   - পাওয়া গেলে ও `isActive = 1` → এরর *"This phone number is already
     registered. Please log in instead."*
   - পাওয়া গেলে ও `isActive = 0` (আগে ডিলিট করা অ্যাকাউন্ট, §৭) →
     **রিঅ্যাক্টিভেট** — নতুন পাসওয়ার্ড হ্যাশ বসিয়ে, `isActive = 1` করে
     পুরোনো `id` টাই পুনর্ব্যবহার (নতুন রো না) — নিচে C-04
   - না পেলে → নতুন `users` রো (`type = 3`)
2. `email` খালি না হলে অন্য কোনো সক্রিয় ইউজারের সাথে না মেলে তা চেক
   (`uq_users_email` এ DB error এর বদলে বন্ধুত্বপূর্ণ মেসেজ)
3. `CustomerService::findOrCreateByPhone(['phone', 'name', 'email',
   'user_id' => $userId])` কল — **এই মেথডই এখন `user_id` লিংক করে** (§৯
   C-01, বিদ্যমান `customers` রো থাকলে তার সাথে টাই হয়, আলাদা রো তৈরি হয় না)
4. `Auth::loginSession()` — রেজিস্ট্রেশনের পরপরই লগইন অবস্থা

> **গোচা — email `NULL` বনাম `''`:** `users.email`/`users.phone` কলাম
> nullable ও UNIQUE (`uq_users_email`, `uq_users_phone`)। MySQL ইউনিক
> ইনডেক্সে একাধিক `NULL` OK, কিন্তু একাধিক `''` (খালি স্ট্রিং) **না** —
> দ্বিতীয় ইমেইল-ছাড়া রেজিস্ট্রেশনেই `Duplicate entry ''` এরর দেবে। তাই
> ইমেইল না দিলে `users.email` এ literal PHP `null` বসাতে হবে, `customers.email`
> এর মতো `''` ডিফল্ট **না** (customers টেবিলে এই সমস্যা নাই কারণ ওখানে
> `email` এ কোনো UNIQUE constraint নাই, শুধু `phone` এ আছে)।

**পাসওয়ার্ডের দৈর্ঘ্য `Validator` এর `min` রুল দিয়ে চেক হবে না** — `min`/`max`
সংখ্যাসদৃশ ভ্যালুকে *সংখ্যা* হিসেবে তুলনা করে, স্ট্রিং দৈর্ঘ্য হিসেবে না
(`Validator::compare()`, exactly [CustomerApi](../app/Modules/Sale/Api/CustomerApi.php)
এর phone-এ `max` না দেওয়ার কমেন্টে যে গোচার কথা বলা আছে)। একটা পুরো-সংখ্যা
পাসওয়ার্ড যেমন `"12345"` কে `min:6` দিলে সংখ্যা হিসেবে `12345 < 6` মিথ্যা
ধরে **পাস করিয়ে দিত**, যদিও এটা মাত্র ৫ ক্যারেক্টার। তাই দৈর্ঘ্য
`mb_strlen()` দিয়ে ম্যানুয়ালি চেক হবে API কোডেই, `Validator::check()` রুল
স্ট্রিং এ না।

---

## ৩. লগইন

`POST /api/v1/auth/login` — **বিদ্যমান, অপরিবর্তিত।** `AuthApi::login()`
আগে থেকেই জেনেরিক (`type` নির্বিশেষে email/phone + password), অ্যাডমিন
লগইন (`LoginController`) এটাই কল করে আসছে। কাস্টমার লগইনও এটাই কল করবে —
নতুন কোনো `customerLogin()` মেথড লাগছে না।

**তবে storefront-সাইডে একটা সিমেট্রিক চেক লাগবে** — `LoginController::submit()`
লগইনের পর `Auth::isAdmin()` না হলে লগআউট করে রিজেক্ট করে (কাস্টমার যেন
`/admin/login` দিয়ে ঢুকতে না পারে); ঠিক আয়নায় storefront লগইনেও
`Auth::isCustomer()` না হলে (কেউ অ্যাডমিন/স্টাফ ক্রেডেনশিয়াল দিয়ে
`/login` এ ঢুকলে) লগআউট করে *"Please use the admin panel to log
in."* দেখানো হবে (§৯ C-05)। যেহেতু `/login` পেজ JS দিয়েই
`/api/v1/auth/login` কল করে (§৯), এই চেকটা **ক্লায়েন্ট-সাইড JS এ** বসবে —
রেসপন্সের `user.is_admin` (AuthApi::publicUser() এ আগে থেকেই আছে) সত্যি হলে
সাথে সাথে `/api/v1/auth/logout` কল করে এরর টোস্ট দেখাবে, রিডাইরেক্ট করবে না।

**কার্ট** — লগইন সফল হলে বর্তমান `cart_token` কুকির কার্টে (যদি
`customer_id = 0` থাকে) কাস্টমার আইডি বসানো হবে, একটা সিম্পল
`UPDATE` — এটা [10-storefront-order.md](10-storefront-order.md) §৪ এর
কমেন্ট ("customer_id বসে চেকআউট/লগইনের সময়") আসলে বাস্তবায়ন করে। **ক্রস-ডিভাইস
কার্ট মার্জ এখনো v1 স্কোপে না** (সেই ডকেই আগে থেকে বলা ছিল) — অন্য ডিভাইসে
আগে থেকে থাকা কার্ট এই লগইনে merge হবে না, শুধু বর্তমান ব্রাউজারের গেস্ট
কার্টে ট্যাগ বসবে।

---

## ৪. প্রোফাইল দেখা ও আপডেট

`GET /api/v1/auth/me` — **বিদ্যমান, অপরিবর্তিত।**

`PUT /api/v1/auth/profile` (guard: **auth**, customer-নির্দিষ্ট না) —
ইনপুট: `name`, `phone`, `email?`। **ইচ্ছাকৃতভাবে জেনেরিক** — API লেভেলে
যেকোনো লগইন-করা ইউজার (admin/staff/customer) নিজের নাম/ফোন/ইমেইল বদলাতে
পারবে। [ProfileController](../app/Modules/Auth/Controllers/ProfileController.php)
এর কমেন্টে থাকা "পাসওয়ার্ড বদলানো ও এডিট ফেজ ৭-এ যুক্ত হবে" এর ব্যাকএন্ড
এখন রেডি — কিন্তু admin UI (`/admin/profile` পেজে এডিট/পাসওয়ার্ড ফর্ম বসানো)
এই ফেজের স্কোপে **নেই**, এখানে শুধু স্টোরফ্রন্ট কাস্টমার UI বানানো হয়েছে
(§৯ C-06)। admin এর প্রোফাইল পেজে ফর্ম বসানো পরে একটা ছোট, আলাদা কাজ।

`AuthApi::updateProfile()`:

1. `users` রো আপডেট (name/phone/email — phone/email এর uniqueness চেক,
   নিজের id বাদে অন্য কারো সাথে না মেলে)
2. **`Auth::isCustomer()` হলে** সাথে সাথে সেই কাস্টমারের `customers` রো-ও
   (`Customer::find` by `user_id`) name/phone/email দিয়ে সিঙ্ক করা —
   admin/staff এর কোনো `customers` রো নেই, তাই ওদের ক্ষেত্রে এই ধাপ স্কিপ
3. দুটো আপডেটই এক `DB::transaction()` এ — অর্ধেক সিঙ্ক হয়ে বাকিটা না হওয়া
   এড়াতে

`customers.phone` এ UNIQUE constraint আছে — কাস্টমার ফোন বদলালে সেই নতুন
ফোনে আগে থেকে অন্য কোনো (গেস্ট) `customers` রো থাকলে ধাক্কা লাগবে; v1 এ
এটা সাধারণ এরর মেসেজ দিয়েই সামলানো হচ্ছে ("This phone number is already
in use."), দুই কাস্টমার রো মার্জ করার জটিলতা স্কোপে নেই।

---

## ৫. পাসওয়ার্ড বদল

`PUT /api/v1/auth/password` (guard: auth) — ইনপুট: `current_password`,
`password`, `password_confirmation`।

`AuthApi::changePassword()`: `password_verify(current_password, ...)`
মিলতে হবে (ভুল হলে *"Current password is incorrect."*), নতুন পাসওয়ার্ড
`password_confirmation` এর সাথে মিলতে হবে (`Validator` এ `same`/`confirmed`
রুল নাই — ম্যানুয়াল `!==` চেক), দৈর্ঘ্য ম্যানুয়াল `mb_strlen() >= 6` (§২
এর একই গোচা)।

---

## ৬. অ্যাকাউন্ট ডিলিট

`DELETE /api/v1/auth/account` (guard: **customer** — শুধু কাস্টমার, admin/
staff নিজের অ্যাকাউন্ট এভাবে ডিলিট করতে পারবে না)।

**সফট-ডিলিট, হিসাব সুরক্ষিত রেখে** — `CustomerService::delete()` (অ্যাডমিন
সাইড, hard delete) অর্ডার থাকলে আটকে দেয়; বেশিরভাগ রেজিস্টার্ড কাস্টমারেরই
অন্তত একটা অর্ডার থাকবে, তাই সেই মেথড এখানে পুনর্ব্যবহার করা যায় না — আর
করা উচিতও না, কারণ পুরোনো অর্ডার/লেজার এন্ট্রি অ্যাডমিনের হিসাবরক্ষণের
জন্য থেকে যেতে হবে (`orders.customer_id` এর FK `ON DELETE RESTRICT` এটাই
বলে)।

`AuthApi::deleteAccount()`:

1. `users.isActive = 0` (শুধু **users** রো — `customers`/`orders`/লেজার
   কিছুই ছোঁয়া হয় না, অ্যাডমিন পরে ইতিহাস দেখতে পারবে)
2. সেই ইউজারের সব `api_tokens` ডিলিট (মোবাইল সেশনও সাথে সাথে অকার্যকর)
3. `Auth::logout()` — সেশন ক্লিয়ার
4. সফল হলে *"Your account has been deleted."*

`isActive = 0` মানে `AuthApi::login()` তখন থেকে *"Incorrect username or
password."*-ই দেখাবে (কোড বদলাতে হয়নি — লগইন এমনিতেই `isActive` চেক করে)।
পরে একই ফোনে আবার রেজিস্টার করতে চাইলে §২ ধাপ ১ এর রিঅ্যাক্টিভেশন পথে
পুরোনো `customers`/অর্ডার ইতিহাসের সাথেই আবার যুক্ত হয়ে যাবে — কেউ একবার
অ্যাকাউন্ট ডিলিট করলে চিরতরে সেই ফোন নাম্বার দিয়ে আর কখনো অ্যাকাউন্ট
খুলতে না পারা একটা ডেড-এন্ড, সেটা এড়ানো হচ্ছে।

**অ্যাডমিনের hard-delete (`CustomerApi::destroy()`) অপরিবর্তিত থাকছে** —
আলাদা, বিদ্যমান, অ্যাডমিন-শুধু ক্ষমতা (টেস্ট/স্প্যাম কাস্টমার সাফ করতে,
অর্ডার না থাকলেই)।

---

## ৭. HTTP API

| Method | URI | Guard | Body | কী |
|---|---|---|---|---|
| `POST`   | `/api/v1/auth/register` | guest    | `name, phone, email?, password, password_confirmation` | নতুন অ্যাকাউন্ট + সাথে সাথে লগইন |
| `POST`   | `/api/v1/auth/login`    | guest    | `username, password, with_token?` | বিদ্যমান, অপরিবর্তিত |
| `POST`   | `/api/v1/auth/logout`   | auth     | — | বিদ্যমান, অপরিবর্তিত |
| `GET`    | `/api/v1/auth/me`       | auth     | — | বিদ্যমান, অপরিবর্তিত |
| `PUT`    | `/api/v1/auth/profile`  | auth     | `name, phone, email?` | নাম/ফোন/ইমেইল আপডেট (§৪) |
| `PUT`    | `/api/v1/auth/password` | auth     | `current_password, password, password_confirmation` | পাসওয়ার্ড বদল (§৫) |
| `DELETE` | `/api/v1/auth/account`  | customer | — | সফট-ডিলিট (§৬) |

---

## ৮. ওয়েব রাউট ও ভিউ

| Method | URI | Guard | কী |
|---|---|---|---|
| `GET` | `/register` | guest    | রেজিস্ট্রেশন ফর্ম |
| `GET` | `/login`    | guest    | লগইন ফর্ম — **নাম হার্ডকোড করা আছে** `Router::deny()` এ (§৯ C-11), বদলানো যাবে না |
| `ANY` | `/logout`   | —        | `Auth::logout()` + redirect `/` (`LoginController::logout()` এর প্যাটার্নে) |
| `GET` | `/profile`  | customer | প্রোফাইল দেখা + এডিট + পাসওয়ার্ড + ডিলিট — একই পেজে তিনটা ফর্ম |

`/account/*` প্রিফিক্স ইচ্ছাকৃতভাবে ব্যবহার হয়নি — `App\Core\Router::deny()`
(guard 401) অ্যাডমিন-বাদে সব পাথের জন্য **হার্ডকোড করে** `/login` এ
রিডাইরেক্ট করে (`?next=` সহ, ফেজ ১ থেকেই কোডে ছিল, আগে ব্যবহারই হয়নি) —
তাই এই চারটা রাউট flat রাখা হলো (§৯ C-11)।

চারটাই **GET-only কন্ট্রোলার মেথড** — চেকআউট পেজের প্যাটার্নে (§৯ C-08):
ফর্ম সাবমিট সার্ভার-সাইড redirect দিয়ে না, বরং পেজের নিজস্ব JS
`window.accountApi()` (নিচে) দিয়ে সরাসরি JSON এন্ডপয়েন্ট কল করে, সফল
হলে ক্লায়েন্ট-সাইডেই `location.href` বদলায় — ঠিক `checkout.php` যেভাবে
`/api/v1/storefront/checkout` কল করে। তাই আলাদা কোনো
`registerSubmit()`/`loginSubmit()` সার্ভার মেথড লাগে না।

**`resources/views/layouts/storefront.php` এ Account নেভ লিংক** —
`.site-actions` এ Cart এর পাশে যোগ হবে: গেস্ট হলে "Login" লিংক
(`/login`), কাস্টমার লগইন থাকলে নাম + "My Account"
(`/profile`) + "Logout"। এই তথ্য `StorefrontController::common()`
থেকে আসবে (এখন `private`, `public` করে `authCustomer` কী যোগ হবে) — যেহেতু
নেভ লেআউটে থাকে, **প্রতিটা** স্টোরফ্রন্ট পেজেই লাগবে, শুধু account পেজে না।

**JS হেল্পার সাধারণীকরণ** — বিদ্যমান `window.storefrontApi()`
হার্ডকোড করা `/api/v1/storefront` prefix ব্যবহার করে; নতুন এন্ডপয়েন্ট
`/api/v1/auth/*` এ, ভিন্ন prefix। তাই ভেতরের fetch/toast লজিকটা একটা
প্রাইভেট `callApi(base, path, body, method)` এ বার করে `storefrontApi`
(base=`/api/v1/storefront`, অপরিবর্তিত আচরণ) আর নতুন `window.accountApi`
(base=`/api/v1/auth`) — দুটোই তার পাতলা wrapper (§৯ C-09)।

**মডিউল কাঠামো:**

```
app/Modules/Auth/
└── Api/AuthApi.php          + register() / updateProfile() / changePassword() / deleteAccount()

app/Modules/Sale/
├── Controllers/AccountController.php   নতুন — registerForm/loginForm/logout/profile
├── Services/CustomerService.php        findOrCreateByPhone() এ user_id লিংক যোগ (§৯ C-01)
├── Controllers/StorefrontController.php  common() public + authCustomer কী
└── Views/storefront/
    ├── register.php  নতুন
    ├── login.php     নতুন
    └── profile.php   নতুন — প্রোফাইল + পাসওয়ার্ড + ডিলিট, তিন ফর্ম
```

`AccountController` কেন Auth মডিউলে না, Sale এ — কারণ এটা storefront-এর
পেজ (`layouts/storefront` রেন্ডার করে, `StorefrontController` এর ঠিক
পাশে), `LoginController`/`ProfileController` (Auth মডিউলে) দুটোই
admin-নির্দিষ্ট। Sale মডিউল থেকে Auth মডিউলের `AuthApi` কল করা নতুন কোনো
প্যাটার্ন না — `CustomerService` ইতিমধ্যে Account মডিউলের `Voucher`/
`LedgerAccounts` cross-module কল করে ([10-storefront-order.md](10-storefront-order.md)
§৩)।

---

## ৯. সিদ্ধান্ত

| # | সিদ্ধান্ত | কেন |
|---|---|---|
| C-01 | `CustomerService::findOrCreateByPhone()` এক্সটেন্ড করা হলো (`user_id` প্যারামিটার + বিদ্যমান রো পেলে লিংক করা) — নতুন আলাদা মেথড না | এটাই ইতিমধ্যে "phone দিয়ে ডিডুপ"-এর ঘোষিত এন্ট্রি পয়েন্ট ([10-storefront-order.md](10-storefront-order.md) §৩); `user_id` না থাকলে (গেস্ট চেকআউট কল সাইট) আচরণ অপরিবর্তিত থাকে (backward-compatible, O-14 এর একই স্টাইল) |
| C-02 | রেজিস্ট্রেশনের ডিডুপ কি শুধু `phone`, `email` না | O-08 এর ধারাবাহিকতা — ফোন প্রাকৃতিক ইউনিক আইডি এই বাজারে, ইমেইল সবসময় থাকে না |
| C-03 | `users.email` খালি হলে PHP `null` বসে, `''` না | `uq_users_email` ইউনিক ইনডেক্স — MySQL এ একাধিক `NULL` OK কিন্তু একাধিক `''` না; `customers.email` এ এই সমস্যা নাই (UNIQUE নাই ওখানে) তাই দুই টেবিলের কনভেনশন এখানে আলাদা |
| C-04 | ডিলিট করা (`isActive=0`) অ্যাকাউন্ট একই ফোনে আবার রেজিস্টার করলে ব্লক না করে রিঅ্যাক্টিভেট হয় | নাহলে ডিলিট = সেই ফোন নাম্বার দিয়ে চিরতরে অ্যাকাউন্ট বন্ধ, যেটা সাপোর্ট-নির্ভরতা তৈরি করত |
| C-05 | Storefront লগইনে `Auth::isCustomer()` না হলে (admin/staff ক্রেডেনশিয়াল) লগআউট করে রিজেক্ট, ক্লায়েন্ট-সাইড JS এ | `LoginController::submit()` এর আয়না — সেটা `isAdmin()` না হলে রিজেক্ট করে; সিমেট্রি বজায় রাখা, `/api/v1/auth/login` নিজে জেনেরিকই থাকছে (মোবাইল অ্যাপ/ভবিষ্যৎ ব্যবহারের জন্য) |
| C-06 | `updateProfile()`/`changePassword()` guard **auth** (customer-নির্দিষ্ট না), `customers` সিঙ্ক শুধু `isCustomer()` হলে কন্ডিশনাল — কিন্তু admin UI (`/admin/profile` ফর্ম) এই ফেজের স্কোপে বানানো হয়নি, শুধু API | একই দরকার (নাম/পাসওয়ার্ড বদল) admin এর জন্য দুইবার আলাদা কোডে লেখার কোনো কারণ নাই — ব্যাকএন্ড এখন রেডি রাখা হলো যাতে `ProfileController` এর কমেন্টে থাকা "ফেজ ৭" TODO পরে UI বসানো মাত্রই মিটে যায়; UI নিজে আলাদা, ছোট কাজ, ব্যবহারকারীর চাওয়া (কাস্টমার রেজিস্ট্রেশন/লগইন/প্রোফাইল/ডিলিট) এর অংশ না |
| C-07 | অ্যাকাউন্ট ডিলিট সবসময় soft (`users.isActive=0`) — অর্ডার আছে কি নাই তা দিয়ে ব্রাঞ্চ না, hard delete কখনোই না | সিদ্ধান্ত এক ও অভিন্ন কোড-পথ রাখা সহজ রাখে; `customers`/`orders`/লেজার রো অক্ষত থাকা accounting integrity-র জন্য জরুরি — `CustomerService::delete()` এর existing hard-delete admin-শুধু ক্ষমতা হিসেবেই থেকে যায়, অপরিবর্তিত |
| C-08 | Account পেজ (register/login/profile) GET-only কন্ট্রোলার, ফর্ম সাবমিট ক্লায়েন্ট-সাইড JS দিয়ে JSON API কল করে | checkout.php এর প্রতিষ্ঠিত প্যাটার্নের সাথে সামঞ্জস্যপূর্ণ (admin এর সার্ভার-POST+redirect প্যাটার্নের বদলে) — কম কোড, redirect/flash আলাদা করে ওয়্যার করা লাগে না |
| C-09 | `storefrontApi()` এর ভেতরের fetch/toast লজিক `callApi(base, ...)` এ বার করে `storefrontApi`/নতুন `accountApi` দুটোই তার wrapper বানানো হলো | `/api/v1/auth/*` ভিন্ন URL prefix এ, কিন্তু fetch/error/toast লজিক ডুপ্লিকেট করার কোনো কারণ নাই |
| C-10 | লগইনে বর্তমান কার্টে `customer_id` ট্যাগ বসে (single UPDATE), কিন্তু ক্রস-ডিভাইস কার্ট মার্জ হয় না | [10-storefront-order.md](10-storefront-order.md) §৪ এর প্রতিশ্রুতি পূরণ, কিন্তু সেই ডকেই বলা "ক্রস-ডিভাইস কার্ট মার্জ v1 স্কোপে নেই" এর বাইরে যাওয়া হচ্ছে না |
| C-11 | রাউট `/account/*` প্রিফিক্সে না, flat `/login`/`/register`/`/profile`/`/logout` — আর লগইন পেজ `next` কোয়েরি প্যারামিটার সাপোর্ট করে (শুধু `/` দিয়ে শুরু, `//` দিয়ে না — `LoginController::safeNext()` এর একই ওপেন-রিডাইরেক্ট সুরক্ষা ক্লায়েন্ট-সাইড JS এ) | `App\Core\Router::deny()` ফেজ ১ থেকেই 401 এ (অ্যাডমিন-বাদে সব পাথ) হার্ডকোড করে `/login?next=<path>` এ রিডাইরেক্ট করে — আবিষ্কার এই ফেজেই হলো, কোডটা আগে কখনো এক্সারসাইজ হয়নি; এই এক্সিস্টিং কনভেনশন মেনে চলাই সঠিক, নতুন `/account` স্কিম চাপিয়ে দেওয়া না |
| C-12 | `StorefrontController::common()` এ সেশন-ফ্ল্যাশ মেসেজ (`Message::flush()`) সবার **আগে** ধরা হয়, `CategoryApi::publicTree()`/`CartApi::show()` কলের **পরে** না | `Response::payload()` নিজেই ভেতরে `Message::flush()` করে (`m` কি বানাতে) — তাই `common()` এর ভেতরের ওই দুটো Api কল আগেই কল করলে ফ্ল্যাশ করা মেসেজ (যেমন লগআউটের "Logged out.") নীরবে খালি হয়ে যেত, পেজে কখনো দেখাই যেত না; এটা প্রতিটা স্টোরফ্রন্ট পেজেই প্রযোজ্য প্রি-এক্সিস্টিং গ্যাপ ছিল, এই ফেজের লগআউট ফ্লো এক্সারসাইজ করেই ধরা পড়ল |

---

## ১০. বিল্ড ক্রম ও যাচাই — ✅ সম্পূর্ণ

1. ✅ `CustomerService::findOrCreateByPhone()` — `user_id` লিংক এক্সটেনশন (C-01)
2. ✅ `AuthApi` — `register()` / `updateProfile()` / `changePassword()` /
   `deleteAccount()`
3. ✅ `routes/api.php` — ৫টা নতুন রাউট (register/profile/password/account +
   বিদ্যমান login/logout/me অপরিবর্তিত)
4. ✅ `AccountController` + `routes/web.php` — `/register`, `/login`,
   `/logout`, `/profile` (C-11)
5. ✅ ভিউ — `storefront/register.php` / `login.php` / `profile.php`
6. ✅ `StorefrontController::common()` public + `authCustomer` + `messages`
   ফিক্স (C-12), `layouts/storefront.php` এ Account নেভ + `accountApi()`/
   `callApi()` JS হেল্পার (C-09)

### যাচাই

`install.php` (স্কিমা-পরিবর্তন নাই, idempotent চেকের জন্যই), তারপর আসল
HTTP রিকোয়েস্ট দিয়ে (কুকি persist সহ curl, [10-storefront-order.md](10-storefront-order.md)
§১২ এর মতোই) পুরো জার্নি — **সবগুলো টেস্ট পাস, টেস্ট ডেটা পরে DB থেকে মুছে
পরিষ্কার করা হয়েছে**:

- ✅ রেজিস্ট্রেশন → সাথে সাথে লগইন সেশন → `/me` এ সঠিক ডেটা
- ✅ একই ফোনে দ্বিতীয়বার রেজিস্ট্রেশন → এরর, ডুপ্লিকেট রো তৈরি হয়নি
- ✅ সংখ্যাসদৃশ কিন্তু ছোট পাসওয়ার্ড (`"12345"`) → রিজেক্ট (§২ গোচা সত্যিই ধরা পড়ত যদি `Validator::min` ব্যবহার করা হতো)
- ✅ অ্যাডমিন দিয়ে আগে বানানো গেস্ট-স্টাইল কাস্টমার (`user_id=0`) এর ফোনে
  রেজিস্ট্রেশন → **একই** `customers` রো-তে `user_id` বসেছে (id অপরিবর্তিত),
  নতুন রো তৈরি হয়নি — doc/10 §৩ এর মূল প্রতিশ্রুতি যাচাই হলো
- ✅ ভুল পাসওয়ার্ডে লগইন এরর, সঠিক পাসওয়ার্ডে সফল
- ✅ প্রোফাইল আপডেট → `users` ও `customers` দুটোই বদলেছে (একই id, সিঙ্কে)
- ✅ পাসওয়ার্ড বদল → ভুল current password এ রিজেক্ট, সঠিকটায় বদলে যায়,
  পুরোনো পাসওয়ার্ডে আর লগইন হয় না, নতুনটায় হয়
- ✅ অ্যাকাউন্ট ডিলিট → লগইন ব্লক (`isActive=0`), `customers` রো/`id` অক্ষত ও
  অপরিবর্তিত, একই ফোনে আবার রেজিস্টার করলে **একই `users.id` পুনর্ব্যবহার**
  হয়ে রিঅ্যাক্টিভেট (নতুন id না) — C-04 যাচাই হলো
- ✅ দুইটা আলাদা ইমেইল-ছাড়া রেজিস্ট্রেশন পরপর → দ্বিতীয়টা `uq_users_email`
  এ ধাক্কা খায়নি (`email: null`, `''` না) — C-03 গোচা যাচাই হলো
- ✅ `curl -D -` দিয়ে: লগইন না করে `/profile` → `/login?next=%2Fprofile` এ
  302 রিডাইরেক্ট (বিদ্যমান `Router::deny()`, C-11)
- ✅ রেন্ডার করা HTML PHP warning/notice-মুক্ত (`/register`, `/login`,
  `/profile`, `/` — লগইন করা ও গেস্ট দুই অবস্থাতেই); নেভে গেস্টে "Login",
  লগইন করা অবস্থায় "Hi, ‹নাম›" + "Logout" ঠিকঠাক দেখাচ্ছে; প্রোফাইল পেজের
  ফর্ম ফিল্ড সঠিক ভ্যালু দিয়ে প্রি-ফিল্ড

> **যা যাচাই হয়নি:** ইন্টারেক্টিভ ব্রাউজারে ক্লিক করে ফর্ম সাবমিট করা — এই
> সেশনে সরাসরি লোকাল Docker সাইটে পৌঁছাতে পারে এমন কোনো ব্রাউজার সারফেস
> চালু ছিল না (sandboxed Browser pane হোস্টের নেটওয়ার্কে পৌঁছাতে পারেনি,
> Claude in Chrome extension কানেক্টেড ছিল না)। JS ফর্ম-সাবমিট লজিক তাই
> শুধু কোড-রিভিউ আর রেন্ডার হওয়া HTML/JS দেখেই যাচাই করা হয়েছে, আসল
> ক্লিক-থ্রু না — ব্যবহারকারীর একবার ব্রাউজারে ঘুরে দেখে নেওয়া উচিত।
