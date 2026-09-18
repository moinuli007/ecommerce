-- =============================================================================
--  004_riylon_launch.sql — RIY LON রিব্র্যান্ড: পুরনো ডেমো প্রোডাক্ট মুছে,
--  Quarter-Zip Knit Sweater — ১৩টা আলাদা প্রোডাক্ট (প্রতি রঙ = ১টা has_variant=0
--  প্রোডাক্ট, ভ্যারিয়েন্ট টেবিল ব্যবহার করা হয়নি — ইউজারের সিদ্ধান্ত অনুযায়ী)।
--
--  চালাতে (003_categories.sql এর পরে, একই কমান্ডে):
--      php database/install.php --demo
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM `product_variant_values`;
DELETE FROM `product_variants`;
DELETE FROM `product_images`;
DELETE FROM `products`;

UPDATE `categories` SET `image` = '';
UPDATE `categories` SET `image` = '/uploads/products/1ac330151ddecdc511156f5f3e39efe4.jpg' WHERE `id` = 12;
UPDATE `categories` SET `image` = '/uploads/products/ea9230e894b5ac620bc2603c16c886c1.jpg' WHERE `id` = 13;

INSERT INTO `products`
  (`id`, `category_id`, `unit_id`, `name`, `slug`, `sku`, `short_description`, `description`,
   `purchase_price`, `sale_price`, `offer_price`, `offer_start`, `offer_end`,
   `stock`, `stock_alert`, `weight`, `has_variant`, `isFeatured`, `isNew`, `isActive`, `sort_order`,
   `meta_title`, `meta_description`, `created_at`, `created_by`, `updated_at`, `updated_by`)
