-- Users table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `name` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('user','admin') NOT NULL DEFAULT 'user',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Products table
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `seller_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `category` ENUM('Fashion','Electronics','Furniture','Food') NOT NULL,
  `image_path` VARCHAR(500) DEFAULT NULL,
  `is_featured` BOOLEAN NOT NULL DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Orders table
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `buyer_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `seller_id` INT UNSIGNED NOT NULL,
  `status` ENUM('Requested','Accepted','Completed','Cancelled') NOT NULL DEFAULT 'Requested',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`buyer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Favorites table
CREATE TABLE IF NOT EXISTS `favorites` (
  `user_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`, `product_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Notifications table
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `message` VARCHAR(500) NOT NULL,
  `is_read` BOOLEAN NOT NULL DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed admin user 
INSERT INTO `users` (`email`, `name`, `password_hash`, `role`) VALUES
('admin@kasitrade.co.za', 'Admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Seed products 
INSERT INTO `products` (`seller_id`, `name`, `description`, `price`, `category`, `image_path`, `is_featured`) VALUES
(1, 'Archive Sneakers', 'Gently used size 8 sneakers.', 350, 'Fashion', 'sneakerss.jpg', 1),
(1, 'iPhone 11', 'Good condition, 64GB.', 3500, 'Electronics', 'phone.jpg', 1),
(1, '2‑Seater Couch', 'Grey fabric, like new.', 800, 'Furniture', 'https://placehold.co/400x250/2a9d8f/ffffff?text=Couch', 0),
(1, 'Homemade Bread', 'Fresh daily.', 15, 'Food', 'https://placehold.co/400x250/2a9d8f/ffffff?text=Bread', 0),
(1, 'Denim Jacket', 'Vintage denim, size M.', 250, 'Fashion', 'Jacket.jpg', 1),
(1, 'Wireless Earbuds', 'BT 5.0, good battery.', 180, 'Electronics', 'https://placehold.co/400x250/2a9d8f/ffffff?text=Earbuds', 0),
(1, 'Wooden Coffee Table', 'Solid wood.', 550, 'Furniture', 'https://placehold.co/400x250/2a9d8f/ffffff?text=Table', 0),
(1, 'Vetkoek (6 pack)', 'Delicious!', 30, 'Food', 'https://placehold.co/400x250/2a9d8f/ffffff?text=Vetkoek', 1),
(1, 'School Shoes', 'Black leather, size 3.', 120, 'Fashion', 'https://placehold.co/400x250/2a9d8f/ffffff?text=Shoes', 0),
(1, '32inch TV', 'HD Ready.', 900, 'Electronics', 'https://placehold.co/400x250/2a9d8f/ffffff?text=TV', 0),
(1, 'Plastic Chairs (4)', 'Stackable, white.', 200, 'Furniture', 'https://placehold.co/400x250/2a9d8f/ffffff?text=Chairs', 0),
(1, 'Achaar (Mango)', 'Spicy mango achaar.', 40, 'Food', 'https://placehold.co/400x250/2a9d8f/ffffff?text=Achaar', 0);
