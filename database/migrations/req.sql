-- Add long-form content to categories
ALTER TABLE categories
  ADD COLUMN IF NOT EXISTS long_description LONGTEXT NULL,
  ADD COLUMN IF NOT EXISTS faq LONGTEXT NULL,
  ADD COLUMN IF NOT EXISTS guide LONGTEXT NULL,
  ADD COLUMN IF NOT EXISTS video_url VARCHAR(255) NULL;

-- Optional badges
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS is_popular TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS is_best_value TINYINT(1) NOT NULL DEFAULT 0;

UPDATE categories SET
  long_description = 'Genshin Impact is a free-to-play action RPG across PS4, iOS, Android, and PC. Explore an open world with gacha mechanics, build your team, and conquer domains.',
  faq = 'First top-up bonus applies only if you never recharged elsewhere.\nPacks: 60–12,960 Genesis Crystals.\nBanners rotate heroes (check in-game).',
  guide = '1) Select a Genesis Crystal pack.\n2) Enter UID + server.\n3) Pick payment method and checkout.\n4) Crystals credit shortly.\nFind UID in profile (bottom-right).',
  video_url = 'https://www.youtube.com/embed/MOkyO-PYAGY'
WHERE slug = 'genshin-impact';

UPDATE categories SET
  long_description = 'Mobile Legends Diamonds: top up to unlock skins, heroes, and events.',
  faq = 'Delivery is instant after payment.\nUse the correct User ID and Server.',
  guide = '1) Choose Diamonds pack.\n2) Enter MLBB User ID + Server.\n3) Pay and receive Diamonds instantly.',
  video_url = ''
WHERE slug = 'mlbb-diamonds';

UPDATE categories SET
  long_description = 'PUBG UC for skins, Royale Pass, and crates.',
  faq = 'UC delivered after payment.\nEnsure correct Player ID.',
  guide = '1) Pick UC amount.\n2) Enter Player ID.\n3) Pay and receive UC.',
  video_url = ''
WHERE slug = 'pubg-uc';

UPDATE categories SET
  long_description = 'Free Fire Diamonds to unlock skins and events.',
  faq = 'Diamonds delivered after payment.\nUse correct Player ID.',
  guide = '1) Pick Diamonds pack.\n2) Enter Player ID.\n3) Pay and receive Diamonds.',
  video_url = ''
WHERE slug = 'freefire-diamonds';

UPDATE categories SET
  long_description = 'Valorant Points for skins and Battle Pass.',
  faq = 'VP delivered after payment.\nRiot ID required.',
  guide = '1) Pick VP pack.\n2) Enter Riot ID.\n3) Pay and receive VP.',
  video_url = ''
WHERE slug = 'valorant-points';

UPDATE categories SET
  long_description = 'Gift Cards for multiple services.',
  faq = 'Codes delivered after payment.\nCheck region compatibility.',
  guide = '1) Pick card value.\n2) Pay and receive code.',
  video_url = ''
WHERE slug = 'gift-cards';

UPDATE categories SET
  long_description = 'Myanmar top-up services.',
  faq = 'Top-ups delivered after payment.',
  guide = '1) Enter phone number.\n2) Pick amount.\n3) Pay and receive top-up.',
  video_url = ''
WHERE slug = 'mm-topup';

UPDATE products SET is_best_value = 1 WHERE slug = 'genshin-impact-6480' OR product_name LIKE '%6480%';
UPDATE products SET is_popular = 1 WHERE slug IN ('genshin-impact-980','mlbb-diamonds-257','pubg-uc-325');
