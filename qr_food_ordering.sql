-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 25, 2025 at 03:15 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `qr_food_ordering`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`` PROCEDURE `GetQRStatistics` ()   BEGIN
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

CREATE DEFINER=`` PROCEDURE `LogQRGeneration` (IN `p_table_number` INT, IN `p_generated_by` INT, IN `p_qr_url` TEXT, IN `p_restaurant_name` VARCHAR(100))   BEGIN
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

CREATE DEFINER=`` PROCEDURE `LogQRScan` (IN `p_table_number` INT, IN `p_user_id` INT, IN `p_ip_address` VARCHAR(45), IN `p_user_agent` TEXT)   BEGIN
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
CREATE DEFINER=`` FUNCTION `GetMostPopularTable` () RETURNS INT(11) DETERMINISTIC READS SQL DATA BEGIN
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
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `menu_item_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `dashboard_stats`
-- (See below for the actual view)
--
CREATE TABLE `dashboard_stats` (
`metric` varchar(16)
,`value` decimal(32,2)
);

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
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`id`, `name`, `description`, `price`, `image`, `created_at`, `available`, `category`) VALUES
(1, 'LAKSA', 'A popular spicy, creamy noodle soup originated from Peranakan cuisine from Malaysia.', 8.00, 'laksa.png', '2025-05-24 11:17:38', 1, 'Main Course'),
(2, 'Nasi Ayam Penyet', 'The name comes from the Javanese word penyet, which means \"pressed\".', 12.00, 'ayam_penyet.png', '2025-05-24 11:17:38', 1, 'Main Course'),
(3, 'Claypot Rice', 'A delicious one-pot meal of tender, succulent chicken pieces and rice.', 9.00, 'claypot_rice.png', '2025-05-24 11:17:38', 1, 'Main Course');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `order_type` enum('dine-in','takeaway') NOT NULL,
  `table_number` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','preparing','ready','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `order_type`, `table_number`, `total_amount`, `status`, `created_at`) VALUES
(1, 2, 'takeaway', NULL, 16.00, 'preparing', '2025-05-25 12:47:32');

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
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `menu_item_id`, `quantity`, `price`) VALUES
(1, 1, 1, 2, 8.00);

-- --------------------------------------------------------

--
-- Table structure for table `qr_activity_log`
--

CREATE TABLE `qr_activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `table_number` int(11) NOT NULL,
  `action` enum('qr_scanned','table_cleared','order_placed','qr_generated') NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `additional_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_data`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qr_activity_log`
--

INSERT INTO `qr_activity_log` (`id`, `user_id`, `table_number`, `action`, `timestamp`, `ip_address`, `user_agent`, `session_id`, `additional_data`) VALUES
(1, 2, 1, 'qr_scanned', '2025-05-24 09:53:54', '127.0.0.1', NULL, NULL, NULL),
(2, 1, 1, 'qr_generated', '2025-05-23 11:53:54', '127.0.0.1', NULL, NULL, NULL),
(3, 2, 3, 'qr_scanned', '2025-05-24 10:53:54', '127.0.0.1', NULL, NULL, NULL),
(4, 2, 5, 'qr_scanned', '2025-05-24 11:23:54', '127.0.0.1', NULL, NULL, NULL),
(5, 2, 1, 'table_cleared', '2025-05-24 10:53:54', '127.0.0.1', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `qr_codes`
--

CREATE TABLE `qr_codes` (
  `id` int(11) NOT NULL,
  `table_number` int(11) NOT NULL,
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
-- Dumping data for table `qr_codes`
--

INSERT INTO `qr_codes` (`id`, `table_number`, `qr_url`, `generated_by`, `generated_at`, `is_active`, `last_scanned`, `scan_count`, `restaurant_name`, `notes`) VALUES
(1, 1, 'http://localhost/qr-food-ordering/qr/scan.php?table=1', 1, '2025-05-24 11:53:54', 1, NULL, 1, 'QR Food Ordering', NULL),
(2, 2, 'http://localhost/qr-food-ordering/qr/scan.php?table=2', 1, '2025-05-24 11:53:54', 1, NULL, 0, 'QR Food Ordering', NULL),
(3, 3, 'http://localhost/qr-food-ordering/qr/scan.php?table=3', 1, '2025-05-24 11:53:54', 1, NULL, 1, 'QR Food Ordering', NULL),
(4, 4, 'http://localhost/qr-food-ordering/qr/scan.php?table=4', 1, '2025-05-24 11:53:54', 1, NULL, 0, 'QR Food Ordering', NULL),
(5, 5, 'http://localhost/qr-food-ordering/qr/scan.php?table=5', 1, '2025-05-24 11:53:54', 1, NULL, 1, 'QR Food Ordering', NULL),
(6, 6, 'http://localhost/qr-food-ordering/qr/scan.php?table=6', 1, '2025-05-24 11:53:54', 1, NULL, 0, 'QR Food Ordering', NULL),
(7, 7, 'http://localhost/qr-food-ordering/qr/scan.php?table=7', 1, '2025-05-24 11:53:54', 1, NULL, 0, 'QR Food Ordering', NULL),
(8, 8, 'http://localhost/qr-food-ordering/qr/scan.php?table=8', 1, '2025-05-24 11:53:54', 1, NULL, 0, 'QR Food Ordering', NULL),
(9, 9, 'http://localhost/qr-food-ordering/qr/scan.php?table=9', 1, '2025-05-24 11:53:54', 1, NULL, 0, 'QR Food Ordering', NULL),
(10, 10, 'http://localhost/qr-food-ordering/qr/scan.php?table=10', 1, '2025-05-24 11:53:54', 1, NULL, 0, 'QR Food Ordering', NULL),
(11, 11, 'http://localhost/qr-food-ordering/qr/scan.php?table=11', 1, '2025-05-24 11:53:54', 1, NULL, 0, 'QR Food Ordering', NULL),
(12, 12, 'http://localhost/qr-food-ordering/qr/scan.php?table=12', 1, '2025-05-24 11:53:54', 1, NULL, 0, 'QR Food Ordering', NULL),
(13, 13, 'http://localhost/qr-food-ordering/qr/scan.php?table=13', 1, '2025-05-24 11:53:54', 1, NULL, 0, 'QR Food Ordering', NULL),
(14, 14, 'http://localhost/qr-food-ordering/qr/scan.php?table=14', 1, '2025-05-24 11:53:54', 1, NULL, 0, 'QR Food Ordering', NULL),
(15, 15, 'http://localhost/qr-food-ordering/qr/scan.php?table=15', 1, '2025-05-24 11:53:54', 1, NULL, 0, 'QR Food Ordering', NULL),
(16, 16, 'http://localhost/qr-food-ordering/qr/scan.php?table=16', 1, '2025-05-24 11:53:54', 1, NULL, 0, 'QR Food Ordering', NULL),
(17, 17, 'http://localhost/qr-food-ordering/qr/scan.php?table=17', 1, '2025-05-24 11:53:54', 1, NULL, 0, 'QR Food Ordering', NULL),
(18, 18, 'http://localhost/qr-food-ordering/qr/scan.php?table=18', 1, '2025-05-24 11:53:54', 1, NULL, 0, 'QR Food Ordering', NULL),
(19, 19, 'http://localhost/qr-food-ordering/qr/scan.php?table=19', 1, '2025-05-24 11:53:54', 1, NULL, 0, 'QR Food Ordering', NULL),
(20, 20, 'http://localhost/qr-food-ordering/qr/scan.php?table=20', 1, '2025-05-24 11:53:54', 1, NULL, 0, 'QR Food Ordering', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `qr_scans`
--

CREATE TABLE `qr_scans` (
  `id` int(11) NOT NULL,
  `table_number` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `scan_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `successful` tinyint(1) DEFAULT 1,
  `redirect_url` varchar(500) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qr_scans`
--

INSERT INTO `qr_scans` (`id`, `table_number`, `user_id`, `scan_time`, `ip_address`, `user_agent`, `successful`, `redirect_url`, `session_id`) VALUES
(1, 1, 2, '2025-05-24 09:53:54', '127.0.0.1', NULL, 1, NULL, NULL),
(2, 3, 2, '2025-05-24 10:53:54', '127.0.0.1', NULL, 1, NULL, NULL),
(3, 5, 2, '2025-05-24 11:23:54', '127.0.0.1', NULL, 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `qr_stats`
-- (See below for the actual view)
--
CREATE TABLE `qr_stats` (
`order_date` date
,`tables_used` bigint(21)
,`total_qr_orders` bigint(21)
,`qr_revenue` decimal(32,2)
,`avg_order_value` decimal(14,6)
,`unique_customers` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `table_performance`
-- (See below for the actual view)
--
CREATE TABLE `table_performance` (
`table_number` int(11)
,`total_orders` bigint(21)
,`total_revenue` decimal(32,2)
,`avg_order_value` decimal(14,6)
,`unique_customers` bigint(21)
,`active_days` bigint(21)
,`last_order_time` timestamp
,`first_order_time` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `users`
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
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'admin', 'admin@restaurant.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', '2025-05-24 11:17:38'),
(2, 'customer1', 'customer@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '2025-05-24 11:17:38');

-- --------------------------------------------------------

--
-- Structure for view `dashboard_stats`
--
DROP TABLE IF EXISTS `dashboard_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`` SQL SECURITY DEFINER VIEW `dashboard_stats`  AS SELECT 'today_orders' AS `metric`, count(0) AS `value` FROM `orders` WHERE cast(`orders`.`created_at` as date) = curdate()union all select 'today_qr_orders' AS `metric`,count(0) AS `value` from `orders` where cast(`orders`.`created_at` as date) = curdate() and `orders`.`table_number` is not null union all select 'today_revenue' AS `metric`,coalesce(sum(`orders`.`total_amount`),0) AS `value` from `orders` where cast(`orders`.`created_at` as date) = curdate() union all select 'today_qr_revenue' AS `metric`,coalesce(sum(`orders`.`total_amount`),0) AS `value` from `orders` where cast(`orders`.`created_at` as date) = curdate() and `orders`.`table_number` is not null union all select 'active_tables' AS `metric`,count(distinct `orders`.`table_number`) AS `value` from `orders` where cast(`orders`.`created_at` as date) = curdate() and `orders`.`table_number` is not null union all select 'pending_orders' AS `metric`,count(0) AS `value` from `orders` where `orders`.`status` in ('pending','confirmed','preparing')  ;