VALUES
  (1, 13, 1, 'Quarter-Zip Knit Sweater — Black', 'quarter-zip-knit-sweater-black', 'RIYLON-QZS-BLACK',
   'Premium cable-knit quarter-zip pullover in Black — soft, durable, a perfect everyday fit.',
   'Timeless style, premium comfort. This cable-knit quarter-zip sweater is crafted for everyday elevation — soft-touch yarn, a relaxed fit, and a brass-tone zip pull. Color: Black.',
   700.0000, 1200.0000, 0.0000, 0, 0, 20.0000, 5.0000, 450.000, 0, 1, 1, 1, 1,
   'Quarter-Zip Knit Sweater — Black | RIY LON', 'Shop the RIY LON cable-knit quarter-zip sweater in Black — timeless style, premium comfort.',
   UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),

  (2, 13, 1, 'Quarter-Zip Knit Sweater — Cream', 'quarter-zip-knit-sweater-cream', 'RIYLON-QZS-CREAM',
   'Premium cable-knit quarter-zip pullover in Cream — soft, durable, a perfect everyday fit.',
   'Timeless style, premium comfort. This cable-knit quarter-zip sweater is crafted for everyday elevation — soft-touch yarn, a relaxed fit, and a brass-tone zip pull. Color: Cream.',
   700.0000, 1200.0000, 0.0000, 0, 0, 20.0000, 5.0000, 450.000, 0, 1, 1, 1, 2,
   'Quarter-Zip Knit Sweater — Cream | RIY LON', 'Shop the RIY LON cable-knit quarter-zip sweater in Cream — timeless style, premium comfort.',
   UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),

  (3, 13, 1, 'Quarter-Zip Knit Sweater — Grey', 'quarter-zip-knit-sweater-grey', 'RIYLON-QZS-GREY',
   'Premium cable-knit quarter-zip pullover in Grey — soft, durable, a perfect everyday fit.',
   'Timeless style, premium comfort. This cable-knit quarter-zip sweater is crafted for everyday elevation — soft-touch yarn, a relaxed fit, and a brass-tone zip pull. Color: Grey.',
   700.0000, 1200.0000, 0.0000, 0, 0, 20.0000, 5.0000, 450.000, 0, 1, 1, 1, 3,
   'Quarter-Zip Knit Sweater — Grey | RIY LON', 'Shop the RIY LON cable-knit quarter-zip sweater in Grey — timeless style, premium comfort.',
   UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),

  (4, 13, 1, 'Quarter-Zip Knit Sweater — Camel', 'quarter-zip-knit-sweater-camel', 'RIYLON-QZS-CAMEL',
   'Premium cable-knit quarter-zip pullover in Camel — soft, durable, a perfect everyday fit.',
   'Timeless style, premium comfort. This cable-knit quarter-zip sweater is crafted for everyday elevation — soft-touch yarn, a relaxed fit, and a brass-tone zip pull. Color: Camel.',
   700.0000, 1200.0000, 0.0000, 0, 0, 20.0000, 5.0000, 450.000, 0, 0, 1, 1, 4,
   'Quarter-Zip Knit Sweater — Camel | RIY LON', 'Shop the RIY LON cable-knit quarter-zip sweater in Camel — timeless style, premium comfort.',
   UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),

  (5, 13, 1, 'Quarter-Zip Knit Sweater — Navy', 'quarter-zip-knit-sweater-navy', 'RIYLON-QZS-NAVY',
   'Premium cable-knit quarter-zip pullover in Navy — soft, durable, a perfect everyday fit.',
   'Timeless style, premium comfort. This cable-knit quarter-zip sweater is crafted for everyday elevation — soft-touch yarn, a relaxed fit, and a brass-tone zip pull. Color: Navy.',
   700.0000, 1200.0000, 0.0000, 0, 0, 20.0000, 5.0000, 450.000, 0, 1, 1, 1, 5,
   'Quarter-Zip Knit Sweater — Navy | RIY LON', 'Shop the RIY LON cable-knit quarter-zip sweater in Navy — timeless style, premium comfort.',
   UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),

  (6, 13, 1, 'Quarter-Zip Knit Sweater — Brown', 'quarter-zip-knit-sweater-brown', 'RIYLON-QZS-BROWN',
   'Premium cable-knit quarter-zip pullover in Brown — soft, durable, a perfect everyday fit.',
   'Timeless style, premium comfort. This cable-knit quarter-zip sweater is crafted for everyday elevation — soft-touch yarn, a relaxed fit, and a brass-tone zip pull. Color: Brown.',
   700.0000, 1200.0000, 0.0000, 0, 0, 20.0000, 5.0000, 450.000, 0, 0, 1, 1, 6,
   'Quarter-Zip Knit Sweater — Brown | RIY LON', 'Shop the RIY LON cable-knit quarter-zip sweater in Brown — timeless style, premium comfort.',
   UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),

  (7, 13, 1, 'Quarter-Zip Knit Sweater — Maroon', 'quarter-zip-knit-sweater-maroon', 'RIYLON-QZS-MAROON',
   'Premium cable-knit quarter-zip pullover in Maroon — soft, durable, a perfect everyday fit.',
   'Timeless style, premium comfort. This cable-knit quarter-zip sweater is crafted for everyday elevation — soft-touch yarn, a relaxed fit, and a brass-tone zip pull. Color: Maroon.',
   700.0000, 1200.0000, 0.0000, 0, 0, 20.0000, 5.0000, 450.000, 0, 1, 1, 1, 7,
   'Quarter-Zip Knit Sweater — Maroon | RIY LON', 'Shop the RIY LON cable-knit quarter-zip sweater in Maroon — timeless style, premium comfort.',
   UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),

  (8, 13, 1, 'Quarter-Zip Knit Sweater — Rust', 'quarter-zip-knit-sweater-rust', 'RIYLON-QZS-RUST',
   'Premium cable-knit quarter-zip pullover in Rust — soft, durable, a perfect everyday fit.',
   'Timeless style, premium comfort. This cable-knit quarter-zip sweater is crafted for everyday elevation — soft-touch yarn, a relaxed fit, and a brass-tone zip pull. Color: Rust.',
   700.0000, 1200.0000, 0.0000, 0, 0, 20.0000, 5.0000, 450.000, 0, 0, 1, 1, 8,
   'Quarter-Zip Knit Sweater — Rust | RIY LON', 'Shop the RIY LON cable-knit quarter-zip sweater in Rust — timeless style, premium comfort.',
   UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),

  (9, 13, 1, 'Quarter-Zip Knit Sweater — Red', 'quarter-zip-knit-sweater-red', 'RIYLON-QZS-RED',
   'Premium cable-knit quarter-zip pullover in Red — soft, durable, a perfect everyday fit.',
   'Timeless style, premium comfort. This cable-knit quarter-zip sweater is crafted for everyday elevation — soft-touch yarn, a relaxed fit, and a brass-tone zip pull. Color: Red.',
   700.0000, 1200.0000, 0.0000, 0, 0, 20.0000, 5.0000, 450.000, 0, 0, 1, 1, 9,
   'Quarter-Zip Knit Sweater — Red | RIY LON', 'Shop the RIY LON cable-knit quarter-zip sweater in Red — timeless style, premium comfort.',
   UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),

  (10, 13, 1, 'Quarter-Zip Knit Sweater — Forest Green', 'quarter-zip-knit-sweater-forest-green', 'RIYLON-QZS-FORESTGREEN',
   'Premium cable-knit quarter-zip pullover in Forest Green — soft, durable, a perfect everyday fit.',
   'Timeless style, premium comfort. This cable-knit quarter-zip sweater is crafted for everyday elevation — soft-touch yarn, a relaxed fit, and a brass-tone zip pull. Color: Forest Green.',
   700.0000, 1200.0000, 0.0000, 0, 0, 20.0000, 5.0000, 450.000, 0, 0, 1, 1, 10,
   'Quarter-Zip Knit Sweater — Forest Green | RIY LON', 'Shop the RIY LON cable-knit quarter-zip sweater in Forest Green — timeless style, premium comfort.',
   UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),

  (11, 13, 1, 'Quarter-Zip Knit Sweater — Charcoal', 'quarter-zip-knit-sweater-charcoal', 'RIYLON-QZS-CHARCOAL',
   'Premium cable-knit quarter-zip pullover in Charcoal — soft, durable, a perfect everyday fit.',
   'Timeless style, premium comfort. This cable-knit quarter-zip sweater is crafted for everyday elevation — soft-touch yarn, a relaxed fit, and a brass-tone zip pull. Color: Charcoal.',
   700.0000, 1200.0000, 0.0000, 0, 0, 20.0000, 5.0000, 450.000, 0, 0, 1, 1, 11,
   'Quarter-Zip Knit Sweater — Charcoal | RIY LON', 'Shop the RIY LON cable-knit quarter-zip sweater in Charcoal — timeless style, premium comfort.',
   UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),

  (12, 13, 1, 'Quarter-Zip Knit Sweater — Taupe', 'quarter-zip-knit-sweater-taupe', 'RIYLON-QZS-TAUPE',
   'Premium cable-knit quarter-zip pullover in Taupe — soft, durable, a perfect everyday fit.',
   'Timeless style, premium comfort. This cable-knit quarter-zip sweater is crafted for everyday elevation — soft-touch yarn, a relaxed fit, and a brass-tone zip pull. Color: Taupe.',
   700.0000, 1200.0000, 0.0000, 0, 0, 20.0000, 5.0000, 450.000, 0, 0, 1, 1, 12,
   'Quarter-Zip Knit Sweater — Taupe | RIY LON', 'Shop the RIY LON cable-knit quarter-zip sweater in Taupe — timeless style, premium comfort.',
   UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),

  (13, 13, 1, 'Quarter-Zip Knit Sweater — Pink', 'quarter-zip-knit-sweater-pink', 'RIYLON-QZS-PINK',
   'Premium cable-knit quarter-zip pullover in Pink — soft, durable, a perfect everyday fit.',
   'Timeless style, premium comfort. This cable-knit quarter-zip sweater is crafted for everyday elevation — soft-touch yarn, a relaxed fit, and a brass-tone zip pull. Color: Pink.',
   700.0000, 1200.0000, 0.0000, 0, 0, 20.0000, 5.0000, 450.000, 0, 0, 1, 1, 13,
   'Quarter-Zip Knit Sweater — Pink | RIY LON', 'Shop the RIY LON cable-knit quarter-zip sweater in Pink — timeless style, premium comfort.',
   UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0);

