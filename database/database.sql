-- ==========================================================
-- The Hide Out Cafe - Database Schema & Sri Lanka Seed Data
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `cafe_pos_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `cafe_pos_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `held_orders`;
DROP TABLE IF EXISTS `product_variants`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `modifiers`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `tables`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `cash_registers`;
DROP TABLE IF EXISTS `expenses`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- Users
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'manager', 'cashier') NOT NULL DEFAULT 'cashier',
  `phone` VARCHAR(20) NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `phone`, `status`) VALUES
(1, 'Administrator', 'admin@cafepos.com', '$2y$10$8t4I1eFSUJpbhNjdMkTAHeIHk.leX5ReNgXZBWY4mXqHei44FPJ82', 'admin', '+94 77 123 4567', 'active'),
(2, 'Sarah Manager', 'manager@cafepos.com', '$2y$10$0gIpwrdaY9EQSViyxizqNeHfqBsznnwe253PSmeaPgCh6uK/LplCW', 'manager', '+94 71 987 6543', 'active'),
(3, 'Alex Cashier', 'cashier@cafepos.com', '$2y$10$JotmnJ0xQASLfpZ7yseX.erv4x6.fPUKPPz2theKSGeuP4/VC5kSy', 'cashier', '+94 76 555 1234', 'active'),
(4, 'Liam Barista', 'barista@cafepos.com', '$2y$10$JotmnJ0xQASLfpZ7yseX.erv4x6.fPUKPPz2theKSGeuP4/VC5kSy', 'cashier', '+94 75 444 8899', 'active');

-- Settings
CREATE TABLE `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('cafe_name', 'The Hide Out Cafe'),
('cafe_tagline', 'Specialty Coffee, Fresh Bakes & Chill Vibes - Sri Lanka'),
('cafe_phone', '+94 77 123 4567'),
('cafe_email', 'hello@thehideoutcafe.lk'),
('cafe_address', 'No. 45 Beach Road, Colombo 03, Sri Lanka'),
('currency_symbol', 'Rs.'),
('currency_code', 'LKR'),
('tax_rate', '0.00'),
('service_charge', '0.00'),
('invoice_prefix', 'HOC-'),
('receipt_header', 'THE HIDE OUT CAFE\nSpecialty Coffee & Artisan Kitchen\nColombo 03, Sri Lanka\nTel: +94 77 123 4567'),
('receipt_footer', 'Thank you for visiting The Hide Out Cafe!\nFollow us on Instagram: @TheHideOutCafeLK\nFree Wi-Fi: HideOutGuest / Code: CoffeeLove'),
('enable_loyalty', '1'),
('points_per_dollar', '1');

-- Categories
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `icon` VARCHAR(50) DEFAULT 'fa-mug-hot',
  `sort_order` INT DEFAULT 0,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `categories` (`id`, `name`, `slug`, `icon`, `sort_order`, `status`) VALUES
(1, 'Hot Coffee', 'hot-coffee', 'fa-mug-hot', 1, 'active'),
(2, 'Iced & Cold Brew', 'iced-cold-brew', 'fa-glass-water', 2, 'active'),
(3, 'Tea & Infusions', 'tea-infusions', 'fa-leaf', 3, 'active'),
(4, 'Bakery & Pastries', 'bakery-pastries', 'fa-bread-slice', 4, 'active'),
(5, 'Breakfast & Toast', 'breakfast-toast', 'fa-egg', 5, 'active'),
(6, 'Desserts & Cakes', 'desserts-cakes', 'fa-cake-candles', 6, 'active'),
(7, 'Beverages & Smoothies', 'beverages-smoothies', 'fa-blender', 7, 'active'),
(8, 'Retail Beans & Bags', 'retail-beans', 'fa-box', 8, 'active');

-- Products
CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `cost_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `image` VARCHAR(255) NULL,
  `track_stock` TINYINT(1) NOT NULL DEFAULT 1,
  `stock_quantity` INT NOT NULL DEFAULT 100,
  `alert_quantity` INT NOT NULL DEFAULT 15,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `products` (`id`, `category_id`, `name`, `code`, `description`, `price`, `cost_price`, `image`, `track_stock`, `stock_quantity`, `alert_quantity`, `status`) VALUES
