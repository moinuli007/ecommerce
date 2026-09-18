# ০১ — আর্কিটেকচার ও ডিজাইন সিদ্ধান্ত

## রিকোয়েস্টের পথ

```
ব্রাউজার / মোবাইল
      │
      ▼
nginx (docker/nginx/ecommerce.conf)
      │  try_files → /index.php
      ▼
public/index.php
      │
      ▼
bootstrap.php ─── Autoloader → Env → timezone → session → View::boot
      │            └── routes/api.php → routes/admin.php → routes/web.php
      ▼
Router::dispatch()
      │  ১. method + path ম্যাচ  ২. {param} বের করা  ৩. guard চেক
      ▼
Handler  =  [SomeClass::class, 'staticMethod']
      │
      ├── API রাউট (json: true) ────► Api\*  ──► Response::json()
      │
      └── পেজ রাউট ──► Controllers\*  ──► Api\*  ──► View::layout() ──► HTML
                                   (একই Api মেথড!)
```

## লেয়ার

| লেয়ার | ফোল্ডার | দায়িত্ব | কী করবে না |
|---|---|---|---|
| **Core** | `app/Core/` | DB, রাউটিং, রিকোয়েস্ট/রেসপন্স, অথ, ভিউ | কোনো ব্যবসায়িক লজিক নয় |
| **Enum** | `app/Enum/` | ধ্রুবক ও তাদের মানে (লেবেল, কোড, nature) | DB টাচ করবে না |
| **Model** | `Modules/*/Models/` | একটা টেবিলের অ্যাক্সেস, সাধারণ কোয়েরি | নিয়ম প্রয়োগ করবে না |
| **Service** | `Modules/*/Services/` | ব্যবসায়িক লজিক, ট্রানজেকশন, ভ্যালিডেশন | `$_POST` পড়বে না, HTML জানবে না |
| **Api** | `Modules/*/Api/` | ইনপুট পড়া → সার্ভিস কল → payload বানানো | HTML রেন্ডার করবে না |
| **Controller** | `Modules/*/Controllers/` | Api কল → ভিউ রেন্ডার/রিডাইরেক্ট | DB বা সার্ভিস সরাসরি কল করবে না |
| **View** | `Modules/*/Views/`, `resources/views/` | HTML | কোনো কোয়েরি নয় |

উপরের লেয়ার নিচেরটাকে কল করে, উল্টোটা কখনো নয়।

---

## ডিজাইন সিদ্ধান্ত

প্রতিটা সিদ্ধান্তের একটা আইডি আছে, যাতে কোডের কমেন্ট থেকে রেফার করা যায়।
মত বদলালে এখানে আপডেট করে কোডে খুঁজে নেওয়া যাবে।

### D-01 · সিঙ্গেল স্টোর, `company_id` নাই

erp_saas মাল্টি-টেন্যান্ট — প্রতিটা টেবিলে `company_id`, প্রতিটা কোয়েরিতে সেই স্কোপ।
এই প্রজেক্ট একটা দোকানের, তাই সেটা বাদ দেওয়া হয়েছে — schema সহজ, কোয়েরি সহজ,
ভুলে স্কোপ বাদ পড়ার ঝুঁকি নাই।

**পরে মাল্টি-টেন্যান্ট লাগলে কী করতে হবে:** প্রতিটা টেবিলে `company_id` যোগ,
`Model::query()` এ ডিফল্ট স্কোপ, আর `CompanyHelper::getCurrentCompanyID()` টাইপের
একটা রেজলভার। কাজটা বড়, তাই দরকার হলে এখনই বলুন — যত পরে, তত বেশি খরচ।

### D-02 · রাউট কোডে, DB-তে নয়

erp_saas এ `module` টেবিল থেকে slug → folder/page_name রেজলভ হয়। ফলে কোড পড়ে
বোঝা যায় না কোন URL কোন ফাইল চালায় — DB না দেখে উপায় নাই।

এখানে রাউট টেবিল সম্পূর্ণ `routes/*.php` এ। `Router::all()` দিয়ে যেকোনো সময়
পুরো তালিকা পাওয়া যায়। মেনু/পারমিশন পরে DB-তে যেতে পারে, কিন্তু **রাউট কখনো নয়**।

### D-03 · অথ: session + Bearer টোকেন দুটোই

`Auth::resolve()` প্রথমে `Authorization: Bearer <token>` হেডার দেখে; না থাকলে
`$_SESSION['user_id']`। ফলে একই API ব্রাউজার আর মোবাইল অ্যাপ দুটোই ব্যবহার করতে পারে।

