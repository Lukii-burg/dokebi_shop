-- Catalog + digital delivery updates for Myanmar top-up, gift cards, and premium accounts.
-- Safe to run multiple times (INSERT ... ON DUPLICATE KEY / IF NOT EXISTS).

-- Optional: scope to the primary DB (adjust if yours differs)
-- USE dokebi_dbt;

/* ------------------------------------------------------------
   Digital delivery tables
------------------------------------------------------------ */
CREATE TABLE IF NOT EXISTS giftcard_codes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED DEFAULT NULL,
  product_id INT UNSIGNED DEFAULT NULL,
  code VARCHAR(255) NOT NULL,
  is_used TINYINT(1) NOT NULL DEFAULT 0,
  used_by INT UNSIGNED DEFAULT NULL,
  order_id INT UNSIGNED DEFAULT NULL,
  delivered_to VARCHAR(190) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  used_at DATETIME DEFAULT NULL,
  UNIQUE KEY uniq_code (code),
  KEY idx_giftcard_cat (category_id),
  KEY idx_giftcard_prod (product_id),
  KEY idx_giftcard_used (is_used),
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS premium_accounts_pool (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED DEFAULT NULL,
  product_id INT UNSIGNED DEFAULT NULL,
  service VARCHAR(120) NOT NULL,
  username VARCHAR(190) NOT NULL,
  password VARCHAR(190) NOT NULL,
  is_assigned TINYINT(1) NOT NULL DEFAULT 0,
  assigned_to INT UNSIGNED DEFAULT NULL,
  order_id INT UNSIGNED DEFAULT NULL,
  assigned_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_service_username (service, username),
  KEY idx_pool_cat (category_id),
  KEY idx_pool_prod (product_id),
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
);