(1, 1, 'Single Origin Espresso', 'COF-ESP-01', 'Rich, velvety double shot espresso.', 650.00, 180.00, 'espresso.jpg', 1, 350, 50, 'active'),
(2, 1, 'Velvet Flat White', 'COF-FLT-02', 'Micro-foamed milk poured over double ristretto.', 950.00, 260.00, 'flatwhite.jpg', 1, 200, 30, 'active'),
(3, 1, 'Classic Caffe Latte', 'COF-LAT-03', 'Rich espresso with steamed milk and delicate foam.', 900.00, 240.00, 'latte.jpg', 1, 180, 25, 'active'),
(4, 1, 'Spanish Caramel Macchiato', 'COF-MAC-04', 'Espresso layered with vanilla, milk and caramel drizzle.', 1150.00, 320.00, 'macchiato.jpg', 1, 140, 20, 'active'),
(5, 1, 'Dark Mocha Truffle', 'COF-MOC-05', 'Belgian dark chocolate blended with double shot & steamed milk.', 1200.00, 350.00, 'mocha.jpg', 1, 120, 20, 'active'),
(6, 1, 'Artisan Cappuccino', 'COF-CAP-06', 'Equal parts espresso, steamed milk, and airy foam with cocoa.', 900.00, 240.00, 'cappuccino.jpg', 1, 220, 30, 'active'),
(7, 2, 'Nitro Cold Brew', 'COL-NIT-01', 'Infused with nitrogen for creamy texture and sweet notes.', 1100.00, 280.00, 'nitrocoldbrew.jpg', 1, 90, 15, 'active'),
(8, 2, 'Iced Vanilla Oat Latte', 'COL-VAN-02', 'Chilled espresso over creamy oat milk & Madagascar vanilla.', 1250.00, 380.00, 'icedvanilla.jpg', 1, 160, 25, 'active'),
(9, 2, 'Iced Spanish Latte', 'COL-SPN-03', 'Sweetened condensed milk, fresh milk and bold espresso.', 1150.00, 310.00, 'icedspanish.jpg', 1, 150, 20, 'active'),
(10, 3, 'Kyoto Ceremonial Matcha Latte', 'TEA-MAT-01', 'First-harvest Uji matcha whisked with milk & honey.', 1350.00, 420.00, 'matcha.jpg', 1, 110, 20, 'active'),
(11, 3, 'Ceylon Earl Grey Lavender Tea', 'TEA-EGL-02', 'Pure Ceylon black tea infused with French culinary lavender.', 750.00, 150.00, 'earlgrey.jpg', 1, 140, 20, 'active'),
(12, 3, 'Hibiscus Rose Berry Iced Tea', 'TEA-HIB-03', 'Refreshing cold steep of Egyptian hibiscus & wild berries.', 850.00, 180.00, 'hibiscus.jpg', 1, 130, 20, 'active'),
(13, 4, 'French Butter Croissant', 'BAK-CRS-01', 'Flaky, buttery 24-layer traditional French croissant.', 650.00, 220.00, 'croissant.jpg', 1, 45, 10, 'active'),
(14, 4, 'Almond Frangipane Croissant', 'BAK-ALM-02', 'Filled with rich almond cream and toasted sliced almonds.', 850.00, 310.00, 'almondcroissant.jpg', 1, 35, 8, 'active'),
(15, 4, 'Pain au Chocolat', 'BAK-PAC-03', 'Laminated pastry filled with Valrhona dark chocolate.', 750.00, 260.00, 'painauchocolat.jpg', 1, 40, 10, 'active'),
(16, 4, 'Blueberry Crumble Muffin', 'BAK-BLU-04', 'Loaded with fresh blueberries & brown sugar butter streusel.', 700.00, 210.00, 'muffin.jpg', 1, 50, 12, 'active'),
(17, 5, 'Smashed Avocado Sourdough Toast', 'BRK-AVO-01', 'Avocado, cherry tomatoes, radish, feta & chili on sourdough.', 1850.00, 650.00, 'avocadotoast.jpg', 1, 60, 10, 'active'),
(18, 5, 'Truffle Egg & Brioche Sandwich', 'BRK-EGG-02', 'Scrambled eggs, truffle aioli, aged cheddar & chives on brioche.', 1650.00, 580.00, 'eggsandwich.jpg', 1, 55, 10, 'active'),
(19, 5, 'Smoked Salmon Bagel', 'BRK-BAG-03', 'Toasted bagel, cream cheese, Norwegian smoked salmon & capers.', 2200.00, 850.00, 'salmonbagel.jpg', 1, 40, 8, 'active'),
(20, 6, 'Basque Burnt Cheesecake', 'DES-BSQ-01', 'Caramelized top with molten cream cheese center.', 1250.00, 420.00, 'basque.jpg', 1, 28, 5, 'active'),
(21, 6, 'Classic Italian Tiramisu', 'DES-TIR-02', 'Espresso-soaked savoiardi layered with mascarpone cream.', 1350.00, 460.00, 'tiramisu.jpg', 1, 25, 5, 'active'),
(22, 7, 'Acai Berry & Banana Smoothie', 'SMO-ACA-01', 'Acai blended with banana, chia seeds & coconut water.', 1450.00, 480.00, 'acaismoothie.jpg', 1, 80, 15, 'active'),
(23, 7, 'Mango Passion Fruit Cooler', 'SMO-MAN-02', 'Fresh mango pulp, passionfruit puree, soda & mint.', 1150.00, 320.00, 'mangocooler.jpg', 1, 95, 15, 'active'),
(24, 8, 'The Hide Out Signature Beans (250g)', 'RET-SIG-01', 'Whole bean blend with notes of dark chocolate & orange.', 3800.00, 1800.00, 'beans250g.jpg', 1, 50, 10, 'active');

