DROP DATABASE IF EXISTS DB_dokebi;
CREATE DATABASE DB_dokebi;
USE DB_dokebi;

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE users (
  id              INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  name            VARCHAR(100) NOT NULL,
  username        VARCHAR(50) NOT NULL UNIQUE,
  email           VARCHAR(150) NOT NULL UNIQUE,
  password        VARCHAR(255) NOT NULL,
  role            ENUM('user','admin') NOT NULL DEFAULT 'user',
  phone           VARCHAR(30) DEFAULT NULL,
  address         TEXT DEFAULT NULL,
  profile_image   VARCHAR(255) DEFAULT 'default_user.png',
  status          ENUM('active','blocked') DEFAULT 'active',
  created_at      TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  updated_at      TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users(name, username, email, password, role, profile_image)
VALUES ('Site Admin', 'admin', 'admin@gmail.com', '1234', 'admin', 'default_user.png');

CREATE TABLE main_categories (
  id           INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(120) NOT NULL,
  slug         VARCHAR(150) NOT NULL UNIQUE,
  description  TEXT DEFAULT NULL,
  cover_image  VARCHAR(255) DEFAULT 'default_main_category.jpg',
  accent_color VARCHAR(20) DEFAULT '#22c55e',
  is_active    TINYINT(1) DEFAULT 1,
  sort_order   INT DEFAULT 0,
  created_at   TIMESTAMP DEFAULT current_timestamp(),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO main_categories(name, slug, description, cover_image, accent_color, is_active, sort_order) VALUES
('Mobile Game Top-Up', 'mobile-game-topup', 'Top up your favorite mobile games', 'main_mobile_topup.jpg', '#22c55e', 1, 1),
('PC Game Top-Up', 'pc-game-topup', 'Top up for PC / desktop titles', 'main_pc_topup.jpg', '#3b82f6', 1, 2),
('Myanmar Mobile Top-up', 'myanmar-mobile-topup', 'Local telco balance (MPT, Atom, Mytel, Ooredoo/U9)', 'main_mobile_credit.jpg', '#f97316', 1, 3),
('Mobile Top-Up', 'mobile-topup', 'Telco and prepaid mobile balance', 'main_mobile_credit.jpg', '#f97316', 1, 4),
('Premium Account', 'premium-account', 'Streaming and premium subscriptions', 'main_premium.jpg', '#8b5cf6', 1, 5),
('Gift Cards', 'gift-cards', 'Gaming and store gift cards', 'main_giftcards.jpg', '#f59e0b', 1, 6);

CREATE TABLE categories (
  id               INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  main_category_id INT(10) UNSIGNED DEFAULT NULL,
  category_name    VARCHAR(100) NOT NULL,
  slug             VARCHAR(150) NOT NULL UNIQUE,
  description      TEXT DEFAULT NULL,
  card_image       VARCHAR(255) DEFAULT 'default_category.jpg',
  sort_order       INT DEFAULT 0,
  is_active        TINYINT(1) DEFAULT 1,
  created_at       TIMESTAMP DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY idx_categories_main (main_category_id),
  FOREIGN KEY (main_category_id) REFERENCES main_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO categories(category_name, slug, description, main_category_id, card_image, is_active, sort_order) VALUES
('Mobile Legends Diamonds', 'mlbb-diamonds', 'MLBB Top Up.', (SELECT id FROM main_categories WHERE slug='mobile-game-topup'), 'mlbb_card.jpg', 1, 1),
('PUBG UC', 'pubg-uc', 'PUBG UC Top Up.', (SELECT id FROM main_categories WHERE slug='mobile-game-topup'), 'pubg_card.jpg', 1, 2),
('Free Fire Diamonds', 'freefire-diamonds', 'Garena Free Fire Diamonds.', (SELECT id FROM main_categories WHERE slug='mobile-game-topup'), 'freefire_card.jpg', 1, 3),
('Valorant Points', 'valorant-points', 'Valorant top-up for PC', (SELECT id FROM main_categories WHERE slug='pc-game-topup'), 'valorant_card.jpg', 1, 4),
('MPT Top-up', 'mpt-topup', 'MPT recharge packages', (SELECT id FROM main_categories WHERE slug='myanmar-mobile-topup'), 'mm_topup_card.jpg', 1, 5),
('U9 Top-up', 'u9-topup', 'Ooredoo / U9 recharge packages', (SELECT id FROM main_categories WHERE slug='myanmar-mobile-topup'), 'mm_topup_card.jpg', 1, 6),
('Atom Top-up', 'atom-topup', 'ATOM recharge packages', (SELECT id FROM main_categories WHERE slug='myanmar-mobile-topup'), 'mm_topup_card.jpg', 1, 7),
('Mytel Top-up', 'mytel-topup', 'Mytel recharge packages', (SELECT id FROM main_categories WHERE slug='myanmar-mobile-topup'), 'mm_topup_card.jpg', 1, 8),
('Myanmar Top Up', 'mm-topup', 'MPT, ATOM, Mytel, Ooredoo Top Up.', (SELECT id FROM main_categories WHERE slug='mobile-topup'), 'mm_topup_card.jpg', 1, 9),
('Steam Wallet', 'steam-wallet', 'Steam, Stream Wallet gift cards.', (SELECT id FROM main_categories WHERE slug='gift-cards'), 'giftcards_card.jpg', 1, 10),
('Google Play Gift Cards', 'google-play-gift-cards', 'Google Play gift cards.', (SELECT id FROM main_categories WHERE slug='gift-cards'), 'giftcards_card.jpg', 1, 11),
('App Store Gift Cards', 'app-store-gift-cards', 'App Store / Apple gift cards.', (SELECT id FROM main_categories WHERE slug='gift-cards'), 'giftcards_card.jpg', 1, 12),
('Game Gift Cards', 'gift-cards', 'Steam, Google Play, PSN Cards.', (SELECT id FROM main_categories WHERE slug='gift-cards'), 'giftcards_card.jpg', 1, 13),
('Premium Accounts', 'premium-accounts', 'Netflix, Spotify, ChatGPT, Adobe.', (SELECT id FROM main_categories WHERE slug='premium-account'), 'premium_card.jpg', 1, 14),
('Spotify Premium', 'spotify-premium', 'Spotify premium accounts', (SELECT id FROM main_categories WHERE slug='premium-account'), 'premium_card.jpg', 1, 15),
('YouTube Premium', 'youtube-premium', 'YouTube premium accounts', (SELECT id FROM main_categories WHERE slug='premium-account'), 'premium_card.jpg', 1, 16),
('Netflix Premium', 'netflix-premium', 'Netflix premium accounts', (SELECT id FROM main_categories WHERE slug='premium-account'), 'premium_card.jpg', 1, 17),
('Telegram Premium', 'telegram-premium', 'Telegram premium accounts', (SELECT id FROM main_categories WHERE slug='premium-account'), 'premium_card.jpg', 1, 18),
('ChatGPT Premium', 'chatgpt-premium', 'ChatGPT premium accounts', (SELECT id FROM main_categories WHERE slug='premium-account'), 'premium_card.jpg', 1, 19);

CREATE TABLE products (
  id             INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id    INT(10) UNSIGNED NOT NULL,
  product_name   VARCHAR(200) NOT NULL,
  slug           VARCHAR(200) NOT NULL UNIQUE,
  description    TEXT DEFAULT NULL,
  price          DECIMAL(10,2) NOT NULL,
  old_price      DECIMAL(10,2) DEFAULT NULL,
  stock          INT DEFAULT 999999,
  product_image  VARCHAR(255) DEFAULT 'default_product.png',
  status         ENUM('active','inactive') DEFAULT 'active',
  created_at     TIMESTAMP DEFAULT current_timestamp(),
  updated_at     TIMESTAMP DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id),
  FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


INSERT INTO products(category_id, product_name, slug, description, price, old_price, stock, product_image, status)
VALUES
((SELECT id FROM categories WHERE slug='mlbb-diamonds'), 'MLBB 86 Diamonds', 'mlbb-86', 'Instant 86 diamonds.', 1.50, 1.80, 5000, 'mlbb_86.png', 'active'),
((SELECT id FROM categories WHERE slug='mlbb-diamonds'), 'MLBB 172 Diamonds', 'mlbb-172', 'Instant 172 diamonds.', 2.90, 3.20, 5000, 'mlbb_172.png', 'active'),
((SELECT id FROM categories WHERE slug='mlbb-diamonds'), 'MLBB Twilight Pass', 'mlbb-twilight', 'Twilight Monthly Pass.', 9.99, 11.99, 3000, 'mlbb_tp.png', 'active'),
((SELECT id FROM categories WHERE slug='pubg-uc'), 'PUBG 60 UC', 'pubg-60', 'Official PUBG UC.', 1.20, 1.50, 5000, 'pubg_60.png', 'active'),
((SELECT id FROM categories WHERE slug='pubg-uc'), 'PUBG 325 UC', 'pubg-325', 'Best PUBG 325 UC.', 5.50, 5.99, 4000, 'pubg_325.png', 'active'),
((SELECT id FROM categories WHERE slug='valorant-points'), 'Valorant 475 VP', 'valorant-475', 'Desktop top-up for Valorant', 5.49, 6.10, 3000, 'valorant_475.png', 'active');

-- Myanmar telco top-up (per carrier)
INSERT INTO products(category_id, product_name, slug, description, price, old_price, stock, product_image, status) VALUES
((SELECT id FROM categories WHERE slug='mpt-topup'), 'MPT 1,000 MMK Top-up', 'mpt-1000', 'MPT recharge 1,000 MMK', 1000.00, NULL, 999999, 'default_product.png', 'active'),
((SELECT id FROM categories WHERE slug='mpt-topup'), 'MPT 3,000 MMK Top-up', 'mpt-3000', 'MPT recharge 3,000 MMK', 3000.00, NULL, 999999, 'default_product.png', 'active'),
((SELECT id FROM categories WHERE slug='mpt-topup'), 'MPT 5,000 MMK Top-up', 'mpt-5000', 'MPT recharge 5,000 MMK', 5000.00, NULL, 999999, 'default_product.png', 'active'),
((SELECT id FROM categories WHERE slug='mpt-topup'), 'MPT 10,000 MMK Top-up', 'mpt-10000', 'MPT recharge 10,000 MMK', 10000.00, NULL, 999999, 'default_product.png', 'active'),
((SELECT id FROM categories WHERE slug='u9-topup'), 'U9 1,000 MMK Top-up', 'u9-1000', 'U9 / Ooredoo recharge 1,000 MMK', 1000.00, NULL, 999999, 'default_product.png', 'active'),
((SELECT id FROM categories WHERE slug='u9-topup'), 'U9 3,000 MMK Top-up', 'u9-3000', 'U9 / Ooredoo recharge 3,000 MMK', 3000.00, NULL, 999999, 'default_product.png', 'active'),
((SELECT id FROM categories WHERE slug='u9-topup'), 'U9 5,000 MMK Top-up', 'u9-5000', 'U9 / Ooredoo recharge 5,000 MMK', 5000.00, NULL, 999999, 'default_product.png', 'active'),
((SELECT id FROM categories WHERE slug='u9-topup'), 'U9 10,000 MMK Top-up', 'u9-10000', 'U9 / Ooredoo recharge 10,000 MMK', 10000.00, NULL, 999999, 'default_product.png', 'active'),
((SELECT id FROM categories WHERE slug='atom-topup'), 'ATOM 1,000 MMK Top-up', 'atom-1000', 'ATOM recharge 1,000 MMK', 1000.00, NULL, 999999, 'default_product.png', 'active'),
((SELECT id FROM categories WHERE slug='atom-topup'), 'ATOM 3,000 MMK Top-up', 'atom-3000', 'ATOM recharge 3,000 MMK', 3000.00, NULL, 999999, 'default_product.png', 'active'),
((SELECT id FROM categories WHERE slug='atom-topup'), 'ATOM 5,000 MMK Top-up', 'atom-5000', 'ATOM recharge 5,000 MMK', 5000.00, NULL, 999999, 'default_product.png', 'active'),
((SELECT id FROM categories WHERE slug='atom-topup'), 'ATOM 10,000 MMK Top-up', 'atom-10000', 'ATOM recharge 10,000 MMK', 10000.00, NULL, 999999, 'default_product.png', 'active'),
((SELECT id FROM categories WHERE slug='mytel-topup'), 'Mytel 1,000 MMK Top-up', 'mytel-1000', 'Mytel recharge 1,000 MMK', 1000.00, NULL, 999999, 'default_product.png', 'active'),
((SELECT id FROM categories WHERE slug='mytel-topup'), 'Mytel 3,000 MMK Top-up', 'mytel-3000', 'Mytel recharge 3,000 MMK', 3000.00, NULL, 999999, 'default_product.png', 'active'),
((SELECT id FROM categories WHERE slug='mytel-topup'), 'Mytel 5,000 MMK Top-up', 'mytel-5000', 'Mytel recharge 5,000 MMK', 5000.00, NULL, 999999, 'default_product.png', 'active'),
((SELECT id FROM categories WHERE slug='mytel-topup'), 'Mytel 10,000 MMK Top-up', 'mytel-10000', 'Mytel recharge 10,000 MMK', 10000.00, NULL, 999999, 'default_product.png', 'active');

-- Gift card ladder (uniform MMK pricing)
INSERT INTO products(category_id, product_name, slug, description, price, old_price, stock, product_image, status) VALUES
((SELECT id FROM categories WHERE slug='steam-wallet'), 'Steam Wallet $5', 'steam-5-usd', 'Redeemable Steam Wallet code $5', 22500.00, NULL, 99999, 'giftcards_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='steam-wallet'), 'Steam Wallet $10', 'steam-10-usd', 'Redeemable Steam Wallet code $10', 45000.00, NULL, 99999, 'giftcards_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='steam-wallet'), 'Steam Wallet $15', 'steam-15-usd', 'Redeemable Steam Wallet code $15', 67500.00, NULL, 99999, 'giftcards_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='steam-wallet'), 'Steam Wallet $20', 'steam-20-usd', 'Redeemable Steam Wallet code $20', 90000.00, NULL, 99999, 'giftcards_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='steam-wallet'), 'Steam Wallet $25', 'steam-25-usd', 'Redeemable Steam Wallet code $25', 112500.00, NULL, 99999, 'giftcards_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='steam-wallet'), 'Steam Wallet $50', 'steam-50-usd', 'Redeemable Steam Wallet code $50', 225000.00, NULL, 99999, 'giftcards_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='steam-wallet'), 'Steam Wallet $100', 'steam-100-usd', 'Redeemable Steam Wallet code $100', 450000.00, NULL, 99999, 'giftcards_card.jpg', 'active'),

