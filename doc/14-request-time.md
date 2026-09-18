# ১৪ — রিকোয়েস্ট টাইম (`App\Core\RequestTime`)

> **স্ট্যাটাস:** ✅ বিল্ড ও যাচাই করা (লাইভ curl + PHPUnit দুটো দিয়েই)।

## ০. কেন

আগে প্রতিটা টেবিলে `created_at`/`updated_at` বসানোর সময় সরাসরি `time()`
কল হতো — `Utility::stampCreate()`/`stampUpdate()` এর ভেতরে, আর অনেক জায়গায়
সরাসরিও (নিচে §২)। সমস্যা: **একই রিকোয়েস্টে একাধিক টেবিলে লেখা হলে**
(যেমন একটা Purchase সেভ করলে `purchases` + `purchase_items` + `stock_ledger`
+ `a_voucher_entry` + `a_ledger_entry` — সব একসাথে এক ট্রানজেকশনে) প্রতিটা
`time()` কল **আলাদা মুহূর্তে** চলে। বেশিরভাগ সময় পার্থক্য কয়েক মিলিসেকেন্ড
হলেও অদৃশ্য থাকে, কিন্তু রিকোয়েস্টটা সেকেন্ড-বাউন্ডারি পেরোলে (যেমন
`14:00:59.998` থেকে `14:01:00.002`) দুটো সম্পর্কিত row এর টাইমস্ট্যাম্প
১ সেকেন্ড আলাদা হয়ে যায় — ডিবাগের সময় "এই দুটো row কি আসলেই একই অ্যাকশনে
তৈরি হয়েছিল?" প্রশ্নের উত্তর অনিশ্চিত করে তোলে।

**সমাধান:** পুরো রিকোয়েস্ট/প্রসেস জুড়ে একটাই "এখন" — প্রথমবার লাগলেই
`time()` কল হয়ে ভ্যালুটা ফিক্সড হয়ে যায়, বাকি পুরো রিকোয়েস্টে সব জায়গায়
সেই একই ভ্যালু ফেরত আসে। create আর update — দুটোতেই সমানভাবে প্রযোজ্য।

---

## ১. `App\Core\RequestTime`

```php
RequestTime::now();          // এই রিকোয়েস্টের "এখন" — প্রথম কলে ফিক্সড, তারপর সবসময় একই
RequestTime::set($timestamp); // টেস্টে নির্দিষ্ট সময় বসানো
RequestTime::reset();         // static ক্যাশ খালি — পরের now() আবার আসল time() থেকে ফ্রেশ নেবে
```

সাধারণ static ক্লাস (D-05, বাকি সব `App\Core\*` এর মতোই)। ভেতরে শুধু একটা
nullable static প্রপার্টি — `null` থাকা মানে "এখনো এই রিকোয়েস্টে সময় লাগেনি"।

PHP-FPM/CLI প্রতিটা রিকোয়েস্ট/প্রসেস **ফ্রেশ static state** দিয়ে শুরু করে,
তাই প্রোডাকশনে `reset()` কখনো ম্যানুয়ালি কল করার দরকার নাই — এটা লাগে শুধু
টেস্টে, যেখানে একটা PHP প্রসেসের ভেতরে অনেকগুলো "রিকোয়েস্ট" (টেস্ট মেথড)
পরপর চলে (§৪)।

---

## ২. কোথায় বসানো হয়েছে

### কেন্দ্রীয় জায়গা — `Utility::stampCreate()`/`stampUpdate()`

এটাই সবচেয়ে গুরুত্বপূর্ণ পরিবর্তন — `Model::create()`/`Model::updateById()`
আর প্রায় সব Service/Api ক্লাস এই দুটো মেথড দিয়েই `created_at`/`created_by`/
`updated_at`/`updated_by` বসায়। শুধু এই একটা জায়গা বদলেই বেশিরভাগ
টেবিলের বেশিরভাগ লেখা কভার হয়ে যায়:

```php
// আগে
public static function stampCreate(array &$data): void
{
    $now = time();
    ...
}

// এখন
public static function stampCreate(array &$data): void
{
    $now = RequestTime::now();
    ...
}
```

### সরাসরি `time()` কল বাইপাস করত এমন জায়গা — এই ফেজে সব `RequestTime::now()`-এ বদলানো হয়েছে

