-- Add long-form content fields to categories (run once).
ALTER TABLE categories
  ADD COLUMN IF NOT EXISTS long_description LONGTEXT NULL,
  ADD COLUMN IF NOT EXISTS faq LONGTEXT NULL,
  ADD COLUMN IF NOT EXISTS guide LONGTEXT NULL,
  ADD COLUMN IF NOT EXISTS video_url VARCHAR(255) NULL;

-- Optional: product badges for Popular / Best Value
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS is_popular TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS is_best_value TINYINT(1) NOT NULL DEFAULT 0;
