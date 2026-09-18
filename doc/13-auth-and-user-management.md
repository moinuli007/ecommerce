# ১৩ — অথ সিকিউরিটি ও অ্যাডমিন ইউজার ম্যানেজমেন্ট (ফেজ ৪.৯)

> **স্ট্যাটাস:** ✅ বিল্ড ও যাচাই করা (লাইভ curl + PHPUnit দুটো দিয়েই)।

## ০. কেন এই ফেজ

চারটা আলাদা অনুরোধ থেকে এসেছে, সবগুলোই `App\Core\Auth`/লগইন-সিস্টেমের গোড়া ছোঁয়:

1. একই ব্রাউজারে **admin আর customer লগইন একসাথে** থাকতে পারা উচিত।
2. admin লগইন **২০ মিনিট নিষ্ক্রিয় থাকলে অটো লগআউট**, কিন্তু customer এ এই
   টাইমআউট প্রযোজ্য না।
3. admin কিছু করলে (যেকোনো অথেনটিকেটেড রিকোয়েস্ট) তার সেশনের মেয়াদ বেড়ে
   যাবে (sliding window, fixed-length না)।
4. পাসওয়ার্ড সরাসরি এক ধাপে হ্যাশ না করে **দুইটা ভেরিয়েবল** (`password` +
   random `password_salt`) দিয়ে — যাতে শুধু `password` কলাম leak হলেই যথেষ্ট
   না হয়।
5. **Super Admin** আর সাধারণ **Admin** — দুই রোল, আর সুপার অ্যাডমিনের জন্য
   একটা ইউজার-ম্যানেজমেন্ট মেনু যেখান থেকে নতুন অ্যাডমিন ইউজার বানানো যাবে।

স্কিমা আগে থেকেই ৯০% প্রস্তুত ছিল — `users.type` (1=super_admin, 2=staff,
3=customer) আর `Auth::TYPE_*`/`isAdmin()`/`isSuperAdmin()`/`isCustomer()`
ফেজ ১ থেকেই ছিল ([01-architecture.md](01-architecture.md) D-03)। এই ফেজে
যোগ হয়েছে: `users.password_salt` কলাম, `App\Core\Password`, `Auth` এর
dual-session/sliding-timeout লজিক, আর অ্যাডমিন ইউজার ম্যানেজমেন্ট মডিউল।

---

## ১. কাঠামো এক নজরে

```
                    ┌─────────────────────────────────────┐
                    │            $_SESSION (একটাই)          │
                    │                                       │
                    │  admin_user_id       ─┐               │
                    │  admin_last_activity ─┤  admin এলাকা   │
                    │                       │  (sliding TTL) │
                    │  customer_user_id    ─┘  customer এলাকা│
                    │                          (টাইমআউট নাই) │
                    └─────────────────────────────────────┘
                                     ▲
                                     │  App\Core\Auth::area()
                        ┌────────────┴────────────┐
                        │                          │
                  পাথ /admin/* দিয়ে          X-Auth-Area হেডার
                  শুরু → 'admin'              থাকলে সেটাই জেতে
                  (নাহলে 'customer')
```

**একটাই PHP session (একটাই কুকি)**, কিন্তু তার ভেতরে admin আর customer
আইডেন্টিটি **আলাদা কি**-তে থাকে — `admin_user_id` বনাম `customer_user_id`।
তাই একই ব্রাউজারে দুটো লগইন সহাবস্থান করতে পারে, একটাতে লগআউট করলে অন্যটা
অক্ষত থাকে। আলাদা কুকি/session name লাগেনি — সেটা লাগলে `bootstrap.php` এর
`session_start()` কল আর cookie path নিয়ে জটিলতা বাড়ত, তার বদলে একই সেশনে
namespace করাই সহজ ও যথেষ্ট।

### কোন রিকোয়েস্ট কোন এলাকার (`Auth::area()`)