কিছু জায়গায় `Utility::stampCreate()`/`stampUpdate()` ছাড়াই সরাসরি
`'created_at' => time()` বা `'updated_at' => time()` লেখা হতো (প্রায়ই
কারণ ওই আপডেটে `updated_by` বসানো হয় না — যেমন সিস্টেম-ট্রিগারড রিক্যালকুলেশন),
অথবা `time()` কে একটা "এখনকার" বিজনেস তারিখ/মুহূর্ত হিসেবে ব্যবহার করা হতো
(ভাউচার পোস্টিং-টাইম, ডিফল্ট ইনভয়েস/রিটার্ন/অ্যাডজাস্ট-ডেট)। সবগুলোই একই
কারণে সমস্যার — সেভাবেই এখন `RequestTime::now()`:

| ফাইল | কী বদলেছে |
|---|---|
| `App\Core\Auth` | `admin_last_activity`, `last_used` (api_tokens), `last_login`, নতুন টোকেনের `created_at`/`updated_at`/`expires_at` |
| `Account\Services\CodeGenerator` | `code_sequence` এর `created_at`/`updated_at` |
| `Account\Services\LedgerAccounts` | পার্টি-লেজার লিংক করার সময় `updated_at` |
| `Catalog\Services\ProductService` | `offerRunning()` এর ডিফল্ট `$now`, "on sale" ফিল্টারের `$now` |
| `Purchase\Services\CostService` | ওয়েটেড-এভারেজ কস্ট রো এর `created_at` |
| `Purchase\Services\PurchaseService` | হেডার `updated_at` (রিক্যালকুলেশন), ডিফল্ট `invoice_date` |
| `Purchase\Services\PurchaseReturnService` | হেডার `updated_at`, ডিফল্ট `return_date` |
| `Purchase\Services\StockAdjustmentService` | হেডার `updated_at`, ডিফল্ট `adjust_date` |
| `Purchase\Services\StockService` | স্টক লেজারের `entry_time` |
| `Purchase\Services\SupplierService` | ওপেনিং ভাউচারের পোস্টিং-টাইম |
| `Sale\Services\CustomerService` | ওপেনিং ভাউচারের পোস্টিং-টাইম |
| `Sale\Services\OrderService` | `placed_at`, `shipped_at`, স্ট্যাটাস-লগের `created_at`, রিটার্ন-ভাউচারের সময় |
| `Sale\Services\PaymentService` | `verified_at`, ভাউচার পোস্টিং-টাইম |
| `database/install.php` | সিড করা ডিফল্ট অ্যাডমিনের `created_at`/`updated_at` |

---

## ৩. ইচ্ছাকৃত ব্যতিক্রম — এই দুই জায়গায় `time()`-ই থেকে গেছে

সব `time()` কে যান্ত্রিকভাবে বদলানো হয়নি — দুটো জায়গায় `time()` আসলে
একটা "রেকর্ড কবে হলো" স্ট্যাম্প না, অন্য কাজে ব্যবহার হচ্ছে বলে বদলানো হয়নি:

- **`Catalog\Services\AttributeService::save()`** — কোড না দিলে ফলব্যাক
  `'V' . time()`। এটা `created_at` না, একটা **ইউনিকনেস সোর্স** (কোড
  জেনারেট করার জন্য)। `RequestTime::now()` করলে একই রিকোয়েস্টে কোডবিহীন
  দুইটা attribute value তৈরি করলে দুটোই **একই ফলব্যাক কোড** পেয়ে যেত —
  উল্টো বাগ তৈরি করত। তাই এখানে ইচ্ছাকৃতভাবে আসল `time()`-ই রাখা হয়েছে।
- **`Sale\Api\CartTokenResolver::ensureCookie()`** — ব্রাউজার কুকির
  `expires` (৩০ দিন পরে) হিসাব করতে `time() + ...`। এটা কোনো DB row এর
  `created_at`/`updated_at` না, তাই ক্রস-টেবিল ডিবাগ-কনসিস্টেন্সির সমস্যাই
  প্রযোজ্য না — সাধারণ কুকি-এক্সপায়ারি কোডের মতোই সরাসরি `time()` রাখা হয়েছে।

**নিয়ম:** কোনো টাইমস্ট্যাম্প DB row এর "কখন তৈরি/আপডেট হলো" বা কোনো বিজনেস
ইভেন্টের "এখন" বোঝাতে হলে `RequestTime::now()`। কোনো কিছুর **ইউনিকনেস/এনট্রপি
সোর্স** হিসেবে `time()` ব্যবহার হলে (যেমন উপরের ফলব্যাক কোড) সেটা বদলাবেন
না — সেখানে প্রতিটা কলে *আলাদা* মুহূর্ত পাওয়াটাই কাম্য।