-- Product Variants
CREATE TABLE `product_variants` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `variant_name` VARCHAR(50) NOT NULL,
  `extra_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `product_variants` (`product_id`, `variant_name`, `extra_price`) VALUES
(1, 'Single Shot (30ml)', 0.00),
(1, 'Double Shot (60ml)', 200.00),
(2, 'Regular (8oz)', 0.00),
(2, 'Large (12oz)', 250.00),
(3, 'Small (8oz)', 0.00),
(3, 'Regular (12oz)', 200.00),
(3, 'Large (16oz)', 350.00),
(4, 'Small (8oz)', 0.00),
(4, 'Regular (12oz)', 200.00),
(4, 'Large (16oz)', 350.00),
(5, 'Regular (12oz)', 0.00),
(5, 'Large (16oz)', 250.00),
(6, 'Small (8oz)', 0.00),
(6, 'Regular (12oz)', 200.00),
(7, 'Regular (12oz)', 0.00),
(7, 'Large (16oz)', 250.00),
(8, 'Regular (16oz)', 0.00),
(8, 'Large (20oz)', 300.00),
(9, 'Regular (16oz)', 0.00),
(9, 'Large (20oz)', 300.00),
(10, 'Regular (12oz)', 0.00),
(10, 'Large (16oz)', 300.00);

-- Modifiers
CREATE TABLE `modifiers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `category` VARCHAR(50) NOT NULL DEFAULT 'General',
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `modifiers` (`name`, `category`, `price`, `status`) VALUES
('Oat Milk (Barista Edition)', 'Milk Option', 250.00, 'active'),
('Almond Milk (Unsweetened)', 'Milk Option', 250.00, 'active'),
('Soy Milk', 'Milk Option', 200.00, 'active'),
('Coconut Milk', 'Milk Option', 200.00, 'active'),
('Extra Espresso Shot', 'Coffee Add-on', 300.00, 'active'),
('Decaf Blend', 'Coffee Add-on', 150.00, 'active'),
('Vanilla Madagascar Syrup', 'Flavors & Syrups', 180.00, 'active'),
('Artisan Salted Caramel Syrup', 'Flavors & Syrups', 180.00, 'active'),
('Hazelnut Praline Syrup', 'Flavors & Syrups', 180.00, 'active'),
('Whipped Cream Topping', 'Topping', 150.00, 'active'),
('Less Sugar (50%)', 'Sweetness', 0.00, 'active'),
('No Sugar (0%)', 'Sweetness', 0.00, 'active'),
('Less Ice', 'Temperature', 0.00, 'active'),
('Extra Hot', 'Temperature', 0.00, 'active');