((SELECT id FROM categories WHERE slug='google-play-gift-cards'), 'Google Play Gift Card $5', 'gplay-5-usd', 'Google Play gift card $5', 22500.00, NULL, 99999, 'giftcards_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='google-play-gift-cards'), 'Google Play Gift Card $10', 'gplay-10-usd', 'Google Play gift card $10', 45000.00, NULL, 99999, 'giftcards_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='google-play-gift-cards'), 'Google Play Gift Card $15', 'gplay-15-usd', 'Google Play gift card $15', 67500.00, NULL, 99999, 'giftcards_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='google-play-gift-cards'), 'Google Play Gift Card $20', 'gplay-20-usd', 'Google Play gift card $20', 90000.00, NULL, 99999, 'giftcards_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='google-play-gift-cards'), 'Google Play Gift Card $25', 'gplay-25-usd', 'Google Play gift card $25', 112500.00, NULL, 99999, 'giftcards_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='google-play-gift-cards'), 'Google Play Gift Card $50', 'gplay-50-usd', 'Google Play gift card $50', 225000.00, NULL, 99999, 'giftcards_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='google-play-gift-cards'), 'Google Play Gift Card $100', 'gplay-100-usd', 'Google Play gift card $100', 450000.00, NULL, 99999, 'giftcards_card.jpg', 'active'),

