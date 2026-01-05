-- Seed commonly requested categories/products (inspired by seagm) for dokebi_dbt.
USE dokebi_dbt;

-- Ensure main categories exist
INSERT INTO main_categories (name, slug, description, cover_image, accent_color, is_active, sort_order) VALUES
('Mobile Game Top-Up', 'mobile-game-topup', 'Top up your favorite mobile games', 'main_mobile_topup.jpg', '#22c55e', 1, 1),
('PC Game Top-Up', 'pc-game-topup', 'Top up for PC / desktop titles', 'main_pc_topup.jpg', '#3b82f6', 1, 2),
('Mobile Top-Up', 'mobile-topup', 'Telco and prepaid mobile balance', 'main_mobile_credit.jpg', '#f97316', 1, 3),
('Premium Account', 'premium-account', 'Streaming and premium subscriptions', 'main_premium.jpg', '#8b5cf6', 1, 4),
('Gift Cards', 'gift-cards', 'Gaming and store gift cards', 'main_giftcards.jpg', '#f59e0b', 1, 5)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  cover_image = VALUES(cover_image),
  accent_color = VALUES(accent_color),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Ensure categories exist (attach to a main category and include card images for UI tiles)
INSERT INTO categories (category_name, slug, description, main_category_id, card_image, is_active, sort_order) VALUES
('Mobile Legends Diamonds', 'mlbb-diamonds', 'MLBB top-up', (SELECT id FROM main_categories WHERE slug='mobile-game-topup'), 'mlbb_card.jpg', 1, 1),
('PUBG UC', 'pubg-uc', 'PUBG Mobile UC top-up', (SELECT id FROM main_categories WHERE slug='mobile-game-topup'), 'pubg_card.jpg', 1, 2),
('Free Fire Diamonds', 'freefire-diamonds', 'Garena Free Fire top-up', (SELECT id FROM main_categories WHERE slug='mobile-game-topup'), 'freefire_card.jpg', 1, 3),
('Valorant Points', 'valorant-points', 'Valorant top-up for PC', (SELECT id FROM main_categories WHERE slug='pc-game-topup'), 'valorant_card.jpg', 1, 4),
('Myanmar Top Up', 'mm-topup', 'MPT, ATOM, Mytel, Ooredoo top-up', (SELECT id FROM main_categories WHERE slug='mobile-topup'), 'mm_topup_card.jpg', 1, 5),
('Game Gift Cards', 'gift-cards', 'Steam, PlayStation, Google Play, iTunes', (SELECT id FROM main_categories WHERE slug='gift-cards'), 'giftcards_card.jpg', 1, 6),
('Streaming & Premium', 'premium-accounts', 'Spotify, Netflix, others', (SELECT id FROM main_categories WHERE slug='premium-account'), 'premium_card.jpg', 1, 7)
ON DUPLICATE KEY UPDATE
  category_name = VALUES(category_name),
  description = VALUES(description),
  main_category_id = VALUES(main_category_id),
  card_image = VALUES(card_image),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Seed products (slugs must be unique)
