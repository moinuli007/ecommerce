SET NAMES utf8mb4;

INSERT INTO `unit_groups` (`id`, `name`, `sort_order`) VALUES
  (1, 'Count',  1),
  (2, 'Weight', 2),
  (3, 'Length', 3)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `sort_order` = VALUES(`sort_order`);

INSERT INTO `units` (`id`, `unit_group_id`, `name`, `code`, `conversion`, `is_base`, `sort_order`) VALUES
  (1, 1, 'Piece',      'pcs',  1.000000,    1, 1),
  (2, 1, 'Pair',       'pair', 2.000000,    0, 2),
  (3, 1, 'Dozen',      'dzn',  12.000000,   0, 3),
  (4, 1, 'Box',        'box',  24.000000,   0, 4),
  (5, 2, 'Gram',       'g',    1.000000,    1, 1),
  (6, 2, 'Kilogram',   'kg',   1000.000000, 0, 2),
  (7, 3, 'Meter',      'm',    1.000000,    1, 1),
  (8, 3, 'Yard',       'yd',   0.914400,    0, 2)
ON DUPLICATE KEY UPDATE
  `unit_group_id` = VALUES(`unit_group_id`), `name` = VALUES(`name`),
  `conversion` = VALUES(`conversion`), `is_base` = VALUES(`is_base`),
  `sort_order` = VALUES(`sort_order`);

INSERT INTO `attributes` (`id`, `name`, `code`, `type`, `sort_order`) VALUES
  (1, 'Size',  'size',  'select', 1),
  (2, 'Color', 'color', 'color',  2)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`), `type` = VALUES(`type`), `sort_order` = VALUES(`sort_order`);

INSERT INTO `attribute_values` (`id`, `attribute_id`, `value`, `code`, `sort_order`) VALUES
  ( 1, 1, 'XS',   'XS',   1),
  ( 2, 1, 'S',    'S',    2),
  ( 3, 1, 'M',    'M',    3),
  ( 4, 1, 'L',    'L',    4),
  ( 5, 1, 'XL',   'XL',   5),
  ( 6, 1, 'XXL',  'XXL',  6),
  ( 7, 1, 'XXXL', 'XXXL', 7),
  ( 8, 1, '30',   '30',   8),
  ( 9, 1, '32',   '32',   9),
  (10, 1, '34',   '34',  10),
  (11, 1, '36',   '36',  11),
  (12, 1, '38',   '38',  12),
  (13, 1, '40',   '40',  13),
  (14, 1, '42',   '42',  14)
ON DUPLICATE KEY UPDATE
  `value` = VALUES(`value`), `sort_order` = VALUES(`sort_order`);

INSERT INTO `attribute_values` (`id`, `attribute_id`, `value`, `code`, `color_hex`, `sort_order`) VALUES
  (21, 2, 'White',  'WHT', '#ffffff', 1),
  (22, 2, 'Black',  'BLK', '#111111', 2),
  (23, 2, 'Navy',   'NVY', '#1f2a44', 3),
  (24, 2, 'Blue',   'BLU', '#2f6fed', 4),
  (25, 2, 'Sky',    'SKY', '#7fc4e8', 5),
  (26, 2, 'Grey',   'GRY', '#8b8f96', 6),
  (27, 2, 'Olive',  'OLV', '#6b7a3a', 7),
  (28, 2, 'Maroon', 'MRN', '#6d1f2c', 8),
  (29, 2, 'Beige',  'BEG', '#d9cbb2', 9),
  (30, 2, 'Green',  'GRN', '#2f7a4c', 10),
  (31, 2, 'Cream',        'CRM', '#f2e8d5', 11),
  (32, 2, 'Camel',        'CML', '#c19a6b', 12),
  (33, 2, 'Brown',        'BRN', '#5b3a29', 13),
  (34, 2, 'Rust',         'RST', '#b7472a', 14),
  (35, 2, 'Red',          'RED', '#a3231f', 15),
  (36, 2, 'Forest Green', 'FGR', '#28472c', 16),
  (37, 2, 'Charcoal',     'CHR', '#36393b', 17),
  (38, 2, 'Taupe',        'TPE', '#8a7a6b', 18),
  (39, 2, 'Pink',         'PNK', '#e2b8c2', 19)
ON DUPLICATE KEY UPDATE
  `value` = VALUES(`value`), `color_hex` = VALUES(`color_hex`), `sort_order` = VALUES(`sort_order`);

INSERT INTO `code_sequence` (`name`, `prefix`, `next_number`, `padding`) VALUES
  ('product', 'P', 1, 5)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