((SELECT id FROM categories WHERE slug='app-store-gift-cards'), 'App Store Gift Card $5', 'appstore-5-usd', 'App Store / Apple gift card $5', 22500.00, NULL, 99999, 'giftcards_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='app-store-gift-cards'), 'App Store Gift Card $10', 'appstore-10-usd', 'App Store / Apple gift card $10', 45000.00, NULL, 99999, 'giftcards_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='app-store-gift-cards'), 'App Store Gift Card $15', 'appstore-15-usd', 'App Store / Apple gift card $15', 67500.00, NULL, 99999, 'giftcards_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='app-store-gift-cards'), 'App Store Gift Card $20', 'appstore-20-usd', 'App Store / Apple gift card $20', 90000.00, NULL, 99999, 'giftcards_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='app-store-gift-cards'), 'App Store Gift Card $25', 'appstore-25-usd', 'App Store / Apple gift card $25', 112500.00, NULL, 99999, 'giftcards_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='app-store-gift-cards'), 'App Store Gift Card $50', 'appstore-50-usd', 'App Store / Apple gift card $50', 225000.00, NULL, 99999, 'giftcards_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='app-store-gift-cards'), 'App Store Gift Card $100', 'appstore-100-usd', 'App Store / Apple gift card $100', 450000.00, NULL, 99999, 'giftcards_card.jpg', 'active');

