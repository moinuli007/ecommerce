# ১২ — ইউনিট টেস্টিং

## টুল

[PHPUnit](https://phpunit.de) — PHP-র স্ট্যান্ডার্ড টেস্ট রানার, composer dev-dependency
হিসেবে যোগ করা হয়েছে (`composer.json` এর `require-dev`)। প্রজেক্টের নিজের কোড
(`app/`) এখনো ফ্রেমওয়ার্ক-মুক্ত raw PHP-ই থাকছে (D-06) — শুধু টেস্ট চালানোর
টুলটাই composer দিয়ে আনা।

## ইনস্টল

`vendor/` এখনো কমিট করা হয়নি বলে প্রথমবার কনটেইনারের ভেতর থেকে:

```bash
docker exec -w /var/www/html/ecommerce my_project_php composer install
```

`vendor/` আগে থেকেই থাকলে (অন্য কেউ `composer install` চালিয়ে রেখেছে) নতুন করে শুধু
PHPUnit টানতে:

```bash
docker exec -w /var/www/html/ecommerce my_project_php composer require --dev phpunit/phpunit:^10.5
```

## চালানো

```bash
docker exec -w /var/www/html/ecommerce my_project_php vendor/bin/phpunit
# অথবা
docker exec -w /var/www/html/ecommerce my_project_php composer test
```

একটা নির্দিষ্ট ফাইল/ক্লাস/মেথড চালাতে:

```bash
docker exec -w /var/www/html/ecommerce my_project_php vendor/bin/phpunit tests/Catalog/CategoryServiceTest.php
docker exec -w /var/www/html/ecommerce my_project_php vendor/bin/phpunit --filter test_delete_is_blocked_while_sub_categories_exist
```

## ডিরেক্টরি স্ট্রাকচার

`tests/` ফোল্ডার `app/Modules/` এর ধাঁচ অনুসরণ করে — মডিউলের নামেই সাবফোল্ডার:

| পাথ | কী |
|---|---|
| `phpunit.xml` | রুট কনফিগ — bootstrap, testsuite, কভারেজ স্কোপ |
| `tests/bootstrap.php` | `bootstrap.php` এর হালকা সংস্করণ — অটোলোডার + `.env`, সেশন/রাউট ছাড়া |
| `tests/TestCase.php` | সব DB-নির্ভর টেস্টের বেস ক্লাস — নিচে দেখুন |
| `tests/Core/` | `app/Core/` এর খাঁটি (pure) ক্লাসের টেস্ট — `SlugTest`, `ValidatorTest` |
| `tests/Catalog/`, `tests/Account/`, … | প্রতিটা মডিউলের Service/Model টেস্ট — `app/Modules/<X>/` এর সাথে ১:১ |

নতুন মডিউলে টেস্ট লিখলে `tests/<ModuleName>/` এর নিচেই রাখবেন, namespace `Tests\<ModuleName>\`।

## ডিজাইন সিদ্ধান্ত

### D-10 · DB রোলব্যাক দিয়ে আইসোলেশন — mock নয়

প্রজেক্টের সব ক্লাস static, কোনো DI কনটেইনার নাই (D-05) — তাই `CategoryService`
বা `Voucher` এর ভেতরের `DB::` কলকে mock করার কোনো সহজ উপায় নাই। সমাধান:
**আসল DB-র বিপরীতেই টেস্ট চালানো**, কিন্তু প্রতিটা টেস্ট একটা ট্রানজেকশনে মুড়ে
শেষে rollback (`DB::begin()` / `DB::rollback()` — `app/Core/DB.php` এ আগে থেকেই
আছে, নেস্টেড কল savepoint দিয়ে হ্যান্ডেল হয়)।

`tests/TestCase.php`:

```php
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::begin();
    }

    protected function tearDown(): void
    {
        DB::rollback();
        Response::reset();
        Message::flush();
        parent::tearDown();
    }
}
```

ফলে টেস্ট যত ইচ্ছা insert/update/delete করুক, আসল ক্যাটাগরি/প্রোডাক্ট/অর্ডার
ডেটা কখনো বদলায় না — ডেভেলপমেন্ট DB-তেই নিরাপদে চালানো যায়, আলাদা টেস্ট DB
বাধ্যতামূলক না। `Response`/`Message` এর static queue-ও রিসেট হয় (D-05 এ
আগে থেকেই উল্লেখ ছিল)।

**সতর্কতা:**

- `CategoryService::delete()`/`ProductService` এর মতো ক্লাস যেগুলো কাজ শেষে
  ফাইল ডিলিট করে (`Upload::delete()`) — DB rollback হলেও ফাইল-সিস্টেমের কাজ
  rollback হয় না। ছবি-আপলোড জড়িত সার্ভিস টেস্ট করলে এই সীমাবদ্ধতা মাথায় রাখবেন।
- `database/install.php` বা schema বদলানোর কোড টেস্ট করবেন না — সেটা transaction
  এর বাইরে (`CREATE TABLE`/DDL implicit commit করে ফেলে)।
- আলাদা, পুরোপুরি ফাঁকা টেস্ট DB চাইলে `.env.testing` বানিয়ে
  `tests/bootstrap.php` এ সেটা লোড করুন (মন্তব্য হিসেবে ফাইলে লেখা আছে)।

### D-11 · খাঁটি (pure) ক্লাস আর DB-নির্ভর ক্লাসের জন্য আলাদা বেস

`app/Core/Slug.php`, `app/Core/Validator.php` এর মতো ক্লাস DB ছোঁয় না — এগুলোর
টেস্ট সরাসরি `PHPUnit\Framework\TestCase` থেকে extend করে (ট্রানজেকশন খোলার
দরকার নাই, দ্রুত চলে)। `Validator::check()` ডিফল্টে `Message` কিউতে পুশ করে,
তাই সেই টেস্টগুলোর নিজস্ব `tearDown()` এ `Message::flush()` কল আছে।

`CategoryService`, `Voucher`, `ProductService` ইত্যাদি DB-নির্ভর সার্ভিসের টেস্ট
`Tests\TestCase` extend করবে (উপরে D-10)।

### D-12 · রিকোয়েস্ট-লেভেল static ক্যাশ টেস্ট করার সময় `::reset()` লাগবেই

`App\Core\Auth` এর মতো ক্লাস (D-05 এর ফলাফল — সব static, DI নাই) নিজের
রেজলভড অবস্থা static প্রপার্টিতে ক্যাশ করে রাখে, রিকোয়েস্ট-প্রতি একবার (এখানে
"রিকোয়েস্ট" মানে এক PHP প্রসেসের এক execution — বাস্তব HTTP তে এটা এমনিতেই
ফ্রেশ, কিন্তু PHPUnit এক প্রসেসে অনেক টেস্ট মেথড চালায়)। একটা টেস্ট মেথডের
ভেতরে একাধিক "রিকোয়েস্ট" সিমুলেট করতে হলে (যেমন: "লগইন নাই" অবস্থা যাচাই
করে তারপর সেশন বসিয়ে "লগইন আছে" অবস্থা যাচাই করা) মাঝে ক্যাশ রিসেট না করলে
দ্বিতীয় assertion গুলো প্রথমবারের (স্টেল) ক্যাশ করা ফলাফলই দেখবে —
পরীক্ষা নীরবে ভুল জিনিস যাচাই করে ফেলবে (fail না করেই, যেটা ধরাই কঠিন)।

সমাধান — এমন প্রতিটা ক্লাসে টেস্টিং-এর জন্য একটা `::reset()` (এই একই
প্যাটার্ন `Response::reset()`/`Message::flush()`/`LedgerAccounts::clearCache()`
এও আছে): সেশন/গ্লোবাল স্টেট সরাসরি বসানোর পর, পরের resolve-নির্ভর assertion
চালানোর ঠিক আগে `Auth::reset()` কল করা। বিস্তারিত উদাহরণ ও কেন-এর ব্যাখ্যা
[13-auth-and-user-management.md](13-auth-and-user-management.md) §৫-এ।

এই একই কারণে `Tests\TestCase::tearDown()` এখন `Auth::reset()` আর
`$_SESSION = []` ও করে — এক টেস্ট ফাইলের লগইন-স্টেট যেন পরের ফাইলে না লিক করে।

`App\Core\RequestTime` (ফেজ ৪.৯৫, [14-request-time.md](14-request-time.md))
একই সমস্যায় পড়ত বলে সেটাও `Tests\TestCase::tearDown()` এ `RequestTime::reset()`
দিয়ে কভার করা আছে — এক টেস্ট মেথডে ফিক্সড হওয়া "এখন" পরের মেথডে লিক করবে না।

## উদাহরণ হিসেবে যা যোগ হয়েছে

| ফাইল | কী দেখায় |
|---|---|
| `tests/Core/SlugTest.php` | খাঁটি ফাংশন টেস্ট — বাংলা ইউনিকোড (`\p{M}` কম্বাইনিং মার্ক) সহ slug, ফাঁকা ইনপুট |
| `tests/Core/ValidatorTest.php` | রুল-বাই-রুল (`required`, `numeric`, `min`, `in`, ঐচ্ছিক ফিল্ড), `Message` কিউ |
| `tests/Core/PasswordTest.php` | খাঁটি — salt/hash/verify, ভুল পাসওয়ার্ড/সল্ট রিজেক্ট, bcrypt এর নিজস্ব র‍্যান্ডমনেস |
| `tests/Core/RequestTimeTest.php` | খাঁটি — একই "রিকোয়েস্টে" বারবার কল করলেও একই ভ্যালু, `set()`/`reset()`, `Utility::stampCreate()` দুইবার কলেও `created_at` হুবহু মেলে |
| `tests/Catalog/CategoryServiceTest.php` | DB-নির্ভর — path/depth রিবিল্ড, প্যারেন্ট সরানো হলে বংশধরদের path আপডেট, চক্র-সুরক্ষা, ডিলিট-গার্ড |
| `tests/Auth/UserServiceTest.php` | DB-নির্ভর — অ্যাডমিন ইউজার CRUD, শেষ super admin সুরক্ষা, পাসওয়ার্ড রিসেট/বদল |
| `tests/Auth/AuthTest.php` | DB-নির্ভর + static ক্যাশ — dual-session, sliding টাইমআউট, `::reset()` প্যাটার্ন (D-12 উপরে) |

নতুন সার্ভিস/মডিউলে টেস্ট লেখার সময় এগুলোর যেকোনো একটাকে টেমপ্লেট ধরে
কপি-পরিবর্তন করলেই যথেষ্ট।

## CI (ঐচ্ছিক, পরে)

এখনো কোনো CI পাইপলাইন নাই। যোগ করলে মূল ধাপ হবে:

```bash
composer install --no-interaction
php database/install.php --skip-admin   # schema তৈরি, খালি DB তে
vendor/bin/phpunit
```

`--skip-admin` দিয়ে admin ইউজার তৈরি এড়ানো যায় (idempotent এমনিতেও, কিন্তু CI
রানে দরকার নাই)। CI-র DB আলাদা কনটেইনার/সার্ভিস হওয়া উচিত — ডেভেলপমেন্ট ডেটার
সাথে না মেশে।
