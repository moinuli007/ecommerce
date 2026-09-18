-- =============================================================================
--  003_categories.sql — ডেমো ক্যাটাগরি গাছ (রেফারেন্স সাইটের Collections মেনু)
--
--  এটা স্বয়ংক্রিয়ভাবে চলে না। চালাতে:
--      php database/install.php --demo
--
--  `path` কলামটা সাধারণত CategoryService নিজে বসায়; কাঁচা SQL বলে এখানে
--  হাতে লেখা হয়েছে। ফরম্যাট: root থেকে নিজের পর্যন্ত id, দুই পাশে স্ল্যাশ।
--
--  গাছ (রেফারেন্স সাইটের Collections মেনু অনুযায়ী হুবহু):
--    All Shirts
--      ├─ Casual Shirts
--      │    ├─ Single Pocket Shirts
--      │    ├─ Check Shirts
--      │    ├─ Solid Shirts
--      │    └─ Corduroy Shirts
--      └─ Half Shirts
--           ├─ Cuban Half Shirts
--           └─ Hawaiian Half Shirts
--    Denim Jeans Pants
--    Polo T-Shirts
--    Winter
--      └─ Quarter-Zip Knit Sweater
--
--  id ১–৭ আগে থেকেই ছিল (প্রোডাক্ট রেফার করে থাকতে পারে বলে অপরিবর্তিত রাখা
--  হয়েছে); ৮–১৩ নতুন যোগ হয়েছে। এই তালিকার বাইরে যেকোনো ক্যাটাগরি (আগে হাতে
--  অ্যাডমিন প্যানেল দিয়ে যোগ করা থাকলে) নিচের DELETE দিয়ে মুছে যাবে — সাব-ক্যাটাগরি
--  বা প্রোডাক্ট থাকলে FK কনস্ট্রেইন্ট এরর দেবে, সাইলেন্টলি অনাথ ডেটা তৈরি হবে না।
-- =============================================================================

SET NAMES utf8mb4;

INSERT INTO `categories`
  (`id`, `parent_id`, `path`, `depth`, `name`, `slug`, `sort_order`, `isFeatured`, `isActive`) VALUES
  ( 1, 0,  '/1/',       0, 'All Shirts',            'all-shirts',            1, 1, 1),
  ( 2, 1,  '/1/2/',     1, 'Casual Shirts',         'casual-shirts',         1, 1, 1),
  ( 8, 2,  '/1/2/8/',   2, 'Single Pocket Shirts',  'single-pocket-shirts',  1, 0, 1),
  ( 9, 2,  '/1/2/9/',   2, 'Check Shirts',          'check-shirts',          2, 0, 1),
  (10, 2,  '/1/2/10/',  2, 'Solid Shirts',          'solid-shirts',          3, 0, 1),
  (11, 2,  '/1/2/11/',  2, 'Corduroy Shirts',       'corduroy-shirts',       4, 0, 1),
  ( 3, 1,  '/1/3/',     1, 'Half Shirts',           'half-shirts',           2, 1, 1),
  ( 4, 3,  '/1/3/4/',   2, 'Cuban Half Shirts',     'cuban-half-shirts',     1, 0, 1),
  ( 5, 3,  '/1/3/5/',   2, 'Hawaiian Half Shirts',  'hawaiian-half-shirts',  2, 0, 1),
  ( 6, 0,  '/6/',       0, 'Denim Jeans Pants',     'denim-jeans-pants',     2, 1, 1),
  ( 7, 0,  '/7/',       0, 'Polo T-Shirts',         'polo-t-shirts',         3, 1, 1),
  (12, 0,  '/12/',      0, 'Winter',                'winter',                4, 1, 1),
  (13, 12, '/12/13/',   1, 'Quarter-Zip Knit Sweater', 'quarter-zip-knit-sweater', 1, 0, 1)
ON DUPLICATE KEY UPDATE
  `parent_id` = VALUES(`parent_id`), `path` = VALUES(`path`), `depth` = VALUES(`depth`),
  `name` = VALUES(`name`), `sort_order` = VALUES(`sort_order`), `isFeatured` = VALUES(`isFeatured`),
  `isActive` = VALUES(`isActive`);

-- এই তালিকার বাইরে যা কিছু আছে (আগে ম্যানুয়ালি অ্যাডমিন থেকে যোগ করা হলে) — মুছে ফেলা।
DELETE FROM `categories`
WHERE `id` NOT IN (1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13);