INSERT INTO products (category_id, product_name, slug, description, price, old_price, stock, product_image, status)
VALUES
-- MLBB
((SELECT id FROM categories WHERE slug='mlbb-diamonds'), 'MLBB 86 Diamonds', 'mlbb-86', 'Instant MLBB top-up 86 diamonds', 1.50, 1.80, 5000, 'mlbb_86.png', 'active'),
((SELECT id FROM categories WHERE slug='mlbb-diamonds'), 'MLBB 172 Diamonds', 'mlbb-172', 'Instant MLBB top-up 172 diamonds', 2.90, 3.20, 5000, 'mlbb_172.png', 'active'),
((SELECT id FROM categories WHERE slug='mlbb-diamonds'), 'MLBB 257 Diamonds', 'mlbb-257', 'Fast delivery diamonds', 4.30, 4.90, 4000, 'mlbb_257.png', 'active'),
((SELECT id FROM categories WHERE slug='mlbb-diamonds'), 'MLBB Weekly Diamond Pass', 'mlbb-weekly', 'Weekly pass for Mobile Legends', 1.20, NULL, 3000, 'mlbb_weekly.png', 'active'),
((SELECT id FROM categories WHERE slug='mlbb-diamonds'), 'MLBB Twilight Pass', 'mlbb-twilight', 'Monthly twilight pass', 9.99, 11.99, 1500, 'mlbb_twilight.png', 'active'),
-- PUBG
((SELECT id FROM categories WHERE slug='pubg-uc'), 'PUBG 60 UC', 'pubg-60', 'Official PUBG UC 60', 1.20, 1.50, 5000, 'pubg_60.png', 'active'),
((SELECT id FROM categories WHERE slug='pubg-uc'), 'PUBG 325 UC', 'pubg-325', 'Best PUBG 325 UC pack', 5.50, 5.99, 4000, 'pubg_325.png', 'active'),
((SELECT id FROM categories WHERE slug='pubg-uc'), 'PUBG 660 UC', 'pubg-660', 'Popular UC bundle', 9.90, 10.50, 3000, 'pubg_660.png', 'active'),
((SELECT id FROM categories WHERE slug='pubg-uc'), 'PUBG 1800 UC', 'pubg-1800', 'Large UC bundle', 25.99, 28.00, 2000, 'pubg_1800.png', 'active'),
-- Free Fire
((SELECT id FROM categories WHERE slug='freefire-diamonds'), 'Free Fire 100 Diamonds', 'ff-100', 'Garena Free Fire diamonds', 1.00, 1.20, 5000, 'ff_100.png', 'active'),
((SELECT id FROM categories WHERE slug='freefire-diamonds'), 'Free Fire 310 Diamonds', 'ff-310', 'Popular FF bundle', 3.20, 3.60, 4000, 'ff_310.png', 'active'),
((SELECT id FROM categories WHERE slug='freefire-diamonds'), 'Free Fire 520 Diamonds', 'ff-520', 'Great value FF pack', 5.20, 5.80, 3000, 'ff_520.png', 'active'),
((SELECT id FROM categories WHERE slug='freefire-diamonds'), 'Free Fire Weekly Membership', 'ff-weekly', 'Weekly membership top-up', 1.20, NULL, 2500, 'ff_weekly.png', 'active'),
-- Gift Cards
((SELECT id FROM categories WHERE slug='gift-cards'), 'Steam Wallet $20 (Global)', 'steam-20', 'Steam Wallet Code', 20.00, 22.00, 800, 'steam_20.png', 'active'),
((SELECT id FROM categories WHERE slug='gift-cards'), 'Steam Wallet $50 (Global)', 'steam-50', 'Steam Wallet Code', 50.00, 55.00, 600, 'steam_50.png', 'active'),
((SELECT id FROM categories WHERE slug='gift-cards'), 'PlayStation Store $50 (US)', 'psn-50-us', 'PSN card US region', 50.00, 55.00, 600, 'psn_50_us.png', 'active'),
((SELECT id FROM categories WHERE slug='gift-cards'), 'Google Play Gift Card $25 (US)', 'gplay-25-us', 'Google Play code US', 25.00, 28.00, 600, 'gplay_25_us.png', 'active'),
((SELECT id FROM categories WHERE slug='gift-cards'), 'iTunes Gift Card $25 (US)', 'itunes-25-us', 'iTunes/Apple gift', 25.00, 28.00, 600, 'itunes_25_us.png', 'active'),
-- Streaming/Premium
((SELECT id FROM categories WHERE slug='premium-accounts'), 'Netflix Premium 1 Month', 'netflix-1m', 'Premium UHD 1 month', 12.99, 14.99, 500, 'netflix_1m.png', 'active'),
((SELECT id FROM categories WHERE slug='premium-accounts'), 'Spotify Premium 1 Month', 'spotify-1m', 'Spotify individual 1 month', 4.99, 5.99, 800, 'spotify_1m.png', 'active'),
((SELECT id FROM categories WHERE slug='premium-accounts'), 'YouTube Premium 1 Month', 'youtube-1m', 'YouTube Premium access', 9.99, 10.99, 700, 'yt_1m.png', 'active'),
-- PC Game
((SELECT id FROM categories WHERE slug='valorant-points'), 'Valorant 475 VP', 'valorant-475', 'Desktop top-up for Valorant', 5.49, 6.10, 3000, 'valorant_475.png', 'active'),
-- Myanmar Top-Up
((SELECT id FROM categories WHERE slug='mm-topup'), 'MPT 10,000 MMK Top-Up', 'mpt-10000', 'Mobile balance for MPT numbers', 6.50, NULL, 8000, 'mpt_10000.png', 'active')
ON DUPLICATE KEY UPDATE
product_name = VALUES(product_name),
description = VALUES(description),
price = VALUES(price),
old_price = VALUES(old_price),
stock = VALUES(stock),
product_image = VALUES(product_image),
status = VALUES(status),
category_id = VALUES(category_id);