/* ------------------------------------------------------------
   Main category + category seeds
------------------------------------------------------------ */
INSERT INTO main_categories (name, slug, description, cover_image, accent_color, is_active, sort_order)
VALUES
('Myanmar Mobile Top-up', 'myanmar-mobile-topup', 'Local telco balance (MPT, Atom, Mytel, Ooredoo/U9)', 'main_mobile_credit.jpg', '#f97316', 1, 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  cover_image = VALUES(cover_image),
  accent_color = VALUES(accent_color),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

INSERT INTO categories (category_name, slug, description, main_category_id, card_image, is_active, sort_order) VALUES
('MPT Top-up', 'mpt-topup', 'Top-up for MPT numbers', (SELECT id FROM main_categories WHERE slug='myanmar-mobile-topup'), 'mm_topup_card.jpg', 1, 1),
('U9 Top-up', 'u9-topup', 'Top-up for Ooredoo / U9 numbers', (SELECT id FROM main_categories WHERE slug='myanmar-mobile-topup'), 'mm_topup_card.jpg', 1, 2),
('Atom Top-up', 'atom-topup', 'Top-up for ATOM numbers', (SELECT id FROM main_categories WHERE slug='myanmar-mobile-topup'), 'mm_topup_card.jpg', 1, 3),
('Mytel Top-up', 'mytel-topup', 'Top-up for Mytel numbers', (SELECT id FROM main_categories WHERE slug='myanmar-mobile-topup'), 'mm_topup_card.jpg', 1, 4),
('Steam Wallet', 'steam-wallet', 'Steam Wallet gift cards', (SELECT id FROM main_categories WHERE slug='gift-cards'), 'giftcards_card.jpg', 1, 1),
('Google Play Gift Cards', 'google-play-gift-cards', 'Google Play gift cards', (SELECT id FROM main_categories WHERE slug='gift-cards'), 'giftcards_card.jpg', 1, 2),
('App Store Gift Cards', 'app-store-gift-cards', 'Apple / App Store gift cards', (SELECT id FROM main_categories WHERE slug='gift-cards'), 'giftcards_card.jpg', 1, 3),
('Spotify Premium', 'spotify-premium', 'Spotify premium accounts', (SELECT id FROM main_categories WHERE slug='premium-account'), 'premium_card.jpg', 1, 1),
('YouTube Premium', 'youtube-premium', 'YouTube premium accounts', (SELECT id FROM main_categories WHERE slug='premium-account'), 'premium_card.jpg', 1, 2),
('Netflix Premium', 'netflix-premium', 'Netflix premium accounts', (SELECT id FROM main_categories WHERE slug='premium-account'), 'premium_card.jpg', 1, 3),
('Telegram Premium', 'telegram-premium', 'Telegram premium accounts', (SELECT id FROM main_categories WHERE slug='premium-account'), 'premium_card.jpg', 1, 4),
('ChatGPT Premium', 'chatgpt-premium', 'ChatGPT premium accounts', (SELECT id FROM main_categories WHERE slug='premium-account'), 'premium_card.jpg', 1, 5)
ON DUPLICATE KEY UPDATE
  category_name = VALUES(category_name),
  description = VALUES(description),
  main_category_id = VALUES(main_category_id),
  card_image = VALUES(card_image),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

/* ------------------------------------------------------------
   Product seeds
------------------------------------------------------------ */
-- Myanmar telco top-up packages
INSERT INTO products (category_id, product_name, slug, description, price, old_price, stock, product_image, status)
SELECT c.id, v.name, v.slug, v.description, v.price, NULL, 999999, 'default_product.png', 'active'
FROM (
  SELECT 'MPT 1,000 MMK Top-up' AS name, 'mpt-1000' AS slug, 'MPT recharge 1,000 MMK' AS description, 1000.00 AS price, 'mpt-topup' AS cat_slug UNION ALL
  SELECT 'MPT 3,000 MMK Top-up', 'mpt-3000', 'MPT recharge 3,000 MMK', 3000.00, 'mpt-topup' UNION ALL
  SELECT 'MPT 5,000 MMK Top-up', 'mpt-5000', 'MPT recharge 5,000 MMK', 5000.00, 'mpt-topup' UNION ALL
  SELECT 'MPT 10,000 MMK Top-up', 'mpt-10000', 'MPT recharge 10,000 MMK', 10000.00, 'mpt-topup' UNION ALL
  SELECT 'U9 1,000 MMK Top-up', 'u9-1000', 'U9 / Ooredoo recharge 1,000 MMK', 1000.00, 'u9-topup' UNION ALL
  SELECT 'U9 3,000 MMK Top-up', 'u9-3000', 'U9 / Ooredoo recharge 3,000 MMK', 3000.00, 'u9-topup' UNION ALL
  SELECT 'U9 5,000 MMK Top-up', 'u9-5000', 'U9 / Ooredoo recharge 5,000 MMK', 5000.00, 'u9-topup' UNION ALL
  SELECT 'U9 10,000 MMK Top-up', 'u9-10000', 'U9 / Ooredoo recharge 10,000 MMK', 10000.00, 'u9-topup' UNION ALL
  SELECT 'ATOM 1,000 MMK Top-up', 'atom-1000', 'ATOM recharge 1,000 MMK', 1000.00, 'atom-topup' UNION ALL
  SELECT 'ATOM 3,000 MMK Top-up', 'atom-3000', 'ATOM recharge 3,000 MMK', 3000.00, 'atom-topup' UNION ALL
  SELECT 'ATOM 5,000 MMK Top-up', 'atom-5000', 'ATOM recharge 5,000 MMK', 5000.00, 'atom-topup' UNION ALL
  SELECT 'ATOM 10,000 MMK Top-up', 'atom-10000', 'ATOM recharge 10,000 MMK', 10000.00, 'atom-topup' UNION ALL
  SELECT 'Mytel 1,000 MMK Top-up', 'mytel-1000', 'Mytel recharge 1,000 MMK', 1000.00, 'mytel-topup' UNION ALL
  SELECT 'Mytel 3,000 MMK Top-up', 'mytel-3000', 'Mytel recharge 3,000 MMK', 3000.00, 'mytel-topup' UNION ALL
  SELECT 'Mytel 5,000 MMK Top-up', 'mytel-5000', 'Mytel recharge 5,000 MMK', 5000.00, 'mytel-topup' UNION ALL
  SELECT 'Mytel 10,000 MMK Top-up', 'mytel-10000', 'Mytel recharge 10,000 MMK', 10000.00, 'mytel-topup'
) AS v
JOIN categories c ON c.slug = v.cat_slug
WHERE NOT EXISTS (SELECT 1 FROM products p WHERE p.slug = v.slug);

-- Gift card denominations (all same price ladder)
INSERT INTO products (category_id, product_name, slug, description, price, old_price, stock, product_image, status)
SELECT c.id, v.name, v.slug, v.description, v.price, NULL, 99999, 'giftcards_card.jpg', 'active'
FROM (
  SELECT 'Steam Wallet $5' AS name, 'steam-5-usd' AS slug, 'Redeemable Steam Wallet code $5' AS description, 22500.00 AS price, 'steam-wallet' AS cat_slug UNION ALL
  SELECT 'Steam Wallet $10', 'steam-10-usd', 'Redeemable Steam Wallet code $10', 45000.00, 'steam-wallet' UNION ALL
  SELECT 'Steam Wallet $15', 'steam-15-usd', 'Redeemable Steam Wallet code $15', 67500.00, 'steam-wallet' UNION ALL
  SELECT 'Steam Wallet $20', 'steam-20-usd', 'Redeemable Steam Wallet code $20', 90000.00, 'steam-wallet' UNION ALL
  SELECT 'Steam Wallet $25', 'steam-25-usd', 'Redeemable Steam Wallet code $25', 112500.00, 'steam-wallet' UNION ALL
  SELECT 'Steam Wallet $50', 'steam-50-usd', 'Redeemable Steam Wallet code $50', 225000.00, 'steam-wallet' UNION ALL
  SELECT 'Steam Wallet $100', 'steam-100-usd', 'Redeemable Steam Wallet code $100', 450000.00, 'steam-wallet' UNION ALL

  SELECT 'Google Play Gift Card $5', 'gplay-5-usd', 'Google Play gift card $5', 22500.00, 'google-play-gift-cards' UNION ALL
  SELECT 'Google Play Gift Card $10', 'gplay-10-usd', 'Google Play gift card $10', 45000.00, 'google-play-gift-cards' UNION ALL
  SELECT 'Google Play Gift Card $15', 'gplay-15-usd', 'Google Play gift card $15', 67500.00, 'google-play-gift-cards' UNION ALL
  SELECT 'Google Play Gift Card $20', 'gplay-20-usd', 'Google Play gift card $20', 90000.00, 'google-play-gift-cards' UNION ALL
  SELECT 'Google Play Gift Card $25', 'gplay-25-usd', 'Google Play gift card $25', 112500.00, 'google-play-gift-cards' UNION ALL
  SELECT 'Google Play Gift Card $50', 'gplay-50-usd', 'Google Play gift card $50', 225000.00, 'google-play-gift-cards' UNION ALL
  SELECT 'Google Play Gift Card $100', 'gplay-100-usd', 'Google Play gift card $100', 450000.00, 'google-play-gift-cards' UNION ALL

  SELECT 'App Store Gift Card $5', 'appstore-5-usd', 'App Store / Apple gift card $5', 22500.00, 'app-store-gift-cards' UNION ALL
  SELECT 'App Store Gift Card $10', 'appstore-10-usd', 'App Store / Apple gift card $10', 45000.00, 'app-store-gift-cards' UNION ALL
  SELECT 'App Store Gift Card $15', 'appstore-15-usd', 'App Store / Apple gift card $15', 67500.00, 'app-store-gift-cards' UNION ALL
  SELECT 'App Store Gift Card $20', 'appstore-20-usd', 'App Store / Apple gift card $20', 90000.00, 'app-store-gift-cards' UNION ALL
  SELECT 'App Store Gift Card $25', 'appstore-25-usd', 'App Store / Apple gift card $25', 112500.00, 'app-store-gift-cards' UNION ALL
  SELECT 'App Store Gift Card $50', 'appstore-50-usd', 'App Store / Apple gift card $50', 225000.00, 'app-store-gift-cards' UNION ALL
  SELECT 'App Store Gift Card $100', 'appstore-100-usd', 'App Store / Apple gift card $100', 450000.00, 'app-store-gift-cards'
) AS v
JOIN categories c ON c.slug = v.cat_slug
WHERE NOT EXISTS (SELECT 1 FROM products p WHERE p.slug = v.slug);

-- Premium account durations (prices per spec)
INSERT INTO products (category_id, product_name, slug, description, price, old_price, stock, product_image, status)
SELECT c.id, v.name, v.slug, v.description, v.price, NULL, 5000, 'premium_card.jpg', 'active'
FROM (
  SELECT 'Spotify Premium 1 Month' AS name, 'spotify-1m-mmk' AS slug, 'Spotify Premium subscription 1 month' AS description, 22500.00 AS price, 'spotify-premium' AS cat_slug UNION ALL
  SELECT 'Spotify Premium 2 Months', 'spotify-2m-mmk', 'Spotify Premium subscription 2 months', 45000.00, 'spotify-premium' UNION ALL
  SELECT 'Spotify Premium 3 Months', 'spotify-3m-mmk', 'Spotify Premium subscription 3 months', 67500.00, 'spotify-premium' UNION ALL
  SELECT 'Spotify Premium 6 Months', 'spotify-6m-mmk', 'Spotify Premium subscription 6 months', 225000.00, 'spotify-premium' UNION ALL
  SELECT 'Spotify Premium 1 Year', 'spotify-12m-mmk', 'Spotify Premium subscription 1 year', 450000.00, 'spotify-premium' UNION ALL

  SELECT 'YouTube Premium 1 Month', 'ytp-1m-mmk', 'YouTube Premium subscription 1 month', 22500.00, 'youtube-premium' UNION ALL
  SELECT 'YouTube Premium 2 Months', 'ytp-2m-mmk', 'YouTube Premium subscription 2 months', 45000.00, 'youtube-premium' UNION ALL
  SELECT 'YouTube Premium 3 Months', 'ytp-3m-mmk', 'YouTube Premium subscription 3 months', 67500.00, 'youtube-premium' UNION ALL
  SELECT 'YouTube Premium 6 Months', 'ytp-6m-mmk', 'YouTube Premium subscription 6 months', 225000.00, 'youtube-premium' UNION ALL
  SELECT 'YouTube Premium 1 Year', 'ytp-12m-mmk', 'YouTube Premium subscription 1 year', 450000.00, 'youtube-premium' UNION ALL

  SELECT 'Netflix Premium 1 Month', 'nfx-1m-mmk', 'Netflix Premium subscription 1 month', 22500.00, 'netflix-premium' UNION ALL
  SELECT 'Netflix Premium 2 Months', 'nfx-2m-mmk', 'Netflix Premium subscription 2 months', 45000.00, 'netflix-premium' UNION ALL
  SELECT 'Netflix Premium 3 Months', 'nfx-3m-mmk', 'Netflix Premium subscription 3 months', 67500.00, 'netflix-premium' UNION ALL
  SELECT 'Netflix Premium 6 Months', 'nfx-6m-mmk', 'Netflix Premium subscription 6 months', 225000.00, 'netflix-premium' UNION ALL
  SELECT 'Netflix Premium 1 Year', 'nfx-12m-mmk', 'Netflix Premium subscription 1 year', 450000.00, 'netflix-premium' UNION ALL

  SELECT 'Telegram Premium 1 Month', 'tgp-1m-mmk', 'Telegram Premium subscription 1 month', 22500.00, 'telegram-premium' UNION ALL
  SELECT 'Telegram Premium 2 Months', 'tgp-2m-mmk', 'Telegram Premium subscription 2 months', 45000.00, 'telegram-premium' UNION ALL
  SELECT 'Telegram Premium 3 Months', 'tgp-3m-mmk', 'Telegram Premium subscription 3 months', 67500.00, 'telegram-premium' UNION ALL
  SELECT 'Telegram Premium 6 Months', 'tgp-6m-mmk', 'Telegram Premium subscription 6 months', 225000.00, 'telegram-premium' UNION ALL
  SELECT 'Telegram Premium 1 Year', 'tgp-12m-mmk', 'Telegram Premium subscription 1 year', 450000.00, 'telegram-premium' UNION ALL

  SELECT 'ChatGPT Premium 1 Month', 'cgpt-1m-mmk', 'ChatGPT Premium subscription 1 month', 22500.00, 'chatgpt-premium' UNION ALL
  SELECT 'ChatGPT Premium 2 Months', 'cgpt-2m-mmk', 'ChatGPT Premium subscription 2 months', 45000.00, 'chatgpt-premium' UNION ALL
  SELECT 'ChatGPT Premium 3 Months', 'cgpt-3m-mmk', 'ChatGPT Premium subscription 3 months', 67500.00, 'chatgpt-premium' UNION ALL
  SELECT 'ChatGPT Premium 6 Months', 'cgpt-6m-mmk', 'ChatGPT Premium subscription 6 months', 225000.00, 'chatgpt-premium' UNION ALL
  SELECT 'ChatGPT Premium 1 Year', 'cgpt-12m-mmk', 'ChatGPT Premium subscription 1 year', 450000.00, 'chatgpt-premium'
) AS v
JOIN categories c ON c.slug = v.cat_slug
WHERE NOT EXISTS (SELECT 1 FROM products p WHERE p.slug = v.slug);
