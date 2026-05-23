create database hotel_inventory_system;
  use hotel_inventory_system;
--
-- Table structure for table `alerts`
--

CREATE TABLE `alerts` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `alert_type` enum('low_stock','expiry','reorder') DEFAULT 'low_stock',
  `message` text NOT NULL,
  `is_read` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `alerts`
--

INSERT INTO `alerts` (`id`, `item_id`, `alert_type`, `message`, `is_read`, `created_at`) VALUES
(1, 3, 'low_stock', 'Beef iko chini ya minimum stock (15kg)', 0, '2026-05-23 10:34:37'),
(2, 4, 'low_stock', 'Bottled Water inakaribia kuisha', 0, '2026-05-23 10:34:37'),
(3, 7, 'low_stock', 'Dishwasher Liquid inahitaji kununuliwa', 0, '2026-05-23 10:34:37');

-- --------------------------------------------------------

--
-- Table structure for table `deliveries`
--

CREATE TABLE `deliveries` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `delivery_date` date NOT NULL,
  `received_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `deliveries`
--

INSERT INTO `deliveries` (`id`, `po_id`, `delivery_date`, `received_by`, `notes`, `created_at`) VALUES
(1, 1, '2026-05-23', 3, '', '2026-05-23 14:36:38');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `current_stock` int(11) DEFAULT 0,
  `minimum_stock` int(11) DEFAULT 10,
  `maximum_stock` int(11) DEFAULT 500,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `item_name`, `category`, `unit`, `current_stock`, `minimum_stock`, `maximum_stock`, `unit_price`, `supplier_id`, `location`, `status`, `created_at`, `updated_at`) VALUES
(1, 'White Rice', 'Food', 'kg', 159, 30, 500, 2500.00, 1, NULL, 'active', '2026-05-23 10:34:37', '2026-05-23 14:36:38'),
(2, 'Cooking Oil', 'Food', 'liters', 80, 20, 500, 3500.00, 1, NULL, 'active', '2026-05-23 10:34:37', '2026-05-23 10:34:37'),
(3, 'Beef', 'Food', 'kg', 40, 15, 500, 12000.00, 1, '0', 'active', '2026-05-23 10:34:37', '2026-05-23 12:30:10'),
(4, 'Bottled Water', 'Beverages', 'bottles', 200, 50, 500, 800.00, 2, NULL, 'active', '2026-05-23 10:34:37', '2026-05-23 10:34:37'),
(5, 'Soda Mix', 'Beverages', 'cartons', 60, 15, 500, 12000.00, 2, NULL, 'active', '2026-05-23 10:34:37', '2026-05-23 10:34:37'),
(6, 'Laundry Soap', 'Cleaning', 'bars', 90, 20, 500, 1800.00, 3, NULL, 'active', '2026-05-23 10:34:37', '2026-05-23 10:34:37'),
(7, 'Dishwasher Liquid', 'Cleaning', 'liters', 40, 10, 500, 8500.00, 3, NULL, 'active', '2026-05-23 10:34:37', '2026-05-23 10:34:37');

-- --------------------------------------------------------

--
-- Table structure for table `po_items`
--

CREATE TABLE `po_items` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `received_quantity` int(11) DEFAULT 0,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `po_items`
--

INSERT INTO `po_items` (`id`, `po_id`, `item_id`, `quantity`, `received_quantity`, `unit_price`, `total_price`) VALUES
(1, 1, 1, 9, 0, 2500.00, 22500.00),
(2, 2, 5, 10, 0, 12000.00, 120000.00);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `po_number` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `expected_delivery` date DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `status` enum('pending','approved','rejected','delivered','confirmed') DEFAULT 'pending',
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `po_number`, `supplier_id`, `order_date`, `expected_delivery`, `total_amount`, `status`, `created_by`, `approved_by`, `notes`, `created_at`) VALUES
(1, 'PO-202605-0001', 2, '2026-05-23', '2026-05-30', 22500.00, 'delivered', 4, 6, 'hello', '2026-05-23 13:15:49'),
(2, 'PO-202605-0002', 4, '2026-05-23', '2026-05-30', 120000.00, 'delivered', 4, 6, 'sabuni zimeisha', '2026-05-23 16:28:06');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `created_at`) VALUES
(1, 'Admin', '2026-05-23 10:34:37'),
(2, 'Hotel Manager', '2026-05-23 10:34:37'),
(3, 'Storekeeper', '2026-05-23 10:34:37'),
(4, 'Procurement Officer', '2026-05-23 10:34:37'),
(5, 'Supplier', '2026-05-23 10:34:37');

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `movement_type` enum('IN','OUT') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `performed_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `item_id`, `movement_type`, `quantity`, `reference_no`, `notes`, `performed_by`, `created_at`) VALUES
(1, 1, 'IN', 100, NULL, NULL, 3, '2026-05-23 10:34:37'),
(2, 1, 'OUT', 25, NULL, NULL, 3, '2026-05-23 10:34:37'),
(3, 2, 'IN', 50, NULL, NULL, 3, '2026-05-23 10:34:37'),
(4, 3, 'IN', 30, NULL, NULL, 3, '2026-05-23 10:34:37'),
(5, 3, 'OUT', 8, '', NULL, 3, '2026-05-23 12:12:44'),
(6, 3, 'IN', 3, '', NULL, 3, '2026-05-23 12:30:10'),
(7, 1, 'IN', 9, 'PO Delivery - Order #1', NULL, 3, '2026-05-23 14:36:38');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `company_name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `password` varchar(255) DEFAULT NULL,
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `phone_verified` tinyint(4) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `company_name`, `contact_person`, `email`, `phone`, `address`, `status`, `created_at`, `password`, `reset_token`, `reset_expires`, `phone_verified`, `updated_at`) VALUES
(1, 'Fresh Food Supplies Ltd', 'Joseph Mwangi', 'info@freshfood.co.tz', '0712345683', 'Dar es Salaam', 'active', '2026-05-23 10:34:37', '$2y$10$udptco2KhV/s9UISmFYDVea/L4.2df4cvezCXY6EPNibJ.ur1oGbW', NULL, NULL, 0, '2026-05-23 16:09:29'),
(2, 'Beverages Wholesale', 'Maria John', 'sales@beverages.co.tz', '0712345684', 'Arusha', 'active', '2026-05-23 10:34:37', '$2y$10$CV2fk0fjDTsx2uPsVT76LO3.D9O/HMLuXhCCNzmhj512SZhJlln5e', NULL, NULL, 0, '2026-05-23 16:09:24'),
(3, 'Hotel Equipments', 'Robert Kimathi', 'robert@hotelequip.co.tz', '0712345685', 'Moshi', 'active', '2026-05-23 10:34:37', '$2y$10$f8cSNmxHnPP15M9VSfc7yO5ubSUHn8u.ijrFHzq2W93/JWYxc2mNW', NULL, NULL, 0, '2026-05-23 16:09:32'),
(4, 'cleanning material', 'bamfu bamfu', 'supplier1@gmail.com', '', '', 'active', '2026-05-23 16:21:04', '$2y$10$yNQ3tnvUnTFnbJEz537pEujOPjGmrDv2OL3E04gcfA8pOnrDm9Mii', NULL, NULL, 0, '2026-05-23 16:21:04');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'Reset Password', 'Reset password for user: storekeeper', '::1', '2026-05-23 11:03:09'),
(2, 1, 'Logout', 'User logged out', '::1', '2026-05-23 11:03:15'),
(3, 3, 'Login', 'User logged in successfully', '::1', '2026-05-23 11:03:41'),
(4, 3, 'Logout', 'User logged out', '::1', '2026-05-23 11:06:42'),
(5, 1, 'Login', 'User logged in successfully', '::1', '2026-05-23 11:06:51'),
(6, 1, 'Reset Password', 'Reset password for user: manager', '::1', '2026-05-23 11:08:23'),
(7, 1, 'Reset Password', 'Reset password for user: procurement', '::1', '2026-05-23 11:08:44'),
(8, 1, 'Reset Password', 'Reset password for user: supplier', '::1', '2026-05-23 11:08:58'),
(9, 1, 'Toggle User', 'Changed user ID 3 status to inactive', '::1', '2026-05-23 11:23:07'),
(10, 1, 'Toggle User', 'Changed user ID 3 status to active', '::1', '2026-05-23 11:23:10'),
(11, 1, 'Edit User', 'Updated user: manager', '::1', '2026-05-23 11:23:19'),
(12, 1, 'Edit User', 'Updated user: manager', '::1', '2026-05-23 11:23:24'),
(13, 1, 'Add User', 'Added new user: bamfu (bamfu bamfu)', '::1', '2026-05-23 11:24:12'),
(14, 1, 'Reset Password', 'Reset password for user: manager', '::1', '2026-05-23 11:24:52'),
(15, 1, 'Reset Password', 'Reset password for user: manager', '::1', '2026-05-23 11:27:09'),
(16, 1, 'Toggle User', 'Changed user ID 6 status to inactive', '::1', '2026-05-23 11:27:57'),
(17, 1, 'Toggle User', 'Changed user ID 6 status to active', '::1', '2026-05-23 11:27:59'),
(18, 1, 'Upload Picture', 'Updated profile picture', '::1', '2026-05-23 11:40:58'),
(19, 1, 'Reset Password', 'Reset password for user: bamfu', '::1', '2026-05-23 11:54:34'),
(20, 1, 'Logout', 'User logged out', '::1', '2026-05-23 11:54:40'),
(21, 6, 'Login', 'User logged in successfully', '::1', '2026-05-23 11:54:49'),
(22, 6, 'Logout', 'User logged out', '::1', '2026-05-23 11:57:39'),
(23, 3, 'Login', 'User logged in successfully', '::1', '2026-05-23 11:57:46'),
(24, 3, 'Stock OUT', 'Removed 8 units from item ID: 3. Department: Kitchen', '::1', '2026-05-23 12:12:44'),
(25, 3, 'Edit Item', 'Updated item: Beef (ID: 3)', '::1', '2026-05-23 12:18:08'),
(26, 3, 'Stock Adjustment', 'Increased stock of Beef by 3. Reason: New Purchase Order', '::1', '2026-05-23 12:30:10'),
(27, 3, 'Logout', 'User logged out', '::1', '2026-05-23 12:45:29'),
(28, 1, 'Login', 'User logged in successfully', '::1', '2026-05-23 12:45:35'),
(29, 1, 'Reset Password', 'Reset password for user: supplier', '::1', '2026-05-23 12:46:35'),
(30, 1, 'Logout', 'User logged out', '::1', '2026-05-23 12:47:34'),
(31, 6, 'Login', 'User logged in successfully', '::1', '2026-05-23 12:47:42'),
(32, 6, 'Logout', 'User logged out', '::1', '2026-05-23 12:49:06'),
(33, 3, 'Login', 'User logged in successfully', '::1', '2026-05-23 12:50:12'),
(34, 3, 'Logout', 'User logged out', '::1', '2026-05-23 12:50:32'),
(35, 1, 'Login', 'User logged in successfully', '::1', '2026-05-23 12:50:53'),
(36, 1, 'Logout', 'User logged out', '::1', '2026-05-23 12:52:42'),
(37, 6, 'Login', 'User logged in successfully', '::1', '2026-05-23 12:52:52'),
(38, 6, 'Reset Password', 'Reset password for user: supplier', '::1', '2026-05-23 13:04:54'),
(39, 6, 'Reset Password', 'Reset password for user: supplier', '::1', '2026-05-23 13:09:09'),
(40, 6, 'Logout', 'User logged out', '::1', '2026-05-23 13:09:17'),
(41, 5, 'Login', 'User logged in successfully', '::1', '2026-05-23 13:09:27'),
(42, 5, 'Logout', 'User logged out', '::1', '2026-05-23 13:09:56'),
(43, 1, 'Login', 'User logged in successfully', '::1', '2026-05-23 13:10:02'),
(44, 1, 'Reset Password', 'Reset password for user: procurement', '::1', '2026-05-23 13:10:32'),
(45, 1, 'Reset Password', 'Reset password for user: supplier', '::1', '2026-05-23 13:10:51'),
(46, 1, 'Logout', 'User logged out', '::1', '2026-05-23 13:10:59'),
(47, 4, 'Login', 'User logged in successfully', '::1', '2026-05-23 13:11:06'),
(48, 4, 'Create PO', 'Created purchase order: PO-202605-0001', '::1', '2026-05-23 13:15:49'),
(49, 4, 'Logout', 'User logged out', '::1', '2026-05-23 13:16:17'),
(50, 4, 'Login', 'User logged in successfully', '::1', '2026-05-23 13:16:58'),
(51, 4, 'Logout', 'User logged out', '::1', '2026-05-23 13:17:06'),
(52, 6, 'Login', 'User logged in successfully', '::1', '2026-05-23 13:17:23'),
(53, 6, 'Approve PO', 'Purchase order ID: 1 - approved', '::1', '2026-05-23 13:17:35'),
(54, 6, 'Logout', 'User logged out', '::1', '2026-05-23 13:42:24'),
(55, 4, 'Login', 'User logged in successfully', '::1', '2026-05-23 13:42:46'),
(56, 4, 'Logout', 'User logged out', '::1', '2026-05-23 14:03:38'),
(57, 5, 'Login', 'User logged in successfully', '::1', '2026-05-23 14:03:52'),
(58, 5, 'Logout', 'User logged out', '::1', '2026-05-23 14:09:37'),
(59, 3, 'Login', 'User logged in successfully', '::1', '2026-05-23 14:09:46'),
(60, 3, 'Logout', 'User logged out', '::1', '2026-05-23 14:11:24'),
(61, 4, 'Login', 'User logged in successfully', '::1', '2026-05-23 14:11:34'),
(62, 4, 'Logout', 'User logged out', '::1', '2026-05-23 14:14:52'),
(63, 5, 'Login', 'User logged in successfully', '::1', '2026-05-23 14:15:10'),
(64, 5, 'Logout', 'User logged out', '::1', '2026-05-23 14:32:47'),
(65, 3, 'Login', 'User logged in successfully', '::1', '2026-05-23 14:35:38'),
(66, 3, 'Confirm Delivery', 'Confirmed delivery for PO ID: 1 and updated stock', '::1', '2026-05-23 14:36:38'),
(67, 3, 'Logout', 'User logged out', '::1', '2026-05-23 14:38:55'),
(68, 6, 'Login', 'User logged in successfully', '::1', '2026-05-23 14:39:02'),
(69, 6, 'Logout', 'User logged out', '::1', '2026-05-23 14:40:45'),
(70, 4, 'Login', 'User logged in successfully', '::1', '2026-05-23 14:40:55'),
(71, 1, 'Login', 'User logged in successfully', '::1', '2026-05-23 14:55:40'),
(72, 1, 'Logout', 'User logged out', '::1', '2026-05-23 14:56:14'),
(73, 6, 'Login', 'User logged in successfully', '::1', '2026-05-23 14:56:29'),
(74, 6, 'Logout', 'User logged out', '::1', '2026-05-23 14:57:33'),
(75, 4, 'Login', 'User logged in successfully', '::1', '2026-05-23 14:57:42'),
(76, 4, 'Logout', 'User logged out', '::1', '2026-05-23 15:00:52'),
(77, 1, 'Login', 'User logged in successfully', '::1', '2026-05-23 15:00:57'),
(78, 1, 'Logout', 'User logged out', '::1', '2026-05-23 15:04:00'),
(79, 6, 'Login', 'User logged in successfully', '::1', '2026-05-23 15:04:07'),
(80, 6, 'Logout', 'User logged out', '::1', '2026-05-23 15:20:40'),
(81, 1, 'Login', 'User logged in successfully', '::1', '2026-05-23 15:24:51'),
(82, 1, 'Logout', 'User logged out', '::1', '2026-05-23 15:25:18'),
(83, 6, 'Login', 'Staff logged in successfully', '::1', '2026-05-23 15:33:38'),
(84, 6, 'Logout', 'User logged out', '::1', '2026-05-23 15:33:57'),
(85, 1, 'Login', 'Staff logged in successfully', '::1', '2026-05-23 15:35:32'),
(86, 1, 'Logout', 'User logged out', '::1', '2026-05-23 15:35:58'),
(88, 1, 'Logout', 'User logged out', '::1', '2026-05-23 15:39:07'),
(89, 1, 'Login', 'Staff logged in successfully', '::1', '2026-05-23 15:46:59'),
(90, 1, 'Logout', 'User logged out', '::1', '2026-05-23 15:47:18'),
(91, 1, 'Logout', 'User logged out', '::1', '2026-05-23 15:47:33'),
(92, 1, 'Login', 'Staff logged in successfully', '::1', '2026-05-23 15:47:43'),
(93, 1, 'Logout', 'User logged out', '::1', '2026-05-23 15:48:16'),
(94, 1, 'Login', 'Staff logged in successfully', '::1', '2026-05-23 15:48:23'),
(95, 1, 'Logout', 'User logged out', '::1', '2026-05-23 15:48:33'),
(96, 1, 'Login', 'Staff logged in successfully', '::1', '2026-05-23 15:54:42'),
(97, 1, 'Reset Supplier Password', 'Reset password for supplier: Beverages Wholesale', '::1', '2026-05-23 16:09:24'),
(98, 1, 'Reset Supplier Password', 'Reset password for supplier: Fresh Food Supplies Ltd', '::1', '2026-05-23 16:09:29'),
(99, 1, 'Reset Supplier Password', 'Reset password for supplier: Hotel Equipments', '::1', '2026-05-23 16:09:32'),
(100, 1, 'Add Supplier', 'Added new supplier: cleanning material', '::1', '2026-05-23 16:21:04'),
(101, 1, 'Logout', 'User logged out', '::1', '2026-05-23 16:21:40'),
(102, 4, 'Logout', 'User logged out', '::1', '2026-05-23 16:24:25'),
(103, 6, 'Login', 'Staff logged in successfully', '::1', '2026-05-23 16:25:05'),
(104, 6, 'Logout', 'User logged out', '::1', '2026-05-23 16:27:03'),
(105, 4, 'Login', 'Staff logged in successfully', '::1', '2026-05-23 16:27:12'),
(106, 4, 'Create PO', 'Created purchase order: PO-202605-0002', '::1', '2026-05-23 16:28:06'),
(107, 4, 'Logout', 'User logged out', '::1', '2026-05-23 16:28:18'),
(108, 6, 'Login', 'Staff logged in successfully', '::1', '2026-05-23 16:28:33'),
(109, 6, 'Approve PO', 'Purchase order ID: 2 - approved', '::1', '2026-05-23 16:29:08'),
(110, 6, 'Logout', 'User logged out', '::1', '2026-05-23 16:29:22');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `phone_verified` tinyint(4) DEFAULT 0,
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullname`, `username`, `password`, `email`, `phone`, `role_id`, `status`, `created_at`, `phone_verified`, `reset_token`, `reset_expires`, `profile_picture`, `updated_at`) VALUES
(1, 'System Admin', 'admin', '$2y$10$kUdzuormSrY2.woydzTZEOTehkQ5O2rybVU41/Rni1fbuIh/E6Wh2', 'admin@hotel.com', '0712345678', 1, 'active', '2026-05-23 10:34:37', 1, NULL, NULL, 'user_1_1779536458.jpg', '2026-05-23 11:40:58'),
(2, 'John Manager', 'manager', '$2y$10$zd8fjaty/tb3XKCzvrU8Su1pgTkCTiu.ebvOVn2KeGb7wvks.biEq', 'manager@hotel.com', '0712345679', 2, 'active', '2026-05-23 10:34:37', 1, NULL, NULL, NULL, '2026-05-23 11:36:11'),
(3, 'James Store', 'storekeeper', '$2y$10$xalpSrvyq9PrOhJgPP7TZOiLeRgA5kLPq2r/l/2N8HUvYe.Oc6M7C', 'storekeeper@hotel.com', '0712345680', 3, 'active', '2026-05-23 10:34:37', 1, NULL, NULL, NULL, '2026-05-23 11:36:11'),
(4, 'Peter Procurement', 'procurement', '$2y$10$XZFodyOhL3B9tOwF8pOaWOGqpmtTQL/frRcQFfkOAGughUqmGC7i.', 'procurement@hotel.com', '0712345681', 4, 'active', '2026-05-23 10:34:37', 1, NULL, NULL, NULL, '2026-05-23 13:10:32'),
(5, 'Sarah Supplier', 'supplier', '$2y$10$mRIyDawkZREZ7S15r.H71elnPapqQ6/OYDT.4nfYk1NgUmJONtFrq', 'supplier@hotel.com', '0712345682', 5, 'active', '2026-05-23 10:34:37', 1, NULL, NULL, NULL, '2026-05-23 13:10:51'),
(6, 'bamfu bamfu', 'bamfu', '$2y$10$ADIOpG4byjoOkDJXUfcUGeMlLqUlI8pVoL9jQPItUcrzkAEUEiV8C', 'bbamfu@gmail.com', '12345678', 2, 'active', '2026-05-23 11:24:12', 0, NULL, NULL, NULL, '2026-05-23 11:54:34');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alerts`
--
ALTER TABLE `alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `received_by` (`received_by`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `po_items`
--
ALTER TABLE `po_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_number` (`po_number`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reset_token` (`reset_token`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `system_logs_ibfk_1` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `idx_reset_token` (`reset_token`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alerts`
--
ALTER TABLE `alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `deliveries`
--
ALTER TABLE `deliveries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `po_items`
--
ALTER TABLE `po_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `alerts`
--
ALTER TABLE `alerts`
  ADD CONSTRAINT `alerts_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`);

--
-- Constraints for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD CONSTRAINT `deliveries_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`),
  ADD CONSTRAINT `deliveries_ibfk_2` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `po_items`
--
ALTER TABLE `po_items`
  ADD CONSTRAINT `po_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `po_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`);

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
COMMIT;

