-- Migration to add main_categories hierarchy and link categories table.
-- Safe to run multiple times: uses IF NOT EXISTS / conditional adds.

-- 1) Create main_categories table
CREATE TABLE IF NOT EXISTS main_categories (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(150) NOT NULL UNIQUE,
  description TEXT DEFAULT NULL,
  cover_image VARCHAR(255) DEFAULT 'default_main_category.jpg',
  accent_color VARCHAR(20) DEFAULT '#22c55e',
  is_active TINYINT(1) DEFAULT 1,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Add columns to categories if missing
ALTER TABLE categories
  ADD COLUMN IF NOT EXISTS main_category_id INT(10) UNSIGNED DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS card_image VARCHAR(255) DEFAULT 'default_category.jpg',
  ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0;

-- 3) Add foreign key to main_categories (drop existing constraint if needed)
ALTER TABLE categories
  DROP FOREIGN KEY IF EXISTS fk_categories_main,
  ADD CONSTRAINT fk_categories_main FOREIGN KEY (main_category_id) REFERENCES main_categories(id) ON DELETE SET NULL;

-- 4) Seed main categories
INSERT IGNORE INTO main_categories (name, slug, description, cover_image, accent_color, is_active, sort_order) VALUES
('Mobile Game Top-Up', 'mobile-game-topup', 'Top up your favorite mobile games', 'main_mobile_topup.jpg', '#22c55e', 1, 1),
('PC Game Top-Up', 'pc-game-topup', 'Top up for PC / desktop titles', 'main_pc_topup.jpg', '#3b82f6', 1, 2),
('Mobile Top-Up', 'mobile-topup', 'Telco and prepaid mobile balance', 'main_mobile_credit.jpg', '#f97316', 1, 3),
('Premium Account', 'premium-account', 'Streaming and premium subscriptions', 'main_premium.jpg', '#8b5cf6', 1, 4),
('Gift Cards', 'gift-cards', 'Gaming and store gift cards', 'main_giftcards.jpg', '#f59e0b', 1, 5);

-- 5) Attach existing categories to a main category (adjust slugs if yours differ)
UPDATE categories SET main_category_id = (SELECT id FROM main_categories WHERE slug='mobile-game-topup') WHERE slug IN ('mlbb-diamonds','pubg-uc','freefire-diamonds');
UPDATE categories SET main_category_id = (SELECT id FROM main_categories WHERE slug='gift-cards') WHERE slug='gift-cards';
UPDATE categories SET main_category_id = (SELECT id FROM main_categories WHERE slug='premium-account') WHERE slug='premium-accounts';
UPDATE categories SET main_category_id = (SELECT id FROM main_categories WHERE slug='mobile-topup') WHERE slug='mm-topup';
-- Example PC category (add if present)
UPDATE categories SET main_category_id = (SELECT id FROM main_categories WHERE slug='pc-game-topup') WHERE slug='valorant-points';

-- 6) Seed example MLBB SKUs if they don't already exist
INSERT INTO products (category_id, product_name, slug, description, price, old_price, stock, product_image, status)
SELECT c.id, v.name, v.slug, v.description, v.price, v.old_price, v.stock, v.image, 'active'
FROM (
    SELECT 'MLBB 22 Diamonds' AS name, 'mlbb-22' AS slug, 'Instant MLBB top-up 22 diamonds' AS description, 0.50 AS price, 0.60 AS old_price, 5000 AS stock, 'mlbb_22.png' AS image UNION ALL
    SELECT 'MLBB 52 Diamonds', 'mlbb-52', 'Instant MLBB top-up 52 diamonds', 0.95, 1.10, 5000, 'mlbb_52.png' UNION ALL
    SELECT 'MLBB 86 Diamonds', 'mlbb-86', 'Instant MLBB top-up 86 diamonds', 1.50, 1.80, 5000, 'mlbb_86.png'
) AS v
JOIN categories c ON c.slug = 'mlbb-diamonds'
WHERE NOT EXISTS (SELECT 1 FROM products p WHERE p.slug = v.slug);

