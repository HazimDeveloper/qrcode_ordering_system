-- phpMyAdmin SQL Dump
-- QR Food Ordering System Database

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `qr_food_ordering`
--
CREATE DATABASE IF NOT EXISTS `qr_food_ordering` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `qr_food_ordering`;

-- --------------------------------------------------------

DELIMITER $$
--
-- Procedures
--
CREATE PROCEDURE `GetQRStatistics` ()
BEGIN
    SELECT 
        'Total QR Codes' as metric,
        COUNT(*) as value
    FROM qr_codes
    WHERE is_active = TRUE
    
    UNION ALL
    
    SELECT 
        'Total Scans Today' as metric,
        COUNT(*) as value
    FROM qr_scans
    WHERE DATE(scan_time) = CURDATE()
    AND successful = TRUE
    
    UNION ALL
    
    SELECT 
        'Unique Tables Scanned Today' as metric,
        COUNT(DISTINCT table_number) as value
    FROM qr_scans
    WHERE DATE(scan_time) = CURDATE()
    AND successful = TRUE
    
    UNION ALL
    
    SELECT 
        'Total Orders from QR Today' as metric,
        COUNT(*) as value
    FROM orders
    WHERE DATE(created_at) = CURDATE()
    AND table_number IS NOT NULL;
END$$

CREATE PROCEDURE `LogQRGeneration` (IN `p_table_number` INT, IN `p_generated_by` INT, IN `p_qr_url` TEXT, IN `p_restaurant_name` VARCHAR(100))
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Insert or update QR code
    INSERT INTO qr_codes (table_number, qr_url, generated_by, generated_at, restaurant_name, is_active)
    VALUES (p_table_number, p_qr_url, p_generated_by, NOW(), COALESCE(p_restaurant_name, 'QR Food Ordering'), TRUE)
    ON DUPLICATE KEY UPDATE
        qr_url = VALUES(qr_url),
        generated_by = VALUES(generated_by),
        generated_at = NOW(),
        restaurant_name = COALESCE(p_restaurant_name, restaurant_name),
        is_active = TRUE;
    
    -- Log the generation activity
    INSERT INTO qr_activity_log (user_id, table_number, action, timestamp)
    VALUES (p_generated_by, p_table_number, 'qr_generated', NOW());
    
    COMMIT;
END$$

CREATE PROCEDURE `LogQRScan` (IN `p_table_number` INT, IN `p_user_id` INT, IN `p_ip_address` VARCHAR(45), IN `p_user_agent` TEXT)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Log the scan
    INSERT INTO qr_scans (table_number, user_id, scan_time, ip_address, user_agent, successful)
    VALUES (p_table_number, p_user_id, NOW(), p_ip_address, p_user_agent, TRUE);
    
    -- Log the activity
    INSERT INTO qr_activity_log (user_id, table_number, action, timestamp, ip_address, user_agent)
    VALUES (p_user_id, p_table_number, 'qr_scanned', NOW(), p_ip_address, p_user_agent);
    
    -- Update QR code scan count and last scanned time
    UPDATE qr_codes 
    SET scan_count = scan_count + 1, 
        last_scanned = NOW()
    WHERE table_number = p_table_number;
    
    COMMIT;
END$$