টোকেনের **sha256 হ্যাশ** DB-তে থাকে, প্লেইন টোকেন শুধু তৈরির সময় একবার ফেরত যায়।

### D-04 · web/admin এর সব ডেটা API দিয়ে

আপনার শর্ত ছিল "web এর সব কিছু API হয়ে যাবে"। বাস্তবায়ন:

`Api\VoucherApi::index()` একটা payload **array** ফেরত দেয় (JSON echo করে না)।

- HTTP রাউট (`routes/api.php`, `json: true`) → Router সেটাকে `Response::json()` করে
- অ্যাডমিন কন্ট্রোলার → **একই মেথড কল করে**, তারপর array নিয়ে ভিউ রেন্ডার করে

ফলে একটাই ডেটা-পাথ। কন্ট্রোলারে যদি কখনো `DB::` বা `Service::` কল দেখেন, সেটা ভুল।

বিকল্প (কন্ট্রোলার থেকে নিজের সার্ভারে HTTP কল) বাদ দেওয়া হয়েছে — প্রতি পেজে
অপ্রয়োজনীয় নেটওয়ার্ক রাউন্ডট্রিপ আর সেশন ফরওয়ার্ডিংয়ের ঝামেলা।

### D-05 · সব ক্লাস static

আপনার শর্ত। কোনো `new Service()`, কোনো DI কনটেইনার, কোনো গ্লোবাল `$db`/`$general` নাই।
`DB::`, `Voucher::`, `VoucherApi::` — সব static।

এর দাম: রিকোয়েস্ট-লেভেল ক্যাশ (`LedgerAccounts::$ledgerCache`) static প্রপার্টিতে থাকে।
টেস্টে সেটা রিসেট করতে `LedgerAccounts::clearCache()` / `Response::reset()` আছে।

### D-06 · namespace + lazy autoload (erp_saas এর সাথে পার্থক্য)

erp_saas এর `controller.php` প্রতি রিকোয়েস্টে `RecursiveIteratorIterator` দিয়ে পুরো
`app/` ফোল্ডার include করে, এবং কোনো namespace নাই।

এখানে PSR-4 autoloader — ক্লাস যখন দরকার তখনই ফাইল লোড হয়। মডিউল বাড়লেও রিকোয়েস্ট
ভারী হবে না, আর `Account\Models\Ledger` বনাম অন্য মডিউলের `Ledger` নামে সংঘর্ষ হবে না।

এটা "র' PHP" ই — কোনো ফ্রেমওয়ার্ক নয়, `spl_autoload_register` PHP-র নিজের ফাংশন।

### D-07 · সব কোয়েরি prepared statement

erp_saas এর `DB::insert()` ভ্যালু concat করে (`escape_string` দিয়ে)। এখানে
`DB::run($sql, $bindings)` সবসময় `prepare` + `bind_param` করে। SQL injection এর
সুযোগ নাই, আর int/float/string টাইপ ঠিক থাকে।

`whereRaw()` আছে জটিল শর্তের জন্য — সেখানেও ইউজার ইনপুট বাইন্ডিং হিসেবেই যাবে।

### D-08 · সময় int (unix timestamp), টাকা DECIMAL(20,4)

erp_saas এর মতোই `time` কলাম `INT UNSIGNED`, PHP-র `time()` / `strtotime()` এর সাথে
সরাসরি খাটে, টাইমজোন নিয়ে ঝামেলা নাই।

টাকা কখনো `FLOAT` নয় — `DECIMAL(20,4)`। ৪ ঘর দশমিক, কারণ ইউনিট প্রাইস আর ভ্যাট
হিসাবে ২ ঘরে গোলমাল হয়।

### D-09 · অটো চার্ট lazily তৈরি

ফ্রেশ ইনস্টলে `a_master_account` / `a_chart_of_accounts` / `a_ledgers` খালি থাকে।
`LedgerAccounts::systemLedger(AutoLedger::Cash)` প্রথমবার কল হলে
মাস্টার → চার্ট → লেজার পুরো চেইন `a_auto_*` টেমপ্লেট থেকে তৈরি হয়ে যায়।

ফলে অ্যাকাউন্টস সেটআপ না করেও প্রথম দিন থেকে অর্ডার নেওয়া যায়, আর ইউজারের চার্টে
শুধু যেগুলো আসলেই ব্যবহার হয়েছে সেগুলোই থাকে।