সমস্যা: web/admin এর সব ডেটা একই `/api/v1/...` API লেয়ার দিয়ে যায়
([01-architecture.md](01-architecture.md) D-04) — অ্যাডমিন প্যানেলের নিজের
AJAX-ও `/admin/*` না, `/api/v1/suppliers` এর মতো পাথে যায়। তাই খালি URL
পাথ দেখে admin/customer আলাদা করা যায় না।

সমাধান দুই ধাপে:

1. **পাথ `/admin` দিয়ে শুরু হলে এলাকা `admin`** — পেজ-লোড (`/admin/login`,
   `/admin/users` ইত্যাদি) এভাবেই ধরা পড়ে।
2. **`X-Auth-Area: admin` হেডার থাকলে সেটাই চূড়ান্ত**, পাথ যাই হোক না কেন।
   [layouts/admin.php](../resources/views/layouts/admin.php) এর শেয়ার্ড
   `api()`/`apiUpload()` জাভাস্ক্রিপ্ট হেল্পার (পুরো অ্যাডমিন প্যানেলের
   **একমাত্র** fetch wrapper) প্রতিটা কলে এই হেডার পাঠায়। স্টোরফ্রন্টের নিজের
   fetch হেল্পার কোনো হেডার পাঠায় না, তাই তার সব কল ডিফল্ট `customer`-ই থেকে
   যায়।

ফলে `App\Core\Router::guard()` এর `'admin'`/`'customer'`/`'super_admin'`
তিনটাই সবসময় সঠিক স্লট থেকে রেজলভ হয় — নতুন কোনো রাউট-লেভেল কনফিগ লাগেনি।

`guard: 'auth'` (যেমন `/api/v1/auth/me`, `/profile`, `/password`) এখনো
আগের মতোই — এগুলো এখনো শুধু **customer** সেলফ-সার্ভিসের জন্যই ব্যবহৃত হয়
([11-customer-account.md](11-customer-account.md)), তাই এলাকা ডিফল্ট
`customer` হলেই যথেষ্ট, নতুন কোনো হেডার লাগেনি সেগুলোতে। অ্যাডমিনের নিজের
পাসওয়ার্ড বদল/ইউজার ম্যানেজমেন্টের জন্য সম্পূর্ণ আলাদা রাউট আছে (§৪) —
`guard: 'auth'` এর ambiguity (admin নাকি customer?) এড়াতে ইচ্ছাকৃতভাবেই
পুরনো রুট শেয়ার করা হয়নি।

---

## ২. পাসওয়ার্ড হ্যাশিং — `App\Core\Password`

আগে: `password_hash($plain, PASSWORD_DEFAULT)` সরাসরি — bcrypt নিজেই
র‍্যান্ডম সল্ট জেনারেট করে সেই হ্যাশের ভেতরেই এনকোড করে রাখে (`$2y$12$...`)।
এটা একাই নিরাপদ, কিন্তু সল্ট আর হ্যাশ **একই কলামে** — কলাম leak হলে
attacker এর কাছে ব্রুটফোর্সের জন্য দরকারি সবকিছুই একসাথে চলে যায়।

### নতুন স্কিম

`users` টেবিলে এখন দুইটা কলাম:

| কলাম | কী |
|---|---|
| `password_salt` | `CHAR(32)`, `random_bytes(16)` থেকে hex — অ্যাকাউন্ট তৈরির সময় **একবারই** তৈরি হয় |
| `password` | `password_hash(hash_hmac('sha256', $plain, $salt), PASSWORD_DEFAULT)` |

```php
// App\Core\Password
Password::salt();                    // ৩২-হেক্স random salt
Password::hash($plain, $salt);       // DB তে রাখার মতো চূড়ান্ত হ্যাশ
Password::verify($plain, $hash, $salt); // লগইন/পাসওয়ার্ড-বদলে মেলানো
```

