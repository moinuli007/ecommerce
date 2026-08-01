# ecommerce

র' PHP (কোনো ফ্রেমওয়ার্ক নাই), মডিউল-বেসড ই-কমার্স। `erp_saas` এর আদলে তৈরি, তবে
কয়েকটা জায়গায় ইচ্ছাকৃত পার্থক্য আছে — সেগুলো [doc/01-architecture.md](doc/01-architecture.md)
এ "সিদ্ধান্ত" হিসেবে লেখা আছে।

## দ্রুত শুরু

```bash
docker compose up -d --build nginx php
docker compose exec php php /var/www/html/ecommerce/database/install.php
```

তারপর <https://ecommerce.moi/admin/login> — ডিফল্ট লগইন `admin@ecommerce.moi` / `admin1234`
(লগইন করেই পাসওয়ার্ড বদলে নিন)।

রেফারেন্স সাইটের ক্যাটাগরি গাছটাও চাইলে ইনস্টলারের শেষে `--demo` যোগ করুন।

`ecommerce.moi` কাজ করতে হলে Windows এর `hosts` ফাইলে লাইনটা থাকতে হবে:

```
127.0.0.1 ecommerce.moi
```

## ফোল্ডার

```
ecommerce/
├── bootstrap.php            # একমাত্র গ্লোবাল সেটআপ (autoload, env, session, routes)
├── public/index.php         # একমাত্র এন্ট্রি পয়েন্ট
├── routes/
│   ├── api.php              # /api/v1/*  → JSON
│   ├── admin.php            # /admin/*   → HTML
│   └── web.php              # /*         → স্টোরফ্রন্ট (Phase 2)
├── app/
│   ├── Core/                # DB, QueryBuilder, Model, Router, Request, Response, Auth, Menu, View …
│   ├── Enum/                # VoucherType, LedgerType, AutoLedger, AutoChart, MasterAccountType …
│   └── Modules/
│       ├── Account/         # ভাউচার + চার্ট অব অ্যাকাউন্টস
│       │   ├── Models/      # টেবিল অ্যাক্সেস
│       │   ├── Services/    # ব্যবসায়িক লজিক (Voucher, LedgerAccounts, LedgerStatement …)
│       │   ├── Api/         # payload ফেরত দেয় — HTTP আর web দুই জায়গা থেকেই কল হয়
│       │   ├── Controllers/ # শুধু ভিউ রেন্ডার
│       │   └── Views/
│       ├── Catalog/         # ক্যাটাগরি, প্রোডাক্ট, ভ্যারিয়েন্ট, ইউনিট, সাইজ/রঙ
│       ├── Dashboard/       # অ্যাডমিন ড্যাশবোর্ড
│       └── Auth/            # লগইন, প্রোফাইল
├── resources/views/         # শেয়ার্ড লেআউট ও পেজ
├── database/
│   ├── schema/*.sql         # টেবিল
│   ├── seed/*.sql           # সিস্টেম চার্ট টেমপ্লেট
│   └── install.php          # schema + seed + অ্যাডমিন ইউজার
└── doc/                     # ডকুমেন্টেশন (নিচে দেখুন)
```

**কোনো `class/` ফোল্ডার নাই** — সব কোড `app/` এর ভেতরে, মডিউল অনুযায়ী ভাগ করা।

## ডকুমেন্টেশন

| ফাইল | কী আছে |
|---|---|
| [doc/00-overview.md](doc/00-overview.md) | প্রজেক্টের লক্ষ্য, ফেজ প্ল্যান, রেফারেন্স সাইট |
| [doc/01-architecture.md](doc/01-architecture.md) | লেয়ার, স্ট্যাটিক কনভেনশন, সব ডিজাইন সিদ্ধান্ত (D-01 … D-08) |
| [doc/02-database.md](doc/02-database.md) | প্রতিটি টেবিল, কলাম, সম্পর্ক, হিসাবের গঠন |
| [doc/03-response-format.md](doc/03-response-format.md) | `jArray` রেসপন্স ফরম্যাট, মেসেজ কোড, ফ্রন্টএন্ড হ্যান্ডলিং |
| [doc/04-routing.md](doc/04-routing.md) | রাউট টেবিল, guard, নতুন রাউট যোগ করার নিয়ম |
| [doc/05-voucher.md](doc/05-voucher.md) | ভাউচার API রেফারেন্স + প্রতিটি লেনদেনের ডেবিট/ক্রেডিট ম্যাপ |
| [doc/06-admin-ui.md](doc/06-admin-ui.md) | অ্যাডমিন লেআউট, মেনু, ড্যাশবোর্ড, শেয়ার্ড CSS ক্লাস |
| [doc/07-catalog.md](doc/07-catalog.md) | ক্যাটাগরি গাছ, ইউনিট রূপান্তর, সাইজ/রঙ, প্রোডাক্ট ও ভ্যারিয়েন্ট |

## নিয়ম যেগুলো ভাঙা যাবে না

1. **হিসাব শুধু `Voucher` সার্ভিস দিয়ে** — `a_voucher_entry` / `a_ledger_entry` তে
   কখনো সরাসরি `INSERT`/`UPDATE` নয়। তাহলেই ট্রায়াল ব্যালেন্স সবসময় মিলবে।
2. **সব ক্লাস static** — কোনো `new Service()` নাই, কোনো গ্লোবাল ইনস্ট্যান্স নাই।
3. **web যা দেখায়, সব API দিয়ে আসে** — কন্ট্রোলার নিজে কোয়েরি করে না,
   `Api\*` ক্লাসের একই static মেথড কল করে যেটা HTTP API-ও ব্যবহার করে।
4. **enum ভ্যালু কখনো বদলাবেন না** — পুরোনো ডেটার মানে পাল্টে যাবে। নতুন কেস শেষে যোগ করুন।