-- --------------------------------------------------------

--
-- Structure for view `qr_stats`
--
DROP TABLE IF EXISTS `qr_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`` SQL SECURITY DEFINER VIEW `qr_stats`  AS SELECT cast(`o`.`created_at` as date) AS `order_date`, count(distinct `o`.`table_number`) AS `tables_used`, count(`o`.`id`) AS `total_qr_orders`, sum(`o`.`total_amount`) AS `qr_revenue`, avg(`o`.`total_amount`) AS `avg_order_value`, count(distinct `o`.`user_id`) AS `unique_customers` FROM `orders` AS `o` WHERE `o`.`table_number` is not null GROUP BY cast(`o`.`created_at` as date) ORDER BY cast(`o`.`created_at` as date) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `table_performance`
--
DROP TABLE IF EXISTS `table_performance`;

CREATE ALGORITHM=UNDEFINED DEFINER=`` SQL SECURITY DEFINER VIEW `table_performance`  AS SELECT `o`.`table_number` AS `table_number`, count(`o`.`id`) AS `total_orders`, sum(`o`.`total_amount`) AS `total_revenue`, avg(`o`.`total_amount`) AS `avg_order_value`, count(distinct `o`.`user_id`) AS `unique_customers`, count(distinct cast(`o`.`created_at` as date)) AS `active_days`, max(`o`.`created_at`) AS `last_order_time`, min(`o`.`created_at`) AS `first_order_time` FROM `orders` AS `o` WHERE `o`.`table_number` is not null GROUP BY `o`.`table_number` ORDER BY sum(`o`.`total_amount`) DESC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `menu_item_id` (`menu_item_id`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_menu_items_category` (`category`),
  ADD KEY `idx_menu_items_available` (`available`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_orders_table_number` (`table_number`),
  ADD KEY `idx_orders_date_status` (`created_at`,`status`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `menu_item_id` (`menu_item_id`);

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
-- Indexes for table `qr_scans`
--
ALTER TABLE `qr_scans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_table_number` (`table_number`),
  ADD KEY `idx_scan_time` (`scan_time`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_successful` (`successful`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `qr_activity_log`
--
ALTER TABLE `qr_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `qr_codes`
--
ALTER TABLE `qr_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `qr_scans`
--
ALTER TABLE `qr_scans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`);

--
-- Constraints for table `qr_activity_log`
--
ALTER TABLE `qr_activity_log`
  ADD CONSTRAINT `qr_activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `qr_codes`
--
ALTER TABLE `qr_codes`
  ADD CONSTRAINT `qr_codes_ibfk_1` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `qr_scans`
--
ALTER TABLE `qr_scans`
  ADD CONSTRAINT `qr_scans_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