**কেন এক ধাপ bcrypt না করে দুই ধাপ:** `password_salt` কে প্রথমে HMAC-এ
"pepper" হিসেবে ব্যবহার করা হয়, তারপর সেই HMAC আউটপুট আবার bcrypt দিয়ে
হ্যাশ হয়। ফলে —

- verify তবু **ধীরগতির** (bcrypt এর cost factor অক্ষত, ব্রুটফোর্স এখনো ব্যয়বহুল)
- `password` কলাম একা leak হলে salt ছাড়া কিছুই বের করা যায় না, আর `password_salt`
  একা leak হলে bcrypt হ্যাশ ছাড়া পাসওয়ার্ড বের করা যায় না — দুটো কলামই লাগবে
- bcrypt নিজের ভেতরেও একটা র‍্যান্ডম সল্ট বসায় (`password_hash()` এর ডিফল্ট
  আচরণ), তাই একই `password`+`password_salt` দিয়ে দুইবার হ্যাশ করলেও দুটো
  আলাদা স্ট্রিং পাওয়া যায় — এটা `tests/Core/PasswordTest.php`-তে সরাসরি
  যাচাই করা আছে

### legacy fallback — migrate on login

`password_salt` কলাম যোগ হওয়ার আগে থেকে থাকা রো (যেমন `database/install.php`
এর সিড করা ডিফল্ট super admin, বা আগে রেজিস্টার করা কাস্টমার) এ
`password_salt` খালি (`NULL`)। এদের পাসওয়ার্ড রিসেট করতে বাধ্য করার বদলে —

`AuthApi::verifyPassword()` (আর সমান্তরালভাবে `UserService::changeOwnPassword()`):

1. `password_salt` খালি না হলে → নতুন স্কিমে `Password::verify()`
2. খালি হলে → পুরনো `password_verify($plain, $hash)` সরাসরি
3. পুরনো পদ্ধতিতে মিললে **চুপচাপ migrate** — নতুন salt বানিয়ে নতুন ফরম্যাটে
   রি-হ্যাশ করে সেভ করে দেয়। পরের বার থেকে সেই ইউজার নতুন স্কিমেই থাকে।

`database/install.php` আর নতুন রেজিস্ট্রেশন/অ্যাডমিন-তৈরি — সবগুলোই এখন
প্রথম থেকেই নতুন স্কিমে লেখে, শুধু **ইতিহাসের রো** গুলোর জন্য এই ফলব্যাক।

### মাইগ্রেশন — `password_salt` কলাম

`database/schema/001_core.sql` এ কলামটা `CREATE TABLE IF NOT EXISTS` এর
ভেতরেই আছে (ফ্রেশ ইনস্টলে যথেষ্ট), কিন্তু আগে থেকে ইনস্টল করা DB-তে
`users` টেবিল ইতিমধ্যে থাকায় সেটা যোগ হবে না। MySQL এ
`ALTER TABLE ... ADD COLUMN IF NOT EXISTS` বলে কিছু নাই (শুধু
`ADD INDEX`/`DROP COLUMN` এ `IF (NOT) EXISTS` চলে, `ADD COLUMN` এ না —
সরাসরি চেষ্টা করে ধরা পড়েছে, নিচে দ্রষ্টব্য) — তাই `database/install.php`
তে `migrateUsersPasswordSaltColumn()` PHP ফাংশন: `information_schema`
চেক করে কলাম না থাকলেই `ALTER TABLE` চালায়, ফলে idempotent থাকে
(বারবার `php database/install.php` চালালেও সমস্যা নাই)।

> **গোচা:** প্রথম দফায় `.sql` ফাইলেই
> `ALTER TABLE users ADD COLUMN IF NOT EXISTS password_salt ...` লেখা
> হয়েছিল, ধরে নেওয়া হয়েছিল MySQL 8.0.29+ এ এটা চলে (MariaDB-তে চলে,
> ধোঁয়াশা এখান থেকেই)। লাইভ `install.php` চালিয়ে দেখা গেল MySQL 8 সিনট্যাক্স
> এরর দেয় — `ADD COLUMN` এ আসলে `IF NOT EXISTS` সাপোর্টই নাই। এখন
> PHP-সাইড চেকে ঠিক করা।

