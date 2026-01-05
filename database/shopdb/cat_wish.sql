DROP TABLE IF EXISTS category_wishlists;

CREATE TABLE category_wishlists (
  user_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, category_id),
  INDEX idx_catwl_user (user_id),
  INDEX idx_catwl_cat (category_id),
  CONSTRAINT fk_catwl_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_catwl_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