-- Premium account durations
INSERT INTO products(category_id, product_name, slug, description, price, old_price, stock, product_image, status) VALUES
((SELECT id FROM categories WHERE slug='spotify-premium'), 'Spotify Premium 1 Month', 'spotify-1m-mmk', 'Spotify Premium subscription 1 month', 22500.00, NULL, 5000, 'premium_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='spotify-premium'), 'Spotify Premium 2 Months', 'spotify-2m-mmk', 'Spotify Premium subscription 2 months', 45000.00, NULL, 5000, 'premium_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='spotify-premium'), 'Spotify Premium 3 Months', 'spotify-3m-mmk', 'Spotify Premium subscription 3 months', 67500.00, NULL, 5000, 'premium_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='spotify-premium'), 'Spotify Premium 6 Months', 'spotify-6m-mmk', 'Spotify Premium subscription 6 months', 225000.00, NULL, 5000, 'premium_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='spotify-premium'), 'Spotify Premium 1 Year', 'spotify-12m-mmk', 'Spotify Premium subscription 1 year', 450000.00, NULL, 5000, 'premium_card.jpg', 'active'),

((SELECT id FROM categories WHERE slug='youtube-premium'), 'YouTube Premium 1 Month', 'ytp-1m-mmk', 'YouTube Premium subscription 1 month', 22500.00, NULL, 5000, 'premium_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='youtube-premium'), 'YouTube Premium 2 Months', 'ytp-2m-mmk', 'YouTube Premium subscription 2 months', 45000.00, NULL, 5000, 'premium_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='youtube-premium'), 'YouTube Premium 3 Months', 'ytp-3m-mmk', 'YouTube Premium subscription 3 months', 67500.00, NULL, 5000, 'premium_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='youtube-premium'), 'YouTube Premium 6 Months', 'ytp-6m-mmk', 'YouTube Premium subscription 6 months', 225000.00, NULL, 5000, 'premium_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='youtube-premium'), 'YouTube Premium 1 Year', 'ytp-12m-mmk', 'YouTube Premium subscription 1 year', 450000.00, NULL, 5000, 'premium_card.jpg', 'active'),