---

## ৩. admin সেশন — ২০ মিনিট sliding টাইমআউট

`ADMIN_SESSION_TTL` (.env, ডিফল্ট `1200` = ২০ মিনিট) — `App\Core\Auth::resolveAdmin()`
প্রতিটা admin-এলাকার রিকোয়েস্টে চেক করে:

```php
if ($lastActivity > 0 && (time() - $lastActivity) > $ttl) {
    // এক্সপায়ার — সেশন স্লট মুছে ফেলা, Auth::adminSessionExpired() = true
}
// এক্সপায়ার না হলে:
$_SESSION['admin_last_activity'] = time(); // sliding — প্রতিটা রিকোয়েস্টেই বাড়ে
```

- **sliding**, fixed-length না — admin ১৯ মিনিট পরপর কিছু একটা করলে (পেজ
  লোড, যেকোনো AJAX) সেশন কখনো এক্সপায়ার হবে না। একটানা ২০ মিনিট **কিছুই না
  করলে** পরের রিকোয়েস্টে লগআউট হয়ে যাবে।
- **customer সেশনে এই লজিক নাই** — `resolveCustomer()` শুধু `isActive`/`type`
  চেক করে, কোনো `*_last_activity` কি-ই বসায় না।
- এক্সপায়ার হলে `LoginController::form()` `Auth::adminSessionExpired()`
  পড়ে "Your session has expired due to 20 minutes of inactivity." মেসেজ
  দেখায় (জেনেরিক "Please log in to continue." এর বদলে) — শুধু একবার, পরের
  রিকোয়েস্টে flag রিসেট।
- **লাইভ যাচাই** (এই ডক লেখার সময়) — `.env` এ সাময়িকভাবে
  `ADMIN_SESSION_TTL=2` বসিয়ে curl দিয়ে টেস্ট করা হয়েছে: ১ সেকেন্ড পরপর ৫টা
  রিকোয়েস্ট (মোট ৫ সেকেন্ড) সেশন জীবিত রেখেছে (প্রতিটাই ২ সেকেন্ডের ভেতরে,
  sliding প্রমাণ করে), তারপর ৩ সেকেন্ড ইনঅ্যাক্টিভ থাকার পর পরের রিকোয়েস্ট
  ৩০২ (login পেজে রিডাইরেক্ট) দিয়েছে।

Bearer token (মোবাইল/এক্সটার্নাল ক্লায়েন্ট) এই লজিকের বাইরে — তার নিজস্ব
`api_tokens.expires_at` (D-03) অক্ষত, area-নিরপেক্ষ।

---

## ৪. অ্যাডমিন ইউজার ম্যানেজমেন্ট — Super Admin বনাম Admin

`users.type`: `1 = super_admin`, `2 = staff` (UI-তে **"Admin"** লেবেল হয়,
`Auth::typeLabel()`)। এটা ফেজ ১ থেকেই স্কিমায় ছিল, শুধু এতদিন কোনো UI/API
এই দুইয়ের মধ্যে পার্থক্য করত না — এখন করে:

| | Super Admin | Admin (staff) |
|---|---|---|
| স্টোর চালানো (ভাউচার, ক্যাটালগ, অর্ডার, স্টক ...) | ✅ | ✅ |
| নিজের পাসওয়ার্ড বদল | ✅ | ✅ |
| **নতুন অ্যাডমিন/স্টাফ তৈরি, এডিট, রোল বদল, ডিঅ্যাক্টিভেট** | ✅ | ❌ (403) |
| অন্য কারো পাসওয়ার্ড রিসেট | ✅ | ❌ (403) |

