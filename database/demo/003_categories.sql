-- =============================================================================
--  003_categories.sql — ডেমো ক্যাটাগরি গাছ (রেফারেন্স সাইটের Collections মেনু)
--
--  এটা স্বয়ংক্রিয়ভাবে চলে না। চালাতে:
--      php database/install.php --demo
--
--  `path` কলামটা সাধারণত CategoryService নিজে বসায়; কাঁচা SQL বলে এখানে
--  হাতে লেখা হয়েছে। ফরম্যাট: root থেকে নিজের পর্যন্ত id, দুই পাশে স্ল্যাশ।
-- =============================================================================

SET NAMES utf8mb4;

INSERT INTO `categories`
  (`id`, `parent_id`, `path`, `depth`, `name`, `slug`, `sort_order`, `isFeatured`, `isActive`) VALUES
  (1, 0, '/1/',     0, 'All Shirts',           'all-shirts',           1, 1, 1),
  (2, 1, '/1/2/',   1, 'Casual Shirts',        'casual-shirts',        1, 1, 1),
  (3, 1, '/1/3/',   1, 'Half Shirts',          'half-shirts',          2, 1, 1),
  (4, 3, '/1/3/4/', 2, 'Cuban Half Shirts',    'cuban-half-shirts',    1, 0, 1),
  (5, 3, '/1/3/5/', 2, 'Hawaiian Half Shirts', 'hawaiian-half-shirts', 2, 0, 1),
  (6, 0, '/6/',     0, 'Denim Jeans Pants',    'denim-jeans-pants',    2, 1, 1),
  (7, 0, '/7/',     0, 'Polo T-Shirts',        'polo-t-shirts',        3, 1, 1)
ON DUPLICATE KEY UPDATE
  `parent_id` = VALUES(`parent_id`), `path` = VALUES(`path`), `depth` = VALUES(`depth`),
  `name` = VALUES(`name`), `sort_order` = VALUES(`sort_order`), `isFeatured` = VALUES(`isFeatured`);
