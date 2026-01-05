-- Recreate Dokebi schema with the database name `dokebi_dbt`
DROP DATABASE IF EXISTS `dokebi_dbt`;
CREATE DATABASE `dokebi_dbt` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `dokebi_dbt`;

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE `users` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('user','admin') NOT NULL DEFAULT 'user',
  `phone` VARCHAR(30) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `profile_image` VARCHAR(255) DEFAULT 'default_user.png',
  `status` ENUM('active','blocked') DEFAULT 'active',
  `otp_code` VARCHAR(10) DEFAULT NULL,
  `otp_expires` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `users`(`name`, `username`, `email`, `password`, `role`, `profile_image`)
VALUES ('Site Admin', 'admin', 'admin@gmail.com', '1234', 'admin', 'default_user.png');

CREATE TABLE `main_categories` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(150) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `cover_image` VARCHAR(255) DEFAULT 'default_main_category.jpg',
  `accent_color` VARCHAR(20) DEFAULT '#22c55e',
  `is_active` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `main_categories`(`name`, `slug`, `description`, `cover_image`, `accent_color`, `is_active`, `sort_order`) VALUES
('Mobile Game Top-Up', 'mobile-game-topup', 'Top up your favorite mobile games', 'main_mobile_topup.jpg', '#22c55e', 1, 1),
('PC Game Top-Up', 'pc-game-topup', 'Top up for PC / desktop titles', 'main_pc_topup.jpg', '#3b82f6', 1, 2),
('Mobile Top-Up', 'mobile-topup', 'Telco and prepaid mobile balance', 'main_mobile_credit.jpg', '#f97316', 1, 3),
('Premium Account', 'premium-account', 'Streaming and premium subscriptions', 'main_premium.jpg', '#8b5cf6', 1, 4),
('Gift Cards', 'gift-cards', 'Gaming and store gift cards', 'main_giftcards.jpg', '#f59e0b', 1, 5);

CREATE TABLE `categories` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `main_category_id` INT(10) UNSIGNED DEFAULT NULL,
  `category_name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(150) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `card_image` VARCHAR(255) DEFAULT 'default_category.jpg',
  `sort_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_categories_main` (`main_category_id`),
  FOREIGN KEY (`main_category_id`) REFERENCES `main_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `categories`(`category_name`, `slug`, `description`, `main_category_id`, `card_image`, `is_active`, `sort_order`) VALUES
('Mobile Legends Diamonds', 'mlbb-diamonds', 'MLBB Top Up.', (SELECT id FROM `main_categories` WHERE `slug`='mobile-game-topup'), 'mlbb_card.jpg', 1, 1),
('PUBG UC', 'pubg-uc', 'PUBG UC Top Up.', (SELECT id FROM `main_categories` WHERE `slug`='mobile-game-topup'), 'pubg_card.jpg', 1, 2),
('Free Fire Diamonds', 'freefire-diamonds', 'Garena Free Fire Diamonds.', (SELECT id FROM `main_categories` WHERE `slug`='mobile-game-topup'), 'freefire_card.jpg', 1, 3),
('Valorant Points', 'valorant-points', 'Valorant top-up for PC', (SELECT id FROM `main_categories` WHERE `slug`='pc-game-topup'), 'valorant_card.jpg', 1, 4),
('Myanmar Top Up', 'mm-topup', 'MPT, ATOM, Mytel, Ooredoo Top Up.', (SELECT id FROM `main_categories` WHERE `slug`='mobile-topup'), 'mm_topup_card.jpg', 1, 5),
('Game Gift Cards', 'gift-cards', 'Steam, Google Play, PSN Cards.', (SELECT id FROM `main_categories` WHERE `slug`='gift-cards'), 'giftcards_card.jpg', 1, 6),
('Premium Accounts', 'premium-accounts', 'Netflix, Spotify, ChatGPT, Adobe.', (SELECT id FROM `main_categories` WHERE `slug`='premium-account'), 'premium_card.jpg', 1, 7);

CREATE TABLE `products` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT(10) UNSIGNED NOT NULL,
  `product_name` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(200) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `old_price` DECIMAL(10,2) DEFAULT NULL,
  `stock` INT DEFAULT 999999,
  `product_image` VARCHAR(255) DEFAULT 'default_product.png',
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `products`(`category_id`, `product_name`, `slug`, `description`, `price`, `old_price`, `product_image`) VALUES
((SELECT id FROM `categories` WHERE `slug`='mlbb-diamonds'), 'MLBB 86 Diamonds', 'mlbb-86', 'Instant 86 diamonds.', 1.50, 1.80, 'mlbb_86.png'),
((SELECT id FROM `categories` WHERE `slug`='mlbb-diamonds'), 'MLBB 172 Diamonds', 'mlbb-172', 'Instant 172 diamonds.', 2.90, 3.20, 'mlbb_172.png'),
((SELECT id FROM `categories` WHERE `slug`='mlbb-diamonds'), 'MLBB Twilight Pass', 'mlbb-twilight', 'Twilight Monthly Pass.', 9.99, 11.99, 'mlbb_tp.png'),
((SELECT id FROM `categories` WHERE `slug`='pubg-uc'), 'PUBG 60 UC', 'pubg-60', 'Official PUBG UC.', 1.20, 1.50, 'pubg_60.png'),
((SELECT id FROM `categories` WHERE `slug`='pubg-uc'), 'PUBG 325 UC', 'pubg-325', 'Best PUBG 325 UC.', 5.50, 5.99, 'pubg_325.png'),
((SELECT id FROM `categories` WHERE `slug`='valorant-points'), 'Valorant 475 VP', 'valorant-475', 'Desktop top-up for Valorant', 5.49, 6.10, 'valorant_475.png');

CREATE TABLE `wishlists` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(10) UNSIGNED NOT NULL,
  `product_id` INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_product` (`user_id`, `product_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `carts` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(10) UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `cart_items` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `cart_id` INT(10) UNSIGNED NOT NULL,
  `product_id` INT(10) UNSIGNED NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cart_product` (`cart_id`, `product_id`),
  FOREIGN KEY (`cart_id`) REFERENCES `carts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `orders` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(10) UNSIGNED NOT NULL,
  `order_code` VARCHAR(50) NOT NULL UNIQUE,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(50) DEFAULT 'KBZPay',
  `payment_status` ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
  `order_status` ENUM('pending','processing','completed','cancelled','refunded') DEFAULT 'pending',
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `order_items` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT(10) UNSIGNED NOT NULL,
  `product_id` INT(10) UNSIGNED NOT NULL,
  `product_name` VARCHAR(200),
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `payments` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT(10) UNSIGNED NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `method` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('pending','success','failed') DEFAULT 'pending',
  `transaction_ref` VARCHAR(100) DEFAULT NULL,
  `paid_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `reviews` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(10) UNSIGNED NOT NULL,
  `product_id` INT(10) UNSIGNED NOT NULL,
  `rating` TINYINT NOT NULL,
  `comment` TEXT DEFAULT NULL,
  `status` ENUM('pending','approved','rejected') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ai_chat_logs` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(10) UNSIGNED DEFAULT NULL,
  `session_id` VARCHAR(100) DEFAULT NULL,
  `role` ENUM('user','assistant','system') NOT NULL,
  `message` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `settings` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings`(`setting_key`, `setting_value`) VALUES
('site_name', 'Dokebi Tekoku'),
('support_email', 'support@dokebi.com'),
('currency', 'MMK'),
('default_currency', 'MMK'),
('payment_methods', 'KBZPay, Wave Pay, Aya Pay, Visa');

SET FOREIGN_KEY_CHECKS = 1;