নতুন `guard: 'super_admin'` (`App\Core\Router::guard()`) — `admin` এর মতোই,
শুধু `Auth::isSuperAdmin()` লাগে।

### মডিউল

`app/Modules/Auth/` এ যোগ হয়েছে (আগের `AuthApi`/`LoginController`/
`ProfileController` এর পাশে):

- `Models/User.php` — `users` টেবিলের সাধারণ কোয়েরি (`byEmail`, `byPhone`,
  `admins()` — type 1/2 বাদে বাকি সব ফিল্টার হয়ে যায়, `countActiveSuperAdmins()`)
- `Services/UserService.php` — CRUD + পাসওয়ার্ড রিসেট/নিজের-পাসওয়ার্ড-বদল,
  সব `RuntimeException` ছোঁড়ে (D-01-এর কোডিং কনভেনশন অনুযায়ী)
- `Api/UserApi.php` — `index`/`show`/`store`/`update`/`destroy`/
  `resetPassword`/`changeOwnPassword`, সবগুলো plain payload array ফেরত দেয়
  (D-04)
- `Controllers/UserController.php` — `/admin/users` পেজ (list/create/edit)
- `Views/user/{list,form}.php`

### সুরক্ষা (UserService এ)

- **শেষ সক্রিয় Super Admin কে demote/deactivate/delete করা যাবে না** —
  নাহলে কেউই আর অ্যাডমিন ম্যানেজ করতে পারবে না (lockout)। `User::countActiveSuperAdmins()`
  দিয়ে চেক।
- **নিজের অ্যাকাউন্ট নিজে ডিলিট করা যাবে না** (`UserService::delete()`
  `Auth::id()` এর সাথে মিলিয়ে দেখে)।
- **type=3 (customer) এই পথে তৈরি/এডিট করা যাবে না** — `save()` টাইপ 1/2
  ছাড়া রিজেক্ট করে। কাস্টমার এখনো শুধু `AuthApi::register()` দিয়েই তৈরি হয়
  ([11-customer-account.md](11-customer-account.md))।
- ডিলিট **সফট** (`isActive = 0`) — কাস্টমার অ্যাকাউন্ট ডিলিটের মতোই
  ([11-customer-account.md](11-customer-account.md) §৬) — কারণ একই:
  `created_by`/`updated_by` অনেক টেবিলেই এই id রেফার করে, হার্ড-ডিলিটে
  হিসাবের ট্রেইল ভাঙত।
- পাসওয়ার্ড রিসেট (super admin অন্য কারো জন্য) বনাম নিজের পাসওয়ার্ড বদল —
  দুটো আলাদা মেথড/এন্ডপয়েন্ট, কারণ প্রথমটায় "current password" লাগে না
  (ভুলে-যাওয়া পাসওয়ার্ড রিকভারির একমাত্র পথ), দ্বিতীয়টায় লাগে।

### রাউট

```
routes/admin.php (পেজ, HTML)
  GET  /admin/users            guard: super_admin
  GET  /admin/users/create     guard: super_admin
  GET  /admin/users/{id}/edit  guard: super_admin

routes/api.php (JSON, /api/v1/... প্রিফিক্স)
  PUT    /users/me/password       guard: admin        ← যেকোনো লগইন admin/staff
  GET    /users                   guard: super_admin
  GET    /users/{id}              guard: super_admin
  POST   /users                   guard: super_admin
  PUT    /users/{id}              guard: super_admin
  DELETE /users/{id}               guard: super_admin
  PUT    /users/{id}/password      guard: super_admin  ← রিসেট, current_password লাগে না
```

⚠ **ক্রম:** `/users/me/password` `/users/{id}/password` এর **আগে**
রেজিস্টার করা — নাহলে `{id}` প্যাটার্ন `"me"` স্ট্রিংটাকেই id হিসেবে
ম্যাচ করে ফেলত (স্থির পাথ আগে কনভেনশন, [04-routing.md](04-routing.md))।