### D-12 · admin/customer dual-session — এক PHP session, আলাদা স্লট

একই ব্রাউজারে admin আর customer একসাথে লগইন থাকতে পারা দরকার হলো (ফেজ ৪.৯,
[13-auth-and-user-management.md](13-auth-and-user-management.md))। আলাদা
session name/cookie দিয়ে করা যেত, কিন্তু D-04 এর কারণে (`web`/`admin` এর সব
ডেটা একই `/api/v1/...` API লেয়ার দিয়ে যায়) cookie-path দিয়ে এলাকা আলাদা করা
সম্ভব না — অ্যাডমিন প্যানেলের নিজের AJAX-ও `/admin/*` পাথে যায় না।

সমাধান: **একটাই PHP session**, কিন্তু ভেতরে আলাদা কি — `admin_user_id` বনাম
`customer_user_id`। কোন কি পড়া হবে সেটা `App\Core\Auth::area()` ঠিক করে —
পাথ `/admin` দিয়ে শুরু হলে `admin`, নাহলে `customer`; ব্যতিক্রম শুধু
`X-Auth-Area: admin` হেডার (অ্যাডমিন লেআউটের শেয়ার্ড fetch হেল্পার পাঠায়)।

একই সিদ্ধান্তের অংশ: admin এলাকায় ২০ মিনিট sliding inactivity-timeout
(`ADMIN_SESSION_TTL`), customer এলাকায় নাই।

### D-13 · সল্টেড পাসওয়ার্ড হ্যাশিং — `App\Core\Password`

`password_hash($plain, PASSWORD_DEFAULT)` সরাসরি না করে দুই ধাপে
(ফেজ ৪.৯): `password_salt` (random, অ্যাকাউন্ট তৈরির সময় একবার) দিয়ে আগে
HMAC-SHA256 পেপার, তারপর সেটা bcrypt। কারণ ও legacy-migration কৌশল
বিস্তারিত [13-auth-and-user-management.md](13-auth-and-user-management.md) §২-এ।

### D-14 · এক রিকোয়েস্টে একটাই "এখন" — `App\Core\RequestTime`

`time()` সরাসরি ছড়িয়ে-ছিটিয়ে কল করলে একই রিকোয়েস্টে একাধিক টেবিলে লেখা
row গুলোর `created_at`/`updated_at` সেকেন্ড-বাউন্ডারি পেরোলে আলাদা হয়ে
যেতে পারত — ডিবাগে সম্পর্কিত row মেলানো কঠিন করে তুলত (ফেজ ৪.৯৫)।
`RequestTime::now()` প্রথম কলেই ভ্যালু ফিক্সড করে রাখে, বাকি পুরো
রিকোয়েস্টে সেই একই ভ্যালু ফেরত দেয় — `Utility::stampCreate()`/
`stampUpdate()` সহ create/update দুই ক্ষেত্রেই। ব্যতিক্রম শুধু যেখানে
`time()` টাইমস্ট্যাম্প না, ইউনিকনেস-সোর্স হিসেবে ব্যবহৃত হচ্ছিল —
বিস্তারিত [14-request-time.md](14-request-time.md)।

---

## কোডিং কনভেনশন

- **নামকরণ:** ক্লাস `PascalCase`, মেথড `camelCase`, DB কলাম `snake_case`
  (ব্যতিক্রম `isActive` — erp_saas এর সাথে মিল রাখতে)
- **রিটার্ন টাইপ:** সার্ভিস/মডেল সবসময় plain array দেয়, কোনো entity object নয়।
  না পেলে `[]`, কখনো `null` নয় — ফলে `if ($row === [])` দিয়েই চেক।
- **এরর:** সার্ভিস `RuntimeException` ছোঁড়ে (বাংলা মেসেজ সহ), Api লেয়ার সেটা ধরে
  `Response::error()` করে। কন্ট্রোলার/সার্ভিসে `die()`/`echo` কখনো নয়।
- **ট্রানজেকশন:** যেখানে একাধিক টেবিলে লেখা হয় সেখানে `DB::transaction(fn() => ...)`।
  নেস্টেড কল savepoint দিয়ে হ্যান্ডেল হয়, তাই সার্ভিস থেকে সার্ভিস কল নিরাপদ।
- **কমেন্ট:** কেন, কী নয়। বাংলা কমেন্ট চলবে — কোডবেসে ইতিমধ্যেই আছে।