---

## ৪. টেস্টেবিলিটি

`RequestTime` নিজেও request-level static ক্যাশ (D-05 এর একই প্যাটার্ন,
[13-auth-and-user-management.md](13-auth-and-user-management.md) §৫ এ
`Auth::reset()` নিয়ে যে গোচার কথা বলা আছে, এখানেও হুবহু সেটাই প্রযোজ্য) —

- **`RequestTime::set($timestamp)`** — সময়-নির্ভর লজিক ডিটারমিনিস্টিকভাবে
  টেস্ট করতে (যেমন "অফার আজ চলছে কি না" নির্দিষ্ট একটা তারিখ ধরে যাচাই করা)।
- **`RequestTime::reset()`** — `Tests\TestCase::tearDown()` এ এখন
  `Auth::reset()`/`$_SESSION = []` এর পাশে এটাও যোগ করা হয়েছে, নাহলে এক
  টেস্ট মেথডে ফিক্সড করা সময় (বা `usleep()` দিয়ে বাস্তবে কয়েক সেকেন্ড পার
  হয়ে যাওয়া) পরের টেস্ট মেথডে ভুল ফলাফল দিতে পারত।

`tests/Core/RequestTimeTest.php` (pure, D-11) যাচাই করে:

- ডিফল্টে আসল `time()` এর কাছাকাছি ভ্যালু দেয়
- বারবার কল করলেও (`usleep()` দিয়ে ~১.১ সেকেন্ড অপেক্ষা করেও) একই ভ্যালু ফেরত আসে
- `set()`/`reset()` ঠিকমতো কাজ করে
- **মূল দাবিটাই সরাসরি যাচাই:** `Utility::stampCreate()` দুইবার কল করা হলো,
  মাঝে ১.১ সেকেন্ড `usleep()` (একটা সেকেন্ড-বাউন্ডারি পার করানোর জন্য ইচ্ছাকৃত) —
  তাও দুটো `created_at` **হুবহু সমান**।

---

## ৫. লাইভ যাচাই

এই ডক লেখার সময় সরাসরি অ্যাপে (curl দিয়ে) একটা সাপ্লায়ার opening balance
সহ তৈরি করে দেখা হয়েছে — একটা রিকোয়েস্টে **৫টা আলাদা টেবিলে** (`suppliers`,
`code_sequence`, `a_ledgers`, `a_voucher_entry`, `a_ledger_entry` — মোট
১০টা ইনসার্ট/আপডেট, `a_ledger_entry` এ ২টা রো) যত জায়গায়
`created_at`/`updated_at`/`time` বসেছে, **সবগুলোই হুবহু একই মান**
(`1789741548`) পেয়েছে। আগে প্রতিটা `time()` আলাদা কল হতো বলে সেকেন্ড-বাউন্ডারিতে
এগুলো এক-দুই সেকেন্ড আলাদা হয়ে যাওয়ার সুযোগ ছিল।

---

## ৬. নতুন কোড লেখার নিয়ম

নতুন কোনো Service/Api/Model লেখার সময়:

- `created_at`/`updated_at` — `Utility::stampCreate()`/`stampUpdate()`
  ব্যবহার করুন, এমনিতেই `RequestTime::now()` পাবেন, আলাদা করে কিছু করা লাগবে না।
- সরাসরি কোনো টেবিলে `'updated_at' => ...`-এর মতো লিখতে হলে (যেমন
  `updated_by` ছাড়া শুধু টাইমস্ট্যাম্প বাম্প করা) — `time()` **না**,
  `RequestTime::now()`।
- কোনো বিজনেস "এখন" (ভাউচার পোস্টিং-টাইম, ডিফল্ট তারিখ) — একই, `RequestTime::now()`।
- ব্যতিক্রম শুধু §৩ এর মতো ইউনিকনেস-সোর্স ব্যবহার — সেখানে সরাসরি `time()`
  রাখুন আর কেন সেটা মন্তব্যে লিখে রাখুন (যাতে পরে কেউ ভুল করে
  `RequestTime::now()` এ বদলে না ফেলে)।

`grep -rn "\btime()" app/` চালিয়ে §৩ এর দুইটা ব্যতিক্রম বাদে অন্য কোনো ফলাফল
এলে সেটা ধরা পড়া উচিত — CI তে একটা লিন্ট রুল হিসেবেও যোগ করা যেতে পারে
(এখনো করা হয়নি, [12-unit-testing.md](12-unit-testing.md) এর CI সেকশনের মতোই ঐচ্ছিক)।
