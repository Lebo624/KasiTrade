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

ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

  ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `seller_id` (`seller_id`);
  
-- Seed admin user 
INSERT INTO `users` (`email`, `name`, `password_hash`, `role`) VALUES
('admin@kasitrade.co.za', 'Admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Seed products 
INSERT INTO `products` (`id`, `seller_id`, `name`, `description`, `price`, `category`, `image_path`, `is_featured`, `created_at`) VALUES
(1, 1, 'Runners', 'Gently used size 8 running shoes.', '350.00', 'Fashion', 'https://placehold.co/400x250/2a9d8f/ffffff?text= Runners', 1, '2026-06-05 09:26:17'),
(2, 1, 'Samsung Galaxy A05', 'Grade A condition, 64GB.', '3500.00', 'Electronics', 'https://placehold.co/400x250/2a9d8f/ffffff?text=Samusung A05', 1, '2026-06-05 09:26:17'),
(3, 1, '2 Seater Couch', 'Grey fabric, like new.', '800.00', 'Furniture', 'https://placehold.co/400x250/2a9d8f/ffffff?text=Couch', 0, '2026-06-05 09:26:17'),
(4, 1, 'Homemade Bread', 'Fresh daily.', '15.00', 'Food', 'https://placehold.co/400x250/2a9d8f/ffffff?text=Bread', 0, '2026-06-05 09:26:17'),
(5, 1, 'Denim Jacket', 'Vintage denim, size M.', '250.00', 'Fashion', 'https://placehold.co/400x250/2a9d8f/ffffff?text=Jacket', 1, '2026-06-05 09:26:17'),
(6, 1, 'Wireless Earbuds', 'BT 5.0, good battery.', '180.00', 'Electronics', 'https://placehold.co/400x250/2a9d8f/ffffff?text=Earbuds', 0, '2026-06-05 09:26:17'),
(7, 1, 'Wooden Coffee Table', 'Solid wood.', '550.00', 'Furniture', 'https://placehold.co/400x250/2a9d8f/ffffff?text=Table', 0, '2026-06-05 09:26:17'),
(8, 1, 'Vetkoek (6 pack)', 'Delicious!', '30.00', 'Food', 'https://placehold.co/400x250/2a9d8f/ffffff?text=Vetkoek', 1, '2026-06-05 09:26:17'),
(9, 1, 'School Shoes', 'Black leather, size 3.', '120.00', 'Fashion', 'https://placehold.co/400x250/2a9d8f/ffffff?text=Shoes', 0, '2026-06-05 09:26:17'),
(10, 1, '32 inch TV', 'HD Ready.', '900.00', 'Electronics', 'https://placehold.co/400x250/2a9d8f/ffffff?text=TV', 0, '2026-06-05 09:26:17'),
(11, 1, 'Plastic Chairs (4)', 'Stackable, white.', '200.00', 'Furniture', 'https://placehold.co/400x250/2a9d8f/ffffff?text=Chairs', 0, '2026-06-05 09:26:17'),
(12, 1, 'Achaar (Mango)', 'Spicy mango achaar.', '40.00', 'Food', 'https://placehold.co/400x250/2a9d8f/ffffff?text=Achaar', 0, '2026-06-05 09:26:17'),
(14, 2, 'Camera', 'Second hand camera', '600.00', 'Electronics', 'https://images.pexels.com/photos/90946/pexels-photo-90946.jpeg?w=400&h=250&fit=crop', 0, '2026-06-05 15:55:01'),
(16, 3, 'Heater', 'An electric powered heater, with gas.', '500.00', 'Electronics', 'https://placehold.co/400x250/2a9d8f/ffffff?text=Heater', 0, '2026-06-09 12:51:10');