### মেনু

`App\Core\Menu::admin()` এ নতুন "Settings" গ্রুপ, "Admin Users" আইটেম —
`superAdminOnly: true` ফ্ল্যাগ দিয়ে `Auth::isSuperAdmin()` না হলে লিস্ট
থেকেই বাদ পড়ে যায় (রাউট গার্ড এমনিতেও আটকাবে, কিন্তু মেনুতে এমন লিংক
দেখানো ঠিক না যা ক্লিক করলে 403 দেখাবে)।

---

## ৫. টেস্টেবিলিটি

### কী যোগ হয়েছে

- `tests/Core/PasswordTest.php` — খাঁটি (pure), DB লাগে না: salt র‍্যান্ডম/৩২-হেক্স,
  সঠিক পাসওয়ার্ড মেলে, ভুল পাসওয়ার্ড রিজেক্ট, ভুল salt রিজেক্ট, একই ইনপুটেও
  bcrypt এর নিজস্ব র‍্যান্ডমনেসের কারণে দুইবার হ্যাশ করলে আলাদা স্ট্রিং, খালি
  hash/salt রিজেক্ট (D-11 প্যাটার্ন)।
- `tests/Auth/UserServiceTest.php` — DB-নির্ভর (`Tests\TestCase`, D-10):
  তৈরি/এডিট, ডুপ্লিকেট ইমেইল রিজেক্ট, customer-type রিজেক্ট, শেষ super admin
  সুরক্ষা (demote/delete দুটোই), পাসওয়ার্ড রিসেট, নিজের পাসওয়ার্ড বদল
  (legacy salt-বিহীন রো মাইগ্রেশনসহ), সফট-ডিলিট, সার্চ ফিল্টার।
- `tests/Auth/AuthTest.php` — `area()` রেজলিউশন (পাথ + হেডার), dual-session
  সহাবস্থান, একটা এলাকায় লগআউট করলে অন্যটা অক্ষত, sliding টাইমআউট
  (এক্সপায়ার + স্লাইড-ফরোয়ার্ড দুটোই), ডিঅ্যাক্টিভেটেড ইউজার রিজেক্ট।

### Auth টেস্ট করার গোচা — request-level static ক্যাশ (D-05)

`Auth::resolve()` রিকোয়েস্ট-প্রতি একবারই চলে, ফলাফল static এ ক্যাশ হয়ে
থাকে (`self::$resolved`/`self::$user`) — বাস্তব HTTP রিকোয়েস্টে এটাই
সঠিক আচরণ (প্রতিটা রিকোয়েস্ট ফ্রেশ PHP প্রসেস/স্টেট পায়)। কিন্তু PHPUnit-এ
একটা টেস্ট মেথডের ভেতরে **একাধিক "রিকোয়েস্ট" সিমুলেট** করতে গেলে (যেমন
"আগে লগইন না থাকা অবস্থা টেস্ট করি, তারপর সেশন বসিয়ে লগইন অবস্থা টেস্ট করি")
এই ক্যাশ ফাঁদে ফেলে দেয়:

```php
// ফাঁদ — UserService::save() এর ভেতরে Utility::stampCreate() 'created_by'
// বসাতে Auth::id() কল করে, যেটা Auth::resolve() ট্রিগার করে এমন এক সময়ে
// যখন $_SESSION এখনো খালি — ফলাফল (null) static এ ক্যাশ হয়ে যায়
$id = UserService::save([...]);

$_SESSION['admin_user_id']       = $id;
$_SESSION['admin_last_activity'] = time();

Auth::check(); // ❌ এখনো পুরনো ক্যাশ করা null-ই ফেরত দেবে, নতুন সেশন-ডেটা পড়বে না
```

