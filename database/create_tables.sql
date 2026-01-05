-- One-time helper to ensure key account tables exist

CREATE TABLE IF NOT EXISTS wishlists (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  product_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_product (user_id, product_id)
);

CREATE TABLE IF NOT EXISTS category_wishlists (
  user_id INT NOT NULL,
  category_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(user_id, category_id)
);

CREATE TABLE IF NOT EXISTS ai_chat_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  session_id VARCHAR(191) NOT NULL,
  role ENUM('user','assistant') NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id_id (user_id, id),
  INDEX idx_session_id_id (session_id, id)
);

-- Helpful secondary indexes
CREATE INDEX idx_orders_user_created ON orders(user_id, created_at);
CREATE INDEX idx_order_items_order ON order_items(order_id);