INSERT INTO `product_images`
  (`product_id`, `variant_id`, `attribute_value_id`, `path`, `alt`, `is_primary`, `sort_order`, `created_at`, `created_by`, `updated_at`, `updated_by`)
VALUES
  (1, 0, 0, '/uploads/products/ea9230e894b5ac620bc2603c16c886c1.jpg', 'Quarter-Zip Knit Sweater — Black', 1, 1, UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),
  (1, 0, 0, '/uploads/products/0e73751b0a04771b47111c583bab00a0.jpg', 'Quarter-Zip Knit Sweater — Black', 0, 2, UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),
  (2, 0, 0, '/uploads/products/6fab49ed7e366aeb5918c6747c08651d.jpg', 'Quarter-Zip Knit Sweater — Cream', 1, 1, UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),
  (2, 0, 0, '/uploads/products/e2040fd6bd31c2903c1847835f650aad.jpg', 'Quarter-Zip Knit Sweater — Cream', 0, 2, UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),
  (3, 0, 0, '/uploads/products/687f92f7fde90d0fc896048081eb44a4.jpg', 'Quarter-Zip Knit Sweater — Grey', 1, 1, UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),
  (3, 0, 0, '/uploads/products/1ac330151ddecdc511156f5f3e39efe4.jpg', 'Quarter-Zip Knit Sweater — Grey', 0, 2, UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),
  (4, 0, 0, '/uploads/products/4da76b68049515ed2d8995fcaf9b1d7f.jpg', 'Quarter-Zip Knit Sweater — Camel', 1, 1, UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),
  (5, 0, 0, '/uploads/products/7f14f1ac8d3f1cbe09c8667831864b34.jpg', 'Quarter-Zip Knit Sweater — Navy', 1, 1, UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),
  (6, 0, 0, '/uploads/products/439522cc76c6b2773161073a715d88d8.jpg', 'Quarter-Zip Knit Sweater — Brown', 1, 1, UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),
  (7, 0, 0, '/uploads/products/8bd723dfb930aa7582bac01033f96594.jpg', 'Quarter-Zip Knit Sweater — Maroon', 1, 1, UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),
  (8, 0, 0, '/uploads/products/a203e49fcd38929048d0911eee61d583.jpg', 'Quarter-Zip Knit Sweater — Rust', 1, 1, UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),
  (9, 0, 0, '/uploads/products/5cb28259c6302632db7182572f09c9b0.jpg', 'Quarter-Zip Knit Sweater — Red', 1, 1, UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),
  (10, 0, 0, '/uploads/products/f4942e1a47ea9e5c272c41ea220873e3.jpg', 'Quarter-Zip Knit Sweater — Forest Green', 1, 1, UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),
  (11, 0, 0, '/uploads/products/b94d93e6645718d8da5751cb7aa2a3fa.jpg', 'Quarter-Zip Knit Sweater — Charcoal', 1, 1, UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),
  (12, 0, 0, '/uploads/products/4cd1e56bfc7c8f873e3fc1359bc3b296.jpg', 'Quarter-Zip Knit Sweater — Taupe', 1, 1, UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0),
  (13, 0, 0, '/uploads/products/55c267e6a445ac1cd0d51f94234c48a2.jpg', 'Quarter-Zip Knit Sweater — Pink', 1, 1, UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP(), 0);

SET FOREIGN_KEY_CHECKS = 1;