সমাধান — `App\Core\Auth::reset()` (এই ফেজেই টেস্টের জন্য যোগ করা, ঠিক
`Response::reset()`/`Message::flush()`/`LedgerAccounts::clearCache()` এর
একই প্যাটার্নে): সেশন-ডেটা সরাসরি বসানোর পর, রেজলভ-নির্ভর assertion চালানোর
**আগে** `Auth::reset()` কল করলে পরের `Auth::check()`/`Auth::user()` কল
আবার ফ্রেশ রেজলভ করবে। `tests/Auth/AuthTest.php` এর প্রতিটা টেস্টে এই
প্যাটার্ন অনুসরণ করা আছে, আর মন্তব্যে ব্যাখ্যা করা আছে কেন লাগছে।

`Tests\TestCase::tearDown()` এখন `Auth::reset()` আর `$_SESSION = []`ও করে
(আগে শুধু `Response::reset()`/`Message::flush()` করত) — নাহলে এক টেস্ট
ফাইলের লগইন-স্টেট/সেশন পরের ফাইলের টেস্টে লিক করতে পারত।

`Auth::loginSession()` এর ভেতরের `session_regenerate_id(true)` এখন
`session_status() === PHP_SESSION_ACTIVE` দিয়ে গার্ড করা — PHPUnit-এ কোনো
সক্রিয় PHP session না থাকায় (`tests/bootstrap.php` ইচ্ছাকৃতভাবে
`session_start()` করে না) এই কলটা আগে একটা PHP warning ছুঁড়ত, আর
`phpunit.xml` এ `failOnWarning="true"` থাকায় সেটা সরাসরি টেস্ট ফেইল করে
দিত। গার্ডটা প্রোডাকশনেও নিরাপদ — বাস্তব HTTP রিকোয়েস্টে session সবসময়ই
সক্রিয় থাকে (`bootstrap.php`)।

### CI

আলাদা কিছু লাগে না — [12-unit-testing.md](12-unit-testing.md) এর
বিদ্যমান `vendor/bin/phpunit` কমান্ডই এই নতুন টেস্টগুলোও তুলে নেয়
(`tests/` এর নিচে যেকোনো `*Test.php` অটো-ডিসকভার হয়)।

---

## ৬. .env

```
# অ্যাডমিন সেশন কত সেকেন্ড নিষ্ক্রিয় থাকলে অটো লগআউট (২০ মিনিট, sliding)
ADMIN_SESSION_TTL=1200
```

`0` বসালে টাইমআউট বন্ধ হয়ে যাবে (`Auth::resolveAdmin()` এ `$ttl > 0` চেক)।

---

## ৭. যা করা হয়নি (স্কোপের বাইরে)

- **"Remember me" / persistent login** — চাওয়া হয়নি, admin সেশন ব্রাউজার
  বন্ধ হলে/কুকি মুছলে এমনিতেও শেষ।
- **পাসওয়ার্ড রিকভারি ইমেইল/SMS** — Super Admin ম্যানুয়ালি রিসেট করে দেয়
  (§৪)। ফেজ ৭-এর ইমেইল/SMS পরিকল্পনার সাথে একসাথে আসবে।
- **পারমিশন গ্রানুলারিটি** (Super Admin/Admin এর বাইরে কাস্টম রোল/পারমিশন)
  — [01-architecture.md](01-architecture.md) এর D-02 তে আগে থেকেই বলা আছে,
  "মেনু/পারমিশন পরে DB-তে যেতে পারে" — এখনো সেই পরিকল্পিত ভবিষ্যতের কাজ,
  এই ফেজ শুধু দুই-স্তরের (super_admin/staff) সিদ্ধান্ত বাস্তবায়ন করেছে।
- **audit log** (কে কবে কী করল) — চাওয়া হয়নি; `created_by`/`updated_by`
  কলাম আগে থেকেই আছে সবখানে, সেটাই এখনকার মতো যথেষ্ট।