((SELECT id FROM categories WHERE slug='netflix-premium'), 'Netflix Premium 1 Month', 'nfx-1m-mmk', 'Netflix Premium subscription 1 month', 22500.00, NULL, 5000, 'premium_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='netflix-premium'), 'Netflix Premium 2 Months', 'nfx-2m-mmk', 'Netflix Premium subscription 2 months', 45000.00, NULL, 5000, 'premium_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='netflix-premium'), 'Netflix Premium 3 Months', 'nfx-3m-mmk', 'Netflix Premium subscription 3 months', 67500.00, NULL, 5000, 'premium_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='netflix-premium'), 'Netflix Premium 6 Months', 'nfx-6m-mmk', 'Netflix Premium subscription 6 months', 225000.00, NULL, 5000, 'premium_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='netflix-premium'), 'Netflix Premium 1 Year', 'nfx-12m-mmk', 'Netflix Premium subscription 1 year', 450000.00, NULL, 5000, 'premium_card.jpg', 'active'),

((SELECT id FROM categories WHERE slug='telegram-premium'), 'Telegram Premium 1 Month', 'tgp-1m-mmk', 'Telegram Premium subscription 1 month', 22500.00, NULL, 5000, 'premium_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='telegram-premium'), 'Telegram Premium 2 Months', 'tgp-2m-mmk', 'Telegram Premium subscription 2 months', 45000.00, NULL, 5000, 'premium_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='telegram-premium'), 'Telegram Premium 3 Months', 'tgp-3m-mmk', 'Telegram Premium subscription 3 months', 67500.00, NULL, 5000, 'premium_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='telegram-premium'), 'Telegram Premium 6 Months', 'tgp-6m-mmk', 'Telegram Premium subscription 6 months', 225000.00, NULL, 5000, 'premium_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='telegram-premium'), 'Telegram Premium 1 Year', 'tgp-12m-mmk', 'Telegram Premium subscription 1 year', 450000.00, NULL, 5000, 'premium_card.jpg', 'active'),