-- Tables
CREATE TABLE `tables` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `table_number` VARCHAR(20) NOT NULL UNIQUE,
  `name` VARCHAR(50) NOT NULL,
  `floor_area` VARCHAR(50) NOT NULL DEFAULT 'Indoor Lounge',
  `seating_capacity` INT NOT NULL DEFAULT 2,
  `status` ENUM('available', 'occupied', 'billed', 'reserved') NOT NULL DEFAULT 'available',
  `current_order_id` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `tables` (`table_number`, `name`, `floor_area`, `seating_capacity`, `status`) VALUES
('T-01', 'Table 1 (Window)', 'Indoor Lounge', 2, 'available'),
('T-02', 'Table 2 (Window)', 'Indoor Lounge', 2, 'available'),
('T-03', 'Table 3 (Booth)', 'Indoor Lounge', 4, 'available'),
('T-04', 'Table 4 (Booth)', 'Indoor Lounge', 4, 'available'),
('T-05', 'Table 5 (Community)', 'Main Hall', 8, 'available'),
('T-06', 'Table 6 (Center)', 'Main Hall', 4, 'available'),
('T-07', 'Table 7 (Center)', 'Main Hall', 4, 'available'),
('T-08', 'Table 8 (Bar Stool)', 'Espresso Bar', 1, 'available'),
('T-09', 'Table 9 (Bar Stool)', 'Espresso Bar', 1, 'available'),
('T-10', 'Table 10 (Garden Patio)', 'Outdoor Terrace', 2, 'available'),
('T-11', 'Table 11 (Garden Patio)', 'Outdoor Terrace', 4, 'available'),
('T-12', 'Table 12 (Garden Patio)', 'Outdoor Terrace', 4, 'available');

-- Customers
CREATE TABLE `customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(25) NOT NULL UNIQUE,
  `email` VARCHAR(100) NULL,
  `address` TEXT NULL,
  `loyalty_points` INT NOT NULL DEFAULT 0,
  `total_spent` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `customers` (`name`, `phone`, `email`, `address`, `loyalty_points`, `total_spent`, `notes`) VALUES
('Walk-in Customer', '0000000000', 'guest@thehideoutcafe.lk', 'In-Store', 0, 0.00, 'Default Guest Account'),
('Kavindu Silva', '+94 77 443 2190', 'kavindu.silva@gmail.com', 'Colombo 07', 45, 14500.00, 'Regular Flat White drinker'),
('Dharshini Perera', '+94 71 881 9230', 'dharshini.p@yahoo.com', 'Nugegoda', 80, 23500.00, 'Prefers Oat Milk in all drinks'),
('Roshan Fernando', '+94 76 321 0987', 'roshan.f@creative.lk', 'Mount Lavinia', 30, 9500.00, 'Loves Matcha and Almond Croissants');

-- Cash Registers
CREATE TABLE `cash_registers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `opening_cash` DECIMAL(10,2) NOT NULL DEFAULT 5000.00,
  `closing_cash` DECIMAL(10,2) NULL,
  `total_cash_sales` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_card_sales` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_upi_sales` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `difference_amount` DECIMAL(10,2) NULL,
  `opening_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `closing_time` TIMESTAMP NULL,
  `status` ENUM('open', 'closed') NOT NULL DEFAULT 'open',
  `notes` TEXT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cash_registers` (`id`, `user_id`, `opening_cash`, `closing_cash`, `total_cash_sales`, `total_card_sales`, `total_upi_sales`, `status`, `notes`) VALUES
(1, 1, 5000.00, NULL, 0.00, 0.00, 0.00, 'open', 'Morning opening float');