--
-- Functions
--
CREATE FUNCTION `GetMostPopularTable` () RETURNS INT DETERMINISTIC READS SQL DATA
BEGIN
    DECLARE popular_table INT DEFAULT 0;
    
    SELECT table_number INTO popular_table
    FROM qr_scans
    WHERE successful = TRUE
    AND scan_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY table_number
    ORDER BY COUNT(*) DESC
    LIMIT 1;
    
    RETURN COALESCE(popular_table, 0);
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `users` (MOVED TO TOP - Referenced by other tables)
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('customer','staff') DEFAULT 'customer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `available` tinyint(1) DEFAULT 1,
  `category` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_menu_items_category` (`category`),
  ADD KEY `idx_menu_items_available` (`available`);

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_name` VARCHAR(100) NULL,
  `customer_phone` VARCHAR(20) NULL,
  `order_type` enum('dine-in','takeaway') NOT NULL,
  `table_number` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','preparing','ready','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_orders_table_number` (`table_number`),
  ADD KEY `idx_orders_date_status` (`created_at`,`status`);

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `menu_item_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `menu_item_id` (`menu_item_id`);

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`);

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `menu_item_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `menu_item_id` (`menu_item_id`);

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`);

-- --------------------------------------------------------

--
-- Table structure for table `qr_codes`
--

CREATE TABLE `qr_codes` (
  `id` int(11) NOT NULL,
  `table_number` int(11) DEFAULT NULL,
  `qr_url` text NOT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `last_scanned` timestamp NULL DEFAULT NULL,
  `scan_count` int(11) DEFAULT 0,
  `restaurant_name` varchar(100) DEFAULT 'QR Food Ordering',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for table `qr_codes`
--
ALTER TABLE `qr_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `table_number` (`table_number`),
  ADD KEY `idx_table_number` (`table_number`),
  ADD KEY `idx_generated_at` (`generated_at`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `generated_by` (`generated_by`);

--
-- AUTO_INCREMENT for table `qr_codes`
--
ALTER TABLE `qr_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for table `qr_codes`
--
ALTER TABLE `qr_codes`
  ADD CONSTRAINT `qr_codes_ibfk_1` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- --------------------------------------------------------

--
-- Table structure for table `qr_scans`
--

CREATE TABLE `qr_scans` (
  `id` int(11) NOT NULL,
  `table_number` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `scan_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `successful` tinyint(1) DEFAULT 1,
  `redirect_url` varchar(500) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for table `qr_scans`
--
ALTER TABLE `qr_scans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_table_number` (`table_number`),
  ADD KEY `idx_scan_time` (`scan_time`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_successful` (`successful`);

--
-- AUTO_INCREMENT for table `qr_scans`
--
ALTER TABLE `qr_scans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for table `qr_scans`
--
ALTER TABLE `qr_scans`
  ADD CONSTRAINT `qr_scans_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- --------------------------------------------------------

--
-- Table structure for table `qr_activity_log`
--

CREATE TABLE `qr_activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `table_number` int(11) DEFAULT NULL,
  `action` enum('qr_scanned','table_cleared','order_placed','qr_generated') NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `additional_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_data`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for table `qr_activity_log`
--
ALTER TABLE `qr_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_table_number` (`table_number`),
  ADD KEY `idx_timestamp` (`timestamp`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- AUTO_INCREMENT for table `qr_activity_log`
--
ALTER TABLE `qr_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for table `qr_activity_log`
--
ALTER TABLE `qr_activity_log`
  ADD CONSTRAINT `qr_activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- --------------------------------------------------------

--
-- Table structure for table `table_bookings`
--

CREATE TABLE `table_bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` VARCHAR(20) NOT NULL,
  `user_id` INT NOT NULL,
  `table_number` INT NOT NULL,
  `booking_date` DATE NOT NULL,
  `booking_time` TIME NOT NULL,
  `guests` INT NOT NULL,
  `event_type` VARCHAR(50) NULL,
  `package` VARCHAR(20) NULL,
  `package_price` DECIMAL(10,2) NULL,
  `special_requests` TEXT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for view `dashboard_stats`
--

CREATE VIEW `dashboard_stats` AS
SELECT 'today_orders' AS `metric`, count(0) AS `value` FROM `orders` WHERE cast(`orders`.`created_at` as date) = curdate()
UNION ALL 
SELECT 'today_qr_orders' AS `metric`,count(0) AS `value` from `orders` where cast(`orders`.`created_at` as date) = curdate() and `orders`.`table_number` is not null 
UNION ALL 
SELECT 'today_revenue' AS `metric`,coalesce(sum(`orders`.`total_amount`),0) AS `value` from `orders` where cast(`orders`.`created_at` as date) = curdate() 
UNION ALL 
SELECT 'today_qr_revenue' AS `metric`,coalesce(sum(`orders`.`total_amount`),0) AS `value` from `orders` where cast(`orders`.`created_at` as date) = curdate() and `orders`.`table_number` is not null 
UNION ALL 
SELECT 'active_tables' AS `metric`,count(distinct `orders`.`table_number`) AS `value` from `orders` where cast(`orders`.`created_at` as date) = curdate() and `orders`.`table_number` is not null 
UNION ALL 
SELECT 'pending_orders' AS `metric`,count(0) AS `value` from `orders` where `orders`.`status` in ('pending','confirmed','preparing');

-- --------------------------------------------------------

--
-- Structure for view `qr_stats`
--

CREATE VIEW `qr_stats` AS
SELECT cast(`o`.`created_at` as date) AS `order_date`, count(distinct `o`.`table_number`) AS `tables_used`, count(`o`.`id`) AS `total_qr_orders`, sum(`o`.`total_amount`) AS `qr_revenue`, avg(`o`.`total_amount`) AS `avg_order_value`, count(distinct `o`.`user_id`) AS `unique_customers` FROM `orders` AS `o` WHERE `o`.`table_number` is not null GROUP BY cast(`o`.`created_at` as date) ORDER BY cast(`o`.`created_at` as date) DESC;

-- --------------------------------------------------------

--
-- Structure for view `table_performance`
--

CREATE VIEW `table_performance` AS
SELECT `o`.`table_number` AS `table_number`, count(`o`.`id`) AS `total_orders`, sum(`o`.`total_amount`) AS `total_revenue`, avg(`o`.`total_amount`) AS `avg_order_value`, count(distinct `o`.`user_id`) AS `unique_customers`, count(distinct cast(`o`.`created_at` as date)) AS `active_days`, max(`o`.`created_at`) AS `last_order_time`, min(`o`.`created_at`) AS `first_order_time` FROM `orders` AS `o` WHERE `o`.`table_number` is not null GROUP BY `o`.`table_number` ORDER BY sum(`o`.`total_amount`) DESC;

--
-- Sample data insertion
--

-- Insert admin and test customer users
INSERT INTO `users` (`username`, `email`, `password`, `role`, `created_at`) VALUES
('admin', 'admin@restaurant.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', NOW()),
('customer1', 'customer@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', NOW());

-- Insert sample menu items
INSERT INTO `menu_items` (`name`, `description`, `price`, `image`, `created_at`, `available`, `category`) VALUES
('LAKSA', 'A popular spicy, creamy noodle soup originated from Peranakan cuisine from Malaysia.', 8.00, 'laksa.png', NOW(), 1, 'Main Course'),
('Nasi Ayam Penyet', 'The name comes from the Javanese word penyet, which means "pressed".', 12.00, 'ayam_penyet.png', NOW(), 1, 'Main Course'),
('Claypot Rice', 'A delicious one-pot meal of tender, succulent chicken pieces and rice.', 9.00, 'claypot_rice.png', NOW(), 1, 'Main Course');

-- Insert global QR code
INSERT INTO `qr_codes` (`table_number`, `qr_url`, `generated_by`, `restaurant_name`, `is_active`) VALUES
(NULL, 'qrfoodordering.website', 1, 'QR Food Ordering', 1);

COMMIT;