((SELECT id FROM categories WHERE slug='chatgpt-premium'), 'ChatGPT Premium 1 Month', 'cgpt-1m-mmk', 'ChatGPT Premium subscription 1 month', 22500.00, NULL, 5000, 'premium_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='chatgpt-premium'), 'ChatGPT Premium 2 Months', 'cgpt-2m-mmk', 'ChatGPT Premium subscription 2 months', 45000.00, NULL, 5000, 'premium_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='chatgpt-premium'), 'ChatGPT Premium 3 Months', 'cgpt-3m-mmk', 'ChatGPT Premium subscription 3 months', 67500.00, NULL, 5000, 'premium_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='chatgpt-premium'), 'ChatGPT Premium 6 Months', 'cgpt-6m-mmk', 'ChatGPT Premium subscription 6 months', 225000.00, NULL, 5000, 'premium_card.jpg', 'active'),
((SELECT id FROM categories WHERE slug='chatgpt-premium'), 'ChatGPT Premium 1 Year', 'cgpt-12m-mmk', 'ChatGPT Premium subscription 1 year', 450000.00, NULL, 5000, 'premium_card.jpg', 'active');


CREATE TABLE wishlists (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT(10) UNSIGNED NOT NULL,
  product_id INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_user_product (user_id, product_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);


CREATE TABLE carts (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT(10) UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY unique_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);


CREATE TABLE cart_items (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  cart_id INT(10) UNSIGNED NOT NULL,
  product_id INT(10) UNSIGNED NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  unit_price DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_cart_product (cart_id, product_id),
  FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
);


CREATE TABLE orders (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT(10) UNSIGNED NOT NULL,
  order_code VARCHAR(50) NOT NULL UNIQUE,
  total_amount DECIMAL(10,2) NOT NULL,
  payment_method VARCHAR(50) DEFAULT 'KBZPay',
  payment_status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
  order_status ENUM('pending','processing','completed','cancelled','refunded') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE order_items (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT(10) UNSIGNED NOT NULL,
  product_id INT(10) UNSIGNED NOT NULL,
  product_name VARCHAR(200),
  quantity INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE TABLE payments (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT(10) UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  method VARCHAR(50) DEFAULT NULL,
  status ENUM('pending','success','failed') DEFAULT 'pending',
  transaction_ref VARCHAR(100) DEFAULT NULL,
  paid_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE TABLE giftcard_codes (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id INT(10) UNSIGNED DEFAULT NULL,
  product_id INT(10) UNSIGNED DEFAULT NULL,
  code VARCHAR(255) NOT NULL,
  is_used TINYINT(1) NOT NULL DEFAULT 0,
  used_by INT(10) UNSIGNED DEFAULT NULL,
  order_id INT(10) UNSIGNED DEFAULT NULL,
  delivered_to VARCHAR(190) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT current_timestamp(),
  used_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_code (code),
  KEY idx_giftcard_cat (category_id),
  KEY idx_giftcard_prod (product_id),
  KEY idx_giftcard_used (is_used),
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE premium_accounts_pool (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id INT(10) UNSIGNED DEFAULT NULL,
  product_id INT(10) UNSIGNED DEFAULT NULL,
  service VARCHAR(120) NOT NULL,
  username VARCHAR(190) NOT NULL,
  password VARCHAR(190) NOT NULL,
  is_assigned TINYINT(1) NOT NULL DEFAULT 0,
  assigned_to INT(10) UNSIGNED DEFAULT NULL,
  order_id INT(10) UNSIGNED DEFAULT NULL,
  assigned_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uniq_service_username (service, username),
  KEY idx_pool_cat (category_id),
  KEY idx_pool_prod (product_id),
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reviews (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT(10) UNSIGNED NOT NULL,
  product_id INT(10) UNSIGNED NOT NULL,
  rating TINYINT NOT NULL,
  comment TEXT DEFAULT NULL,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE ai_chat_logs (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT(10) UNSIGNED DEFAULT NULL,
  session_id VARCHAR(100) DEFAULT NULL,
  role ENUM('user','assistant','system') NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE settings (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT NOT NULL,
  PRIMARY KEY (id)
);

INSERT INTO settings(setting_key, setting_value) VALUES
('site_name', 'Dokebi Tekoku'),
('support_email', 'support@dokebi.com'),
('currency', 'MMK');