-- Orders
CREATE TABLE `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_no` VARCHAR(50) NOT NULL UNIQUE,
  `order_type` ENUM('dine_in', 'takeaway', 'delivery') NOT NULL DEFAULT 'dine_in',
  `table_id` INT NULL,
  `customer_id` INT NULL,
  `user_id` INT NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `service_charge` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `grand_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `change_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` ENUM('cash', 'card', 'upi', 'split') NOT NULL DEFAULT 'cash',
  `payment_status` ENUM('paid', 'unpaid', 'refunded') NOT NULL DEFAULT 'paid',
  `order_status` ENUM('pending', 'preparing', 'ready', 'completed', 'cancelled') NOT NULL DEFAULT 'completed',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`table_id`) REFERENCES `tables`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Order Items
CREATE TABLE `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `product_name` VARCHAR(150) NOT NULL,
  `variant_name` VARCHAR(50) NULL,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `quantity` INT NOT NULL DEFAULT 1,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `modifiers_json` TEXT NULL,
  `notes` VARCHAR(255) NULL,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Held Orders
CREATE TABLE `held_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `hold_reference` VARCHAR(50) NOT NULL,
  `customer_name` VARCHAR(100) NULL,
  `customer_id` INT NULL,
  `table_id` INT NULL,
  `order_type` VARCHAR(20) NOT NULL DEFAULT 'dine_in',
  `cart_json` LONGTEXT NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `cashier_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`cashier_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Expenses
CREATE TABLE `expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category` VARCHAR(50) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `description` TEXT NOT NULL,
  `user_id` INT NOT NULL,
  `expense_date` DATE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `expenses` (`category`, `amount`, `description`, `user_id`, `expense_date`) VALUES
('Coffee Beans & Roasts', 18500.00, 'Purchase of 10kg Single Origin Beans', 1, CURDATE()),
('Dairy & Plant Milks', 8500.00, 'Fresh Milk & Oatly Barista oat milk restock', 1, CURDATE()),
('Bakery Ingredients', 12000.00, 'Butter, Flour, Belgian Chocolate Batons', 2, CURDATE()),
('Store Utilities', 5500.00, 'Paper Cups, Sip Lids, Kraft Bags', 1, CURDATE());

-- Sample Order Seed
INSERT INTO `orders` (`id`, `invoice_no`, `order_type`, `table_id`, `customer_id`, `user_id`, `subtotal`, `discount_amount`, `grand_total`, `paid_amount`, `change_amount`, `payment_method`, `payment_status`, `order_status`, `created_at`) VALUES
(1, 'HOC-20260819-0001', 'dine_in', 1, 2, 3, 2200.00, 0.00, 2200.00, 2500.00, 300.00, 'card', 'paid', 'completed', NOW() - INTERVAL 4 HOUR),
(2, 'HOC-20260819-0002', 'takeaway', NULL, 3, 3, 1900.00, 0.00, 1900.00, 2000.00, 100.00, 'cash', 'paid', 'completed', NOW() - INTERVAL 2 HOUR),
(3, 'HOC-20260819-0003', 'dine_in', 3, 4, 4, 3200.00, 0.00, 3200.00, 3200.00, 0.00, 'card', 'paid', 'completed', NOW() - INTERVAL 30 MINUTE);

INSERT INTO `order_items` (`order_id`, `product_id`, `product_name`, `variant_name`, `unit_price`, `quantity`, `subtotal`, `modifiers_json`) VALUES
(1, 3, 'Classic Caffe Latte', 'Regular (12oz)', 1100.00, 2, 2200.00, '[]'),
(2, 4, 'Spanish Caramel Macchiato', 'Regular (12oz)', 1150.00, 1, 1150.00, '[]'),
(2, 15, 'Pain au Chocolat', NULL, 750.00, 1, 750.00, '[]'),
(3, 17, 'Smashed Avocado Sourdough Toast', NULL, 1850.00, 1, 1850.00, '[]'),
(3, 10, 'Kyoto Ceremonial Matcha Latte', NULL, 1350.00, 1, 1350.00, '[]');
