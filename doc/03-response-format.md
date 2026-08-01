# ০৩ — রেসপন্স ফরম্যাট

erp_saas এর `$jArray` ফরম্যাট হুবহু রাখা হয়েছে, যাতে ওখানকার ফ্রন্টএন্ড কোড/অভ্যাস
এখানেও খাটে।

## শেপ

```json
{
  "status": 1,
  "m": [ ["s", "ভাউচার সংরক্ষণ হয়েছে।"] ],
  "voucher_id": 42,
  "voucher": { "...": "..." }
}
```

| কি | সবসময় থাকে? | মানে |
|---|---|---|
| `status` | হ্যাঁ | `1` = কাজ হয়েছে · `0` = হয়নি |
| `m` | হ্যাঁ | মেসেজ লিস্ট, প্রতিটা `[type, text]` |
| `html` | না | পার্শিয়াল HTML (টেবিল রো, মোডাল ইত্যাদি) |
| বাকি সব | না | এন্ডপয়েন্ট অনুযায়ী ডেটা |
| `_sql` | না | `RESPONSE_DEBUG=1` হলে চালানো সব কোয়েরি |
| `_exception` | না | `APP_DEBUG=1` এবং exception হলে |

`status` আর `m` সবসময় এই ক্রমেই প্রথম দুটো কি।

## মেসেজ টাইপ

| কোড | মানে | UI ক্লাস |
|---|---|---|
| `e` | error | লাল |
| `s` | success | সবুজ |
| `w` | warning | হলুদ |
| `i` | info | নীল |

**একটা রেসপন্সে একাধিক মেসেজ থাকতে পারে** — লুপ করে সবগুলো দেখাবেন,
`m[0]` ধরে বসে থাকবেন না।

## HTTP স্ট্যাটাস কোড

| কোড | কখন |
|---|---|
| `200` | সব স্বাভাবিক — **ব্যবসায়িক ব্যর্থতাও এখানেই** (`status: 0` দেখে বুঝবেন) |
| `401` | লগইন নাই |
| `403` | লগইন আছে, কিন্তু অনুমতি নাই |
| `404` | রাউট নাই |
| `500` | অপ্রত্যাশিত exception |

⚠ ভ্যালিডেশন ব্যর্থতা `422` নয়, `200` + `status: 0`। erp_saas এর সাথে মিল রাখতে।
ক্লায়েন্টে **সবসময় `status` চেক করবেন**, শুধু `response.ok` নয়।

## সার্ভার সাইডে ব্যবহার

```php
use App\Core\Response;

// শর্টকাট — মেসেজ + status একসাথে
return Response::success('ভাউচার সংরক্ষণ হয়েছে।', ['voucher_id' => $id]);
return Response::error('ভাউচার পাওয়া যায়নি।');

// ধাপে ধাপে
Response::ok();
Response::set('vouchers', $rows);
Response::set('pagination', $meta);
return Response::payload();

// একাধিক মেসেজ
Message::warning('স্টক কমে গেছে।');
Message::success('অর্ডার নেওয়া হয়েছে।');
return Response::payload();
```

`Response::payload()` কল হলে মেসেজ কিউ খালি হয়ে যায় — তাই এক রিকোয়েস্টে
একবারই কল করবেন। একাধিকবার লাগলে (যেমন কন্ট্রোলার দুটো API কল করছে) প্রতিটার
আগে `Response::reset()` দিন।

## রিডাইরেক্টের পরের মেসেজ

পেজ রিডাইরেক্টে মেসেজ টিকিয়ে রাখতে সেশন ব্যবহার হয়:

```php
Message::flash(Message::ERROR, 'ইউজারনেম বা পাসওয়ার্ড সঠিক নয়।');
header('Location: /admin/login');
```

পরের রিকোয়েস্টে `Message::flush()` সেশনের মেসেজগুলো নিজে থেকেই মিশিয়ে দেয়।

## ডিবাগ কি

erp_saas এর `fl()` হেল্পারের সমতুল্য — কোন ফাইলের কোন লাইন থেকে ভ্যালুটা এসেছে
সেটা কি-এর মধ্যেই থাকে:

```php
Response::debug($someArray);          // "EL#42_Voucher": [...]
Response::debug($x, 'before_save');   // "EL#42_Voucher_before_save": ...
```

`RESPONSE_DEBUG=0` (`.env`) হলে এই কলগুলো কিছুই করে না — প্রোডাকশনে কোড থেকে
সরানোর দরকার নাই।

## ক্লায়েন্ট সাইডে

```js
async function api(path, body = null, method = 'GET') {
    const response = await fetch('/api/v1' + path, {
        method,
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body: body ? JSON.stringify(body) : undefined
    });

    const data = await response.json();

    // মেসেজ সবসময় দেখান — status 1 হলেও warning থাকতে পারে
    (data.m || []).forEach(([type, text]) => showToast(type, text));

    if (response.status === 401) {
        location.href = '/admin/login';
        return null;
    }

    return data.status === 1 ? data : null;
}
```

## পেজিনেশন

লিস্ট এন্ডপয়েন্ট `pagination` অবজেক্ট দেয়:

```json
{
  "pagination": { "total": 137, "page": 2, "per_page": 50, "pages": 3 }
}
```

`per_page=0` পাঠালে পেজিনেশন বন্ধ, সব রো আসবে। `per_page` এর সর্বোচ্চ ৫০০।
