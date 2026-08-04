-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.0.45 - MySQL Community Server - GPL
-- Server OS:                    Linux
-- HeidiSQL Version:             12.15.0.7171
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Dumping structure for table ocean_db.affiliate_conversions
CREATE TABLE IF NOT EXISTS `affiliate_conversions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `referrer_id` bigint unsigned NOT NULL,
  `buyer_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `commission_rate` decimal(5,2) NOT NULL DEFAULT '5.00',
  `commission_amount` decimal(15,2) NOT NULL,
  `status` enum('pending','approved','cancelled','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `affiliate_conversions_order_id_unique` (`order_id`),
  KEY `affiliate_conversions_buyer_id_foreign` (`buyer_id`),
  KEY `affiliate_conversions_referrer_id_index` (`referrer_id`),
  KEY `affiliate_conversions_status_index` (`status`),
  CONSTRAINT `affiliate_conversions_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `affiliate_conversions_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `affiliate_conversions_referrer_id_foreign` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.affiliate_conversions: ~0 rows (approximately)
DELETE FROM `affiliate_conversions`;

-- Dumping structure for table ocean_db.affiliate_withdrawals
CREATE TABLE IF NOT EXISTS `affiliate_withdrawals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `withdrawal_method` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bank',
  `bank_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_account_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_account_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','approved','rejected','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `affiliate_withdrawals_user_id_index` (`user_id`),
  KEY `affiliate_withdrawals_status_index` (`status`),
  CONSTRAINT `affiliate_withdrawals_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.affiliate_withdrawals: ~0 rows (approximately)
DELETE FROM `affiliate_withdrawals`;

-- Dumping structure for table ocean_db.brands
CREATE TABLE IF NOT EXISTS `brands` (
  `brand_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `logo_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`brand_id`),
  UNIQUE KEY `brands_name_unique` (`name`),
  UNIQUE KEY `brands_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.brands: ~5 rows (approximately)
DELETE FROM `brands`;
INSERT INTO `brands` (`brand_id`, `name`, `slug`, `description`, `logo_url`, `is_active`, `created_at`, `updated_at`) VALUES
	(1, 'Kuikma', 'kuikma', 'Thương hiệu thể thao dùng vợt của Decathlon, nổi bật ở cầu lông và pickleball.', NULL, 1, '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(2, 'Kipsta', 'kipsta', 'Thương hiệu thể thao đồng đội của Decathlon, phù hợp bóng chuyền và phụ kiện sân bãi.', NULL, 1, '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(3, 'Artengo', 'artengo', 'Thương hiệu dụng cụ và giày sân vợt của Decathlon.', NULL, 1, '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(4, 'Decathlon', 'decathlon', 'Dòng sản phẩm phổ thông do Decathlon phát triển cho người chơi mới bắt đầu.', NULL, 1, '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(5, 'Facolos', 'facolos', 'Thương hiệu pickleball thiên về hiệu năng cao, phù hợp người chơi nâng cao.', NULL, 1, '2026-06-05 10:54:43', '2026-06-05 10:54:43');

-- Dumping structure for table ocean_db.cart_items
CREATE TABLE IF NOT EXISTS `cart_items` (
  `cart_item_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cart_id` bigint unsigned NOT NULL,
  `variant_id` bigint unsigned NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `selected` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`cart_item_id`),
  UNIQUE KEY `cart_items_cart_id_variant_id_unique` (`cart_id`,`variant_id`),
  KEY `cart_items_variant_id_foreign` (`variant_id`),
  KEY `idx_cart_items_cart_variant` (`cart_id`,`variant_id`),
  CONSTRAINT `cart_items_cart_id_foreign` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`cart_id`) ON DELETE CASCADE,
  CONSTRAINT `cart_items_variant_id_foreign` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`variant_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.cart_items: ~4 rows (approximately)
DELETE FROM `cart_items`;
INSERT INTO `cart_items` (`cart_item_id`, `cart_id`, `variant_id`, `quantity`, `selected`, `created_at`, `updated_at`) VALUES
	(19, 1, 30, 1, 1, '2026-06-24 22:03:50', '2026-06-24 22:03:50'),
	(20, 1, 32, 1, 1, '2026-06-24 22:11:01', '2026-06-24 22:11:01'),
	(24, 1, 29, 1, 1, '2026-06-29 16:52:05', '2026-06-29 16:52:05'),
	(33, 5, 1, 7, 1, '2026-07-22 09:00:04', '2026-07-22 09:03:20');

-- Dumping structure for table ocean_db.carts
CREATE TABLE IF NOT EXISTS `carts` (
  `cart_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `status` enum('active','converted','abandoned') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `is_abandoned_reminded` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`cart_id`),
  KEY `carts_user_id_foreign` (`user_id`),
  CONSTRAINT `carts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.carts: ~5 rows (approximately)
DELETE FROM `carts`;
INSERT INTO `carts` (`cart_id`, `user_id`, `status`, `is_abandoned_reminded`, `created_at`, `updated_at`) VALUES
	(1, 8, 'active', 0, '2026-06-06 14:17:38', '2026-06-06 14:17:38'),
	(2, 9, 'active', 0, '2026-06-06 15:26:38', '2026-06-06 15:26:38'),
	(3, 5, 'active', 0, '2026-06-16 23:35:15', '2026-06-16 23:35:15'),
	(4, 1, 'active', 0, '2026-06-20 22:59:31', '2026-06-20 22:59:31'),
	(5, 10, 'active', 0, '2026-07-22 09:00:04', '2026-07-22 09:00:04');

-- Dumping structure for table ocean_db.categories
CREATE TABLE IF NOT EXISTS `categories` (
  `category_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint unsigned DEFAULT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `categories_slug_unique` (`slug`),
  KEY `categories_parent_id_foreign` (`parent_id`),
  CONSTRAINT `categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.categories: ~13 rows (approximately)
DELETE FROM `categories`;
INSERT INTO `categories` (`category_id`, `parent_id`, `name`, `slug`, `image`, `description`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
	(4, NULL, 'Pickleball', 'pickleball', 'categories/G8bw3wqaayvXhK6C5unT7andNcgkMV1t6x7ZSlD7.webp', '<p>Vợt, giày và set pickleball cho nhu cầu giải trí đến nâng cao.</p>', 63, 1, '2026-06-05 10:54:43', '2026-07-24 00:43:42'),
	(6, NULL, 'Cầu  lông', 'cau-long', 'categories/wXIPmud3lOmerM9DCT1trsBpY5l7KbwEHDLXqTry.webp', NULL, 0, 1, '2026-07-21 07:46:50', '2026-07-24 00:44:10'),
	(7, 6, 'Vợt', 'badminton-vot', NULL, NULL, 0, 1, '2026-07-21 07:46:50', '2026-07-21 07:46:50'),
	(8, 6, 'Giày', 'badminton-giay', NULL, NULL, 0, 1, '2026-07-21 07:47:47', '2026-07-21 07:47:47'),
	(9, 6, 'Áo', 'badminton-ao', NULL, NULL, 0, 1, '2026-07-21 07:49:50', '2026-07-21 07:49:50'),
	(10, 6, 'Balo', 'badminton-balo', NULL, NULL, 0, 1, '2026-07-21 08:02:32', '2026-07-21 08:02:32'),
	(11, 4, 'Vợt', 'pickleball-vot', NULL, NULL, 0, 1, '2026-07-21 08:02:51', '2026-07-21 08:02:51'),
	(12, 4, 'Giày', 'pickleball-giay', NULL, NULL, 0, 1, '2026-07-21 08:03:15', '2026-07-21 08:03:15'),
	(13, 4, 'Balo', 'pickleball-balo', NULL, NULL, 0, 1, '2026-07-21 08:08:01', '2026-07-21 08:08:01'),
	(14, NULL, 'Tennis', 'tennis', 'categories/wom0RdIP3W1uRGQEvpMNbtDbVn9B3VRwxlAeO9ZC.webp', NULL, 0, 1, '2026-07-21 08:08:22', '2026-07-24 00:44:24'),
	(15, 14, 'Vợt', 'tennis-vot', NULL, NULL, 0, 1, '2026-07-21 08:08:22', '2026-07-21 08:08:22'),
	(16, 14, 'Giày', 'tennis-giay', NULL, NULL, 0, 1, '2026-07-21 08:09:44', '2026-07-21 08:09:44'),
	(17, 14, 'Balo', 'tennis-balo', NULL, NULL, 0, 1, '2026-07-21 08:11:06', '2026-07-21 08:11:06');

-- Dumping structure for table ocean_db.chat_messages
CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `chat_session_id` bigint unsigned NOT NULL,
  `sender_type` enum('user','admin') COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chat_messages_chat_session_id_foreign` (`chat_session_id`),
  CONSTRAINT `chat_messages_chat_session_id_foreign` FOREIGN KEY (`chat_session_id`) REFERENCES `chat_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.chat_messages: ~0 rows (approximately)
DELETE FROM `chat_messages`;

-- Dumping structure for table ocean_db.chat_sessions
CREATE TABLE IF NOT EXISTS `chat_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `session_token` char(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Dành cho guest và user để định danh phòng chat',
  `user_id` bigint unsigned DEFAULT NULL COMMENT 'Null nếu là khách vãng lai',
  `status` enum('open','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `last_message_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chat_sessions_session_token_unique` (`session_token`),
  KEY `chat_sessions_user_id_foreign` (`user_id`),
  CONSTRAINT `chat_sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.chat_sessions: ~2 rows (approximately)
DELETE FROM `chat_sessions`;
INSERT INTO `chat_sessions` (`id`, `session_token`, `user_id`, `status`, `last_message_at`, `created_at`, `updated_at`) VALUES
	(1, '95fc2bed-737a-49e0-bd75-6f9e13191da8', 1, 'closed', '2026-06-13 22:39:47', '2026-06-05 14:44:37', '2026-06-13 22:39:54'),
	(2, 'df4536c5-e6d8-4acc-8203-0e431a7825ba', NULL, 'closed', '2026-06-13 22:39:42', '2026-06-05 14:46:36', '2026-06-13 22:39:58');

-- Dumping structure for table ocean_db.contacts
CREATE TABLE IF NOT EXISTS `contacts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','replied') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `admin_reply` text COLLATE utf8mb4_unicode_ci,
  `replied_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.contacts: ~3 rows (approximately)
DELETE FROM `contacts`;
INSERT INTO `contacts` (`id`, `name`, `email`, `subject`, `message`, `status`, `admin_reply`, `replied_at`, `created_at`, `updated_at`) VALUES
	(1, 'Newsletter Subscriber', 'buichibinh2401@gmail.com', 'Đăng ký nhận bản tin', 'Khách hàng đăng ký nhận tin từ footer.', 'pending', NULL, NULL, '2026-06-05 15:51:42', '2026-06-05 15:51:42'),
	(2, 'ddddddddd', 'buichibinh22222222222@gmail.com', 'Hỏi về đơn hàng', 'ddddddddd', 'pending', NULL, NULL, '2026-07-13 17:13:21', '2026-07-13 17:13:21'),
	(3, 'sssssss', 'binhbcpk03952@gmail.com', 'Đổi/Trả sản phẩm', 'ssssssssss', 'pending', NULL, NULL, '2026-07-22 08:57:05', '2026-07-22 08:57:05');

-- Dumping structure for table ocean_db.coupon_categories
CREATE TABLE IF NOT EXISTS `coupon_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `coupon_id` bigint unsigned NOT NULL,
  `category_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coupon_categories_coupon_id_category_id_unique` (`coupon_id`,`category_id`),
  KEY `coupon_categories_category_id_foreign` (`category_id`),
  CONSTRAINT `coupon_categories_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE,
  CONSTRAINT `coupon_categories_coupon_id_foreign` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.coupon_categories: ~0 rows (approximately)
DELETE FROM `coupon_categories`;

-- Dumping structure for table ocean_db.coupon_products
CREATE TABLE IF NOT EXISTS `coupon_products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `coupon_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `min_qty` int unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coupon_products_coupon_id_product_id_unique` (`coupon_id`,`product_id`),
  KEY `coupon_products_product_id_foreign` (`product_id`),
  CONSTRAINT `coupon_products_coupon_id_foreign` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `coupon_products_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.coupon_products: ~0 rows (approximately)
DELETE FROM `coupon_products`;

-- Dumping structure for table ocean_db.coupons
CREATE TABLE IF NOT EXISTS `coupons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('fixed','percent','free_ship','combo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fixed',
  `value` decimal(10,2) NOT NULL,
  `max_discount_value` decimal(10,2) DEFAULT NULL,
  `min_order_value` decimal(10,2) DEFAULT NULL,
  `usage_limit` int DEFAULT NULL,
  `used_count` int NOT NULL DEFAULT '0',
  `user_usage_limit` int NOT NULL DEFAULT '1',
  `is_public` tinyint(1) NOT NULL DEFAULT '1',
  `is_first_order` tinyint(1) NOT NULL DEFAULT '0',
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `auto_apply` tinyint(1) NOT NULL DEFAULT '0',
  `min_product_qty` int unsigned NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coupons_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.coupons: ~20 rows (approximately)
DELETE FROM `coupons`;
INSERT INTO `coupons` (`id`, `code`, `type`, `value`, `max_discount_value`, `min_order_value`, `usage_limit`, `used_count`, `user_usage_limit`, `is_public`, `is_first_order`, `start_date`, `end_date`, `is_active`, `auto_apply`, `min_product_qty`, `deleted_at`, `created_at`, `updated_at`) VALUES
	(1, 'WELCOME2026', 'percent', 10.00, 50000.00, NULL, NULL, 0, 1, 0, 0, '2026-06-05 10:54:43', NULL, 1, 0, 1, NULL, '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(2, 'FIRSTORDER', 'fixed', 50000.00, NULL, 200000.00, 1000, 0, 1, 1, 1, '2026-06-05 10:54:43', '2026-09-03 10:54:43', 1, 0, 1, NULL, '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(3, 'FREESHIP50K', 'free_ship', 50000.00, NULL, 150000.00, 500, 10, 3, 1, 0, '2026-06-03 10:54:43', '2026-06-20 10:54:43', 1, 0, 1, NULL, '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(4, 'FLASHSALE50', 'percent', 50.00, 200000.00, 500000.00, 20, 19, 1, 1, 0, '2026-06-04 10:54:43', '2026-06-07 10:54:43', 1, 0, 1, NULL, '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(5, 'VIP63X', 'percent', 15.00, 100000.00, 300000.00, 50, 6, 5, 0, 0, '2026-06-10 10:29:52', '2026-08-08 23:14:19', 1, 0, 1, NULL, '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(6, 'SALE66', 'percent', 20.00, 30000.00, 300000.00, 50, 3, 1, 1, 0, '2026-06-08 15:13:44', '2026-09-06 11:50:30', 0, 0, 1, NULL, '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(7, 'LUCKY06', 'percent', 5.00, 50000.00, NULL, NULL, 27, 1, 0, 1, '2026-06-06 07:57:35', '2026-08-19 06:06:27', 1, 0, 1, NULL, '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(8, 'COOL74VN', 'percent', 10.00, 30000.00, NULL, NULL, 5, 1, 1, 0, '2026-06-12 07:00:01', '2026-09-01 04:08:47', 1, 0, 1, NULL, '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(9, 'SALE58X', 'free_ship', 50000.00, NULL, NULL, NULL, 25, 5, 0, 0, '2026-06-02 23:35:57', '2026-08-27 02:31:44', 1, 0, 1, NULL, '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(10, 'PRO62X', 'percent', 10.00, 50000.00, NULL, 1000, 21, 3, 1, 0, '2026-05-27 11:01:57', '2026-07-10 09:30:35', 1, 0, 1, NULL, '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(11, 'PRO13X', 'free_ship', 20000.00, NULL, 500000.00, NULL, 18, 2, 1, 1, '2026-05-27 19:14:34', '2026-08-26 03:17:48', 1, 0, 1, NULL, '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(12, 'MEGA72K', 'fixed', 100000.00, NULL, 300000.00, 100, 10, 1, 1, 0, '2026-06-01 02:46:31', '2026-08-11 17:00:25', 1, 0, 1, NULL, '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(13, 'HOT12K', 'free_ship', 20000.00, NULL, 200000.00, NULL, 10, 3, 1, 0, '2026-06-03 05:44:00', '2026-07-23 22:56:57', 0, 0, 1, NULL, '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(14, 'HOT25X', 'percent', 20.00, 50000.00, 100000.00, NULL, 9, 1, 0, 0, '2026-05-23 07:08:46', '2026-09-08 04:43:47', 0, 0, 1, NULL, '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(15, 'GIAM83K', 'percent', 25.00, 30000.00, NULL, NULL, 21, 2, 1, 0, '2026-05-28 18:35:00', '2026-07-15 11:37:35', 1, 0, 1, NULL, '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(16, 'MEGA88X', 'free_ship', 50000.00, NULL, NULL, NULL, 15, 1, 0, 0, '2026-06-06 12:00:54', '2026-07-12 17:36:44', 0, 0, 1, NULL, '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(17, 'SALE15', 'percent', 25.00, 500000.00, 150000.00, NULL, 11, 1, 1, 0, '2026-05-26 15:52:53', '2026-07-18 02:53:57', 1, 0, 1, NULL, '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(18, 'VIP26', 'percent', 5.00, NULL, 200000.00, NULL, 29, 2, 1, 1, '2026-05-23 23:43:18', '2026-09-01 04:04:25', 1, 0, 1, NULL, '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(19, 'LUCKY07', 'percent', 25.00, 100000.00, 300000.00, 100, 27, 5, 0, 0, '2026-05-23 13:52:49', '2026-08-08 04:38:56', 1, 0, 1, NULL, '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(20, 'VIP11K', 'percent', 5.00, NULL, 200000.00, NULL, 15, 3, 1, 0, '2026-05-28 04:42:15', '2026-07-06 13:56:32', 0, 0, 1, NULL, '2026-06-05 10:54:43', '2026-06-05 10:54:43');

-- Dumping structure for table ocean_db.face_encodings
CREATE TABLE IF NOT EXISTS `face_encodings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `user_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `encoding` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `face_user_active_idx` (`user_id`,`user_type`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.face_encodings: ~0 rows (approximately)
DELETE FROM `face_encodings`;

-- Dumping structure for table ocean_db.failed_jobs
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.failed_jobs: ~0 rows (approximately)
DELETE FROM `failed_jobs`;

-- Dumping structure for table ocean_db.favorites
CREATE TABLE IF NOT EXISTS `favorites` (
  `favorite_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`favorite_id`),
  UNIQUE KEY `favorites_user_id_product_id_unique` (`user_id`,`product_id`),
  KEY `favorites_product_id_foreign` (`product_id`),
  CONSTRAINT `favorites_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  CONSTRAINT `favorites_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.favorites: ~10 rows (approximately)
DELETE FROM `favorites`;
INSERT INTO `favorites` (`favorite_id`, `user_id`, `product_id`, `created_at`) VALUES
	(1, 8, 20, '2026-06-06 14:15:47'),
	(2, 8, 8, '2026-06-06 14:16:06'),
	(3, 8, 7, '2026-06-06 14:16:10'),
	(4, 8, 6, '2026-06-06 14:16:12'),
	(5, 8, 5, '2026-06-06 14:16:14'),
	(6, 8, 4, '2026-06-06 14:16:15'),
	(7, 8, 3, '2026-06-06 14:16:18'),
	(8, 1, 246, '2026-07-24 00:23:54'),
	(9, 1, 245, '2026-07-24 00:23:54'),
	(10, 1, 244, '2026-07-24 00:23:59');

-- Dumping structure for table ocean_db.job_batches
CREATE TABLE IF NOT EXISTS `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.job_batches: ~0 rows (approximately)
DELETE FROM `job_batches`;

-- Dumping structure for table ocean_db.jobs
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_reserved_at_available_at_index` (`queue`,`reserved_at`,`available_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.jobs: ~0 rows (approximately)
DELETE FROM `jobs`;

-- Dumping structure for table ocean_db.loyalty_rules
CREATE TABLE IF NOT EXISTS `loyalty_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('earn','burn') COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `points_per_unit` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `vnd_per_point` decimal(10,2) NOT NULL DEFAULT '0.00',
  `min_points` int unsigned NOT NULL DEFAULT '0',
  `max_points_per_order` int unsigned DEFAULT NULL,
  `max_burn_percent` decimal(5,2) DEFAULT NULL,
  `earn_expiry_days` int unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loyalty_rules_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.loyalty_rules: ~9 rows (approximately)
DELETE FROM `loyalty_rules`;
INSERT INTO `loyalty_rules` (`id`, `key`, `type`, `name`, `description`, `points_per_unit`, `vnd_per_point`, `min_points`, `max_points_per_order`, `max_burn_percent`, `earn_expiry_days`, `is_active`, `created_at`, `updated_at`) VALUES
	(1, 'ORDER_COMPLETE', 'earn', 'Mua hàng tích điểm', '1 điểm cho mỗi 10.000đ giá trị đơn hàng', 1.0000, 0.00, 0, NULL, NULL, 365, 1, '2026-06-08 13:04:06', '2026-06-08 13:04:06'),
	(2, 'FIRST_ORDER', 'earn', 'Bonus đơn hàng đầu tiên', 'Tặng 200 điểm cho đơn hàng đầu tiên', 200.0000, 0.00, 0, NULL, NULL, 365, 1, '2026-06-08 13:04:06', '2026-06-08 13:04:06'),
	(3, 'REFERRAL', 'earn', 'Giới thiệu bạn bè', 'Tặng 200 điểm khi giới thiệu bạn bè mua hàng thành công', 200.0000, 0.00, 0, NULL, NULL, 365, 1, '2026-06-08 13:04:06', '2026-06-21 23:42:07'),
	(4, 'BIRTHDAY', 'earn', 'Quà sinh nhật', 'Tặng 100 điểm vào ngày sinh nhật khách hàng', 100.0000, 0.00, 0, NULL, NULL, 90, 1, '2026-06-08 13:04:06', '2026-06-21 23:42:07'),
	(5, 'REVIEW', 'earn', 'Viết đánh giá sản phẩm', 'Tặng 20 điểm khi viết đánh giá sản phẩm có nội dung', 20.0000, 0.00, 0, NULL, NULL, 365, 1, '2026-06-08 13:04:06', '2026-06-21 23:42:07'),
	(6, 'ABANDONED_CART', 'earn', 'Nhắc giỏ hàng bỏ quên', 'Tặng 30 điểm khi quay lại hoàn tất đơn hàng từ giỏ bỏ quên', 30.0000, 0.00, 0, NULL, NULL, 90, 1, '2026-06-08 13:04:06', '2026-06-21 23:42:07'),
	(7, 'REDEEM_DISCOUNT', 'burn', 'Dùng điểm giảm giá', '1 điểm = 100đ giảm giá. Tối thiểu 100 điểm. Tối đa 30% giá trị đơn.', 0.0000, 100.00, 100, 5000, 30.00, NULL, 1, '2026-06-08 13:04:06', '2026-06-08 13:04:06'),
	(8, 'REVIEW_WITH_IMAGE', 'earn', 'Đánh giá kèm hình ảnh', 'Tặng thêm 50 điểm khi đánh giá sản phẩm kèm hình ảnh', 50.0000, 0.00, 0, NULL, NULL, 365, 1, '2026-06-21 23:42:07', '2026-06-21 23:42:07'),
	(9, 'SOCIAL_SHARE', 'earn', 'Chia sẻ sản phẩm', 'Tặng 10 điểm khi chia sẻ sản phẩm lên mạng xã hội', 10.0000, 0.00, 0, NULL, NULL, 365, 1, '2026-06-21 23:42:07', '2026-06-21 23:42:07');

-- Dumping structure for table ocean_db.loyalty_transactions
CREATE TABLE IF NOT EXISTS `loyalty_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `type` enum('earn','burn','expire','adjust','refund') COLLATE utf8mb4_unicode_ci NOT NULL,
  `points` int unsigned NOT NULL,
  `balance_before` int unsigned NOT NULL DEFAULT '0',
  `balance_after` int unsigned NOT NULL DEFAULT '0',
  `reference_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL,
  `description` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `expired_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `loyalty_transactions_user_id_type_index` (`user_id`,`type`),
  KEY `loyalty_transactions_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `loyalty_transactions_expires_at_type_index` (`expires_at`,`type`),
  CONSTRAINT `loyalty_transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.loyalty_transactions: ~17 rows (approximately)
DELETE FROM `loyalty_transactions`;
INSERT INTO `loyalty_transactions` (`id`, `user_id`, `type`, `points`, `balance_before`, `balance_after`, `reference_type`, `reference_id`, `description`, `expires_at`, `expired_at`, `created_at`, `updated_at`) VALUES
	(1, 9, 'earn', 50, 0, 50, 'cart', 2, 'Điểm thưởng nhắc giỏ hàng bỏ quên', '2026-09-11 21:49:03', NULL, '2026-06-13 21:49:03', '2026-06-13 21:49:03'),
	(2, 8, 'earn', 179, 0, 179, 'App\\Models\\Order', 2, 'Tích điểm đơn hàng #ORD6A2ED176B893699 (1.798.000đ)', '2027-06-14 23:20:28', NULL, '2026-06-14 23:20:28', '2026-06-14 23:20:28'),
	(3, 8, 'earn', 179, 179, 358, 'App\\Models\\Order', 2, 'Tích điểm đơn hàng #ORD6A2ED176B893699 (1.798.000đ)', '2027-06-14 23:20:29', NULL, '2026-06-14 23:20:29', '2026-06-14 23:20:29'),
	(4, 8, 'earn', 200, 358, 558, 'App\\Models\\Order', 2, 'Bonus đơn hàng đầu tiên #ORD6A2ED176B893699', '2027-06-14 23:20:29', NULL, '2026-06-14 23:20:29', '2026-06-14 23:20:29'),
	(5, 5, 'earn', 50, 0, 50, 'cart', 3, 'Điểm thưởng nhắc giỏ hàng bỏ quên', '2026-09-17 00:38:03', NULL, '2026-06-19 00:38:03', '2026-06-19 00:38:03'),
	(6, 8, 'earn', 50, 558, 608, 'cart', 1, 'Điểm thưởng nhắc giỏ hàng bỏ quên', '2026-09-18 17:06:06', NULL, '2026-06-20 17:06:06', '2026-06-20 17:06:06'),
	(7, 1, 'earn', 53, 0, 53, 'App\\Models\\Order', 9, 'Tích điểm đơn hàng #ORD6A38E82914D4274 (539.000đ)', '2027-06-22 15:20:00', NULL, '2026-06-22 15:20:00', '2026-06-22 15:20:00'),
	(8, 1, 'earn', 53, 53, 106, 'App\\Models\\Order', 9, 'Tích điểm đơn hàng #ORD6A38E82914D4274 (539.000đ)', '2027-06-29 14:06:12', NULL, '2026-06-29 14:06:12', '2026-06-29 14:06:12'),
	(9, 1, 'earn', 200, 106, 306, 'App\\Models\\Order', 9, 'Bonus đơn hàng đầu tiên #ORD6A38E82914D4274', '2027-06-29 14:06:12', NULL, '2026-06-29 14:06:12', '2026-06-29 14:06:12'),
	(10, 5, 'earn', 73, 50, 123, 'App\\Models\\Order', 11, 'Tích điểm đơn hàng #ORD6A421ACF8E8F623 (738.000đ)', '2027-06-29 14:25:11', NULL, '2026-06-29 14:25:11', '2026-06-29 14:25:11'),
	(11, 5, 'earn', 139, 123, 262, 'App\\Models\\Order', 12, 'Tích điểm đơn hàng #ORD6A423E26677ED34 (1.399.000đ)', '2027-06-29 17:02:26', NULL, '2026-06-29 17:02:26', '2026-06-29 17:02:26'),
	(12, 5, 'earn', 139, 262, 401, 'App\\Models\\Order', 12, 'Tích điểm đơn hàng #ORD6A423E26677ED34 (1.399.000đ)', '2027-06-29 17:02:32', NULL, '2026-06-29 17:02:32', '2026-06-29 17:02:32'),
	(13, 5, 'earn', 200, 401, 601, 'App\\Models\\Order', 12, 'Bonus đơn hàng đầu tiên #ORD6A423E26677ED34', '2027-06-29 17:02:32', NULL, '2026-06-29 17:02:32', '2026-06-29 17:02:32'),
	(14, 1, 'burn', 297, 306, 9, 'App\\Models\\Order', 16, 'Đổi 297 điểm = giảm 29.700đ cho đơn #ORD-F5F7444B-516', NULL, NULL, '2026-07-21 23:10:20', '2026-07-21 23:10:20'),
	(15, 1, 'earn', 749, 9, 758, 'App\\Models\\Order', 17, 'Tích điểm đơn hàng #ORD-D531EC9C-925 (7.499.000đ)', '2027-07-21 23:12:47', NULL, '2026-07-21 23:12:47', '2026-07-21 23:12:47'),
	(16, 1, 'earn', 20, 758, 778, 'product_comment', 1, 'Tích điểm viết đánh giá sản phẩm', '2027-07-22 00:29:04', NULL, '2026-07-22 00:29:04', '2026-07-22 00:29:04'),
	(17, 1, 'earn', 20, 778, 798, 'product_comment', 2, 'Tích điểm viết đánh giá sản phẩm', '2027-07-22 00:29:05', NULL, '2026-07-22 00:29:05', '2026-07-22 00:29:05');

-- Dumping structure for table ocean_db.migrations
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=123 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.migrations: ~120 rows (approximately)
DELETE FROM `migrations`;
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(1, '0001_01_01_000000_create_users_table', 1),
	(2, '0001_01_01_000001_create_cache_table', 1),
	(3, '0001_01_01_000002_create_jobs_table', 1),
	(4, '2026_03_14_010505_create_brands_table', 1),
	(5, '2026_03_14_010702_create_categories_table', 1),
	(6, '2026_03_14_010714_create_addresses_table', 1),
	(7, '2026_03_14_010722_create_products_table', 1),
	(8, '2026_03_14_010729_create_product_variants_table', 1),
	(9, '2026_03_14_010739_create_product_images_table', 1),
	(10, '2026_03_14_010747_create_carts_table', 1),
	(11, '2026_03_14_010755_create_cart_items_table', 1),
	(12, '2026_03_14_010805_create_favorites_table', 1),
	(13, '2026_03_14_010812_create_promotions_table', 1),
	(14, '2026_03_14_010819_create_promotion_categories_table', 1),
	(15, '2026_03_14_010826_create_promotion_products_table', 1),
	(16, '2026_03_14_010834_create_promotion_usages_table', 1),
	(17, '2026_03_14_010841_create_orders_table', 1),
	(18, '2026_03_14_010848_create_order_items_table', 1),
	(19, '2026_03_14_010856_create_order_status_histories_table', 1),
	(20, '2026_03_14_010903_create_payments_table', 1),
	(21, '2026_03_14_010911_create_inventory_transactions_table', 1),
	(22, '2026_03_14_010921_create_product_comments_table', 1),
	(23, '2026_03_19_020825_create_admins_table', 1),
	(24, '2026_03_20_011200_create_password_resets_otp_table', 1),
	(25, '2026_03_20_025300_add_google_id_to_users_table', 1),
	(26, '2026_03_20_031800_create_contacts_table', 1),
	(27, '2026_03_23_034912_create_coupons_table', 1),
	(28, '2026_03_23_100000_add_location_codes_to_addresses_table', 1),
	(29, '2026_03_23_194000_create_user_coupons_table', 1),
	(30, '2026_03_25_084408_create_post_categories_table', 1),
	(31, '2026_03_26_105720_create_posts_table', 1),
	(32, '2026_03_27_010000_create_coupon_categories_table', 1),
	(33, '2026_03_27_030000_create_shipping_zones_table', 1),
	(34, '2026_03_28_061940_add_profile_fields_to_admins_table', 1),
	(35, '2026_03_28_075824_fix_orders_promotion_id_foreign_key', 1),
	(36, '2026_03_30_132550_add_facebook_id_to_users_table', 1),
	(37, '2026_03_30_141039_change_avatar_url_column_type_in_users_table', 1),
	(38, '2026_03_31_100207_add_pos_payment_methods_to_orders_table', 1),
	(39, '2026_04_01_000000_add_seller_id_to_orders_table', 1),
	(40, '2026_04_02_072032_create_chat_sessions_table', 1),
	(42, '2026_04_02_170000_add_birthday_points_to_users_table', 1),
	(43, '2026_04_02_170001_create_notifications_table', 1),
	(44, '2026_04_03_140000_add_email_sent_to_orders_table', 1),
	(45, '2026_04_07_094500_add_unique_index_to_order_code', 1),
	(46, '2026_04_07_094501_change_gateway_response_to_json', 1),
	(47, '2026_04_09_013922_create_attendances_table', 1),
	(48, '2026_04_09_175723_add_commenter_type_to_product_comments_table', 1),
	(49, '2026_04_10_172307_add_check_out_image_path_to_attendances_table', 1),
	(50, '2026_04_10_233120_add_status_to_admins_table', 1),
	(51, '2026_04_15_000001_create_flash_sales_table', 1),
	(52, '2026_04_15_000003_recreate_flash_sales_tables', 1),
	(53, '2026_04_15_151500_add_sale_price_columns_to_product_variants_table', 1),
	(54, '2026_04_17_135845_add_wifi_to_attendances_table', 1),
	(55, '2026_04_18_000001_add_image_to_categories_table', 1),
	(56, '2026_05_25_000001_add_affiliate_columns_to_users_table', 1),
	(57, '2026_05_25_000002_create_affiliate_clicks_table', 1),
	(58, '2026_05_25_000003_create_affiliate_conversions_table', 1),
	(59, '2026_05_25_000004_create_affiliate_withdrawals_table', 1),
	(60, '2026_05_28_000001_create_courts_table', 1),
	(61, '2026_05_28_000002_create_court_schedules_table', 1),
	(62, '2026_05_28_000003_create_court_prices_table', 1),
	(63, '2026_05_28_000004_create_court_bookings_table', 1),
	(64, '2026_05_28_000005_create_court_booking_status_histories_table', 1),
	(65, '2026_05_28_000006_create_court_booking_locks_table', 1),
	(66, '2026_05_28_000007_create_court_services_table', 1),
	(67, '2026_05_28_000008_create_court_booking_services_table', 1),
	(68, '2026_05_28_000009_create_court_maintenances_table', 1),
	(69, '2026_05_28_000010_create_court_booking_payments_table', 1),
	(70, '2026_05_28_000011_create_court_booking_extensions_table', 1),
	(71, '2026_05_28_000012_create_court_activity_logs_table', 1),
	(72, '2026_05_29_150000_create_work_locations_table', 1),
	(73, '2026_05_29_150001_upgrade_attendances_for_gps', 1),
	(74, '2026_05_29_160000_create_work_shifts_table', 1),
	(75, '2026_05_29_160001_create_shift_assignments_table', 1),
	(76, '2026_05_29_160002_add_shift_flag_to_attendances', 1),
	(77, '2026_05_30_000001_make_court_booking_user_nullable', 1),
	(78, '2026_05_30_000002_expand_password_resets_otp_hash_column', 1),
	(79, '2026_05_30_195100_add_date_of_birth_to_admins_table', 1),
	(80, '2026_05_31_120000_create_return_requests_table', 1),
	(81, '2026_05_31_120100_expand_order_and_payment_statuses_for_returns', 1),
	(82, '2026_06_05_000001_add_payment_callback_idempotency_columns', 2),
	(83, '2026_06_05_000002_harden_payment_idempotency_state', 2),
	(84, '2026_06_05_000002_harden_payment_idempotency_state copy', 3),
	(85, '2026_06_06_000002_add_images_to_products_comments_table', 3),
	(86, '2026_06_06_092816_create_loyalty_transactions_table', 3),
	(87, '2026_06_06_110000_add_combo_to_flash_sales', 3),
	(88, '2026_06_06_110001_add_combo_to_coupons', 3),
	(89, '2026_06_06_110002_add_combo_discount_to_orders', 3),
	(90, '2026_06_06_120000_build_loyalty_transactions_schema', 3),
	(91, '2026_06_06_120001_create_loyalty_rules_table', 4),
	(92, '2026_06_05_000000_create_tickets_table', 5),
	(93, '2026_06_06_154629_create_recently_viewed_products_table', 6),
	(94, '2026_06_06_154629_create_search_histories_table', 7),
	(95, '2026_06_07_000001_harden_court_booking_phase_one', 7),
	(96, '2026_06_11_140000_create_face_encodings_table', 7),
	(97, '2026_06_11_140001_add_face_fields_to_attendances', 7),
	(98, '2026_06_19_002234_add_ghn_order_code_to_orders_table', 7),
	(99, '2026_06_17_000000_make_user_id_nullable_in_orders_table', 8),
	(100, '2026_06_18_000001_add_loyalty_rules_review_image_social_share', 8),
	(102, '2026_06_20_114034_user_devices', 9),
	(103, '2026_06_21_000001_create_user_bank_accounts_table', 9),
	(104, '2026_06_18_000002_create_wallet_tables', 10),
	(105, '2026_06_29_000001_fix_user_devices_foreign_key', 11),
	(106, '2026_06_20_000001_create_wallets_table', 12),
	(107, '2026_06_20_000002_create_wallet_transactions_table', 12),
	(108, '2026_06_20_000003_add_wallet_discount_to_orders_table', 12),
	(109, '2026_06_20_000004_create_wallet_deposits_table', 12),
	(110, '2026_06_20_000005_create_wallet_withdrawals_table', 12),
	(111, '2026_06_22_000001_add_default_payment_method_to_users', 12),
	(112, '2026_06_25_000001_add_email_to_orders_table', 12),
	(113, '2026_06_29_000001_add_ghn_v2_fields', 12),
	(114, '2026_06_29_000002_add_guest_location_codes_to_orders_table', 12),
	(115, '2026_07_13_174045_add_email_to_orders_table', 12),
	(116, '2026_07_18_235301_add_composite_indexes_to_tables', 13),
	(117, '2026_07_21_001654_create_rewards_table', 14),
	(118, '2026_04_02_072033_create_chat_messages_table', 15),
	(119, '2026_07_21_001655_create_user_rewards_table', 16),
	(120, '2026_07_21_164543_optimize_court_bookings_table', 17),
	(121, '2026_07_23_090000_add_unique_index_to_attendances', 17),
	(122, '2026_07_24_004001_update_category_id_on_products_table', 18);

-- Dumping structure for table ocean_db.notifications
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.notifications: ~87 rows (approximately)
DELETE FROM `notifications`;
INSERT INTO `notifications` (`id`, `type`, `notifiable_type`, `notifiable_id`, `data`, `read_at`, `created_at`, `updated_at`) VALUES
	('03c917d2-5682-43c0-b2ec-0395b790cd0e', 'court_booking', 'App\\Models\\User', 8, '{"title":"Đặt sân thành công","message":"Booking BK-20260613-FSJL tại Sân 1 lúc 14\\/06\\/2026 05:00:00-06:00:00 đang chờ xác nhận.","type":"court_booking","event":"CourtBookingCreated","booking_id":67,"booking_code":"BK-20260613-FSJL","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-14","start_time":"05:00:00","end_time":"06:00:00","status":"pending","payment_status":"unpaid","total_amount":96000}', '2026-06-18 23:42:12', '2026-06-13 23:54:22', '2026-06-18 23:42:12'),
	('0a26048e-28b1-4f1a-8f88-6970add6a531', 'court_booking', 'App\\Models\\User', 8, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260614-RSIQ tại Sân 1 lúc 14\\/06\\/2026 07:00:00-07:30:00 đã được cập nhật sang trạng thái đã xác nhận.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":70,"booking_code":"BK-20260614-RSIQ","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-14","start_time":"07:00:00","end_time":"07:30:00","status":"confirmed","payment_status":"unpaid","total_amount":48000,"old_status":"pending","new_status":"confirmed","note":"Admin xac nhan booking"}', '2026-06-18 23:42:12', '2026-06-14 00:22:21', '2026-06-18 23:42:12'),
	('0a9e93d9-8228-4a12-821e-27b2f5f52106', 'App\\Notifications\\OrderCreatedNotification', 'App\\Models\\User', 5, '{"title":"\\u0110\\u1eb7t h\\u00e0ng th\\u00e0nh c\\u00f4ng","message":"\\u0110\\u01a1n h\\u00e0ng ORD6A32DB552B14847 c\\u1ee7a b\\u1ea1n \\u0111\\u00e3 \\u0111\\u01b0\\u1ee3c ghi nh\\u1eadn.","order_code":"ORD6A32DB552B14847","grand_total":"2999000.00","type":"order_created"}', NULL, '2026-06-18 00:39:08', '2026-06-18 00:39:08'),
	('0b65c7d7-0359-4c93-ab7a-11f4b46d7179', 'App\\Notifications\\OrderCreatedNotification', 'App\\Models\\User', 1, '{"title":"\\u0110\\u1eb7t h\\u00e0ng th\\u00e0nh c\\u00f4ng","message":"\\u0110\\u01a1n h\\u00e0ng ORD-D531EC9C-925 c\\u1ee7a b\\u1ea1n \\u0111\\u00e3 \\u0111\\u01b0\\u1ee3c ghi nh\\u1eadn.","order_code":"ORD-D531EC9C-925","grand_total":"7499000.00","type":"order_created"}', '2026-07-21 23:18:44', '2026-07-21 23:14:09', '2026-07-21 23:18:44'),
	('0d1a27c8-b689-48f7-8453-2111e99fccd2', 'App\\Notifications\\AbandonedCartNotification', 'App\\Models\\User', 8, '{"type":"abandoned_cart","title":"\\ud83d\\uded2 Gi\\u1ecf h\\u00e0ng \\u0111ang ch\\u1edd b\\u1ea1n!","message":"B\\u1ea1n c\\u00f3 1 s\\u1ea3n ph\\u1ea9m trong gi\\u1ecf h\\u00e0ng. Ch\\u00fang t\\u00f4i \\u0111\\u00e3 t\\u1eb7ng b\\u1ea1n 30 \\u0111i\\u1ec3m th\\u01b0\\u1edfng!","item_count":1,"points_awarded":30}', '2026-07-20 20:25:26', '2026-06-23 13:51:06', '2026-07-20 20:25:26'),
	('1369edcd-9281-4eec-8e2f-e5c254e560eb', 'App\\Notifications\\OrderCreatedNotification', 'App\\Models\\User', 5, '{"title":"\\u0110\\u1eb7t h\\u00e0ng th\\u00e0nh c\\u00f4ng","message":"\\u0110\\u01a1n h\\u00e0ng ORD6A317C05681E216 c\\u1ee7a b\\u1ea1n \\u0111\\u00e3 \\u0111\\u01b0\\u1ee3c ghi nh\\u1eadn.","order_code":"ORD6A317C05681E216","grand_total":"4500000.00","type":"order_created"}', '2026-06-18 00:10:29', '2026-06-17 00:00:08', '2026-06-18 00:10:29'),
	('16cef3f9-7441-434c-81d8-bb18c5ef7f4d', 'court_booking', 'App\\Models\\User', 4, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260610-O66B tại Sân 3 lúc 10\\/06\\/2026 07:00:00-09:00:00 đã được cập nhật sang trạng thái vắng mặt.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":53,"booking_code":"BK-20260610-O66B","court_id":3,"court_name":"Sân 3","booking_date":"2026-06-10","start_time":"07:00:00","end_time":"09:00:00","status":"no_show","payment_status":"unpaid","total_amount":160000,"old_status":"pending","new_status":"no_show","note":"Auto no-show after 15 minutes"}', NULL, '2026-06-11 18:15:04', '2026-06-11 18:15:04'),
	('17a19bde-a49f-47b6-8be2-bd953af5cab1', 'court_booking', 'App\\Models\\User', 3, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260611-0WZA tại Sân 1 lúc 11\\/06\\/2026 07:00:00-08:00:00 đã được cập nhật sang trạng thái vắng mặt.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":55,"booking_code":"BK-20260611-0WZA","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-11","start_time":"07:00:00","end_time":"08:00:00","status":"no_show","payment_status":"unpaid","total_amount":64000,"old_status":"confirmed","new_status":"no_show","note":"Auto no-show after 15 minutes"}', NULL, '2026-06-11 18:15:04', '2026-06-11 18:15:04'),
	('19d70884-22cf-4d20-af83-7a6fd1172cf5', 'App\\Notifications\\OrderCreatedNotification', 'App\\Models\\User', 8, '{"title":"\\u0110\\u1eb7t h\\u00e0ng th\\u00e0nh c\\u00f4ng","message":"\\u0110\\u01a1n h\\u00e0ng ORD6A342B0AEF27D72 c\\u1ee7a b\\u1ea1n \\u0111\\u00e3 \\u0111\\u01b0\\u1ee3c ghi nh\\u1eadn.","order_code":"ORD6A342B0AEF27D72","grand_total":"2999000.00","type":"order_created"}', '2026-07-20 20:25:27', '2026-06-19 00:31:08', '2026-07-20 20:25:27'),
	('1cdd7dc3-fc7f-48ff-bd90-d1f02d057c8e', 'court_booking', 'App\\Models\\User', 8, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260614-RSIQ tại Sân 1 lúc 14\\/06\\/2026 07:00:00-07:30:00 đã được cập nhật sang trạng thái vắng mặt.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":70,"booking_code":"BK-20260614-RSIQ","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-14","start_time":"07:00:00","end_time":"07:30:00","status":"no_show","payment_status":"unpaid","total_amount":48000,"old_status":"confirmed","new_status":"no_show","note":"Auto no-show after 15 minutes"}', '2026-06-18 23:42:12', '2026-06-14 12:35:04', '2026-06-18 23:42:12'),
	('205d9815-a223-4f3b-b10f-2c17ff0daf9d', 'court_booking', 'App\\Models\\User', 5, '{"title":"Đặt sân thành công","message":"Booking BK-20260616-1TFC tại Sân 2 lúc 16\\/06\\/2026 18:30:00-19:30:00 đang chờ xác nhận.","type":"court_booking","event":"CourtBookingCreated","booking_id":72,"booking_code":"BK-20260616-1TFC","court_id":2,"court_name":"Sân 2","booking_date":"2026-06-16","start_time":"18:30:00","end_time":"19:30:00","status":"pending","payment_status":"unpaid","total_amount":114000}', '2026-06-16 18:26:37', '2026-06-16 18:21:35', '2026-06-16 18:26:37'),
	('2127de99-0c6e-4a6e-9854-6a09b75adb84', 'court_booking', 'App\\Models\\User', 8, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260614-G6GB tại Sân 1 lúc 14\\/06\\/2026 06:30:00-07:00:00 đã được cập nhật sang trạng thái đã xác nhận.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":69,"booking_code":"BK-20260614-G6GB","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-14","start_time":"06:30:00","end_time":"07:00:00","status":"confirmed","payment_status":"unpaid","total_amount":48000,"old_status":"pending","new_status":"confirmed","note":"Admin xac nhan booking"}', '2026-06-18 23:42:12', '2026-06-14 00:20:30', '2026-06-18 23:42:12'),
	('2a563fba-f70d-47a1-aebb-3d94bdf1b621', 'App\\Notifications\\AbandonedCartNotification', 'App\\Models\\User', 8, '{"type":"abandoned_cart","title":"\\ud83d\\uded2 Gi\\u1ecf h\\u00e0ng \\u0111ang ch\\u1edd b\\u1ea1n!","message":"B\\u1ea1n c\\u00f3 1 s\\u1ea3n ph\\u1ea9m trong gi\\u1ecf h\\u00e0ng. Ch\\u00fang t\\u00f4i \\u0111\\u00e3 t\\u1eb7ng b\\u1ea1n 50 \\u0111i\\u1ec3m th\\u01b0\\u1edfng!","item_count":1,"points_awarded":50}', '2026-06-11 15:53:08', '2026-06-08 08:52:12', '2026-06-11 15:53:08'),
	('2b3fda94-c0db-4386-a059-392abb314e26', 'App\\Notifications\\OrderPlacedNotification', 'App\\Models\\User', 1, '{"type":"order","title":"\\u0110\\u1eb7t h\\u00e0ng th\\u00e0nh c\\u00f4ng!","message":"\\u0110\\u01a1n h\\u00e0ng ORD6A39155C6B15016 \\u0111\\u00e3 \\u0111\\u01b0\\u1ee3c \\u0111\\u1eb7t th\\u00e0nh c\\u00f4ng v\\u00e0 \\u0111ang ch\\u1edd x\\u00e1c nh\\u1eadn.","order_id":10,"order_code":"ORD6A39155C6B15016"}', '2026-06-29 17:16:46', '2026-06-22 17:58:36', '2026-06-29 17:16:46'),
	('2d32d9f3-23b6-409f-9e32-71ce58e643b7', 'App\\Notifications\\OrderPlacedNotification', 'App\\Models\\User', 5, '{"type":"order","title":"\\u0110\\u1eb7t h\\u00e0ng th\\u00e0nh c\\u00f4ng!","message":"\\u0110\\u01a1n h\\u00e0ng ORD6A421ACF8E8F623 \\u0111\\u00e3 \\u0111\\u01b0\\u1ee3c \\u0111\\u1eb7t th\\u00e0nh c\\u00f4ng v\\u00e0 \\u0111ang ch\\u1edd x\\u00e1c nh\\u1eadn.","order_id":11,"order_code":"ORD6A421ACF8E8F623"}', NULL, '2026-06-29 14:12:15', '2026-06-29 14:12:15'),
	('3060397d-1660-4996-82e3-cec268d1895d', 'court_booking', 'App\\Models\\User', 8, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260611-2BXK tại Sân 2 lúc 11\\/06\\/2026 19:00:00-20:00:00 đã được cập nhật sang trạng thái đã check-in.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":65,"booking_code":"BK-20260611-2BXK","court_id":2,"court_name":"Sân 2","booking_date":"2026-06-11","start_time":"19:00:00","end_time":"20:00:00","status":"checked_in","payment_status":"unpaid","total_amount":104000,"old_status":"confirmed","new_status":"checked_in","note":"Check-in nhan san"}', '2026-06-18 23:42:12', '2026-06-11 18:57:05', '2026-06-18 23:42:12'),
	('30b7cc39-8a2f-4b12-9a84-ecd29dbf0d04', 'court_booking', 'App\\Models\\User', 8, '{"title":"Đặt sân thành công","message":"Booking BK-20260614-OODH tại Sân 1 lúc 14\\/06\\/2026 07:30:00-08:00:00 đang chờ xác nhận.","type":"court_booking","event":"CourtBookingCreated","booking_id":71,"booking_code":"BK-20260614-OODH","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-14","start_time":"07:30:00","end_time":"08:00:00","status":"pending","payment_status":"unpaid","total_amount":48000}', '2026-06-18 23:42:12', '2026-06-14 00:22:37', '2026-06-18 23:42:12'),
	('34c78658-6db1-46c8-b605-0bce6ddd8f1e', 'App\\Notifications\\OrderCreatedNotification', 'App\\Models\\User', 1, '{"title":"\\u0110\\u1eb7t h\\u00e0ng th\\u00e0nh c\\u00f4ng","message":"\\u0110\\u01a1n h\\u00e0ng ORD6A42497F20B7729 c\\u1ee7a b\\u1ea1n \\u0111\\u00e3 \\u0111\\u01b0\\u1ee3c ghi nh\\u1eadn.","order_code":"ORD6A42497F20B7729","grand_total":"2695000.00","type":"order_created"}', '2026-07-21 23:11:41', '2026-06-29 17:33:10', '2026-07-21 23:11:41'),
	('36ce04b1-eb25-4e45-bfc0-e86b700e7024', 'court_booking', 'App\\Models\\User', 1, '{"title":"Đặt sân thành công","message":"Booking BK-20260622-FCFM tại Sân 1 lúc 22\\/06\\/2026 15:00:00-16:00:00 đang chờ xác nhận.","type":"court_booking","event":"CourtBookingCreated","booking_id":74,"booking_code":"BK-20260622-FCFM","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-22","start_time":"15:00:00","end_time":"16:00:00","status":"pending","payment_status":"unpaid","total_amount":90000}', '2026-06-29 17:16:46', '2026-06-22 14:38:52', '2026-06-29 17:16:46'),
	('37bb1249-4734-4c68-8216-699605e6baf3', 'court_booking', 'App\\Models\\User', 5, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260629-NZQD tại Sân 1 lúc 29\\/06\\/2026 17:00:00-17:30:00 đã được cập nhật sang trạng thái đã xác nhận.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":76,"booking_code":"BK-20260629-NZQD","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-29","start_time":"17:00:00","end_time":"17:30:00","status":"confirmed","payment_status":"unpaid","total_amount":52000,"old_status":"pending","new_status":"confirmed","note":"Admin xac nhan booking"}', NULL, '2026-06-29 16:47:07', '2026-06-29 16:47:07'),
	('4080009c-e7a6-4b4f-9dbe-7f58bf8fd9c5', 'App\\Notifications\\AbandonedCartNotification', 'App\\Models\\User', 1, '{"type":"abandoned_cart","title":"\\ud83d\\uded2 Gi\\u1ecf h\\u00e0ng \\u0111ang ch\\u1edd b\\u1ea1n!","message":"B\\u1ea1n c\\u00f3 1 s\\u1ea3n ph\\u1ea9m trong gi\\u1ecf h\\u00e0ng. Ch\\u00fang t\\u00f4i \\u0111\\u00e3 t\\u1eb7ng b\\u1ea1n 30 \\u0111i\\u1ec3m th\\u01b0\\u1edfng!","item_count":1,"points_awarded":30}', '2026-07-21 23:11:41', '2026-07-16 11:00:12', '2026-07-21 23:11:41'),
	('42160812-50cd-487a-a3dd-c873a0dd5ab5', 'court_booking', 'App\\Models\\User', 5, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260616-1TFC tại Sân 2 lúc 16\\/06\\/2026 18:30:00-19:30:00 đã được cập nhật sang trạng thái đã check-in.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":72,"booking_code":"BK-20260616-1TFC","court_id":2,"court_name":"Sân 2","booking_date":"2026-06-16","start_time":"18:30:00","end_time":"19:30:00","status":"checked_in","payment_status":"unpaid","total_amount":114000,"old_status":"confirmed","new_status":"checked_in","note":"Check-in nhan san"}', '2026-06-18 00:10:30', '2026-06-16 18:31:52', '2026-06-18 00:10:30'),
	('42af4c42-cb54-458f-9d1b-7d6449a9934c', 'court_booking', 'App\\Models\\User', 8, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260613-FSJL tại Sân 1 lúc 14\\/06\\/2026 05:00:00-06:00:00 đã được cập nhật sang trạng thái đã xác nhận.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":67,"booking_code":"BK-20260613-FSJL","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-14","start_time":"05:00:00","end_time":"06:00:00","status":"confirmed","payment_status":"unpaid","total_amount":96000,"old_status":"pending","new_status":"confirmed","note":"Admin xac nhan booking"}', '2026-06-18 23:42:12', '2026-06-14 00:08:13', '2026-06-18 23:42:12'),
	('4a438adf-2d3e-4ea8-a2d0-6ffec63c10b3', 'court_booking', 'App\\Models\\User', 8, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260611-DGIB tại Sân 1 lúc 11\\/06\\/2026 21:00:00-22:00:00 đã được cập nhật sang trạng thái đã check-in.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":66,"booking_code":"BK-20260611-DGIB","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-11","start_time":"21:00:00","end_time":"22:00:00","status":"checked_in","payment_status":"unpaid","total_amount":104000,"old_status":"confirmed","new_status":"checked_in","note":"Check-in nhan san"}', '2026-06-11 21:33:06', '2026-06-11 21:06:12', '2026-06-11 21:33:06'),
	('4c327174-858d-4dc4-a553-f025d35b3a47', 'court_booking', 'App\\Models\\User', 4, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260611-IAYD tại Sân 2 lúc 11\\/06\\/2026 06:00:00-07:00:00 đã được cập nhật sang trạng thái vắng mặt.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":56,"booking_code":"BK-20260611-IAYD","court_id":2,"court_name":"Sân 2","booking_date":"2026-06-11","start_time":"06:00:00","end_time":"07:00:00","status":"no_show","payment_status":"unpaid","total_amount":64000,"old_status":"pending","new_status":"no_show","note":"Auto no-show after 15 minutes"}', NULL, '2026-06-11 18:15:04', '2026-06-11 18:15:04'),
	('4d69932d-61b3-4151-8424-fc38c62af391', 'court_booking', 'App\\Models\\User', 8, '{"title":"Đặt sân thành công","message":"Booking BK-20260611-DGIB tại Sân 1 lúc 11\\/06\\/2026 21:00:00-22:00:00 đang chờ xác nhận.","type":"court_booking","event":"CourtBookingCreated","booking_id":66,"booking_code":"BK-20260611-DGIB","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-11","start_time":"21:00:00","end_time":"22:00:00","status":"pending","payment_status":"unpaid","total_amount":104000}', '2026-06-18 23:42:12', '2026-06-11 20:05:10', '2026-06-18 23:42:12'),
	('4feefe4c-80ec-4395-a973-3c99192e445a', 'App\\Notifications\\AbandonedCartNotification', 'App\\Models\\User', 9, '{"type":"abandoned_cart","title":"\\ud83d\\uded2 Gi\\u1ecf h\\u00e0ng \\u0111ang ch\\u1edd b\\u1ea1n!","message":"B\\u1ea1n c\\u00f3 3 s\\u1ea3n ph\\u1ea9m trong gi\\u1ecf h\\u00e0ng. Ch\\u00fang t\\u00f4i \\u0111\\u00e3 t\\u1eb7ng b\\u1ea1n 30 \\u0111i\\u1ec3m th\\u01b0\\u1edfng!","item_count":3,"points_awarded":30}', '2026-07-16 15:14:23', '2026-07-16 11:00:10', '2026-07-16 15:14:23'),
	('50913745-fc0b-4617-b0e3-05065844e518', 'court_booking', 'App\\Models\\User', 5, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260616-1TFC tại Sân 2 lúc 16\\/06\\/2026 18:30:00-19:30:00 đã được cập nhật sang trạng thái đã xác nhận.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":72,"booking_code":"BK-20260616-1TFC","court_id":2,"court_name":"Sân 2","booking_date":"2026-06-16","start_time":"18:30:00","end_time":"19:30:00","status":"confirmed","payment_status":"unpaid","total_amount":114000,"old_status":"pending","new_status":"confirmed","note":"Admin xac nhan booking"}', '2026-06-18 00:10:30', '2026-06-16 18:31:47', '2026-06-18 00:10:30'),
	('55ff857f-6c0b-4e91-81e4-66aeaef3f5f2', 'court_booking', 'App\\Models\\User', 7, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260610-1ENP tại Sân 2 lúc 10\\/06\\/2026 20:00:00-21:00:00 đã được cập nhật sang trạng thái vắng mặt.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":52,"booking_code":"BK-20260610-1ENP","court_id":2,"court_name":"Sân 2","booking_date":"2026-06-10","start_time":"20:00:00","end_time":"21:00:00","status":"no_show","payment_status":"unpaid","total_amount":104000,"old_status":"pending","new_status":"no_show","note":"Auto no-show after 15 minutes"}', NULL, '2026-06-11 18:15:04', '2026-06-11 18:15:04'),
	('58014688-b1c4-414b-be88-2463124b7b0f', 'App\\Notifications\\OrderCompletedNotification', 'App\\Models\\User', 5, '{"type":"order_completed","title":"\\u0110\\u01a1n h\\u00e0ng ho\\u00e0n t\\u1ea5t!","message":"\\u0110\\u01a1n h\\u00e0ng ORD6A423E26677ED34 c\\u1ee7a b\\u1ea1n \\u0111\\u00e3 ho\\u00e0n t\\u1ea5t. C\\u1ea3m \\u01a1n b\\u1ea1n \\u0111\\u00e3 mua s\\u1eafm!","order_id":12,"order_code":"ORD6A423E26677ED34"}', NULL, '2026-06-29 17:02:29', '2026-06-29 17:02:29'),
	('59c80f89-5a95-4d26-85f2-c411581c4a8b', 'App\\Notifications\\OrderCreatedNotification', 'App\\Models\\User', 1, '{"title":"\\u0110\\u1eb7t h\\u00e0ng th\\u00e0nh c\\u00f4ng","message":"\\u0110\\u01a1n h\\u00e0ng POS6A63A3B39E17767 c\\u1ee7a b\\u1ea1n \\u0111\\u00e3 \\u0111\\u01b0\\u1ee3c ghi nh\\u1eadn.","order_code":"POS6A63A3B39E17767","grand_total":"765600.00","type":"order_created"}', NULL, '2026-07-25 00:43:10', '2026-07-25 00:43:10'),
	('59c8bd41-d950-4202-a094-0836619bf2cd', 'App\\Notifications\\AbandonedCartNotification', 'App\\Models\\User', 10, '{"type":"abandoned_cart","title":"\\ud83d\\uded2 Gi\\u1ecf h\\u00e0ng \\u0111ang ch\\u1edd b\\u1ea1n!","message":"B\\u1ea1n c\\u00f3 1 s\\u1ea3n ph\\u1ea9m trong gi\\u1ecf h\\u00e0ng. Ch\\u00fang t\\u00f4i \\u0111\\u00e3 t\\u1eb7ng b\\u1ea1n 30 \\u0111i\\u1ec3m th\\u01b0\\u1edfng!","item_count":1,"points_awarded":30}', NULL, '2026-07-23 10:00:11', '2026-07-23 10:00:11'),
	('5afeba0d-b178-44a8-bd57-c7d17b3c2a4a', 'App\\Notifications\\OrderCreatedNotification', 'App\\Models\\User', 8, '{"title":"\\u0110\\u1eb7t h\\u00e0ng th\\u00e0nh c\\u00f4ng","message":"\\u0110\\u01a1n h\\u00e0ng ORD6A2ED30FA2EA237 c\\u1ee7a b\\u1ea1n \\u0111\\u00e3 \\u0111\\u01b0\\u1ee3c ghi nh\\u1eadn.","order_code":"ORD6A2ED30FA2EA237","grand_total":"539000.00","type":"order_created"}', '2026-06-14 23:16:33', '2026-06-14 23:14:08', '2026-06-14 23:16:33'),
	('5b3add57-dadd-4d84-92f1-45123a89505f', 'court_booking', 'App\\Models\\User', 5, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260616-UTIJ tại Sân 2 lúc 16\\/06\\/2026 19:30:00-20:00:00 đã được cập nhật sang trạng thái đã xác nhận.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":73,"booking_code":"BK-20260616-UTIJ","court_id":2,"court_name":"Sân 2","booking_date":"2026-06-16","start_time":"19:30:00","end_time":"20:00:00","status":"confirmed","payment_status":"unpaid","total_amount":76000,"old_status":"pending","new_status":"confirmed","note":"Admin xac nhan booking"}', '2026-06-18 00:10:31', '2026-06-16 18:26:48', '2026-06-18 00:10:31'),
	('5c104f65-8f5b-4309-9aee-cf6776bf725b', 'court_booking', 'App\\Models\\User', 3, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260612-Y56P tại Sân 3 lúc 12\\/06\\/2026 18:00:00-19:00:00 đã được cập nhật sang trạng thái vắng mặt.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":60,"booking_code":"BK-20260612-Y56P","court_id":3,"court_name":"Sân 3","booking_date":"2026-06-12","start_time":"18:00:00","end_time":"19:00:00","status":"no_show","payment_status":"unpaid","total_amount":195000,"old_status":"pending","new_status":"no_show","note":"Auto no-show after 15 minutes"}', NULL, '2026-06-12 18:15:04', '2026-06-12 18:15:04'),
	('5cdfe5b7-dab6-4092-963e-8283d6ffb346', 'App\\Notifications\\OrderCreatedNotification', 'App\\Models\\User', 5, '{"title":"\\u0110\\u1eb7t h\\u00e0ng th\\u00e0nh c\\u00f4ng","message":"\\u0110\\u01a1n h\\u00e0ng ORD6A421ACF8E8F623 c\\u1ee7a b\\u1ea1n \\u0111\\u00e3 \\u0111\\u01b0\\u1ee3c ghi nh\\u1eadn.","order_code":"ORD6A421ACF8E8F623","grand_total":"738000.00","type":"order_created"}', NULL, '2026-06-29 14:14:07', '2026-06-29 14:14:07'),
	('5f4386ea-67dc-490e-a936-b5e3fdc08922', 'App\\Notifications\\OrderCreatedNotification', 'App\\Models\\User', 9, '{"title":"\\u0110\\u1eb7t h\\u00e0ng th\\u00e0nh c\\u00f4ng","message":"\\u0110\\u01a1n h\\u00e0ng ORD6A58D80083B6553 c\\u1ee7a b\\u1ea1n \\u0111\\u00e3 \\u0111\\u01b0\\u1ee3c ghi nh\\u1eadn.","order_code":"ORD6A58D80083B6553","grand_total":"1399000.00","type":"order_created"}', NULL, '2026-07-16 20:11:07', '2026-07-16 20:11:07'),
	('5f53608f-3831-4eed-b61a-ba3d38131fc3', 'court_booking', 'App\\Models\\User', 8, '{"title":"Đặt sân thành công","message":"Booking BK-20260614-EVQU tại Sân 1 lúc 14\\/06\\/2026 06:00:00-06:30:00 đang chờ xác nhận.","type":"court_booking","event":"CourtBookingCreated","booking_id":68,"booking_code":"BK-20260614-EVQU","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-14","start_time":"06:00:00","end_time":"06:30:00","status":"pending","payment_status":"unpaid","total_amount":48000}', '2026-06-18 23:42:12', '2026-06-14 00:19:53', '2026-06-18 23:42:12'),
	('65a092f7-b4f1-489f-aecd-7b1e129255bb', 'App\\Notifications\\AbandonedCartNotification', 'App\\Models\\User', 8, '{"type":"abandoned_cart","title":"\\ud83d\\uded2 Gi\\u1ecf h\\u00e0ng \\u0111ang ch\\u1edd b\\u1ea1n!","message":"B\\u1ea1n c\\u00f3 2 s\\u1ea3n ph\\u1ea9m trong gi\\u1ecf h\\u00e0ng. Ch\\u00fang t\\u00f4i \\u0111\\u00e3 t\\u1eb7ng b\\u1ea1n 30 \\u0111i\\u1ec3m th\\u01b0\\u1edfng!","item_count":2,"points_awarded":30}', '2026-07-20 20:25:24', '2026-06-26 08:08:09', '2026-07-20 20:25:24'),
	('66fabaf0-916d-4a58-9276-103f8bafceb7', 'court_booking', 'App\\Models\\User', 8, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260614-G6GB tại Sân 1 lúc 14\\/06\\/2026 06:30:00-07:00:00 đã được cập nhật sang trạng thái vắng mặt.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":69,"booking_code":"BK-20260614-G6GB","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-14","start_time":"06:30:00","end_time":"07:00:00","status":"no_show","payment_status":"unpaid","total_amount":48000,"old_status":"confirmed","new_status":"no_show","note":"Auto no-show after 15 minutes"}', '2026-06-18 23:42:12', '2026-06-14 12:35:04', '2026-06-18 23:42:12'),
	('6ec11a7c-3f7c-4d6a-be98-325b3a2688fe', 'court_booking', 'App\\Models\\User', 5, '{"title":"Đặt sân thành công","message":"Booking BK-20260629-NZQD tại Sân 1 lúc 29\\/06\\/2026 17:00:00-17:30:00 đang chờ xác nhận.","type":"court_booking","event":"CourtBookingCreated","booking_id":76,"booking_code":"BK-20260629-NZQD","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-29","start_time":"17:00:00","end_time":"17:30:00","status":"pending","payment_status":"unpaid","total_amount":52000}', NULL, '2026-06-29 16:46:41', '2026-06-29 16:46:41'),
	('7198c7be-8cc6-46b1-87cd-548ce885bbab', 'App\\Notifications\\AbandonedCartNotification', 'App\\Models\\User', 9, '{"type":"abandoned_cart","title":"\\ud83d\\uded2 Gi\\u1ecf h\\u00e0ng \\u0111ang ch\\u1edd b\\u1ea1n!","message":"B\\u1ea1n c\\u00f3 1 s\\u1ea3n ph\\u1ea9m trong gi\\u1ecf h\\u00e0ng. Ch\\u00fang t\\u00f4i \\u0111\\u00e3 t\\u1eb7ng b\\u1ea1n 30 \\u0111i\\u1ec3m th\\u01b0\\u1edfng!","item_count":1,"points_awarded":30}', '2026-07-16 15:14:27', '2026-06-23 08:26:07', '2026-07-16 15:14:27'),
	('739b0f15-4b3c-4ed4-8b6f-0e9d385328a8', 'App\\Notifications\\OrderCreatedNotification', 'App\\Models\\User', 9, '{"title":"\\u0110\\u1eb7t h\\u00e0ng th\\u00e0nh c\\u00f4ng","message":"\\u0110\\u01a1n h\\u00e0ng ORD6A58A23D80FE856 c\\u1ee7a b\\u1ea1n \\u0111\\u00e3 \\u0111\\u01b0\\u1ee3c ghi nh\\u1eadn.","order_code":"ORD6A58A23D80FE856","grand_total":"4536000.00","type":"order_created"}', NULL, '2026-07-16 16:21:08', '2026-07-16 16:21:08'),
	('761bc12b-a301-4f52-816d-f8bc0efffefa', 'court_booking', 'App\\Models\\User', 1, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260622-FCFM tại Sân 1 lúc 22\\/06\\/2026 15:00:00-16:00:00 đã được cập nhật sang trạng thái đã xác nhận.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":74,"booking_code":"BK-20260622-FCFM","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-22","start_time":"15:00:00","end_time":"16:00:00","status":"confirmed","payment_status":"unpaid","total_amount":90000,"old_status":"pending","new_status":"confirmed","note":"Admin xac nhan booking"}', '2026-06-29 17:16:46', '2026-06-22 14:41:20', '2026-06-29 17:16:46'),
	('76a089c2-fc65-4eb1-bb97-827d08aa79e6', 'App\\Notifications\\OrderCreatedNotification', 'App\\Models\\User', 1, '{"title":"\\u0110\\u1eb7t h\\u00e0ng th\\u00e0nh c\\u00f4ng","message":"\\u0110\\u01a1n h\\u00e0ng ORD6A38E82914D4274 c\\u1ee7a b\\u1ea1n \\u0111\\u00e3 \\u0111\\u01b0\\u1ee3c ghi nh\\u1eadn.","order_code":"ORD6A38E82914D4274","grand_total":"539000.00","type":"order_created"}', '2026-06-29 17:16:46', '2026-06-22 14:47:09', '2026-06-29 17:16:46'),
	('771df7f7-ed9f-4997-9675-1e7b3b1eeeb9', 'court_booking', 'App\\Models\\User', 1, '{"title":"Đặt sân thành công","message":"Booking BK-20260622-2UGH tại Sân 1 lúc 22\\/06\\/2026 15:30:00-16:30:00 đang chờ xác nhận.","type":"court_booking","event":"CourtBookingCreated","booking_id":75,"booking_code":"BK-20260622-2UGH","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-22","start_time":"15:30:00","end_time":"16:30:00","status":"pending","payment_status":"unpaid","total_amount":90000}', '2026-06-29 17:16:46', '2026-06-22 15:16:28', '2026-06-29 17:16:46'),
	('7965c2d2-0757-4008-bb11-f79feb156d91', 'App\\Notifications\\OrderCreatedNotification', 'App\\Models\\User', 8, '{"title":"\\u0110\\u1eb7t h\\u00e0ng th\\u00e0nh c\\u00f4ng","message":"\\u0110\\u01a1n h\\u00e0ng ORD6A2ED176B893699 c\\u1ee7a b\\u1ea1n \\u0111\\u00e3 \\u0111\\u01b0\\u1ee3c ghi nh\\u1eadn.","order_code":"ORD6A2ED176B893699","grand_total":"1798000.00","type":"order_created"}', '2026-06-18 23:42:12', '2026-06-14 23:08:08', '2026-06-18 23:42:12'),
	('7fb24de3-2249-401d-8316-f8075321362e', 'court_booking', 'App\\Models\\User', 8, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260614-EVQU tại Sân 1 lúc 14\\/06\\/2026 06:00:00-06:30:00 đã được cập nhật sang trạng thái đã xác nhận.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":68,"booking_code":"BK-20260614-EVQU","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-14","start_time":"06:00:00","end_time":"06:30:00","status":"confirmed","payment_status":"unpaid","total_amount":48000,"old_status":"pending","new_status":"confirmed","note":"Admin xac nhan booking"}', '2026-06-18 23:42:12', '2026-06-14 00:20:10', '2026-06-18 23:42:12'),
	('83e27083-0483-4ca0-8c71-35db6da0ec31', 'App\\Notifications\\AbandonedCartNotification', 'App\\Models\\User', 9, '{"type":"abandoned_cart","title":"\\ud83d\\uded2 Gi\\u1ecf h\\u00e0ng \\u0111ang ch\\u1edd b\\u1ea1n!","message":"B\\u1ea1n c\\u00f3 1 s\\u1ea3n ph\\u1ea9m trong gi\\u1ecf h\\u00e0ng. Ch\\u00fang t\\u00f4i \\u0111\\u00e3 t\\u1eb7ng b\\u1ea1n 50 \\u0111i\\u1ec3m th\\u01b0\\u1edfng!","item_count":1,"points_awarded":50}', '2026-07-16 15:14:32', '2026-06-13 21:49:08', '2026-07-16 15:14:32'),
	('85115ec7-23c6-4a76-878a-78b2fe584e82', 'court_booking', 'App\\Models\\User', 3, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260612-QQ0D tại Sân 2 lúc 12\\/06\\/2026 07:00:00-08:00:00 đã được cập nhật sang trạng thái vắng mặt.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":59,"booking_code":"BK-20260612-QQ0D","court_id":2,"court_name":"Sân 2","booking_date":"2026-06-12","start_time":"07:00:00","end_time":"08:00:00","status":"no_show","payment_status":"unpaid","total_amount":64000,"old_status":"pending","new_status":"no_show","note":"Auto no-show after 15 minutes"}', NULL, '2026-06-12 08:05:34', '2026-06-12 08:05:34'),
	('8584bd8b-16e2-4123-8f9a-83096a94c8ae', 'App\\Notifications\\AbandonedCartNotification', 'App\\Models\\User', 8, '{"type":"abandoned_cart","title":"\\ud83d\\uded2 Gi\\u1ecf h\\u00e0ng \\u0111ang ch\\u1edd b\\u1ea1n!","message":"B\\u1ea1n c\\u00f3 1 s\\u1ea3n ph\\u1ea9m trong gi\\u1ecf h\\u00e0ng. Ch\\u00fang t\\u00f4i \\u0111\\u00e3 t\\u1eb7ng b\\u1ea1n 50 \\u0111i\\u1ec3m th\\u01b0\\u1edfng!","item_count":1,"points_awarded":50}', '2026-07-20 20:25:26', '2026-06-20 17:06:11', '2026-07-20 20:25:26'),
	('88308708-dda4-4a87-8c1a-99777bba48c6', 'court_booking', 'App\\Models\\User', 8, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260611-DGIB tại Sân 1 lúc 11\\/06\\/2026 21:00:00-22:00:00 đã được cập nhật sang trạng thái đã xác nhận.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":66,"booking_code":"BK-20260611-DGIB","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-11","start_time":"21:00:00","end_time":"22:00:00","status":"confirmed","payment_status":"unpaid","total_amount":104000,"old_status":"pending","new_status":"confirmed","note":"Admin xac nhan booking"}', '2026-06-18 23:42:12', '2026-06-11 21:06:09', '2026-06-18 23:42:12'),
	('8aa59b50-aaf1-48ea-8d83-97d1c2821d86', 'court_booking', 'App\\Models\\User', 9, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260713-ADOP tại Sân 1 lúc 13\\/07\\/2026 18:00:00-18:30:00 đã được cập nhật sang trạng thái vắng mặt.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":77,"booking_code":"BK-20260713-ADOP","court_id":1,"court_name":"Sân 1","booking_date":"2026-07-13","start_time":"18:00:00","end_time":"18:30:00","status":"no_show","payment_status":"unpaid","total_amount":52000,"old_status":"confirmed","new_status":"no_show","note":"Auto no-show after 15 minutes"}', '2026-07-16 15:14:24', '2026-07-13 18:15:05', '2026-07-16 15:14:24'),
	('8de7a269-4fb9-41b3-8621-0fd5bfe8ed9b', 'court_booking', 'App\\Models\\User', 6, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260611-DYYB tại Sân 3 lúc 11\\/06\\/2026 14:00:00-15:00:00 đã được cập nhật sang trạng thái vắng mặt.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":57,"booking_code":"BK-20260611-DYYB","court_id":3,"court_name":"Sân 3","booking_date":"2026-06-11","start_time":"14:00:00","end_time":"15:00:00","status":"no_show","payment_status":"unpaid","total_amount":150000,"old_status":"pending","new_status":"no_show","note":"Auto no-show after 15 minutes"}', NULL, '2026-06-11 18:15:04', '2026-06-11 18:15:04'),
	('8eeb92b2-4971-4f27-add7-a1baf9b94c56', 'court_booking', 'App\\Models\\User', 8, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260614-EVQU tại Sân 1 lúc 14\\/06\\/2026 06:00:00-06:30:00 đã được cập nhật sang trạng thái vắng mặt.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":68,"booking_code":"BK-20260614-EVQU","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-14","start_time":"06:00:00","end_time":"06:30:00","status":"no_show","payment_status":"unpaid","total_amount":48000,"old_status":"confirmed","new_status":"no_show","note":"Auto no-show after 15 minutes"}', '2026-06-18 23:42:12', '2026-06-14 12:35:04', '2026-06-18 23:42:12'),
	('938cb6de-701b-46d9-b9df-f228e476688e', 'court_booking', 'App\\Models\\User', 5, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260629-NZQD tại Sân 1 lúc 29\\/06\\/2026 17:00:00-17:30:00 đã được cập nhật sang trạng thái vắng mặt.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":76,"booking_code":"BK-20260629-NZQD","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-29","start_time":"17:00:00","end_time":"17:30:00","status":"no_show","payment_status":"unpaid","total_amount":52000,"old_status":"confirmed","new_status":"no_show","note":"Auto no-show after 15 minutes"}', NULL, '2026-06-29 17:15:05', '2026-06-29 17:15:05'),
	('96224a06-2faa-49f4-957e-436b22708238', 'App\\Notifications\\OrderPlacedNotification', 'App\\Models\\User', 1, '{"type":"order","title":"\\u0110\\u1eb7t h\\u00e0ng th\\u00e0nh c\\u00f4ng!","message":"\\u0110\\u01a1n h\\u00e0ng ORD6A38E82914D4274 \\u0111\\u00e3 \\u0111\\u01b0\\u1ee3c \\u0111\\u1eb7t th\\u00e0nh c\\u00f4ng v\\u00e0 \\u0111ang ch\\u1edd x\\u00e1c nh\\u1eadn.","order_id":9,"order_code":"ORD6A38E82914D4274"}', '2026-06-29 17:16:46', '2026-06-22 14:45:45', '2026-06-29 17:16:46'),
	('9917dda6-457d-426c-805a-20a19e572167', 'App\\Notifications\\AbandonedCartNotification', 'App\\Models\\User', 9, '{"type":"abandoned_cart","title":"\\ud83d\\uded2 Gi\\u1ecf h\\u00e0ng \\u0111ang ch\\u1edd b\\u1ea1n!","message":"B\\u1ea1n c\\u00f3 1 s\\u1ea3n ph\\u1ea9m trong gi\\u1ecf h\\u00e0ng. Ch\\u00fang t\\u00f4i \\u0111\\u00e3 t\\u1eb7ng b\\u1ea1n 30 \\u0111i\\u1ec3m th\\u01b0\\u1edfng!","item_count":1,"points_awarded":30}', '2026-07-16 15:14:27', '2026-06-30 14:06:07', '2026-07-16 15:14:27'),
	('9b34a3f1-424e-497f-a90c-20a8965cdaed', 'App\\Notifications\\OrderCreatedNotification', 'App\\Models\\User', 9, '{"title":"\\u0110\\u1eb7t h\\u00e0ng th\\u00e0nh c\\u00f4ng","message":"\\u0110\\u01a1n h\\u00e0ng ORD6A2FC960EDE9A87 c\\u1ee7a b\\u1ea1n \\u0111\\u00e3 \\u0111\\u01b0\\u1ee3c ghi nh\\u1eadn.","order_code":"ORD6A2FC960EDE9A87","grand_total":"1299000.00","type":"order_created"}', '2026-07-16 15:14:28', '2026-06-15 16:45:10', '2026-07-16 15:14:28'),
	('a139be29-9fd8-453f-818d-cc716f177cf5', 'court_booking', 'App\\Models\\User', 8, '{"title":"Đặt sân thành công","message":"Booking BK-20260611-2BXK tại Sân 2 lúc 11\\/06\\/2026 19:00:00-20:00:00 đang chờ xác nhận.","type":"court_booking","event":"CourtBookingCreated","booking_id":65,"booking_code":"BK-20260611-2BXK","court_id":2,"court_name":"Sân 2","booking_date":"2026-06-11","start_time":"19:00:00","end_time":"20:00:00","status":"pending","payment_status":"unpaid","total_amount":104000}', '2026-06-18 23:42:12', '2026-06-11 18:55:50', '2026-06-18 23:42:12'),
	('a1f8b161-98d0-4abe-8e68-5eea7040b780', 'court_booking', 'App\\Models\\User', 1, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260622-2UGH tại Sân 1 lúc 22\\/06\\/2026 15:30:00-16:30:00 đã được cập nhật sang trạng thái đã hết hạn.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":75,"booking_code":"BK-20260622-2UGH","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-22","start_time":"15:30:00","end_time":"16:30:00","status":"expired","payment_status":"unpaid","total_amount":90000,"old_status":"pending","new_status":"expired","note":"Auto expired after 15 minutes without payment"}', '2026-06-29 17:16:46', '2026-06-22 15:32:06', '2026-06-29 17:16:46'),
	('a3f21d67-1757-4277-9008-5b439a15d9c2', 'App\\Notifications\\AbandonedCartNotification', 'App\\Models\\User', 1, '{"type":"abandoned_cart","title":"\\ud83d\\uded2 Gi\\u1ecf h\\u00e0ng \\u0111ang ch\\u1edd b\\u1ea1n!","message":"B\\u1ea1n c\\u00f3 1 s\\u1ea3n ph\\u1ea9m trong gi\\u1ecf h\\u00e0ng. Ch\\u00fang t\\u00f4i \\u0111\\u00e3 t\\u1eb7ng b\\u1ea1n 30 \\u0111i\\u1ec3m th\\u01b0\\u1edfng!","item_count":1,"points_awarded":30}', '2026-06-29 17:16:46', '2026-06-21 23:43:08', '2026-06-29 17:16:46'),
	('a78838c3-57cb-430a-9b1a-6aad398cf50b', 'court_booking', 'App\\Models\\User', 9, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260713-ADOP tại Sân 1 lúc 13\\/07\\/2026 18:00:00-18:30:00 đã được cập nhật sang trạng thái đã xác nhận.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":77,"booking_code":"BK-20260713-ADOP","court_id":1,"court_name":"Sân 1","booking_date":"2026-07-13","start_time":"18:00:00","end_time":"18:30:00","status":"confirmed","payment_status":"unpaid","total_amount":52000,"old_status":"pending","new_status":"confirmed","note":"Admin xac nhan booking"}', '2026-07-16 15:14:25', '2026-07-13 17:44:50', '2026-07-16 15:14:25'),
	('aa1fa94d-5950-45d2-b278-9daf71be525e', 'App\\Notifications\\AbandonedCartNotification', 'App\\Models\\User', 1, '{"type":"abandoned_cart","title":"\\ud83d\\uded2 Gi\\u1ecf h\\u00e0ng \\u0111ang ch\\u1edd b\\u1ea1n!","message":"B\\u1ea1n c\\u00f3 1 s\\u1ea3n ph\\u1ea9m trong gi\\u1ecf h\\u00e0ng. Ch\\u00fang t\\u00f4i \\u0111\\u00e3 t\\u1eb7ng b\\u1ea1n 30 \\u0111i\\u1ec3m th\\u01b0\\u1edfng!","item_count":1,"points_awarded":30}', '2026-07-23 23:41:33', '2026-07-23 00:00:12', '2026-07-23 23:41:33'),
	('aa422f2b-d8a5-45a2-a512-7be0952edbb7', 'court_booking', 'App\\Models\\User', 8, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260613-FSJL tại Sân 1 lúc 14\\/06\\/2026 05:00:00-06:00:00 đã được cập nhật sang trạng thái vắng mặt.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":67,"booking_code":"BK-20260613-FSJL","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-14","start_time":"05:00:00","end_time":"06:00:00","status":"no_show","payment_status":"unpaid","total_amount":96000,"old_status":"confirmed","new_status":"no_show","note":"Auto no-show after 15 minutes"}', '2026-06-18 23:42:12', '2026-06-14 12:35:04', '2026-06-18 23:42:12'),
	('aff4256d-b486-4b84-a3d7-60c5745b907e', 'court_booking', 'App\\Models\\User', 9, '{"title":"Đặt sân thành công","message":"Booking BK-20260713-ADOP tại Sân 1 lúc 13\\/07\\/2026 18:00:00-18:30:00 đang chờ xác nhận.","type":"court_booking","event":"CourtBookingCreated","booking_id":77,"booking_code":"BK-20260713-ADOP","court_id":1,"court_name":"Sân 1","booking_date":"2026-07-13","start_time":"18:00:00","end_time":"18:30:00","status":"pending","payment_status":"unpaid","total_amount":52000}', '2026-07-16 15:14:25', '2026-07-13 17:43:46', '2026-07-16 15:14:25'),
	('b6936b1a-1ede-4944-b644-7c155e5e870f', 'court_booking', 'App\\Models\\User', 7, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260610-IKA4 tại Sân 1 lúc 10\\/06\\/2026 19:00:00-20:00:00 đã được cập nhật sang trạng thái vắng mặt.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":51,"booking_code":"BK-20260610-IKA4","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-10","start_time":"19:00:00","end_time":"20:00:00","status":"no_show","payment_status":"unpaid","total_amount":104000,"old_status":"confirmed","new_status":"no_show","note":"Auto no-show after 15 minutes"}', NULL, '2026-06-11 18:15:04', '2026-06-11 18:15:04'),
	('b7c874c1-0f10-4a13-bbfd-62be5cf60635', 'App\\Notifications\\AbandonedCartNotification', 'App\\Models\\User', 8, '{"type":"abandoned_cart","title":"\\ud83d\\uded2 Gi\\u1ecf h\\u00e0ng \\u0111ang ch\\u1edd b\\u1ea1n!","message":"B\\u1ea1n c\\u00f3 3 s\\u1ea3n ph\\u1ea9m trong gi\\u1ecf h\\u00e0ng. Ch\\u00fang t\\u00f4i \\u0111\\u00e3 t\\u1eb7ng b\\u1ea1n 30 \\u0111i\\u1ec3m th\\u01b0\\u1edfng!","item_count":3,"points_awarded":30}', '2026-07-20 20:25:23', '2026-06-30 16:53:07', '2026-07-20 20:25:23'),
	('b8851d1e-cb03-41e2-a7e7-cef4055e1d5d', 'court_booking', 'App\\Models\\User', 8, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260614-OODH tại Sân 1 lúc 14\\/06\\/2026 07:30:00-08:00:00 đã được cập nhật sang trạng thái vắng mặt.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":71,"booking_code":"BK-20260614-OODH","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-14","start_time":"07:30:00","end_time":"08:00:00","status":"no_show","payment_status":"unpaid","total_amount":48000,"old_status":"confirmed","new_status":"no_show","note":"Auto no-show after 15 minutes"}', '2026-06-18 23:42:12', '2026-06-14 12:35:04', '2026-06-18 23:42:12'),
	('bf204de4-1b80-4e64-8474-462936884fd5', 'App\\Notifications\\AbandonedCartNotification', 'App\\Models\\User', 5, '{"type":"abandoned_cart","title":"\\ud83d\\uded2 Gi\\u1ecf h\\u00e0ng \\u0111ang ch\\u1edd b\\u1ea1n!","message":"B\\u1ea1n c\\u00f3 1 s\\u1ea3n ph\\u1ea9m trong gi\\u1ecf h\\u00e0ng. Ch\\u00fang t\\u00f4i \\u0111\\u00e3 t\\u1eb7ng b\\u1ea1n 50 \\u0111i\\u1ec3m th\\u01b0\\u1edfng!","item_count":1,"points_awarded":50}', NULL, '2026-06-19 00:38:08', '2026-06-19 00:38:08'),
	('bf4953ad-23b9-4030-9b7f-4614c4e0d959', 'court_booking', 'App\\Models\\User', 8, '{"title":"Đặt sân thành công","message":"Booking BK-20260614-G6GB tại Sân 1 lúc 14\\/06\\/2026 06:30:00-07:00:00 đang chờ xác nhận.","type":"court_booking","event":"CourtBookingCreated","booking_id":69,"booking_code":"BK-20260614-G6GB","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-14","start_time":"06:30:00","end_time":"07:00:00","status":"pending","payment_status":"unpaid","total_amount":48000}', '2026-06-18 23:42:12', '2026-06-14 00:20:23', '2026-06-18 23:42:12'),
	('c04a587d-3343-4472-b900-e0b65836d760', 'App\\Notifications\\OrderCreatedNotification', 'App\\Models\\User', 8, '{"title":"\\u0110\\u1eb7t h\\u00e0ng th\\u00e0nh c\\u00f4ng","message":"\\u0110\\u01a1n h\\u00e0ng ORD6A34216ED78A965 c\\u1ee7a b\\u1ea1n \\u0111\\u00e3 \\u0111\\u01b0\\u1ee3c ghi nh\\u1eadn.","order_code":"ORD6A34216ED78A965","grand_total":"1299000.00","type":"order_created"}', '2026-07-20 20:25:33', '2026-06-18 23:50:08', '2026-07-20 20:25:33'),
	('c390657e-1eff-4f7b-943b-0b0deae53820', 'App\\Notifications\\OrderCreatedNotification', 'App\\Models\\User', 1, '{"title":"\\u0110\\u1eb7t h\\u00e0ng th\\u00e0nh c\\u00f4ng","message":"\\u0110\\u01a1n h\\u00e0ng ORD6A39155C6B15016 c\\u1ee7a b\\u1ea1n \\u0111\\u00e3 \\u0111\\u01b0\\u1ee3c ghi nh\\u1eadn.","order_code":"ORD6A39155C6B15016","grand_total":"1399000.00","type":"order_created"}', '2026-06-29 17:16:44', '2026-06-22 18:00:11', '2026-06-29 17:16:44'),
	('c75ebfc1-3241-4b9f-b2d5-ec8abf8eb4af', 'court_booking', 'App\\Models\\User', 8, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260614-OODH tại Sân 1 lúc 14\\/06\\/2026 07:30:00-08:00:00 đã được cập nhật sang trạng thái đã xác nhận.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":71,"booking_code":"BK-20260614-OODH","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-14","start_time":"07:30:00","end_time":"08:00:00","status":"confirmed","payment_status":"unpaid","total_amount":48000,"old_status":"pending","new_status":"confirmed","note":"Admin xac nhan booking"}', '2026-06-18 23:42:12', '2026-06-14 00:22:47', '2026-06-18 23:42:12'),
	('ca2d7233-e8ea-4ad3-ac06-daff0bf07bd8', 'court_booking', 'App\\Models\\User', 5, '{"title":"Đặt sân thành công","message":"Booking BK-20260616-UTIJ tại Sân 2 lúc 16\\/06\\/2026 19:30:00-20:00:00 đang chờ xác nhận.","type":"court_booking","event":"CourtBookingCreated","booking_id":73,"booking_code":"BK-20260616-UTIJ","court_id":2,"court_name":"Sân 2","booking_date":"2026-06-16","start_time":"19:30:00","end_time":"20:00:00","status":"pending","payment_status":"unpaid","total_amount":76000}', '2026-06-16 18:26:35', '2026-06-16 18:23:28', '2026-06-16 18:26:35'),
	('cd403af3-6c0b-45ba-8c5a-7e74746bd618', 'App\\Notifications\\OrderPlacedNotification', 'App\\Models\\User', 1, '{"type":"order","title":"\\u0110\\u1eb7t h\\u00e0ng th\\u00e0nh c\\u00f4ng!","message":"\\u0110\\u01a1n h\\u00e0ng ORD6A42497F20B7729 \\u0111\\u00e3 \\u0111\\u01b0\\u1ee3c \\u0111\\u1eb7t th\\u00e0nh c\\u00f4ng v\\u00e0 \\u0111ang ch\\u1edd x\\u00e1c nh\\u1eadn.","order_id":13,"order_code":"ORD6A42497F20B7729"}', '2026-07-21 23:11:41', '2026-06-29 17:31:27', '2026-07-21 23:11:41'),
	('cf2b3555-f21f-44e6-ad03-37bf25cfcaba', 'App\\Notifications\\OrderCreatedNotification', 'App\\Models\\User', 9, '{"title":"\\u0110\\u1eb7t h\\u00e0ng th\\u00e0nh c\\u00f4ng","message":"\\u0110\\u01a1n h\\u00e0ng POS6A2D8BDB88F8351 c\\u1ee7a b\\u1ea1n \\u0111\\u00e3 \\u0111\\u01b0\\u1ee3c ghi nh\\u1eadn.","order_code":"POS6A2D8BDB88F8351","grand_total":"599000.00","type":"order_created"}', '2026-07-16 15:14:32', '2026-06-13 23:58:08', '2026-07-16 15:14:32'),
	('d2a0630b-9b17-4382-b925-360d863632aa', 'court_booking', 'App\\Models\\User', 8, '{"title":"Đặt sân thành công","message":"Booking BK-20260614-RSIQ tại Sân 1 lúc 14\\/06\\/2026 07:00:00-07:30:00 đang chờ xác nhận.","type":"court_booking","event":"CourtBookingCreated","booking_id":70,"booking_code":"BK-20260614-RSIQ","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-14","start_time":"07:00:00","end_time":"07:30:00","status":"pending","payment_status":"unpaid","total_amount":48000}', '2026-06-18 23:42:12', '2026-06-14 00:22:15', '2026-06-18 23:42:12'),
	('d3266503-2c0c-4421-babb-cf77a8442ac2', 'court_booking', 'App\\Models\\User', 1, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260622-FCFM tại Sân 1 lúc 22\\/06\\/2026 15:00:00-16:00:00 đã được cập nhật sang trạng thái vắng mặt.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":74,"booking_code":"BK-20260622-FCFM","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-22","start_time":"15:00:00","end_time":"16:00:00","status":"no_show","payment_status":"unpaid","total_amount":90000,"old_status":"confirmed","new_status":"no_show","note":"Auto no-show after 15 minutes"}', '2026-06-29 17:16:46', '2026-06-22 15:15:07', '2026-06-29 17:16:46'),
	('de8bf21e-a4fe-496a-bf0c-bc5310bbe4a9', 'App\\Notifications\\OrderCreatedNotification', 'App\\Models\\User', 5, '{"title":"\\u0110\\u1eb7t h\\u00e0ng th\\u00e0nh c\\u00f4ng","message":"\\u0110\\u01a1n h\\u00e0ng ORD6A423E26677ED34 c\\u1ee7a b\\u1ea1n \\u0111\\u00e3 \\u0111\\u01b0\\u1ee3c ghi nh\\u1eadn.","order_code":"ORD6A423E26677ED34","grand_total":"1399000.00","type":"order_created"}', NULL, '2026-06-29 16:44:13', '2026-06-29 16:44:13'),
	('e1296f97-e51a-4c72-99fe-eb9f8cfb8cab', 'App\\Notifications\\AbandonedCartNotification', 'App\\Models\\User', 9, '{"type":"abandoned_cart","title":"\\ud83d\\uded2 Gi\\u1ecf h\\u00e0ng \\u0111ang ch\\u1edd b\\u1ea1n!","message":"B\\u1ea1n c\\u00f3 1 s\\u1ea3n ph\\u1ea9m trong gi\\u1ecf h\\u00e0ng. Ch\\u00fang t\\u00f4i \\u0111\\u00e3 t\\u1eb7ng b\\u1ea1n 30 \\u0111i\\u1ec3m th\\u01b0\\u1edfng!","item_count":1,"points_awarded":30}', '2026-07-23 15:09:23', '2026-07-23 00:35:07', '2026-07-23 15:09:23'),
	('e43d1b67-81f7-46cb-93c5-1bce96067fe6', 'court_booking', 'App\\Models\\User', 8, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260611-2BXK tại Sân 2 lúc 11\\/06\\/2026 19:00:00-20:00:00 đã được cập nhật sang trạng thái đã xác nhận.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":65,"booking_code":"BK-20260611-2BXK","court_id":2,"court_name":"Sân 2","booking_date":"2026-06-11","start_time":"19:00:00","end_time":"20:00:00","status":"confirmed","payment_status":"unpaid","total_amount":104000,"old_status":"pending","new_status":"confirmed","note":"Admin xac nhan booking"}', '2026-06-18 23:42:12', '2026-06-11 18:56:02', '2026-06-18 23:42:12'),
	('ec36b785-3d93-43a5-ad37-ab64d4ece602', 'App\\Notifications\\OrderPlacedNotification', 'App\\Models\\User', 5, '{"type":"order","title":"\\u0110\\u1eb7t h\\u00e0ng th\\u00e0nh c\\u00f4ng!","message":"\\u0110\\u01a1n h\\u00e0ng ORD6A423E26677ED34 \\u0111\\u00e3 \\u0111\\u01b0\\u1ee3c \\u0111\\u1eb7t th\\u00e0nh c\\u00f4ng v\\u00e0 \\u0111ang ch\\u1edd x\\u00e1c nh\\u1eadn.","order_id":12,"order_code":"ORD6A423E26677ED34"}', NULL, '2026-06-29 16:43:02', '2026-06-29 16:43:02'),
	('ee822de3-7e02-4c5e-a252-741cbc9b5833', 'App\\Notifications\\OrderCreatedNotification', 'App\\Models\\User', 1, '{"title":"\\u0110\\u1eb7t h\\u00e0ng th\\u00e0nh c\\u00f4ng","message":"\\u0110\\u01a1n h\\u00e0ng ORD-F5F7444B-516 c\\u1ee7a b\\u1ea1n \\u0111\\u00e3 \\u0111\\u01b0\\u1ee3c ghi nh\\u1eadn.","order_code":"ORD-F5F7444B-516","grand_total":"99300.00","type":"order_created"}', '2026-07-21 23:12:58', '2026-07-21 23:12:08', '2026-07-21 23:12:58'),
	('ef6a09a3-4c70-48f5-8196-50465ec3ee35', 'court_booking', 'App\\Models\\User', 5, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260616-UTIJ tại Sân 2 lúc 16\\/06\\/2026 19:30:00-20:00:00 đã được cập nhật sang trạng thái vắng mặt.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":73,"booking_code":"BK-20260616-UTIJ","court_id":2,"court_name":"Sân 2","booking_date":"2026-06-16","start_time":"19:30:00","end_time":"20:00:00","status":"no_show","payment_status":"unpaid","total_amount":76000,"old_status":"confirmed","new_status":"no_show","note":"Auto no-show after 15 minutes"}', '2026-06-18 00:10:30', '2026-06-16 19:45:05', '2026-06-18 00:10:30'),
	('f70bc805-81ea-4cff-80e4-3dc9c7bd338c', 'court_booking', 'App\\Models\\User', 6, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260610-UZJQ tại Sân 4 lúc 10\\/06\\/2026 07:00:00-09:00:00 đã được cập nhật sang trạng thái vắng mặt.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":54,"booking_code":"BK-20260610-UZJQ","court_id":4,"court_name":"Sân 4","booking_date":"2026-06-10","start_time":"07:00:00","end_time":"09:00:00","status":"no_show","payment_status":"unpaid","total_amount":160000,"old_status":"pending","new_status":"no_show","note":"Auto no-show after 15 minutes"}', NULL, '2026-06-11 18:15:04', '2026-06-11 18:15:04'),
	('fc9c3677-07b5-468d-853d-5f5224d81cf2', 'court_booking', 'App\\Models\\User', 7, '{"title":"Lịch đặt sân cập nhật","message":"Booking BK-20260612-IMFB tại Sân 1 lúc 12\\/06\\/2026 14:00:00-16:00:00 đã được cập nhật sang trạng thái vắng mặt.","type":"court_booking","event":"CourtBookingStatusChanged","booking_id":58,"booking_code":"BK-20260612-IMFB","court_id":1,"court_name":"Sân 1","booking_date":"2026-06-12","start_time":"14:00:00","end_time":"16:00:00","status":"no_show","payment_status":"unpaid","total_amount":160000,"old_status":"confirmed","new_status":"no_show","note":"Auto no-show after 15 minutes"}', NULL, '2026-06-12 14:15:06', '2026-06-12 14:15:06');

-- Dumping structure for table ocean_db.order_items
CREATE TABLE IF NOT EXISTS `order_items` (
  `order_item_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `variant_id` bigint unsigned DEFAULT NULL,
  `product_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `variant_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sku` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_item_id`),
  KEY `order_items_order_id_foreign` (`order_id`),
  KEY `order_items_product_id_foreign` (`product_id`),
  KEY `idx_order_items_variant` (`variant_id`),
  CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE RESTRICT,
  CONSTRAINT `order_items_variant_id_foreign` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`variant_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.order_items: ~24 rows (approximately)
DELETE FROM `order_items`;
INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `variant_id`, `product_name`, `variant_name`, `sku`, `color`, `size`, `quantity`, `unit_price`, `discount_amount`, `line_total`, `created_at`) VALUES
	(1, 1, 7, 13, 'Giày cầu lông BS Sensation 500 White Blue', 'Size 33', 'BDM-BSS500-33', 'Trắng/Xanh dương', '33', 1, 599000.00, 0.00, 599000.00, '2026-06-13 16:56:59'),
	(2, 2, 5, 9, 'Vợt cầu lông BR 500 White', 'Cán G4', 'BDM-BR500-WHITE-G4', 'Trắng', 'G4', 1, 399000.00, 0.00, 399000.00, '2026-06-14 16:06:14'),
	(3, 2, 19, 35, 'Giày tennis/pickleball nam All Court Light Grey Blue', 'Size 41', 'PKB-ALLCOURT-M-41', 'Xám nhạt/Xanh dương', '41', 1, 1399000.00, 0.00, 1399000.00, '2026-06-14 16:06:14'),
	(4, 3, 20, NULL, 'Giày pickleball nam Essential White', NULL, 'giay-pickleball-nam-essential-white-trang-39-2X4n', 'Trắng', '39', 1, 539000.00, 0.00, 539000.00, '2026-06-14 16:13:03'),
	(5, 4, 8, 20, 'Giày cầu lông BS Lite 560 White Sea Blue', 'Size 38', 'BDM-BSL560-38', 'Trắng/Xanh biển', '38', 1, 1299000.00, 0.00, 1299000.00, '2026-06-15 09:44:01'),
	(6, 5, 18, 32, 'Vợt Pickleball EliteX 16MM Blue', 'One Size', 'PKB-ELITEX16', 'Xanh dương', 'One Size', 1, 4500000.00, 0.00, 4500000.00, '2026-06-16 16:38:29'),
	(7, 6, 14, 28, 'Bộ sân bóng chuyền bãi biển BV900 Official', 'Bộ sân official', 'VLB-BV900-SET', 'Cam/Đen', 'One Size', 1, 2999000.00, 0.00, 2999000.00, '2026-06-17 17:37:25'),
	(8, 7, 8, 17, 'Giày cầu lông BS Lite 560 White Sea Blue', 'Size 35', 'BDM-BSL560-35', 'Trắng/Xanh biển', '35', 1, 1299000.00, 0.00, 1299000.00, '2026-06-18 16:48:46'),
	(9, 8, 14, 28, 'Bộ sân bóng chuyền bãi biển BV900 Official', 'Bộ sân official', 'VLB-BV900-SET', 'Cam/Đen', 'One Size', 1, 2999000.00, 0.00, 2999000.00, '2026-06-18 17:29:46'),
	(10, 9, 20, NULL, 'Giày pickleball nam Essential White', NULL, 'giay-pickleball-nam-essential-white-trang-39-2X4n', 'Trắng', '39', 1, 539000.00, 0.00, 539000.00, '2026-06-22 07:45:45'),
	(11, 10, 19, 34, 'Giày tennis/pickleball nam All Court Light Grey Blue', 'Size 40', 'PKB-ALLCOURT-M-40', 'Xám nhạt/Xanh dương', '40', 1, 1399000.00, 0.00, 1399000.00, '2026-06-22 10:58:36'),
	(12, 11, 12, 26, 'Băng bảo vệ gối bóng chuyền VKP100 Black', 'Size L', 'VLB-VKP100-L', 'Đen', 'L', 1, 199000.00, 0.00, 199000.00, '2026-06-29 07:12:15'),
	(13, 11, 20, 51, 'Giày pickleball nam Essential White', NULL, 'giay-pickleball-nam-essential-white-trang-42-Y2XY', 'Trắng', '42', 1, 539000.00, 0.00, 539000.00, '2026-06-29 07:12:15'),
	(14, 12, 19, 33, 'Giày tennis/pickleball nam All Court Light Grey Blue', 'Size 39', 'PKB-ALLCOURT-M-39', 'Xám nhạt/Xanh dương', '39', 1, 1399000.00, 0.00, 1399000.00, '2026-06-29 09:43:02'),
	(15, 13, 20, 48, 'Giày pickleball nam Essential White', NULL, 'giay-pickleball-nam-essential-white-trang-39-rQ4c', 'Trắng', '39', 5, 539000.00, 0.00, 2695000.00, '2026-06-29 10:31:27'),
	(16, 14, 8, 17, 'Giày cầu lông BS Lite 560 White Sea Blue', 'Size 35', 'BDM-BSL560-35', 'Trắng/Xanh biển', '35', 2, 1299000.00, 0.00, 2598000.00, '2026-07-16 09:19:57'),
	(17, 14, 19, 33, 'Giày tennis/pickleball nam All Court Light Grey Blue', 'Size 39', 'PKB-ALLCOURT-M-39', 'Xám nhạt/Xanh dương', '39', 1, 1399000.00, 0.00, 1399000.00, '2026-07-16 09:19:57'),
	(18, 14, 20, 48, 'Giày pickleball nam Essential White', NULL, 'giay-pickleball-nam-essential-white-trang-39-rQ4c', 'Trắng', '39', 1, 539000.00, 0.00, 539000.00, '2026-07-16 09:19:57'),
	(19, 15, 19, 33, 'Giày tennis/pickleball nam All Court Light Grey Blue', 'Size 39', 'PKB-ALLCOURT-M-39', 'Xám nhạt/Xanh dương', '39', 1, 1399000.00, 0.00, 1399000.00, '2026-07-16 13:09:20'),
	(20, 16, 9, 21, 'Bóng chuyền đa dụng BV Crystal Orange', 'Size 4', 'VLB-BVCRYSTAL', 'Cam', '4', 1, 99000.00, 0.00, 99000.00, '2026-07-21 16:10:20'),
	(21, 17, 14, 28, 'Bộ sân bóng chuyền bãi biển BV900 Official', 'Bộ sân official', 'VLB-BV900-SET', 'Cam/Đen', 'One Size', 1, 2999000.00, 0.00, 2999000.00, '2026-07-21 16:12:26'),
	(22, 17, 18, 32, 'Vợt Pickleball EliteX 16MM Blue', 'One Size', 'PKB-ELITEX16', 'Xanh dương', 'One Size', 1, 4500000.00, 0.00, 4500000.00, '2026-07-21 16:12:26'),
	(23, 18, 246, NULL, 'Giày tennis Babolat Jet Mach 3 All Court Junior chính hãng (26883A–5081)', 'Mặc định', 'SKU-WL0URUL6', NULL, NULL, 1, 2518800.00, 0.00, 2518800.00, '2026-07-22 02:39:25'),
	(24, 19, 166, 273, 'Vợt cầu lông VNB V88 xanh chính hãng', NULL, 'vot-cau-long-vnb-v88-xanh-chinh-hang-xanh--jYzQ', 'Xanh ', '', 1, 765600.00, 0.00, 765600.00, '2026-07-24 17:41:07');

-- Dumping structure for table ocean_db.order_status_histories
CREATE TABLE IF NOT EXISTS `order_status_histories` (
  `history_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `old_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ghn_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `happened_at` timestamp NULL DEFAULT NULL,
  `changed_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `order_status_histories_order_id_foreign` (`order_id`),
  KEY `order_status_histories_changed_by_foreign` (`changed_by`),
  CONSTRAINT `order_status_histories_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `order_status_histories_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.order_status_histories: ~60 rows (approximately)
DELETE FROM `order_status_histories`;
INSERT INTO `order_status_histories` (`history_id`, `order_id`, `old_status`, `new_status`, `note`, `ghn_status`, `source`, `location`, `description`, `happened_at`, `changed_by`, `created_at`) VALUES
	(1, 1, NULL, 'completed', 'Bán hàng trực tiếp tại cửa hàng (POS)', NULL, 'system', NULL, NULL, '2026-06-13 16:56:59', NULL, '2026-06-13 16:56:59'),
	(2, 2, NULL, 'pending', 'Khách hàng đặt đơn hàng mới', NULL, 'system', NULL, NULL, '2026-06-14 16:06:14', NULL, '2026-06-14 16:06:14'),
	(3, 3, NULL, 'pending', 'Khách hàng đặt đơn hàng mới', NULL, 'system', NULL, NULL, '2026-06-14 16:13:03', NULL, '2026-06-14 16:13:03'),
	(4, 2, 'pending', 'confirmed', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, '2026-06-14 16:20:23', NULL, '2026-06-14 16:20:23'),
	(5, 2, 'confirmed', 'processing', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, '2026-06-14 16:20:24', NULL, '2026-06-14 16:20:24'),
	(6, 2, 'processing', 'shipping', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, '2026-06-14 16:20:25', NULL, '2026-06-14 16:20:25'),
	(7, 2, 'shipping', 'delivered', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, '2026-06-14 16:20:28', NULL, '2026-06-14 16:20:28'),
	(8, 2, 'unpaid', 'paid', '[Thanh toán] Tự động cập nhật theo trạng thái đơn hàng', NULL, 'system', NULL, NULL, '2026-06-14 16:20:28', NULL, '2026-06-14 16:20:28'),
	(9, 2, 'delivered', 'completed', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, '2026-06-14 16:20:29', NULL, '2026-06-14 16:20:29'),
	(10, 4, NULL, 'pending', 'Khách hàng đặt đơn hàng mới', NULL, 'system', NULL, NULL, '2026-06-15 09:44:01', NULL, '2026-06-15 09:44:01'),
	(11, 4, 'pending', 'cancelled', 'Khách hàng hủy đơn: Tôi tìm thấy giá rẻ hơn ở nơi khác', NULL, 'system', NULL, NULL, '2026-06-15 09:46:01', NULL, '2026-06-15 09:46:01'),
	(12, 5, NULL, 'pending', 'Khách hàng đặt đơn hàng mới', NULL, 'system', NULL, NULL, '2026-06-16 16:38:29', NULL, '2026-06-16 16:38:29'),
	(13, 6, NULL, 'pending', 'Khách hàng đặt đơn hàng mới', NULL, 'system', NULL, NULL, '2026-06-17 17:37:25', NULL, '2026-06-17 17:37:25'),
	(14, 7, NULL, 'pending', 'Khách hàng đặt đơn hàng mới', NULL, 'system', NULL, NULL, '2026-06-18 16:48:46', NULL, '2026-06-18 16:48:46'),
	(15, 8, NULL, 'pending', 'Khách hàng đặt đơn hàng mới', NULL, 'system', NULL, NULL, '2026-06-18 17:29:46', NULL, '2026-06-18 17:29:46'),
	(16, 9, NULL, 'pending', 'Khách hàng đặt đơn hàng mới', NULL, 'system', NULL, NULL, '2026-06-22 07:45:45', NULL, '2026-06-22 07:45:45'),
	(17, 9, 'pending', 'confirmed', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, '2026-06-22 08:19:55', NULL, '2026-06-22 08:19:55'),
	(18, 9, 'confirmed', 'packing', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, '2026-06-22 08:19:58', NULL, '2026-06-22 08:19:58'),
	(19, 9, 'packing', 'shipping', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, '2026-06-22 08:19:59', NULL, '2026-06-22 08:19:59'),
	(20, 9, 'shipping', 'delivered', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, '2026-06-22 08:20:00', NULL, '2026-06-22 08:20:00'),
	(21, 9, 'unpaid', 'paid', '[Thanh toán] Tự động cập nhật theo trạng thái đơn hàng', NULL, 'system', NULL, NULL, '2026-06-22 08:20:00', NULL, '2026-06-22 08:20:00'),
	(22, 10, NULL, 'pending', 'Khách hàng đặt đơn hàng mới', NULL, 'system', NULL, NULL, '2026-06-22 10:58:36', NULL, '2026-06-22 10:58:36'),
	(23, 6, 'pending', 'confirmed', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, '2026-06-29 07:06:05', NULL, '2026-06-29 07:06:05'),
	(24, 9, 'delivered', 'completed', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, '2026-06-29 07:06:12', NULL, '2026-06-29 07:06:12'),
	(25, 11, NULL, 'pending', 'Khách hàng đặt đơn hàng mới', NULL, 'system', NULL, NULL, '2026-06-29 07:12:15', NULL, '2026-06-29 07:12:15'),
	(26, 11, 'pending', 'confirmed', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, '2026-06-29 07:24:48', NULL, '2026-06-29 07:24:48'),
	(27, 11, 'confirmed', 'processing', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, '2026-06-29 07:24:52', NULL, '2026-06-29 07:24:52'),
	(28, 11, 'processing', 'packing', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, '2026-06-29 07:24:55', NULL, '2026-06-29 07:24:55'),
	(29, 11, 'packing', 'shipping', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, '2026-06-29 07:25:00', NULL, '2026-06-29 07:25:00'),
	(30, 11, 'shipping', 'delivered', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, '2026-06-29 07:25:11', NULL, '2026-06-29 07:25:11'),
	(31, 11, 'unpaid', 'paid', '[Thanh toán] Tự động cập nhật theo trạng thái đơn hàng', NULL, 'system', NULL, NULL, '2026-06-29 07:25:11', NULL, '2026-06-29 07:25:11'),
	(32, 12, NULL, 'pending', 'Khách hàng đặt đơn hàng mới', NULL, 'system', NULL, NULL, '2026-06-29 09:43:02', NULL, '2026-06-29 09:43:02'),
	(33, 12, 'pending', 'confirmed', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, '2026-06-29 10:02:13', NULL, '2026-06-29 10:02:13'),
	(34, 12, 'confirmed', 'processing', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, '2026-06-29 10:02:16', NULL, '2026-06-29 10:02:16'),
	(35, 12, 'processing', 'packing', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, '2026-06-29 10:02:18', NULL, '2026-06-29 10:02:18'),
	(36, 12, 'packing', 'shipping', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, '2026-06-29 10:02:19', NULL, '2026-06-29 10:02:19'),
	(37, 12, 'shipping', 'delivered', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, '2026-06-29 10:02:26', NULL, '2026-06-29 10:02:26'),
	(38, 12, 'unpaid', 'paid', '[Thanh toán] Tự động cập nhật theo trạng thái đơn hàng', NULL, 'system', NULL, NULL, '2026-06-29 10:02:26', NULL, '2026-06-29 10:02:26'),
	(39, 12, 'delivered', 'completed', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, '2026-06-29 10:02:29', NULL, '2026-06-29 10:02:29'),
	(40, 13, NULL, 'pending', 'Khách hàng đặt đơn hàng mới', NULL, 'system', NULL, NULL, '2026-06-29 10:31:27', NULL, '2026-06-29 10:31:27'),
	(41, 14, NULL, 'pending', 'Khách hàng đặt đơn hàng mới', NULL, 'system', NULL, NULL, NULL, NULL, '2026-07-16 09:19:57'),
	(42, 15, NULL, 'pending', 'Khách hàng đặt đơn hàng mới', NULL, 'system', NULL, NULL, NULL, NULL, '2026-07-16 13:09:20'),
	(43, 13, 'pending', 'cancelled', 'Hệ thống tự động hủy: chưa thanh toán sau 30 phút.', NULL, 'system', NULL, NULL, NULL, NULL, '2026-07-21 02:25:05'),
	(44, 16, NULL, 'pending', 'Khách hàng đặt đơn hàng mới', NULL, 'system', NULL, NULL, NULL, NULL, '2026-07-21 16:10:20'),
	(45, 16, 'pending', 'confirmed', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, NULL, NULL, '2026-07-21 16:10:54'),
	(46, 16, 'confirmed', 'packing', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, NULL, NULL, '2026-07-21 16:10:58'),
	(47, 16, 'packing', 'shipping', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, NULL, NULL, '2026-07-21 16:11:02'),
	(48, 16, 'shipping', 'delivered', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, NULL, NULL, '2026-07-21 16:11:04'),
	(49, 16, 'unpaid', 'paid', '[Thanh toán] Tự động cập nhật theo trạng thái đơn hàng', NULL, 'system', NULL, NULL, NULL, NULL, '2026-07-21 16:11:04'),
	(50, 16, 'delivered', 'completed', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, NULL, NULL, '2026-07-21 16:11:20'),
	(51, 17, NULL, 'pending', 'Khách hàng đặt đơn hàng mới', NULL, 'system', NULL, NULL, NULL, NULL, '2026-07-21 16:12:26'),
	(52, 17, 'pending', 'confirmed', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, NULL, NULL, '2026-07-21 16:12:39'),
	(53, 17, 'confirmed', 'packing', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, NULL, NULL, '2026-07-21 16:12:42'),
	(54, 17, 'packing', 'shipping', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, NULL, NULL, '2026-07-21 16:12:45'),
	(55, 17, 'shipping', 'delivered', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, NULL, NULL, '2026-07-21 16:12:47'),
	(56, 17, 'unpaid', 'paid', '[Thanh toán] Tự động cập nhật theo trạng thái đơn hàng', NULL, 'system', NULL, NULL, NULL, NULL, '2026-07-21 16:12:47'),
	(57, 17, 'delivered', 'completed', 'Chuyển trạng thái bởi Admin', NULL, 'system', NULL, NULL, NULL, NULL, '2026-07-21 16:12:50'),
	(58, 18, NULL, 'pending', 'Khách vãng lai đặt đơn hàng mới', NULL, 'system', NULL, NULL, NULL, NULL, '2026-07-22 02:39:25'),
	(59, 17, 'completed', 'return_requested', 'Khách hàng gửi yêu cầu hoàn hàng: Sản phẩm không đúng mô tả', NULL, 'system', NULL, NULL, NULL, 1, '2026-07-23 17:23:24'),
	(60, 19, NULL, 'completed', 'Bán hàng trực tiếp tại cửa hàng (POS)', NULL, 'system', NULL, NULL, NULL, NULL, '2026-07-24 17:41:07');

-- Dumping structure for table ocean_db.orders
CREATE TABLE IF NOT EXISTS `orders` (
  `order_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ghn_order_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_type` enum('online','pos') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'online',
  `user_id` bigint unsigned DEFAULT NULL,
  `seller_id` bigint unsigned DEFAULT NULL COMMENT 'Account created or handled the POS order',
  `address_id` bigint unsigned DEFAULT NULL,
  `promotion_id` bigint unsigned DEFAULT NULL,
  `recipient_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `province_code` int DEFAULT NULL,
  `district_code` int DEFAULT NULL,
  `ward_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `payment_method` enum('cod','vnpay','momo','bank_transfer','pos_cash','pos_transfer','pos_card') COLLATE utf8mb4_unicode_ci DEFAULT 'cod',
  `payment_status` enum('unpaid','paid','failed','refund_pending','refunded','refund_failed','partially_refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `is_abandoned_checkout` tinyint(1) NOT NULL DEFAULT '0',
  `fulfillment_status` enum('pending','confirmed','processing','packing','shipping','delivered','completed','cancelled','return_requested','return_approved','return_rejected','returned','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `wallet_deposit_discount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `wallet_commission_discount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `combo_discount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `shipping_fee` decimal(12,2) NOT NULL DEFAULT '0.00',
  `grand_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `wallet_spent` decimal(15,2) NOT NULL DEFAULT '0.00',
  `email_sent` tinyint(1) NOT NULL DEFAULT '0',
  `confirmed_at` datetime DEFAULT NULL,
  `shipped_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `orders_order_code_unique` (`order_code`),
  UNIQUE KEY `orders_tracking_token_unique` (`tracking_token`),
  KEY `orders_address_id_foreign` (`address_id`),
  KEY `orders_promotion_id_foreign` (`promotion_id`),
  KEY `idx_orders_user_fulfillment` (`user_id`,`fulfillment_status`),
  KEY `idx_orders_fulfillment_created` (`fulfillment_status`,`created_at`),
  CONSTRAINT `orders_address_id_foreign` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`address_id`) ON DELETE SET NULL,
  CONSTRAINT `orders_promotion_id_foreign` FOREIGN KEY (`promotion_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.orders: ~19 rows (approximately)
DELETE FROM `orders`;
INSERT INTO `orders` (`order_id`, `order_code`, `ghn_order_code`, `tracking_token`, `order_type`, `user_id`, `seller_id`, `address_id`, `promotion_id`, `recipient_name`, `recipient_phone`, `email`, `shipping_address`, `province_code`, `district_code`, `ward_code`, `note`, `payment_method`, `payment_status`, `is_abandoned_checkout`, `fulfillment_status`, `subtotal`, `discount_amount`, `wallet_deposit_discount`, `wallet_commission_discount`, `combo_discount`, `shipping_fee`, `grand_total`, `wallet_spent`, `email_sent`, `confirmed_at`, `shipped_at`, `delivered_at`, `completed_at`, `cancelled_at`, `cancel_reason`, `created_at`, `updated_at`) VALUES
	(1, 'POS6A2D8BDB88F8351', NULL, 'c6d5780e454789382aa27504ef6ec975813028e8531a2319481e546740003f13', 'online', 9, 9, NULL, NULL, 'Khách lẻ', '', NULL, 'Mua tại cửa hàng', NULL, NULL, NULL, '', 'pos_cash', 'paid', 0, 'completed', 599000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 599000.00, 0.00, 1, NULL, NULL, NULL, '2026-06-13 23:56:59', NULL, NULL, '2026-06-13 23:56:59', '2026-06-13 23:58:08'),
	(2, 'ORD6A2ED176B893699', NULL, 'd0c36d698491cd51e3489bb367fe14daf17952f9b8437821c4343d745ffe68c0', 'online', 8, NULL, 1, NULL, 'sss', '090000000ssss', NULL, 'sssssss, Xã Viên An, Huyện Ngọc Hiển, Cà Mau', NULL, NULL, NULL, 'sss', 'cod', 'paid', 0, 'completed', 1798000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1798000.00, 0.00, 1, '2026-06-14 23:20:23', '2026-06-14 23:20:25', '2026-06-14 23:20:28', '2026-06-14 23:20:29', NULL, NULL, '2026-06-14 23:06:14', '2026-06-14 23:20:29'),
	(3, 'ORD6A2ED30FA2EA237', NULL, 'ddedcb5840f904c02cb5c7d1b2edc249bb8ef17ae447cc5a14bf23fd10874ba3', 'online', 8, NULL, 2, NULL, 'zzz', '0909009009', NULL, 'zzzzz, Xã Trưng Trắc, Huyện Văn Lâm, Hưng Yên', NULL, NULL, NULL, NULL, 'cod', 'unpaid', 0, 'pending', 539000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 539000.00, 0.00, 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-14 23:13:03', '2026-06-14 23:14:08'),
	(4, 'ORD6A2FC960EDE9A87', NULL, 'd5b4f50aff5ffacca728d16f8c7d62a0ff63102747dc5ba14b10c95c155b9d39', 'online', 9, NULL, 3, NULL, 'sss', '0905094644', NULL, 'sss, Thị Trấn Si Ma Cai, Huyện Si Ma Cai, Lào Cai', NULL, NULL, NULL, 'ssss', 'bank_transfer', 'unpaid', 0, 'cancelled', 1299000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1299000.00, 0.00, 1, NULL, NULL, NULL, NULL, '2026-06-15 16:46:01', 'Tôi tìm thấy giá rẻ hơn ở nơi khác', '2026-06-15 16:44:00', '2026-06-15 16:46:01'),
	(5, 'ORD6A317C05681E216', NULL, 'e9d83193cff1bea93cfb1d77181376c8b627ada5d2567bf2f2c75b4f351b24f3', 'online', 5, NULL, 5, NULL, 'sss', 'sssssss', NULL, 'ssss, Xã Bình An, Huyện Lâm Bình, Tỉnh Tuyên Quang', NULL, NULL, NULL, NULL, 'cod', 'unpaid', 0, 'pending', 4500000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4500000.00, 0.00, 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-16 23:38:29', '2026-06-17 00:00:08'),
	(6, 'ORD6A32DB552B14847', NULL, '9c4c7b13418e6c313a4705decde78498e3a5151b5b73759d3aa1ed3124da7d47', 'online', 5, NULL, 4, NULL, 'bcbc', 'adssfhjj', NULL, '123abc, Xã Ea Ô, Huyện Ea Kar, Tỉnh Đắk Lắk', NULL, NULL, NULL, NULL, 'cod', 'unpaid', 0, 'confirmed', 2999000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 2999000.00, 0.00, 1, '2026-06-29 14:06:05', NULL, NULL, NULL, NULL, NULL, '2026-06-18 00:37:25', '2026-06-29 14:06:05'),
	(7, 'ORD6A34216ED78A965', NULL, '15fb7eee6117890de97f5f08cc3f4f2daa6488db87b295619e4aae273ac1c31b', 'online', 8, NULL, 6, NULL, 'bccbc', '0906096666', NULL, 'ssss, Phường Tân An, Thành phố Buôn Ma Thuột, Đắk Lắk', NULL, NULL, NULL, 'ssssssssssss', 'cod', 'unpaid', 0, 'pending', 1299000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1299000.00, 0.00, 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-18 23:48:46', '2026-06-18 23:50:08'),
	(8, 'ORD6A342B0AEF27D72', 'LXA4W4', '2b9a517024d86c6886a68fdc2e40d95a974a8b853010d66a1aeeafc7a686a0e9', 'online', 8, NULL, 6, NULL, 'bccbc', '0906096666', NULL, 'ssss, Phường Tân An, Thành phố Buôn Ma Thuột, Đắk Lắk', NULL, NULL, NULL, NULL, 'cod', 'unpaid', 0, 'pending', 2999000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 2999000.00, 0.00, 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-19 00:29:46', '2026-06-19 00:37:28'),
	(9, 'ORD6A38E82914D4274', NULL, 'bf5b67f53c74716afccecfba75c329dfc60f19dae19daa2c10b925bf5a8fd823', 'online', 1, NULL, NULL, NULL, 'bcb', '0987878778', NULL, 'aaaasss, Xã Kim Cúc, Huyện Bảo Lạc, Tỉnh Cao Bằng', NULL, NULL, NULL, NULL, 'cod', 'paid', 0, 'completed', 539000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 539000.00, 0.00, 1, '2026-06-22 15:19:55', '2026-06-22 15:19:59', '2026-06-22 15:20:00', '2026-06-29 14:06:12', NULL, NULL, '2026-06-22 14:45:45', '2026-06-29 14:06:12'),
	(10, 'ORD6A39155C6B15016', NULL, '276fdf58191487d4787fce529d941a256d365a8915c5a4e62aed38402f8942c0', 'online', 1, NULL, NULL, NULL, 'bcb', '0905094644', NULL, '300/6 Ha Huy Tap, Phường Tân An, Thành phố Buôn Ma Thuột, Tỉnh Đắk Lắk', NULL, NULL, NULL, NULL, 'cod', 'unpaid', 0, 'pending', 1399000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1399000.00, 0.00, 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-22 17:58:36', '2026-06-22 18:00:11'),
	(11, 'ORD6A421ACF8E8F623', NULL, '57ea77d7bd504d6c9e05cbbfec48c097351700c5b1e2e90f4460fa2524b97f0b', 'online', 5, NULL, 4, NULL, 'bcbc', 'adssfhjj', NULL, '123abc, Xã Ea Ô, Huyện Ea Kar, Tỉnh Đắk Lắk', NULL, NULL, NULL, NULL, 'cod', 'paid', 0, 'delivered', 738000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 738000.00, 0.00, 1, '2026-06-29 14:24:48', '2026-06-29 14:25:00', '2026-06-29 14:25:11', NULL, NULL, NULL, '2026-06-29 14:12:15', '2026-06-29 14:25:11'),
	(12, 'ORD6A423E26677ED34', NULL, '3c219d7af36aa2f0d2b3dd49d96a6587c3a18a2e31d9938f1fff04cd20e39f18', 'online', 5, NULL, 4, NULL, 'bcbc', 'adssfhjj', NULL, '123abc, Xã Ea Ô, Huyện Ea Kar, Tỉnh Đắk Lắk', NULL, NULL, NULL, NULL, 'cod', 'paid', 0, 'completed', 1399000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1399000.00, 0.00, 1, '2026-06-29 17:02:13', '2026-06-29 17:02:19', '2026-06-29 17:02:26', '2026-06-29 17:02:29', NULL, NULL, '2026-06-29 16:43:02', '2026-06-29 17:02:29'),
	(13, 'ORD6A42497F20B7729', NULL, 'e2ae05204907f13211321f1640c119aeb3a357ebdb25c860d65103ced413d3b5', 'online', 1, NULL, NULL, NULL, 'bcb', '0905094644', NULL, '300/6 Ha Huy Tap, Phường Tân An, Thành phố Buôn Ma Thuột, Tỉnh Đắk Lắk', NULL, NULL, NULL, NULL, 'vnpay', 'failed', 0, 'cancelled', 2695000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 2695000.00, 0.00, 1, NULL, NULL, NULL, NULL, '2026-07-21 09:25:05', 'Hệ thống tự động hủy: quá thời hạn thanh toán (30 phút)', '2026-06-29 17:31:27', '2026-07-21 09:25:05'),
	(14, 'ORD6A58A23D80FE856', NULL, NULL, 'online', 9, NULL, 3, NULL, 'sss', '0905094644', 'binhbcpk03952@gmail.com', 'sss, Thị Trấn Si Ma Cai, Huyện Si Ma Cai, Lào Cai', 269, 2264, '90816', NULL, 'cod', 'unpaid', 0, 'pending', 4536000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4536000.00, 0.00, 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-16 16:19:57', '2026-07-16 16:21:08'),
	(15, 'ORD6A58D80083B6553', NULL, NULL, 'online', 9, NULL, 3, NULL, 'sss', '0905094644', 'binhbcpk03952@gmail.com', 'sss, Thị Trấn Si Ma Cai, Huyện Si Ma Cai, Lào Cai', 269, 2264, '90816', NULL, 'cod', 'unpaid', 0, 'pending', 1399000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1399000.00, 0.00, 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-16 20:09:20', '2026-07-16 20:11:07'),
	(16, 'ORD-F5F7444B-516', NULL, NULL, 'online', 1, NULL, NULL, NULL, 'bcb', '0905094644', 'admin123@gmail.com', '300/6 Ha Huy Tap, Phường Tân An, Thành phố Buôn Ma Thuột, Tỉnh Đắk Lắk', 66, 643, '24124', NULL, 'cod', 'paid', 0, 'completed', 99000.00, 29700.00, 0.00, 0.00, 0.00, 30000.00, 99300.00, 0.00, 1, '2026-07-21 23:10:53', '2026-07-21 23:11:02', '2026-07-21 23:11:04', '2026-07-21 23:11:20', NULL, NULL, '2026-07-21 23:10:20', '2026-07-21 23:12:08'),
	(17, 'ORD-D531EC9C-925', NULL, NULL, 'online', 1, NULL, NULL, NULL, 'bcb', '0905094644', 'admin123@gmail.com', '300/6 Ha Huy Tap, Phường Tân An, Thành phố Buôn Ma Thuột, Tỉnh Đắk Lắk', 66, 643, '24124', NULL, 'cod', 'paid', 0, 'return_requested', 7499000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 7499000.00, 0.00, 1, '2026-07-21 23:12:39', '2026-07-21 23:12:45', '2026-07-21 23:12:47', '2026-07-21 23:12:50', NULL, NULL, '2026-07-21 23:12:26', '2026-07-24 00:23:24'),
	(18, 'ORD-ACB8A294-965', NULL, '495a9bec48cc1e183b7860a920144ec76696cf535436dfec96254bbd8c9ce4ed', 'online', NULL, NULL, NULL, NULL, 'xxxxxxxx', '0900990009', 'binhbcpk03952@gmail.com', 'xxxxx, Xã Tân Quang, Huyện Văn Lâm, Hưng Yên', 268, 2046, '220909', NULL, 'cod', 'unpaid', 0, 'pending', 2518800.00, 0.00, 0.00, 0.00, 0.00, 0.00, 2518800.00, 0.00, 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-22 09:39:25', '2026-07-22 09:41:08'),
	(19, 'POS6A63A3B39E17767', NULL, NULL, 'online', 1, 1, NULL, NULL, 'sss', 'sssss', NULL, 'Mua tại cửa hàng', NULL, NULL, NULL, 'ssss', 'pos_cash', 'paid', 0, 'completed', 765600.00, 0.00, 0.00, 0.00, 0.00, 0.00, 765600.00, 0.00, 1, NULL, NULL, NULL, '2026-07-25 00:41:07', NULL, NULL, '2026-07-25 00:41:07', '2026-07-25 00:43:10');

-- Dumping structure for table ocean_db.password_reset_tokens
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.password_reset_tokens: ~0 rows (approximately)
DELETE FROM `password_reset_tokens`;

-- Dumping structure for table ocean_db.password_resets_otp
CREATE TABLE IF NOT EXISTS `password_resets_otp` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `otp` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `password_resets_otp_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.password_resets_otp: ~0 rows (approximately)
DELETE FROM `password_resets_otp`;

-- Dumping structure for table ocean_db.payments
CREATE TABLE IF NOT EXISTS `payments` (
  `payment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `payment_method` enum('cod','vnpay','momo','bank_transfer') COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_code` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` enum('pending','success','failed','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `paid_at` datetime DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `confirmed_source` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `post_payment_key` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `post_payment_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `post_payment_started_at` datetime DEFAULT NULL,
  `post_payment_processed_at` datetime DEFAULT NULL,
  `post_payment_source` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `post_payment_last_error` text COLLATE utf8mb4_unicode_ci,
  `gateway_response` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`payment_id`),
  UNIQUE KEY `payments_order_id_payment_method_unique` (`order_id`,`payment_method`),
  CONSTRAINT `payments_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.payments: ~2 rows (approximately)
DELETE FROM `payments`;
INSERT INTO `payments` (`payment_id`, `order_id`, `payment_method`, `transaction_code`, `amount`, `status`, `paid_at`, `confirmed_at`, `confirmed_source`, `post_payment_key`, `post_payment_status`, `post_payment_started_at`, `post_payment_processed_at`, `post_payment_source`, `post_payment_last_error`, `gateway_response`, `created_at`, `updated_at`) VALUES
	(1, 4, 'bank_transfer', NULL, 1299000.00, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-15 16:44:01', '2026-06-15 16:44:01'),
	(2, 13, 'vnpay', NULL, 2695000.00, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-29 17:31:27', '2026-06-29 17:31:27');

-- Dumping structure for table ocean_db.post_categories
CREATE TABLE IF NOT EXISTS `post_categories` (
  `post_category_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint unsigned DEFAULT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `thumbnail_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`post_category_id`),
  UNIQUE KEY `post_categories_slug_unique` (`slug`),
  KEY `post_categories_parent_id_foreign` (`parent_id`),
  CONSTRAINT `post_categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `post_categories` (`post_category_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.post_categories: ~0 rows (approximately)
DELETE FROM `post_categories`;

-- Dumping structure for table ocean_db.posts
CREATE TABLE IF NOT EXISTS `posts` (
  `post_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `post_category_id` bigint unsigned NOT NULL,
  `author_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `thumbnail_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `banner_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_keywords` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `post_type` enum('news','promotion','guide','review') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'news',
  `status` enum('draft','published','hidden') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `view_count` int NOT NULL DEFAULT '0',
  `published_at` datetime DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`post_id`),
  UNIQUE KEY `posts_slug_unique` (`slug`),
  KEY `posts_post_category_id_foreign` (`post_category_id`),
  KEY `posts_author_id_foreign` (`author_id`),
  CONSTRAINT `posts_author_id_foreign` FOREIGN KEY (`author_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `posts_post_category_id_foreign` FOREIGN KEY (`post_category_id`) REFERENCES `post_categories` (`post_category_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.posts: ~0 rows (approximately)
DELETE FROM `posts`;

-- Dumping structure for table ocean_db.product_comments
CREATE TABLE IF NOT EXISTS `product_comments` (
  `comment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `commenter_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user' COMMENT 'user = from users table, admin = from admins table',
  `order_item_id` bigint unsigned DEFAULT NULL,
  `rating` tinyint NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `images` json DEFAULT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`comment_id`),
  KEY `product_comments_product_id_foreign` (`product_id`),
  KEY `product_comments_user_id_foreign` (`user_id`),
  KEY `product_comments_order_item_id_foreign` (`order_item_id`),
  CONSTRAINT `product_comments_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`order_item_id`) ON DELETE SET NULL,
  CONSTRAINT `product_comments_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  CONSTRAINT `product_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.product_comments: ~2 rows (approximately)
DELETE FROM `product_comments`;
INSERT INTO `product_comments` (`comment_id`, `product_id`, `user_id`, `commenter_type`, `order_item_id`, `rating`, `content`, `images`, `is_approved`, `created_at`, `updated_at`) VALUES
	(1, 14, 1, 'user', 21, 5, '<p>////////////////////</p>', NULL, 1, '2026-07-22 00:29:04', '2026-07-23 22:06:21'),
	(2, 18, 1, 'user', 22, 5, '<p>lllllllllllllllllllll</p>', NULL, 1, '2026-07-22 00:29:05', '2026-07-23 22:06:16');

-- Dumping structure for table ocean_db.product_images
CREATE TABLE IF NOT EXISTS `product_images` (
  `image_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `variant_id` bigint unsigned DEFAULT NULL,
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alt_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_main` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`image_id`),
  KEY `product_images_product_id_foreign` (`product_id`),
  KEY `product_images_variant_id_foreign` (`variant_id`),
  CONSTRAINT `product_images_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  CONSTRAINT `product_images_variant_id_foreign` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`variant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2113 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.product_images: ~350 rows (approximately)
DELETE FROM `product_images`;
INSERT INTO `product_images` (`image_id`, `product_id`, `variant_id`, `image_url`, `alt_text`, `is_main`, `sort_order`, `created_at`) VALUES
	(61, 20, NULL, 'products/thumbnails/OKDXJ1J3zG2IjETN2RlZMvbc0Ciw7A0Wta243chf.webp', NULL, 1, 0, '2026-06-24 15:14:12'),
	(63, 166, NULL, '/storage/products/65fcc5ca-40a3-4ca6-9302-11d75a3a3602.webp', NULL, 1, 0, '2026-07-21 08:24:47'),
	(64, 166, NULL, '/storage/products/c24b74fd-4794-4ce1-8657-e7e6eabae4e9.webp', NULL, 0, 1, '2026-07-21 08:24:47'),
	(89, 167, NULL, '/storage/products/98b5a886-a3f4-4249-a290-eff59e0a7545.webp', NULL, 1, 0, '2026-07-21 08:25:01'),
	(90, 167, NULL, '/storage/products/9c343487-7b34-4d3e-94cb-a6c78cd47678.webp', NULL, 0, 1, '2026-07-21 08:25:01'),
	(91, 167, NULL, '/storage/products/8a54a2fd-7651-4fd5-8408-db6e90db78e8.webp', NULL, 0, 2, '2026-07-21 08:25:01'),
	(115, 168, NULL, '/storage/products/84d741b5-f214-44fd-9313-b2e499d2c023.webp', NULL, 1, 0, '2026-07-21 08:26:00'),
	(116, 168, NULL, '/storage/products/c4e231fd-8fc4-493b-8e3c-5513c12f13e0.webp', NULL, 0, 1, '2026-07-21 08:26:00'),
	(141, 169, NULL, '/storage/products/438154c9-5e99-49b6-8322-a78ab56d85a0.webp', NULL, 1, 0, '2026-07-21 08:26:14'),
	(142, 169, NULL, '/storage/products/60d8e05b-5269-4f6b-8791-e47a29fe3aab.webp', NULL, 0, 1, '2026-07-21 08:26:14'),
	(166, 170, NULL, '/storage/products/e2ea55d1-130e-4cdc-9af6-47cfd37619d5.webp', NULL, 1, 0, '2026-07-21 08:26:26'),
	(167, 170, NULL, '/storage/products/3b415fda-c1fb-4d48-bc0e-6c84e2c4a823.webp', NULL, 0, 1, '2026-07-21 08:26:26'),
	(168, 170, NULL, '/storage/products/a2a579b0-7593-4492-8a64-81f4660d29fa.webp', NULL, 0, 2, '2026-07-21 08:26:26'),
	(169, 170, NULL, '/storage/products/66cae3c1-03a5-4ec9-83d7-ae735a8e5f69.webp', NULL, 0, 3, '2026-07-21 08:26:26'),
	(170, 170, NULL, '/storage/products/93dbab84-931b-42e0-bb98-fb5297227e38.webp', NULL, 0, 4, '2026-07-21 08:26:26'),
	(194, 171, NULL, '/storage/products/dfe9856e-053a-4fb9-a651-324a2332cdaa.webp', NULL, 1, 0, '2026-07-21 08:26:40'),
	(195, 171, NULL, '/storage/products/687fda8f-84a5-4c7f-bc82-07b5e905e8a6.webp', NULL, 0, 1, '2026-07-21 08:26:40'),
	(196, 171, NULL, '/storage/products/9c0dcad2-0b41-43bb-beb8-505e9a9de2d7.webp', NULL, 0, 2, '2026-07-21 08:26:40'),
	(197, 171, NULL, '/storage/products/6bd0d500-83be-47f2-889d-8e70aa5b0c0b.webp', NULL, 0, 3, '2026-07-21 08:26:40'),
	(198, 171, NULL, '/storage/products/0345754e-6db0-4c96-bce9-e86580499867.webp', NULL, 0, 4, '2026-07-21 08:26:40'),
	(222, 172, NULL, '/storage/products/3f4dd8f6-a1c5-475f-baa8-8fc08955fbeb.webp', NULL, 1, 0, '2026-07-21 08:26:54'),
	(223, 172, NULL, '/storage/products/6f657aa8-ce3a-412f-bb6e-df3807beaf0b.webp', NULL, 0, 1, '2026-07-21 08:26:54'),
	(249, 173, NULL, '/storage/products/bdd70cbf-7bce-4345-8ab9-2a672a5abb25.webp', NULL, 1, 0, '2026-07-21 08:27:06'),
	(250, 173, NULL, '/storage/products/1fd8c33f-427c-4fc7-aeea-47fa4476662e.webp', NULL, 0, 1, '2026-07-21 08:27:06'),
	(274, 174, NULL, '/storage/products/86fac134-f2ca-457a-ab14-bbeed3bd689e.webp', NULL, 1, 0, '2026-07-21 08:27:17'),
	(275, 174, NULL, '/storage/products/d0e05984-aee2-44e1-ae58-729b5be9e155.webp', NULL, 0, 1, '2026-07-21 08:27:17'),
	(291, 175, NULL, '/storage/products/899492e4-ba06-428b-a9ed-545c68b579b1.webp', NULL, 1, 0, '2026-07-21 08:27:28'),
	(292, 175, NULL, '/storage/products/891975e6-06a2-4da3-ac20-06d7b9e47bd4.jpg', NULL, 0, 1, '2026-07-21 08:27:28'),
	(308, 176, NULL, '/storage/products/3ffcd1b7-bc96-4a66-acab-c038fe912d17.webp', NULL, 1, 0, '2026-07-21 08:27:36'),
	(309, 176, NULL, '/storage/products/d82e9550-f71a-4db5-a2bf-1a565763b964.webp', NULL, 0, 1, '2026-07-21 08:27:36'),
	(325, 177, NULL, '/storage/products/ca359c4c-8df3-4b59-8f06-c7c34bc54ab9.webp', NULL, 1, 0, '2026-07-21 08:27:47'),
	(326, 177, NULL, '/storage/products/4dd0840a-e571-4c15-ba9b-39c17418f645.webp', NULL, 0, 1, '2026-07-21 08:27:47'),
	(342, 178, NULL, '/storage/products/f971b034-a350-488d-bb3f-8d7dee4d100e.webp', NULL, 1, 0, '2026-07-21 08:27:58'),
	(343, 178, NULL, '/storage/products/a962b634-4eab-455e-88db-d0dc2fd34e4d.webp', NULL, 0, 1, '2026-07-21 08:27:58'),
	(359, 179, NULL, '/storage/products/e3c18f97-af88-4b3a-9e49-79e4e77728b9.webp', NULL, 1, 0, '2026-07-21 08:28:09'),
	(360, 179, NULL, '/storage/products/0b9ea47f-8fa4-43bf-9e0f-4e9ed6281a8f.webp', NULL, 0, 1, '2026-07-21 08:28:09'),
	(376, 180, NULL, '/storage/products/f5b95888-b98b-41c7-87d3-68f6e18f81fc.webp', NULL, 1, 0, '2026-07-21 08:28:19'),
	(377, 180, NULL, '/storage/products/16c07c68-9de6-43d3-b32a-0ba3884a6e81.webp', NULL, 0, 1, '2026-07-21 08:28:19'),
	(393, 181, NULL, '/storage/products/090e6016-437f-46d6-9d13-4f0ac818c574.webp', NULL, 1, 0, '2026-07-21 08:28:31'),
	(394, 181, NULL, '/storage/products/34d1b2e7-bb6e-403c-9875-ba0465f2e9d3.webp', NULL, 0, 1, '2026-07-21 08:28:31'),
	(395, 181, NULL, '/storage/products/67073f0e-3c2a-473f-a212-dc408006d78c.jpg', NULL, 0, 2, '2026-07-21 08:28:31'),
	(396, 181, NULL, '/storage/products/46e0b383-11aa-43b1-a848-12fdfe2f39a9.webp', NULL, 0, 3, '2026-07-21 08:28:31'),
	(397, 181, NULL, '/storage/products/436ad3da-3931-4258-b32e-8810993bf493.webp', NULL, 0, 4, '2026-07-21 08:28:31'),
	(398, 181, NULL, '/storage/products/626b62e3-3334-4a14-94a0-ee5796564417.webp', NULL, 0, 5, '2026-07-21 08:28:31'),
	(399, 181, NULL, '/storage/products/87dbad4c-5df3-455b-9147-eca23810df7b.webp', NULL, 0, 6, '2026-07-21 08:28:31'),
	(400, 181, NULL, '/storage/products/f0f6e187-83ba-4a78-80fe-ceca68176ba1.webp', NULL, 0, 7, '2026-07-21 08:28:31'),
	(401, 181, NULL, '/storage/products/a47e9850-d009-40a6-b1f0-4a4425eefcac.webp', NULL, 0, 8, '2026-07-21 08:28:31'),
	(402, 181, NULL, '/storage/products/01dad4f9-8349-4b07-84d3-d5b8a7f2564a.webp', NULL, 0, 9, '2026-07-21 08:28:31'),
	(403, 181, NULL, '/storage/products/4c43dd33-edba-4c76-878b-5c9ebe53be31.webp', NULL, 0, 10, '2026-07-21 08:28:31'),
	(404, 181, NULL, '/storage/products/b23d8a07-2491-4aeb-b1dc-c664f8dbb0c3.webp', NULL, 0, 11, '2026-07-21 08:28:31'),
	(405, 181, NULL, '/storage/products/0eb7c05e-64e0-4968-b49f-e74f5669750a.webp', NULL, 0, 12, '2026-07-21 08:28:31'),
	(406, 181, NULL, '/storage/products/3db7aa09-1a34-466f-9eb1-b5152924f9b0.gif', NULL, 0, 13, '2026-07-21 08:28:31'),
	(407, 181, NULL, '/storage/products/0d49dbb2-2db1-4912-9f6d-4ce546dd010a.webp', NULL, 0, 14, '2026-07-21 08:28:32'),
	(408, 181, NULL, '/storage/products/1ce06947-a51b-4fa8-8f42-e4c983649e1d.webp', NULL, 0, 15, '2026-07-21 08:28:32'),
	(409, 181, NULL, '/storage/products/a6020add-7002-4c52-a87c-d0aededc3168.webp', NULL, 0, 16, '2026-07-21 08:28:32'),
	(410, 181, NULL, '/storage/products/af55fd9b-6db0-495a-81ab-2b56ba8bc1c0.webp', NULL, 0, 17, '2026-07-21 08:28:32'),
	(413, 182, NULL, '/storage/products/48b421bc-62e9-4955-a7e1-defcb212f66a.webp', NULL, 1, 0, '2026-07-21 08:28:42'),
	(414, 182, NULL, '/storage/products/f15dd6c3-6824-4884-ae89-bd52a21960ff.webp', NULL, 0, 1, '2026-07-21 08:28:42'),
	(435, 183, NULL, '/storage/products/e95099e7-8d65-40a0-b124-b7707e071aed.webp', NULL, 1, 0, '2026-07-21 08:28:55'),
	(436, 183, NULL, '/storage/products/2d579cd1-0dd5-468b-8b4d-c4c9182a8f33.webp', NULL, 0, 1, '2026-07-21 08:28:55'),
	(457, 184, NULL, '/storage/products/b2091180-a285-4355-8fed-92ba8c763bbb.webp', NULL, 1, 0, '2026-07-21 08:30:40'),
	(458, 184, NULL, '/storage/products/c70fb429-2af7-4cab-adfd-c37be5e69a72.webp', NULL, 0, 1, '2026-07-21 08:30:40'),
	(459, 184, NULL, '/storage/products/262de4bc-39be-46ea-a823-8e4f9c7dc522.webp', NULL, 0, 2, '2026-07-21 08:30:40'),
	(460, 184, NULL, '/storage/products/99fd76d5-6c51-4755-a39f-8162cf182e39.webp', NULL, 0, 3, '2026-07-21 08:30:40'),
	(484, 185, NULL, '/storage/products/04aa7e03-3342-4a64-a244-d3e4f37ff3c1.webp', NULL, 1, 0, '2026-07-21 08:30:55'),
	(485, 185, NULL, '/storage/products/681a1d20-8ade-463b-a1a1-f1e1bf57c38e.webp', NULL, 0, 1, '2026-07-21 08:30:55'),
	(486, 185, NULL, '/storage/products/99393dbe-6265-40d4-9918-91103c981693.webp', NULL, 0, 2, '2026-07-21 08:30:55'),
	(487, 185, NULL, '/storage/products/5726a023-3f08-4195-89d3-2ceb214fbbc9.webp', NULL, 0, 3, '2026-07-21 08:30:55'),
	(511, 186, NULL, '/storage/products/98970777-c1f6-475f-93da-8e2234116d35.webp', NULL, 1, 0, '2026-07-21 08:33:10'),
	(512, 186, NULL, '/storage/products/f958fdb6-138c-46e3-9d78-9a858500fdf4.webp', NULL, 0, 1, '2026-07-21 08:33:10'),
	(513, 186, NULL, '/storage/products/3371da3f-dbc0-4ec2-bee9-4f9045fb0d43.webp', NULL, 0, 2, '2026-07-21 08:33:10'),
	(514, 186, NULL, '/storage/products/00a163bc-0487-4e68-9d64-5e20e5ccea1f.webp', NULL, 0, 3, '2026-07-21 08:33:10'),
	(515, 186, NULL, '/storage/products/ad427aba-f5f0-4fee-b504-9eba58f49baf.webp', NULL, 0, 4, '2026-07-21 08:33:10'),
	(516, 186, NULL, '/storage/products/17e99300-8187-4daf-975e-5f689fa624c5.webp', NULL, 0, 5, '2026-07-21 08:33:10'),
	(517, 186, NULL, '/storage/products/00f81992-b446-4321-90b2-1483c5db21be.webp', NULL, 0, 6, '2026-07-21 08:33:10'),
	(544, 187, NULL, '/storage/products/963eb6ad-d588-4729-a89b-e1c16d13e0b5.webp', NULL, 1, 0, '2026-07-21 08:33:26'),
	(545, 187, NULL, '/storage/products/f8f75991-fe56-4c63-8a16-f4b10c58bc94.webp', NULL, 0, 1, '2026-07-21 08:33:26'),
	(546, 187, NULL, '/storage/products/3f88ac2e-716c-4cc7-b68b-c6f49767df44.webp', NULL, 0, 2, '2026-07-21 08:33:26'),
	(547, 187, NULL, '/storage/products/4d4ccc73-eeb0-4cd2-9ed7-3f881e38f45b.webp', NULL, 0, 3, '2026-07-21 08:33:26'),
	(548, 187, NULL, '/storage/products/9b78c372-c3f3-4607-be43-6bc7789ea1b7.webp', NULL, 0, 4, '2026-07-21 08:33:26'),
	(549, 187, NULL, '/storage/products/68873162-a751-4f4a-a2d1-1eea471f0b8a.webp', NULL, 0, 5, '2026-07-21 08:33:26'),
	(550, 187, NULL, '/storage/products/de40a8a1-ee64-49ed-8a16-f4bd24730340.webp', NULL, 0, 6, '2026-07-21 08:33:26'),
	(577, 188, NULL, '/storage/products/73e54e86-3760-47ae-a10d-2654a32fd0e5.webp', NULL, 1, 0, '2026-07-21 08:33:43'),
	(578, 188, NULL, '/storage/products/2cb1a23a-6eed-4964-b789-73972323b925.webp', NULL, 0, 1, '2026-07-21 08:33:43'),
	(579, 188, NULL, '/storage/products/ad2d55b8-4830-42a5-94e4-28b84bef668f.webp', NULL, 0, 2, '2026-07-21 08:33:43'),
	(580, 188, NULL, '/storage/products/cefa0620-d24c-4998-84ee-301752ab9f5c.webp', NULL, 0, 3, '2026-07-21 08:33:43'),
	(581, 188, NULL, '/storage/products/9d8f281d-d475-4736-ba3f-7354e54d1d57.webp', NULL, 0, 4, '2026-07-21 08:33:43'),
	(582, 188, NULL, '/storage/products/300ffba5-bf68-4a66-a4ca-14f590128128.webp', NULL, 0, 5, '2026-07-21 08:33:43'),
	(609, 189, NULL, '/storage/products/3f43b297-c483-465d-9d58-e9bca5283d40.webp', NULL, 1, 0, '2026-07-21 08:33:58'),
	(610, 189, NULL, '/storage/products/c5ff11e9-d8bf-4452-b988-c05e47fa61de.webp', NULL, 0, 1, '2026-07-21 08:33:58'),
	(611, 189, NULL, '/storage/products/3681d661-f255-4654-9183-ad8934ef31af.webp', NULL, 0, 2, '2026-07-21 08:33:58'),
	(612, 189, NULL, '/storage/products/d19c827c-0141-4a96-9f11-5b279b36c5ef.webp', NULL, 0, 3, '2026-07-21 08:33:58'),
	(613, 189, NULL, '/storage/products/55577ee0-06a9-4164-a0bc-127d46d2aac9.webp', NULL, 0, 4, '2026-07-21 08:33:58'),
	(614, 189, NULL, '/storage/products/e18f048c-997e-484c-b69d-228f69c55b50.webp', NULL, 0, 5, '2026-07-21 08:33:58'),
	(615, 189, NULL, '/storage/products/d18c4e08-1935-405e-9788-770edb74e93b.webp', NULL, 0, 6, '2026-07-21 08:33:58'),
	(641, 190, NULL, '/storage/products/3210a0d5-e4eb-4592-ae29-c246deee7342.webp', NULL, 1, 0, '2026-07-21 08:34:15'),
	(642, 190, NULL, '/storage/products/21d9732b-673e-4847-883a-a8df26fc6e90.webp', NULL, 0, 1, '2026-07-21 08:34:15'),
	(643, 190, NULL, '/storage/products/7d870ac1-7390-4063-84f0-8962a89bc110.webp', NULL, 0, 2, '2026-07-21 08:34:15'),
	(644, 190, NULL, '/storage/products/1f89feed-2239-439f-9b83-1dd8f8e16e9a.webp', NULL, 0, 3, '2026-07-21 08:34:15'),
	(645, 190, NULL, '/storage/products/8c29bed0-84b8-4a79-ba2e-3ee8a50b9522.webp', NULL, 0, 4, '2026-07-21 08:34:15'),
	(646, 190, NULL, '/storage/products/dd200ebf-9a42-456e-84d7-53f0d1d09866.webp', NULL, 0, 5, '2026-07-21 08:34:15'),
	(671, 191, NULL, '/storage/products/6f148e06-4505-48c7-9e97-225e06c6eddb.webp', NULL, 1, 0, '2026-07-21 08:34:30'),
	(672, 191, NULL, '/storage/products/04c89fb4-9f2e-43ee-a4f3-284a6dd63de4.webp', NULL, 0, 1, '2026-07-21 08:34:30'),
	(673, 191, NULL, '/storage/products/c2c66bd7-f309-428a-9db0-7bd8729ed57c.webp', NULL, 0, 2, '2026-07-21 08:34:30'),
	(674, 191, NULL, '/storage/products/75fb5135-b7ce-49f0-9be5-fb0a99e603fd.webp', NULL, 0, 3, '2026-07-21 08:34:30'),
	(675, 191, NULL, '/storage/products/f1af54b1-8fa4-4181-b911-3ed233c702d4.webp', NULL, 0, 4, '2026-07-21 08:34:30'),
	(676, 191, NULL, '/storage/products/f894310e-ed81-4242-8dc0-97099a88d4fd.webp', NULL, 0, 5, '2026-07-21 08:34:30'),
	(677, 191, NULL, '/storage/products/bc1cdcfc-fc0a-45e7-918f-eef6594ded24.webp', NULL, 0, 6, '2026-07-21 08:34:30'),
	(701, 192, NULL, '/storage/products/991a02dc-c652-46c8-a7c9-59096d787e60.webp', NULL, 1, 0, '2026-07-21 08:34:46'),
	(702, 192, NULL, '/storage/products/e364ae94-4518-495e-b8f7-ce8342989845.webp', NULL, 0, 1, '2026-07-21 08:34:46'),
	(703, 192, NULL, '/storage/products/83e32636-b6ca-41e1-90bd-39b9f405010e.webp', NULL, 0, 2, '2026-07-21 08:34:46'),
	(704, 192, NULL, '/storage/products/1b56dc96-a0db-494b-88da-e47db7ed0a04.webp', NULL, 0, 3, '2026-07-21 08:34:46'),
	(705, 192, NULL, '/storage/products/2c071086-7921-4f1c-9b9d-706b57bd303f.webp', NULL, 0, 4, '2026-07-21 08:34:46'),
	(706, 192, NULL, '/storage/products/57367bd8-809d-46a6-8619-3475748e7732.webp', NULL, 0, 5, '2026-07-21 08:34:46'),
	(707, 192, NULL, '/storage/products/74378d65-10fa-4a3d-835e-ba4c7382b746.webp', NULL, 0, 6, '2026-07-21 08:34:46'),
	(730, 193, NULL, '/storage/products/026f9252-4579-4f19-a72a-740bfeb619ee.webp', NULL, 1, 0, '2026-07-21 08:35:08'),
	(731, 193, NULL, '/storage/products/feadccd1-ea7c-46eb-bd99-2214bb0c77db.webp', NULL, 0, 1, '2026-07-21 08:35:08'),
	(760, 194, NULL, '/storage/products/c28c22f6-378b-44ec-a990-d274d23105ce.webp', NULL, 1, 0, '2026-07-21 08:35:22'),
	(761, 194, NULL, '/storage/products/fcb59b03-0770-4ab1-b33b-a89c48900773.webp', NULL, 0, 1, '2026-07-21 08:35:22'),
	(789, 195, NULL, '/storage/products/6aa3cc68-484a-402d-ab0c-791c6ca156f9.webp', NULL, 1, 0, '2026-07-21 08:35:37'),
	(790, 195, NULL, '/storage/products/122505fd-e691-447e-8b6e-639fd2ba95d7.webp', NULL, 0, 1, '2026-07-21 08:35:37'),
	(817, 196, NULL, '/storage/products/89ae4a7a-b5e6-4f3b-bb8a-51951bd15360.webp', NULL, 1, 0, '2026-07-21 08:35:50'),
	(818, 196, NULL, '/storage/products/4afc059b-6d3f-4f05-85db-d60f714c2d3b.webp', NULL, 0, 1, '2026-07-21 08:35:50'),
	(844, 197, NULL, '/storage/products/e1b0f6f0-a316-43b4-9d7d-4a20f68d6690.webp', NULL, 1, 0, '2026-07-21 08:36:04'),
	(845, 197, NULL, '/storage/products/adae0713-12f0-49ca-aa65-9dd814cc5315.webp', NULL, 0, 1, '2026-07-21 08:36:04'),
	(870, 198, NULL, '/storage/products/f9f64a37-36f3-417e-8443-1172e1e67c45.webp', NULL, 1, 0, '2026-07-21 08:36:19'),
	(894, 199, NULL, '/storage/products/24f742be-420d-4939-b248-b8a7b2ceb79f.webp', NULL, 1, 0, '2026-07-21 08:36:30'),
	(917, 200, NULL, '/storage/products/d7f4be6c-68fe-44f8-a890-6a31ee755f98.webp', NULL, 1, 0, '2026-07-21 08:36:49'),
	(918, 200, NULL, '/storage/products/bb99b9c9-f6fc-4b10-9447-7446c2401710.webp', NULL, 0, 1, '2026-07-21 08:36:49'),
	(919, 200, NULL, '/storage/products/cb4ed4f3-1a9c-42ea-9244-f1654b220a66.webp', NULL, 0, 2, '2026-07-21 08:36:49'),
	(920, 200, NULL, '/storage/products/6b5a0a02-e3bc-405e-9fbb-3fce2540c1e9.webp', NULL, 0, 3, '2026-07-21 08:36:49'),
	(937, 201, NULL, '/storage/products/b803f2f5-e032-467f-b956-9e44e73d9a13.jpg', NULL, 1, 0, '2026-07-21 08:38:40'),
	(938, 201, NULL, '/storage/products/65ad713d-f7bc-42c8-86c1-928cee8f9e32.jpg', NULL, 0, 1, '2026-07-21 08:38:40'),
	(939, 201, NULL, '/storage/products/11bcf399-f4e3-4597-ad05-6369ff32c35a.jpg', NULL, 0, 2, '2026-07-21 08:38:40'),
	(958, 202, NULL, '/storage/products/d179fa9f-8201-41f6-a466-cbecabce0610.webp', NULL, 1, 0, '2026-07-21 08:39:59'),
	(960, 202, NULL, '/storage/products/585adca3-4e35-4086-8a75-afaa603a053a.webp', NULL, 0, 2, '2026-07-21 08:39:59'),
	(970, 203, NULL, '/storage/products/8787c4f0-cca6-4927-8698-aec3a96a24c9.webp', NULL, 1, 0, '2026-07-21 08:41:29'),
	(971, 203, NULL, '/storage/products/c69a91d8-faf0-4604-959b-1f3e6645926f.webp', NULL, 0, 1, '2026-07-21 08:41:29'),
	(972, 203, NULL, '/storage/products/97b22701-c5f9-4abf-a202-eef62738a513.webp', NULL, 0, 2, '2026-07-21 08:41:29'),
	(973, 203, NULL, '/storage/products/5f43b621-bc94-4446-8798-e6f607c39c08.webp', NULL, 0, 3, '2026-07-21 08:41:29'),
	(974, 203, NULL, '/storage/products/04dd14bd-ca71-47db-9516-393a9ca36136.webp', NULL, 0, 4, '2026-07-21 08:41:29'),
	(975, 203, NULL, '/storage/products/1c781e81-51f9-4c4b-a8c6-60f7c06fed48.webp', NULL, 0, 5, '2026-07-21 08:41:29'),
	(989, 204, NULL, '/storage/products/582e3d25-efe3-4399-bf6b-3b138c9a4008.webp', NULL, 1, 0, '2026-07-21 08:43:51'),
	(990, 204, NULL, '/storage/products/947c4a93-b015-4058-9480-3a7a14bdf83b.webp', NULL, 0, 1, '2026-07-21 08:43:51'),
	(991, 204, NULL, '/storage/products/9dbb23e8-c88b-45f3-91ce-6214f540c756.webp', NULL, 0, 2, '2026-07-21 08:43:51'),
	(992, 204, NULL, '/storage/products/fb2bd9c8-d15f-4a72-8de4-49e3b39d563d.webp', NULL, 0, 3, '2026-07-21 08:43:51'),
	(994, 204, NULL, '/storage/products/7bc32c1b-befe-4bbf-968b-1d9cb1fcbe06.webp', NULL, 0, 5, '2026-07-21 08:43:51'),
	(1009, 205, NULL, '/storage/products/291dc1fb-00e1-4873-9296-b0aefbf3007c.webp', NULL, 1, 0, '2026-07-21 08:44:04'),
	(1010, 205, NULL, '/storage/products/2e05f1ac-b07b-4aa0-912e-082b72d19082.webp', NULL, 0, 1, '2026-07-21 08:44:04'),
	(1011, 205, NULL, '/storage/products/a244884b-8efc-45dc-ba4b-d6a4c957624c.webp', NULL, 0, 2, '2026-07-21 08:44:04'),
	(1012, 205, NULL, '/storage/products/fa3e7e95-60a3-4192-947d-75cda475b8c8.webp', NULL, 0, 3, '2026-07-21 08:44:04'),
	(1013, 205, NULL, '/storage/products/5a2561c6-479a-4808-9ee0-f3aa3434297e.webp', NULL, 0, 4, '2026-07-21 08:44:04'),
	(1014, 205, NULL, '/storage/products/8a6da9e9-7fb4-45f4-a8a0-236509be2543.webp', NULL, 0, 5, '2026-07-21 08:44:04'),
	(1032, 206, NULL, '/storage/products/0c3ebf11-0b73-40f9-b38a-ab98c07949df.webp', NULL, 1, 0, '2026-07-21 08:44:16'),
	(1033, 206, NULL, '/storage/products/d85f662f-bc22-4cc0-b59a-37d446fb4abb.webp', NULL, 0, 1, '2026-07-21 08:44:16'),
	(1034, 206, NULL, '/storage/products/805f8a4e-86f0-4c40-81ae-afe0342117d4.webp', NULL, 0, 2, '2026-07-21 08:44:16'),
	(1035, 206, NULL, '/storage/products/ca78e98c-2186-4059-8d79-224351dc9e2f.webp', NULL, 0, 3, '2026-07-21 08:44:16'),
	(1036, 206, NULL, '/storage/products/ffa6b33a-ff1f-4d04-b7d1-9bdca332488c.webp', NULL, 0, 4, '2026-07-21 08:44:16'),
	(1054, 207, NULL, '/storage/products/1d36d70c-ddc2-4850-9023-523dce116210.webp', NULL, 1, 0, '2026-07-21 08:44:29'),
	(1055, 207, NULL, '/storage/products/80a69c67-805f-4ddb-8918-c67c1ebf384c.webp', NULL, 0, 1, '2026-07-21 08:44:29'),
	(1056, 207, NULL, '/storage/products/bb4d8208-04bb-4d77-8214-fa301f6a6022.webp', NULL, 0, 2, '2026-07-21 08:44:29'),
	(1057, 207, NULL, '/storage/products/fd2da8d4-4219-4a8e-bb89-42ae956e7342.webp', NULL, 0, 3, '2026-07-21 08:44:29'),
	(1058, 207, NULL, '/storage/products/1f6087aa-11be-4640-8c4a-1acb4e9b6e9c.webp', NULL, 0, 4, '2026-07-21 08:44:29'),
	(1076, 208, NULL, '/storage/products/da7ca016-4548-4d7f-be7b-6206570120fa.webp', NULL, 1, 0, '2026-07-21 08:48:19'),
	(1077, 208, NULL, '/storage/products/6356416b-d5c7-401e-b54b-7e96b1258e72.webp', NULL, 0, 1, '2026-07-21 08:48:19'),
	(1078, 208, NULL, '/storage/products/202da3de-d8e2-4bc4-8059-c99fce99a616.webp', NULL, 0, 2, '2026-07-21 08:48:19'),
	(1079, 208, NULL, '/storage/products/5c55cde0-e27f-4e2b-8ad4-f97f178cc6c5.webp', NULL, 0, 3, '2026-07-21 08:48:19'),
	(1080, 208, NULL, '/storage/products/aedae1cc-bfcb-49a7-8111-3ac3df9387b4.webp', NULL, 0, 4, '2026-07-21 08:48:19'),
	(1096, 209, NULL, '/storage/products/743617ef-9b47-4de4-9f38-04323f78566b.webp', NULL, 1, 0, '2026-07-21 08:48:29'),
	(1097, 209, NULL, '/storage/products/226ed933-4d10-4e95-b841-eff784c59499.webp', NULL, 0, 1, '2026-07-21 08:48:29'),
	(1098, 209, NULL, '/storage/products/ac1de38c-b416-4bd5-bded-da6a43f8c62a.webp', NULL, 0, 2, '2026-07-21 08:48:29'),
	(1099, 209, NULL, '/storage/products/38af38cc-e124-478a-b225-9c35a86f340d.webp', NULL, 0, 3, '2026-07-21 08:48:29'),
	(1100, 209, NULL, '/storage/products/53d20cc5-abf9-4892-98f7-d7631591dca0.webp', NULL, 0, 4, '2026-07-21 08:48:29'),
	(1101, 209, NULL, '/storage/products/e96b8ac3-b1ce-4593-a19b-9b41f12fcb6f.webp', NULL, 0, 5, '2026-07-21 08:48:29'),
	(1102, 209, NULL, '/storage/products/7d7b5b66-e3cc-488e-b7cc-9636d72118bf.jpg', NULL, 0, 6, '2026-07-21 08:48:29'),
	(1103, 209, NULL, '/storage/products/67d89f83-3c13-46cc-b030-4821b448b3bd.webp', NULL, 0, 7, '2026-07-21 08:48:29'),
	(1104, 209, NULL, '/storage/products/e0c4bd40-6d1b-45e2-b4b8-7bb3d23426b3.webp', NULL, 0, 8, '2026-07-21 08:48:29'),
	(1105, 209, NULL, '/storage/products/d9595c05-5552-4224-bee7-cd9057d50bfb.webp', NULL, 0, 9, '2026-07-21 08:48:29'),
	(1106, 209, NULL, '/storage/products/a4a9be0f-ae64-41fc-9df2-3c1a8cb0a5dc.webp', NULL, 0, 10, '2026-07-21 08:48:29'),
	(1107, 209, NULL, '/storage/products/6e6cabcd-4dd5-484d-ab20-fd88376cbaa2.webp', NULL, 0, 11, '2026-07-21 08:48:29'),
	(1108, 209, NULL, '/storage/products/d3c37b34-1044-4292-8666-2a36e47101a3.webp', NULL, 0, 12, '2026-07-21 08:48:29'),
	(1109, 209, NULL, '/storage/products/7896a8ff-5f69-4125-95d2-fd9b19e8bd85.webp', NULL, 0, 13, '2026-07-21 08:48:29'),
	(1112, 210, NULL, '/storage/products/6eda8763-24b6-419c-a422-61bb1b3583a1.webp', NULL, 1, 0, '2026-07-21 08:48:39'),
	(1113, 210, NULL, '/storage/products/e01e36b0-874f-4613-ad8a-19d8dc73b25e.webp', NULL, 0, 1, '2026-07-21 08:48:39'),
	(1114, 210, NULL, '/storage/products/86e10a5a-becf-47ae-a8f9-f9b257eb6d81.webp', NULL, 0, 2, '2026-07-21 08:48:39'),
	(1115, 210, NULL, '/storage/products/5f33e38d-763b-4ef3-b22c-ece64f77e238.webp', NULL, 0, 3, '2026-07-21 08:48:39'),
	(1116, 210, NULL, '/storage/products/417de635-1709-493f-afa0-855495c4a20d.webp', NULL, 0, 4, '2026-07-21 08:48:39'),
	(1128, 211, NULL, '/storage/products/ebbd4249-52a2-414f-bf99-445fd7f7ff56.webp', NULL, 1, 0, '2026-07-21 08:49:16'),
	(1129, 211, NULL, '/storage/products/0f97a968-c3d4-40cd-91f0-883a886e3d96.webp', NULL, 0, 1, '2026-07-21 08:49:16'),
	(1130, 211, NULL, '/storage/products/6c932c5c-1a62-4613-bbcc-c84786d0b059.webp', NULL, 0, 2, '2026-07-21 08:49:16'),
	(1148, 212, NULL, '/storage/products/fc411bc5-40f3-4c90-baa5-814a0ca72bcf.webp', NULL, 1, 0, '2026-07-21 08:51:01'),
	(1149, 212, NULL, '/storage/products/d5a1a110-e2e2-4ce8-bb3d-dd7b8bfba6d0.webp', NULL, 0, 1, '2026-07-21 08:51:01'),
	(1150, 212, NULL, '/storage/products/b22549e1-ab87-46eb-a3ad-66578080e562.webp', NULL, 0, 2, '2026-07-21 08:51:01'),
	(1169, 213, NULL, '/storage/products/c990050d-3cf0-4a87-93e3-3bd338be5695.webp', NULL, 1, 0, '2026-07-21 08:51:21'),
	(1170, 213, NULL, '/storage/products/ce3ba37b-9909-4b3f-9d32-e8fdaebb4d01.webp', NULL, 0, 1, '2026-07-21 08:51:21'),
	(1171, 213, NULL, '/storage/products/7e07762c-80f0-4168-a5db-a44ff5faf1a1.webp', NULL, 0, 2, '2026-07-21 08:51:21'),
	(1172, 213, NULL, '/storage/products/08ec9eb9-924c-45dc-9ad8-8606242dadac.webp', NULL, 0, 3, '2026-07-21 08:51:21'),
	(1173, 213, NULL, '/storage/products/6dc0d2ff-b125-49f2-b40f-c65bf1bb64bc.webp', NULL, 0, 4, '2026-07-21 08:51:21'),
	(1174, 213, NULL, '/storage/products/1d3d297b-3793-449d-bff3-54a79a177f87.webp', NULL, 0, 5, '2026-07-21 08:51:21'),
	(1198, 214, NULL, '/storage/products/95ce6218-a99b-477b-adcb-f103dacec50a.webp', NULL, 1, 0, '2026-07-21 08:51:54'),
	(1199, 214, NULL, '/storage/products/c3b5c814-9155-40be-8214-0d16736826fa.webp', NULL, 0, 1, '2026-07-21 08:51:54'),
	(1200, 214, NULL, '/storage/products/5065d3da-096f-45b4-ac55-1de519984cb3.webp', NULL, 0, 2, '2026-07-21 08:51:54'),
	(1201, 214, NULL, '/storage/products/f9fa8b1d-120e-4fb2-9e2a-17dcd157bf33.webp', NULL, 0, 3, '2026-07-21 08:51:54'),
	(1202, 214, NULL, '/storage/products/81d30b8c-b2ec-4d4d-929d-912ee2ca0d87.webp', NULL, 0, 4, '2026-07-21 08:51:54'),
	(1203, 214, NULL, '/storage/products/714624ae-91fd-4ea0-8b9e-c64eace8b5a5.webp', NULL, 0, 5, '2026-07-21 08:51:54'),
	(1221, 215, NULL, '/storage/products/1c2169e4-4ba2-4d74-a442-6be9e4d8bc45.webp', NULL, 1, 0, '2026-07-21 08:52:18'),
	(1222, 215, NULL, '/storage/products/422095bb-5f59-48aa-858c-3357166cb0fa.webp', NULL, 0, 1, '2026-07-21 08:52:18'),
	(1223, 215, NULL, '/storage/products/994f5584-0e06-42ac-ae13-476de54ab534.webp', NULL, 0, 2, '2026-07-21 08:52:18'),
	(1224, 215, NULL, '/storage/products/6db63bd8-0533-452e-b7c0-3e7b1d7c5e38.webp', NULL, 0, 3, '2026-07-21 08:52:18'),
	(1225, 215, NULL, '/storage/products/857300a1-4091-4105-b428-fb820a39ee58.webp', NULL, 0, 4, '2026-07-21 08:52:18'),
	(1226, 215, NULL, '/storage/products/e3b95bfa-2f4b-4604-a7ea-3774d3ae5555.webp', NULL, 0, 5, '2026-07-21 08:52:18'),
	(1244, 216, NULL, '/storage/products/fb4b72fa-7524-46d2-8430-2fbfa7784505.webp', NULL, 1, 0, '2026-07-21 08:52:30'),
	(1245, 216, NULL, '/storage/products/079d5411-8ba4-42dc-b02b-fb5499404d05.webp', NULL, 0, 1, '2026-07-21 08:52:30'),
	(1246, 216, NULL, '/storage/products/ac52a9b5-59e5-45e8-b5da-6a6ab0236a40.webp', NULL, 0, 2, '2026-07-21 08:52:30'),
	(1247, 216, NULL, '/storage/products/def102d6-f65e-4c9e-9c53-5857d258a1cc.webp', NULL, 0, 3, '2026-07-21 08:52:30'),
	(1248, 216, NULL, '/storage/products/ac22c42e-1ae6-4abd-a0be-0f0c1792f86c.webp', NULL, 0, 4, '2026-07-21 08:52:30'),
	(1249, 216, NULL, '/storage/products/de987d1f-c71f-4e80-8f70-d3560300d9c5.webp', NULL, 0, 5, '2026-07-21 08:52:30'),
	(1268, 217, NULL, '/storage/products/ee5ace7c-ff72-488a-8dca-4cb71bc96e2d.webp', NULL, 1, 0, '2026-07-21 08:52:42'),
	(1269, 217, NULL, '/storage/products/c075932d-c695-434c-81c3-00b4611e3dc9.webp', NULL, 0, 1, '2026-07-21 08:52:42'),
	(1270, 217, NULL, '/storage/products/98735d51-75b0-4bdf-9e14-3afeb362302d.webp', NULL, 0, 2, '2026-07-21 08:52:42'),
	(1271, 217, NULL, '/storage/products/05ec9f1d-d20f-481d-93f7-fd43339e0d28.webp', NULL, 0, 3, '2026-07-21 08:52:42'),
	(1272, 217, NULL, '/storage/products/e4ca7b91-c830-4588-8b2b-9631ce3e4be7.webp', NULL, 0, 4, '2026-07-21 08:52:42'),
	(1273, 217, NULL, '/storage/products/d56c72f7-ca77-4c7c-945f-46195cc785ff.webp', NULL, 0, 5, '2026-07-21 08:52:42'),
	(1292, 218, NULL, '/storage/products/741bd223-d9d8-4bc4-966d-1a71862d1890.webp', NULL, 1, 0, '2026-07-21 08:52:55'),
	(1293, 218, NULL, '/storage/products/a318cd1e-6346-42f5-a50a-76aef7c6472f.webp', NULL, 0, 1, '2026-07-21 08:52:55'),
	(1294, 218, NULL, '/storage/products/5e24fcb7-b09e-4c36-b664-1959b80dff2e.webp', NULL, 0, 2, '2026-07-21 08:52:55'),
	(1295, 218, NULL, '/storage/products/e59750f7-5015-46c8-a052-855f3867c8eb.webp', NULL, 0, 3, '2026-07-21 08:52:55'),
	(1296, 218, NULL, '/storage/products/ff4d6b49-68f0-4c34-a66d-baf16b0b3b86.webp', NULL, 0, 4, '2026-07-21 08:52:55'),
	(1315, 219, NULL, '/storage/products/a2b31c5f-03f1-4653-977a-12fa8d762391.webp', NULL, 1, 0, '2026-07-21 08:53:07'),
	(1316, 219, NULL, '/storage/products/be7cd10c-3326-48dd-9d46-c49fca55f7c1.webp', NULL, 0, 1, '2026-07-21 08:53:07'),
	(1317, 219, NULL, '/storage/products/a8c5d44f-d185-4812-b30e-e884bb59ec55.webp', NULL, 0, 2, '2026-07-21 08:53:07'),
	(1318, 219, NULL, '/storage/products/3fb1348b-9448-4fb5-9aad-f5c11f158eb2.webp', NULL, 0, 3, '2026-07-21 08:53:07'),
	(1319, 219, NULL, '/storage/products/a82d94a8-6342-428d-94fd-205e97f24029.webp', NULL, 0, 4, '2026-07-21 08:53:07'),
	(1320, 219, NULL, '/storage/products/16ba2043-afb0-400c-9e81-1b4a0cbf6df1.webp', NULL, 0, 5, '2026-07-21 08:53:07'),
	(1336, 219, NULL, '/storage/products/d95d056b-64ba-4086-aa91-6b532fd4e899.webp', NULL, 0, 21, '2026-07-21 08:53:07'),
	(1339, 220, NULL, '/storage/products/e0fd87d5-9750-40e7-b013-725d0960b173.webp', NULL, 1, 0, '2026-07-21 08:53:21'),
	(1340, 220, NULL, '/storage/products/8aa26d72-3da6-4e52-8d24-b4bffcabced0.webp', NULL, 0, 1, '2026-07-21 08:53:21'),
	(1341, 220, NULL, '/storage/products/74d18c24-5bc0-4aa1-b5f9-0cffc8ec6dee.jpg', NULL, 0, 2, '2026-07-21 08:53:21'),
	(1342, 220, NULL, '/storage/products/28e6ffd6-91ab-4b26-906e-b0b7844cd141.jpg', NULL, 0, 3, '2026-07-21 08:53:21'),
	(1371, 221, NULL, '/storage/products/089c1258-3614-4cdb-9377-f9f303d55397.webp', NULL, 1, 0, '2026-07-21 08:53:36'),
	(1372, 221, NULL, '/storage/products/3d3bd6cd-95e8-4803-b80b-4a6ccb3eaf55.webp', NULL, 0, 1, '2026-07-21 08:53:36'),
	(1403, 222, NULL, '/storage/products/ae7b2ea7-0a3a-4aa5-a467-4c1e4b172ae6.webp', NULL, 1, 0, '2026-07-21 08:53:52'),
	(1404, 222, NULL, '/storage/products/213213c0-a743-4b14-99eb-a5d7d221c7b3.webp', NULL, 0, 1, '2026-07-21 08:53:52'),
	(1405, 222, NULL, '/storage/products/1a5c10ed-8b4f-49db-8ca5-00f2eca7e64b.webp', NULL, 0, 2, '2026-07-21 08:53:52'),
	(1406, 222, NULL, '/storage/products/75119d60-12ed-43d6-9f78-c37c1ded4ff5.webp', NULL, 0, 3, '2026-07-21 08:53:52'),
	(1407, 222, NULL, '/storage/products/bdfa020c-1a7b-419e-8b20-dbc5b9690543.webp', NULL, 0, 4, '2026-07-21 08:53:52'),
	(1435, 223, NULL, '/storage/products/bf5a2fc5-864d-4407-8e5b-1c52bd55e8a5.webp', NULL, 1, 0, '2026-07-21 08:54:06'),
	(1436, 223, NULL, '/storage/products/8e6373d8-cf40-49b9-bcb8-a85354c102eb.webp', NULL, 0, 1, '2026-07-21 08:54:06'),
	(1437, 223, NULL, '/storage/products/7ce0f2d9-70b4-4709-8214-6d3ea1424c13.webp', NULL, 0, 2, '2026-07-21 08:54:06'),
	(1438, 223, NULL, '/storage/products/5d03fe2f-3e85-4bb7-acba-cfa178637199.webp', NULL, 0, 3, '2026-07-21 08:54:06'),
	(1439, 223, NULL, '/storage/products/ba91ba6b-ef13-4319-b54d-4ca21b585bb7.webp', NULL, 0, 4, '2026-07-21 08:54:06'),
	(1466, 224, NULL, '/storage/products/d8dbd9e1-d9f3-403e-a1fc-d955b8213c80.webp', NULL, 1, 0, '2026-07-21 08:54:21'),
	(1467, 224, NULL, '/storage/products/880b0e03-2743-47db-8d29-2b6579becca6.webp', NULL, 0, 1, '2026-07-21 08:54:21'),
	(1468, 224, NULL, '/storage/products/3f4b60a1-fb64-4248-b4f6-857bc240ddc4.webp', NULL, 0, 2, '2026-07-21 08:54:21'),
	(1469, 224, NULL, '/storage/products/e83546ec-2bb4-40e2-8c6f-1468c70b66f7.webp', NULL, 0, 3, '2026-07-21 08:54:22'),
	(1470, 224, NULL, '/storage/products/515af52f-70d6-4962-ba4a-2c4cf56152fe.webp', NULL, 0, 4, '2026-07-21 08:54:22'),
	(1501, 225, NULL, '/storage/products/f918d114-f114-4ae6-bb87-e065a0f7240f.webp', NULL, 1, 0, '2026-07-21 08:54:37'),
	(1502, 225, NULL, '/storage/products/cbace4ed-03d4-4cba-8488-367319d93623.webp', NULL, 0, 1, '2026-07-21 08:54:37'),
	(1503, 225, NULL, '/storage/products/c928c01a-4bbc-43f7-bb99-225cabadf6e4.webp', NULL, 0, 2, '2026-07-21 08:54:37'),
	(1504, 225, NULL, '/storage/products/31d021b1-9711-4ab7-82b6-18d2961d8e32.webp', NULL, 0, 3, '2026-07-21 08:54:37'),
	(1505, 225, NULL, '/storage/products/b8dedb07-e782-406e-b647-941ef87787cf.webp', NULL, 0, 4, '2026-07-21 08:54:37'),
	(1533, 226, NULL, '/storage/products/72b710a6-2931-4dd7-a229-887e527bcf80.webp', NULL, 1, 0, '2026-07-21 08:54:50'),
	(1534, 226, NULL, '/storage/products/91dcdc01-1331-4260-b567-b9ea26ce326a.webp', NULL, 0, 1, '2026-07-21 08:54:50'),
	(1535, 226, NULL, '/storage/products/d7aae608-955b-48a4-a61b-166d13de34cd.webp', NULL, 0, 2, '2026-07-21 08:54:50'),
	(1536, 226, NULL, '/storage/products/d84d259f-b59c-4aed-80f9-fd7ed03b6901.webp', NULL, 0, 3, '2026-07-21 08:54:50'),
	(1537, 226, NULL, '/storage/products/68ecea10-b541-4068-9d06-8dcde48504f9.webp', NULL, 0, 4, '2026-07-21 08:54:50'),
	(1564, 227, NULL, '/storage/products/51a6e7b7-5305-4a6f-918e-c032ab28deb4.webp', NULL, 1, 0, '2026-07-21 08:55:05'),
	(1565, 227, NULL, '/storage/products/99fcab8f-fd4e-4034-b074-25e6d1449b31.webp', NULL, 0, 1, '2026-07-21 08:55:05'),
	(1566, 227, NULL, '/storage/products/1b128a76-310d-424f-9960-a04f97f57acc.webp', NULL, 0, 2, '2026-07-21 08:55:05'),
	(1567, 227, NULL, '/storage/products/67c7d57f-c9b7-4168-8691-d6be1a1f87ba.webp', NULL, 0, 3, '2026-07-21 08:55:05'),
	(1568, 227, NULL, '/storage/products/4d2c8f14-aba2-40bf-8951-fcce7666b47d.webp', NULL, 0, 4, '2026-07-21 08:55:05'),
	(1596, 228, NULL, '/storage/products/0bc5530f-8d10-4e8e-9bb6-0ffa33f197e3.webp', NULL, 1, 0, '2026-07-21 08:55:19'),
	(1597, 228, NULL, '/storage/products/ff5c3fbe-d877-46dd-80ce-daccebd23430.webp', NULL, 0, 1, '2026-07-21 08:55:19'),
	(1598, 228, NULL, '/storage/products/44591fb2-2700-4fe9-b024-809407bbe81e.webp', NULL, 0, 2, '2026-07-21 08:55:19'),
	(1599, 228, NULL, '/storage/products/708c37ea-8c34-4202-9f23-ddb59c73ae07.webp', NULL, 0, 3, '2026-07-21 08:55:20'),
	(1600, 228, NULL, '/storage/products/83819d4d-e7fc-4ef0-bd31-6cfb7ab601c2.webp', NULL, 0, 4, '2026-07-21 08:55:20'),
	(1628, 229, NULL, '/storage/products/ae50e509-93b4-42c1-95f8-594a069de502.webp', NULL, 1, 0, '2026-07-21 08:55:35'),
	(1629, 229, NULL, '/storage/products/ac7ac848-5c03-4d0e-9837-aa2a39a6ae3a.webp', NULL, 0, 1, '2026-07-21 08:55:35'),
	(1630, 229, NULL, '/storage/products/79b288f7-6798-4fd2-84d3-47f5aa37bf9e.webp', NULL, 0, 2, '2026-07-21 08:55:35'),
	(1659, 230, NULL, '/storage/products/1ce16836-a1a8-4de6-a67d-fe20a066de2a.webp', NULL, 1, 0, '2026-07-21 08:55:48'),
	(1660, 230, NULL, '/storage/products/4a039b73-a71d-4144-820f-3033f28f4fea.webp', NULL, 0, 1, '2026-07-21 08:55:48'),
	(1661, 230, NULL, '/storage/products/da8d755d-f858-459c-aad9-09c78921011c.webp', NULL, 0, 2, '2026-07-21 08:55:48'),
	(1689, 231, NULL, '/storage/products/6d3d7a08-6537-49cb-bb84-efe9ba55b580.webp', NULL, 1, 0, '2026-07-21 08:56:02'),
	(1690, 231, NULL, '/storage/products/3bde0f27-33d8-4ccc-8d1e-42e8fc8ce156.webp', NULL, 0, 1, '2026-07-21 08:56:02'),
	(1717, 232, NULL, '/storage/products/723631b9-da75-430f-9139-5adc4eda9010.webp', NULL, 1, 0, '2026-07-21 08:56:15'),
	(1718, 232, NULL, '/storage/products/aa0623c3-a6e4-41cb-91ef-0bb7e97fa50e.webp', NULL, 0, 1, '2026-07-21 08:56:15'),
	(1719, 232, NULL, '/storage/products/27eaa7f2-f560-468b-be89-e357ec8758b5.webp', NULL, 0, 2, '2026-07-21 08:56:15'),
	(1745, 233, NULL, '/storage/products/bc10ff10-9e4f-4691-be69-35094c5c5a29.webp', NULL, 1, 0, '2026-07-21 08:56:30'),
	(1746, 233, NULL, '/storage/products/b7d8a338-0e44-4dc3-844f-4b74dfc6346d.webp', NULL, 0, 1, '2026-07-21 08:56:30'),
	(1747, 233, NULL, '/storage/products/7a5c998e-4db9-41c8-962a-d122a1aea475.webp', NULL, 0, 2, '2026-07-21 08:56:30'),
	(1748, 233, NULL, '/storage/products/acf91400-7394-4fa6-bcfc-c396300ea437.webp', NULL, 0, 3, '2026-07-21 08:56:30'),
	(1749, 233, NULL, '/storage/products/5f93cebf-5fba-43c5-bdfe-7826a63ac2ec.webp', NULL, 0, 4, '2026-07-21 08:56:30'),
	(1750, 233, NULL, '/storage/products/132b4d34-ab16-4977-8c73-30b452b3f18b.webp', NULL, 0, 5, '2026-07-21 08:56:30'),
	(1782, 234, NULL, '/storage/products/dec84be1-6c20-4761-b833-c4642e34bd07.webp', NULL, 1, 0, '2026-07-21 08:56:45'),
	(1783, 234, NULL, '/storage/products/215df7e3-ff8a-4c3f-880f-b4ef081b894e.webp', NULL, 0, 1, '2026-07-21 08:56:45'),
	(1784, 234, NULL, '/storage/products/80e2b5e8-5e12-455d-9e91-3db58612c173.webp', NULL, 0, 2, '2026-07-21 08:56:45'),
	(1785, 234, NULL, '/storage/products/932d4962-b719-42ff-aa35-38bffd9507b6.webp', NULL, 0, 3, '2026-07-21 08:56:45'),
	(1803, 235, NULL, '/storage/products/c21465d3-ee4e-48f0-b0f5-9de5659e9e19.webp', NULL, 1, 0, '2026-07-21 08:56:57'),
	(1804, 235, NULL, '/storage/products/d010635d-1e0d-406e-95bc-1e4ba019647f.webp', NULL, 0, 1, '2026-07-21 08:56:57'),
	(1805, 235, NULL, '/storage/products/0135728b-6813-4da1-96e7-a41a5f52b712.webp', NULL, 0, 2, '2026-07-21 08:56:57'),
	(1806, 235, NULL, '/storage/products/7f3a58d8-4fe1-4440-995e-134999629551.webp', NULL, 0, 3, '2026-07-21 08:56:57'),
	(1824, 236, NULL, '/storage/products/f597118c-9f07-4e42-98cb-5cb40a5055d0.webp', NULL, 1, 0, '2026-07-21 08:57:11'),
	(1825, 236, NULL, '/storage/products/1ec4c50f-9685-4203-8bc4-c412b8a0b6c8.webp', NULL, 0, 1, '2026-07-21 08:57:11'),
	(1826, 236, NULL, '/storage/products/623a43d0-4845-4674-9ecb-a5af3a6f752b.webp', NULL, 0, 2, '2026-07-21 08:57:11'),
	(1827, 236, NULL, '/storage/products/bc691da9-a71e-41b4-8098-965ff922b81c.webp', NULL, 0, 3, '2026-07-21 08:57:11'),
	(1845, 237, NULL, '/storage/products/d158df86-8f08-4508-bee9-4d67d6e273e7.webp', NULL, 1, 0, '2026-07-21 08:57:23'),
	(1846, 237, NULL, '/storage/products/96c786f5-3e88-4e1f-8c72-99cc7e93afa3.webp', NULL, 0, 1, '2026-07-21 08:57:23'),
	(1847, 237, NULL, '/storage/products/ce9d25c1-6b64-4d61-af5d-3bdb7dc28602.webp', NULL, 0, 2, '2026-07-21 08:57:23'),
	(1848, 237, NULL, '/storage/products/d6f50178-9f51-4d66-9502-3f5179ae2154.webp', NULL, 0, 3, '2026-07-21 08:57:23'),
	(1866, 238, NULL, '/storage/products/98fb592b-d53c-4b26-bb90-f6822a73e54f.webp', NULL, 1, 0, '2026-07-21 08:57:37'),
	(1867, 238, NULL, '/storage/products/b9a52a82-406e-412b-9cce-32e21a176094.webp', NULL, 0, 1, '2026-07-21 08:57:37'),
	(1868, 238, NULL, '/storage/products/55394b77-14ff-4c91-ab96-6ec2e8ac849b.webp', NULL, 0, 2, '2026-07-21 08:57:37'),
	(1869, 238, NULL, '/storage/products/c0cf012a-6512-4f4c-8e12-81144e003874.webp', NULL, 0, 3, '2026-07-21 08:57:37'),
	(1870, 238, NULL, '/storage/products/21887ab1-20d2-4360-aac9-97f3b2a36c94.webp', NULL, 0, 4, '2026-07-21 08:57:37'),
	(1871, 238, NULL, '/storage/products/10ddbd7a-dd91-452a-aa06-7805b21c387d.webp', NULL, 0, 5, '2026-07-21 08:57:37'),
	(1893, 239, NULL, '/storage/products/36304e2a-b1f4-453d-91e4-3a995a9cacd5.webp', NULL, 1, 0, '2026-07-21 08:57:51'),
	(1894, 239, NULL, '/storage/products/bebb49a9-230e-447e-a9c0-6ed451fe72ee.webp', NULL, 0, 1, '2026-07-21 08:57:51'),
	(1895, 239, NULL, '/storage/products/ebed66f7-9730-4ddc-9d0e-21e583f2929c.webp', NULL, 0, 2, '2026-07-21 08:57:51'),
	(1896, 239, NULL, '/storage/products/89d53483-15f7-46e1-b1be-85e2bc4a14f7.webp', NULL, 0, 3, '2026-07-21 08:57:51'),
	(1897, 239, NULL, '/storage/products/b1a2258a-77d8-4d3e-97a8-009ca1d42cef.webp', NULL, 0, 4, '2026-07-21 08:57:51'),
	(1898, 239, NULL, '/storage/products/24f26530-4497-49ca-bdc7-528a48c212f8.webp', NULL, 0, 5, '2026-07-21 08:57:51'),
	(1920, 240, NULL, '/storage/products/f5801e74-1129-4d64-a1c3-00c68f34e191.webp', NULL, 1, 0, '2026-07-21 08:58:07'),
	(1921, 240, NULL, '/storage/products/5b87591b-5634-41b4-81bc-ebaa8402f7a4.webp', NULL, 0, 1, '2026-07-21 08:58:07'),
	(1922, 240, NULL, '/storage/products/d0d44a1c-8f44-4ca6-afad-f3b9a9378116.webp', NULL, 0, 2, '2026-07-21 08:58:07'),
	(1923, 240, NULL, '/storage/products/5df29cb8-49be-4be2-9d8d-95bfe679ca59.webp', NULL, 0, 3, '2026-07-21 08:58:07'),
	(1948, 241, NULL, '/storage/products/e5b0a026-fa49-426f-b002-157181539369.webp', NULL, 1, 0, '2026-07-21 08:58:43'),
	(1949, 241, NULL, '/storage/products/e4063522-c44a-48ca-87e0-23aaff799377.webp', NULL, 0, 1, '2026-07-21 08:58:43'),
	(1950, 241, NULL, '/storage/products/b3727279-3c74-4c5b-be47-bcc7f79261ba.webp', NULL, 0, 2, '2026-07-21 08:58:43'),
	(1951, 241, NULL, '/storage/products/dcd1401c-dece-48f9-a42a-46d6e30ee85c.webp', NULL, 0, 3, '2026-07-21 08:58:43'),
	(1952, 241, NULL, '/storage/products/e2865407-ccc9-459f-ae04-84de0567f3ef.webp', NULL, 0, 4, '2026-07-21 08:58:43'),
	(1977, 242, NULL, '/storage/products/1742a8b7-d948-4e83-a225-6b6215d25d8b.jpg', NULL, 1, 0, '2026-07-21 08:59:33'),
	(1978, 242, NULL, '/storage/products/8b863f5b-43b5-4993-ac23-331c978504da.jpg', NULL, 0, 1, '2026-07-21 08:59:33'),
	(1979, 242, NULL, '/storage/products/7bfd3976-977c-4ba3-8fdd-5690db2b7d58.jpg', NULL, 0, 2, '2026-07-21 08:59:33'),
	(1980, 242, NULL, '/storage/products/4b49d4ac-a41d-4f5a-8f35-c2819e9a248b.webp', NULL, 0, 3, '2026-07-21 08:59:33'),
	(2005, 243, NULL, '/storage/products/e8636c70-8076-4eab-b6c5-4f0e657f010b.webp', NULL, 1, 0, '2026-07-21 08:59:59'),
	(2006, 243, NULL, '/storage/products/c20da90f-003a-4cce-9407-d8ff37912803.webp', NULL, 0, 1, '2026-07-21 08:59:59'),
	(2007, 243, NULL, '/storage/products/db1031e3-fc8d-432a-a987-b838fb63415e.webp', NULL, 0, 2, '2026-07-21 08:59:59'),
	(2031, 244, NULL, '/storage/products/d41541cd-9dde-4a7e-a5ed-c82d2fd83883.webp', NULL, 1, 0, '2026-07-21 09:00:42'),
	(2032, 244, NULL, '/storage/products/3a8e199d-48ad-4909-9686-5d30c39bdb6b.webp', NULL, 0, 1, '2026-07-21 09:00:42'),
	(2033, 244, NULL, '/storage/products/0cff608b-409e-4251-9065-773a0825e02d.webp', NULL, 0, 2, '2026-07-21 09:00:42'),
	(2034, 244, NULL, '/storage/products/35232d85-c8d8-48ac-8011-6fcd86dcf326.webp', NULL, 0, 3, '2026-07-21 09:00:42'),
	(2058, 245, NULL, '/storage/products/41474de7-cbf1-4a30-928b-076d78b3b448.webp', NULL, 1, 0, '2026-07-21 09:01:13'),
	(2059, 245, NULL, '/storage/products/b2cc58db-9615-4c3c-9bf0-6cffa0557a49.webp', NULL, 0, 1, '2026-07-21 09:01:13'),
	(2060, 245, NULL, '/storage/products/383325aa-a613-4b1a-9b55-bbf34b2cd25b.webp', NULL, 0, 2, '2026-07-21 09:01:13'),
	(2061, 245, NULL, '/storage/products/ba3ecb34-4827-480a-ad5c-3f3e44c11d31.webp', NULL, 0, 3, '2026-07-21 09:01:13'),
	(2085, 246, NULL, '/storage/products/bd2b354d-365e-4a75-9b8f-b82092002cc4.webp', NULL, 1, 0, '2026-07-21 09:01:36'),
	(2086, 246, NULL, '/storage/products/f40eb672-23d7-4a11-9958-ac19b948dc69.webp', NULL, 0, 1, '2026-07-21 09:01:36'),
	(2087, 246, NULL, '/storage/products/2bcb77f3-7005-4e97-abb4-3730ff2f6d34.webp', NULL, 0, 2, '2026-07-21 09:01:36'),
	(2088, 246, NULL, '/storage/products/1ea97273-827a-4a06-ab0d-a8debc72c4d1.webp', NULL, 0, 3, '2026-07-21 09:01:36'),
	(2106, 246, NULL, '/storage/products/4f0a5569-0130-48b0-b14f-4d1519e2a612.webp', NULL, 0, 21, '2026-07-21 09:01:37');

-- Dumping structure for table ocean_db.product_variants
CREATE TABLE IF NOT EXISTS `product_variants` (
  `variant_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `sku` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `barcode` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variant_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `material` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weight_gram` int DEFAULT NULL,
  `cost_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `price` decimal(12,2) NOT NULL,
  `compare_at_price` decimal(12,2) DEFAULT NULL,
  `sale_price` decimal(12,2) DEFAULT NULL,
  `sale_starts_at` datetime DEFAULT NULL,
  `sale_ends_at` datetime DEFAULT NULL,
  `stock` int NOT NULL DEFAULT '0',
  `reserved_stock` int NOT NULL DEFAULT '0',
  `safety_stock` int NOT NULL DEFAULT '0',
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive','out_of_stock') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`variant_id`),
  UNIQUE KEY `product_variants_sku_unique` (`sku`),
  UNIQUE KEY `product_variants_product_id_color_size_unique` (`product_id`,`color`,`size`),
  KEY `idx_product_variants_product_status` (`product_id`,`status`),
  CONSTRAINT `product_variants_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=274 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.product_variants: ~123 rows (approximately)
DELETE FROM `product_variants`;
INSERT INTO `product_variants` (`variant_id`, `product_id`, `sku`, `barcode`, `variant_name`, `color`, `size`, `material`, `weight_gram`, `cost_price`, `price`, `compare_at_price`, `sale_price`, `sale_starts_at`, `sale_ends_at`, `stock`, `reserved_stock`, `safety_stock`, `image_url`, `status`, `created_at`, `updated_at`) VALUES
	(1, 1, 'BDM-BR160-G4', 'BDMD6BD8B7E53', 'Cán G4', 'Đen/Xanh lá', 'G4', 'Nhôm + thép', 95, 203320.00, 299000.00, 349000.00, NULL, NULL, NULL, 12, 0, 2, 'products/sports/cau-long/vot-cau-long-br160/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(2, 1, 'BDM-BR160-G5', 'BDMFF3A280377', 'Cán G5', 'Đen/Xanh lá', 'G5', 'Nhôm + thép', 95, 203320.00, 299000.00, 349000.00, NULL, NULL, NULL, 12, 0, 2, 'products/sports/cau-long/vot-cau-long-br160/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(3, 2, 'BDM-S190-BLUE-G4', 'BDM7864C26F63', 'Cán G4', 'Xanh dương', 'G4', 'Graphite + composite', 87, 203320.00, 299000.00, 359000.00, NULL, NULL, NULL, 12, 0, 2, 'products/sports/cau-long/vot-cau-long-br-sensation-190-blue/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(4, 2, 'BDM-S190-BLUE-G5', 'BDM4CB1C1C7B7', 'Cán G5', 'Xanh dương', 'G5', 'Graphite + composite', 87, 203320.00, 299000.00, 359000.00, NULL, NULL, NULL, 12, 0, 2, 'products/sports/cau-long/vot-cau-long-br-sensation-190-blue/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(5, 3, 'BDM-S530-G4', 'BDM37C3A7C975', 'Cán G4', 'Xanh lá/Đen', 'G4', 'Graphite 100%', 87, 522920.00, 769000.00, 899000.00, NULL, NULL, NULL, 12, 0, 2, 'products/sports/cau-long/vot-cau-long-br-sensation-530-green-black/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(6, 3, 'BDM-S530-G5', 'BDM57E99D0A9F', 'Cán G5', 'Xanh lá/Đen', 'G5', 'Graphite 100%', 87, 522920.00, 769000.00, 899000.00, NULL, NULL, NULL, 12, 0, 2, 'products/sports/cau-long/vot-cau-long-br-sensation-530-green-black/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(7, 4, 'BDM-P590-G4', 'BDM46FD45D4EE', 'Cán G4', 'Tím', 'G4', 'Graphite + resin', 84, 482120.00, 709000.00, 1199000.00, NULL, NULL, NULL, 12, 0, 2, 'products/sports/cau-long/vot-cau-long-br-perform-590-purple/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(8, 4, 'BDM-P590-G5', 'BDMF6F951EDAF', 'Cán G5', 'Tím', 'G5', 'Graphite + resin', 84, 482120.00, 709000.00, 1199000.00, NULL, NULL, NULL, 12, 0, 2, 'products/sports/cau-long/vot-cau-long-br-perform-590-purple/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(9, 5, 'BDM-BR500-WHITE-G4', 'BDM8CBCE9D7C6', 'Cán G4', 'Trắng', 'G4', 'Graphite 100%', 90, 271320.00, 399000.00, 499000.00, NULL, NULL, NULL, 11, 0, 2, 'products/sports/cau-long/vot-cau-long-br-500-white/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-14 23:06:14'),
	(10, 5, 'BDM-BR500-WHITE-G5', 'BDM45C23B2CB6', 'Cán G5', 'Trắng', 'G5', 'Graphite 100%', 90, 271320.00, 399000.00, 499000.00, NULL, NULL, NULL, 12, 0, 2, 'products/sports/cau-long/vot-cau-long-br-500-white/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(11, 6, 'BDM-DISCOVER-G4', 'BDMF620F80467', 'Cán G4', 'Vàng/Xanh', 'G4', 'Thép', 104, 166600.00, 245000.00, 299000.00, NULL, NULL, NULL, 12, 0, 2, 'products/sports/cau-long/vot-cau-long-br-discover/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(12, 6, 'BDM-DISCOVER-G5', 'BDM2AFD6C6E7E', 'Cán G5', 'Vàng/Xanh', 'G5', 'Thép', 104, 166600.00, 245000.00, 299000.00, NULL, NULL, NULL, 12, 0, 2, 'products/sports/cau-long/vot-cau-long-br-discover/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(13, 7, 'BDM-BSS500-33', 'BDMF5A306737E', 'Size 33', 'Trắng/Xanh dương', '33', 'Mesh + PU', 520, 407320.00, 599000.00, 699000.00, NULL, NULL, NULL, 13, 0, 3, 'products/sports/cau-long/giay-cau-long-bs-sensation-500-white-blue/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-13 23:56:59'),
	(14, 7, 'BDM-BSS500-34', 'BDM2E86EFDF63', 'Size 34', 'Trắng/Xanh dương', '34', 'Mesh + PU', 520, 407320.00, 599000.00, 699000.00, NULL, NULL, NULL, 14, 0, 3, 'products/sports/cau-long/giay-cau-long-bs-sensation-500-white-blue/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(15, 7, 'BDM-BSS500-35', 'BDMC7360EF23F', 'Size 35', 'Trắng/Xanh dương', '35', 'Mesh + PU', 520, 407320.00, 599000.00, 699000.00, NULL, NULL, NULL, 14, 0, 3, 'products/sports/cau-long/giay-cau-long-bs-sensation-500-white-blue/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(16, 7, 'BDM-BSS500-36', 'BDM2601EFF18A', 'Size 36', 'Trắng/Xanh dương', '36', 'Mesh + PU', 520, 407320.00, 599000.00, 699000.00, NULL, NULL, NULL, 14, 0, 3, 'products/sports/cau-long/giay-cau-long-bs-sensation-500-white-blue/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(17, 8, 'BDM-BSL560-35', 'BDM7C5AE8D6AB', 'Size 35', 'Trắng/Xanh biển', '35', 'TPU + EVA + mesh', 540, 883320.00, 1299000.00, 1499000.00, NULL, NULL, NULL, 11, 0, 3, 'products/sports/cau-long/giay-cau-long-bs-lite-560-white-sea-blue/variant.svg', 'active', '2026-06-05 10:54:43', '2026-07-16 16:19:57'),
	(18, 8, 'BDM-BSL560-36', 'BDM0E984521CD', 'Size 36', 'Trắng/Xanh biển', '36', 'TPU + EVA + mesh', 540, 883320.00, 1299000.00, 1499000.00, NULL, NULL, NULL, 14, 0, 3, 'products/sports/cau-long/giay-cau-long-bs-lite-560-white-sea-blue/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(19, 8, 'BDM-BSL560-37', 'BDMBB74D54BAB', 'Size 37', 'Trắng/Xanh biển', '37', 'TPU + EVA + mesh', 540, 883320.00, 1299000.00, 1499000.00, NULL, NULL, NULL, 14, 0, 3, 'products/sports/cau-long/giay-cau-long-bs-lite-560-white-sea-blue/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(20, 8, 'BDM-BSL560-38', 'BDM0CFF0DE266', 'Size 38', 'Trắng/Xanh biển', '38', 'TPU + EVA + mesh', 540, 883320.00, 1299000.00, 1499000.00, NULL, NULL, NULL, 14, 0, 3, 'products/sports/cau-long/giay-cau-long-bs-lite-560-white-sea-blue/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-15 09:46:01'),
	(21, 9, 'VLB-BVCRYSTAL', 'VLB0F5644EFA2', 'Size 4', 'Cam', '4', 'PVC mềm', 200, 67320.00, 99000.00, 129000.00, NULL, NULL, NULL, 25, 0, 5, 'products/sports/bong-chuyen/bong-chuyen-da-dung-bv-crystal-orange/variant.svg', 'active', '2026-06-05 10:54:43', '2026-07-21 23:10:20'),
	(22, 10, 'VLB-BV100', 'VLB148DFEFFBE', 'Size 5', 'Xanh turquoise', '5', 'PVC + butyl', 260, 176120.00, 259000.00, 319000.00, NULL, NULL, NULL, 20, 0, 4, 'products/sports/bong-chuyen/bong-chuyen-bai-bien-bv100-classic-turquoise/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(23, 11, 'VLB-VB500', 'VLBC599B8788D', 'Size 5 thi đấu', 'Trắng/Xanh dương', '5', 'Laminate tổng hợp', 270, 325720.00, 479000.00, 569000.00, NULL, NULL, NULL, 18, 0, 4, 'products/sports/bong-chuyen/bong-chuyen-vb500-classic-white-blue/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(24, 12, 'VLB-VKP100-S', 'VLB75DF8243BD', 'Size S', 'Đen', 'S', 'Polyester + foam PU', 180, 135320.00, 199000.00, 249000.00, NULL, NULL, NULL, 16, 0, 3, 'products/sports/bong-chuyen/bang-bao-ve-goi-bong-chuyen-vkp100-black/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(25, 12, 'VLB-VKP100-M', 'VLBB4363D48E2', 'Size M', 'Đen', 'M', 'Polyester + foam PU', 180, 135320.00, 199000.00, 249000.00, NULL, NULL, NULL, 16, 0, 3, 'products/sports/bong-chuyen/bang-bao-ve-goi-bong-chuyen-vkp100-black/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(26, 12, 'VLB-VKP100-L', 'VLB570F2DA780', 'Size L', 'Đen', 'L', 'Polyester + foam PU', 180, 135320.00, 199000.00, 249000.00, NULL, NULL, NULL, 15, 0, 3, 'products/sports/bong-chuyen/bang-bao-ve-goi-bong-chuyen-vkp100-black/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-29 14:12:15'),
	(27, 13, 'VLB-BV500-SET', 'VLBA43285B25E', 'Bộ chuẩn bãi biển', 'Vàng', 'One Size', 'Nhôm + lưới polyester', 6200, 1699320.00, 2499000.00, 2799000.00, NULL, NULL, NULL, 7, 0, 1, 'products/sports/bong-chuyen/bo-luoi-bong-chuyen-bai-bien-bv500-yellow/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(28, 14, 'VLB-BV900-SET', 'VLB8ECE0779F1', 'Bộ sân official', 'Cam/Đen', 'One Size', 'Nhôm + polyester', 7800, 2039320.00, 2999000.00, 3399000.00, NULL, NULL, NULL, 2, 0, 1, 'products/sports/bong-chuyen/bo-san-bong-chuyen-bai-bien-bv900-official/variant.svg', 'active', '2026-06-05 10:54:43', '2026-07-21 23:12:26'),
	(29, 15, 'PKB-P100', 'PKBFEF6A4B709', 'One Size', 'Đen', 'One Size', 'Fiberglass + carbon + polypropylene', 230, 271320.00, 399000.00, 499000.00, NULL, NULL, NULL, 16, 0, 3, 'products/sports/pickleball/pickleball-paddle-100-black/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(30, 16, 'PKB-OPEN', 'PKB70831F538E', 'One Size', 'Xanh dương', 'One Size', 'Fiberglass + polypropylene honeycomb', 225, 475320.00, 699000.00, 799000.00, NULL, NULL, NULL, 14, 0, 3, 'products/sports/pickleball/vot-pickleball-kuikma-open-blue/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(31, 17, 'PKB-PLAY-SET', 'PKB088EF064FB', 'Set 2 vợt', 'Cam/Xanh', 'One Size', 'Composite + lưới túi', 650, 747320.00, 1099000.00, 1299000.00, NULL, NULL, NULL, 11, 0, 2, 'products/sports/pickleball/bo-2-vot-pickleball-2-bong-tui-play/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(32, 18, 'PKB-ELITEX16', 'PKB8BEF41A532', 'One Size', 'Xanh dương', 'One Size', 'Carbon + ElasticPP', 235, 3060000.00, 4500000.00, 4900000.00, NULL, NULL, NULL, 6, 0, 1, 'products/sports/pickleball/vot-pickleball-elitex-16mm-blue/variant.svg', 'active', '2026-06-05 10:54:43', '2026-07-21 23:12:26'),
	(33, 19, 'PKB-ALLCOURT-M-39', 'PKB5831E859E4', 'Size 39', 'Xám nhạt/Xanh dương', '39', 'Mesh + rubber all-court', 710, 951320.00, 1399000.00, 1699000.00, NULL, NULL, NULL, 9, 0, 2, 'products/sports/pickleball/giay-tennispickleball-nam-all-court-light-grey-blue/variant.svg', 'active', '2026-06-05 10:54:43', '2026-07-16 20:09:20'),
	(34, 19, 'PKB-ALLCOURT-M-40', 'PKB39459CE399', 'Size 40', 'Xám nhạt/Xanh dương', '40', 'Mesh + rubber all-court', 710, 951320.00, 1399000.00, 1699000.00, NULL, NULL, NULL, 11, 0, 2, 'products/sports/pickleball/giay-tennispickleball-nam-all-court-light-grey-blue/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-22 17:58:36'),
	(35, 19, 'PKB-ALLCOURT-M-41', 'PKB371AB64301', 'Size 41', 'Xám nhạt/Xanh dương', '41', 'Mesh + rubber all-court', 710, 951320.00, 1399000.00, 1699000.00, NULL, NULL, NULL, 11, 0, 2, 'products/sports/pickleball/giay-tennispickleball-nam-all-court-light-grey-blue/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-14 23:06:14'),
	(36, 19, 'PKB-ALLCOURT-M-42', 'PKB68BF1D328E', 'Size 42', 'Xám nhạt/Xanh dương', '42', 'Mesh + rubber all-court', 710, 951320.00, 1399000.00, 1699000.00, NULL, NULL, NULL, 12, 0, 2, 'products/sports/pickleball/giay-tennispickleball-nam-all-court-light-grey-blue/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(37, 19, 'PKB-ALLCOURT-M-43', 'PKB7E0F69E374', 'Size 43', 'Xám nhạt/Xanh dương', '43', 'Mesh + rubber all-court', 710, 951320.00, 1399000.00, 1699000.00, NULL, NULL, NULL, 12, 0, 2, 'products/sports/pickleball/giay-tennispickleball-nam-all-court-light-grey-blue/variant.svg', 'active', '2026-06-05 10:54:43', '2026-06-05 10:54:43'),
	(48, 20, 'giay-pickleball-nam-essential-white-trang-39-rQ4c', 'OCNH4GAPHZ2IG16', NULL, 'Trắng', '39', NULL, NULL, 0.00, 539000.00, NULL, NULL, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-06-24 22:14:12', '2026-07-21 09:25:05'),
	(49, 20, 'giay-pickleball-nam-essential-white-trang-40-AsqP', 'OCN7CPMOTWV5V40', NULL, 'Trắng', '40', NULL, NULL, 0.00, 539000.00, NULL, NULL, NULL, NULL, 13, 0, 0, NULL, 'active', '2026-06-24 22:14:12', '2026-06-24 22:14:12'),
	(50, 20, 'giay-pickleball-nam-essential-white-trang-41-b5yJ', 'OCNHENZK6CESO30', NULL, 'Trắng', '41', NULL, NULL, 0.00, 539000.00, NULL, NULL, NULL, NULL, 13, 0, 0, NULL, 'active', '2026-06-24 22:14:12', '2026-06-24 22:14:12'),
	(51, 20, 'giay-pickleball-nam-essential-white-trang-42-Y2XY', 'OCNNX4CHPPJAH86', NULL, 'Trắng', '42', NULL, NULL, 0.00, 539000.00, NULL, NULL, NULL, NULL, 12, 0, 0, NULL, 'active', '2026-06-24 22:14:13', '2026-06-29 14:12:15'),
	(52, 20, 'giay-pickleball-nam-essential-white-trang-43-QL81', 'OCNB2MLOADNGR68', NULL, 'Trắng', '43', NULL, NULL, 0.00, 539000.00, NULL, NULL, NULL, NULL, 13, 0, 0, NULL, 'active', '2026-06-24 22:14:13', '2026-06-24 22:14:13'),
	(128, 181, 'SKU-NK0WXZY7', NULL, 'Mặc định', NULL, NULL, NULL, NULL, 0.00, 6156000.00, NULL, 5130000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-21 15:28:31', '2026-07-21 15:28:31'),
	(156, 209, 'SKU-GM8VXJMH', NULL, 'Mặc định', NULL, NULL, NULL, NULL, 0.00, 2820000.00, NULL, 2350000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-21 15:48:29', '2026-07-21 15:48:29'),
	(195, 246, 'giay-tennis-babolat-jet-mach-3-all-court-junior-chinh-hang-26883a-5081-cam--Jra6', 'OCNTBKBHQGI0M26', NULL, 'Cam', '', NULL, NULL, 0.00, 2518800.00, NULL, 2099000.00, NULL, NULL, 9, 0, 0, NULL, 'active', '2026-07-23 14:45:57', '2026-07-23 14:45:57'),
	(196, 245, 'giay-tennis-babolat-jet-mach-4-all-court-men-chinh-hang-26629b-5050-do--Suha', 'OCNVBGJUEMNZ423', NULL, 'Đỏ', '', NULL, NULL, 0.00, 4500000.00, NULL, 3750000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 14:46:27', '2026-07-23 14:46:27'),
	(197, 244, 'giay-tennis-babolat-jet-mach-4-all-court-men-chinh-hang-26629b-1115-trang--u90a', 'OCN9RYSIYPJFR50', NULL, 'Trắng ', '', NULL, NULL, 0.00, 4500000.00, NULL, 3750000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 14:46:53', '2026-07-23 14:46:53'),
	(198, 243, 'giay-tennis-babolat-jet-mach-4-all-court-men-chinh-hang-26629b-2036-xanh-than--pSAt', 'OCNIGCOE8HISY54', NULL, 'Xanh than', '', NULL, NULL, 0.00, 4500000.00, NULL, 3750000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 14:47:23', '2026-07-23 14:47:23'),
	(199, 242, 'giay-tennis-babolat-jet-mach-4-all-court-women-chinh-hang-26630b-4150-xanh--d3gR', 'OCNITNGIC1PX922', NULL, 'Xanh ', '', NULL, NULL, 0.00, 4500000.00, NULL, 3750000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 14:48:09', '2026-07-23 14:48:09'),
	(200, 241, 'giay-tennis-babolat-propulse-fury-3-all-court-men-chinh-hang-30s26208b-1069-trang--OnT0', 'OCNPB8YWXDE9M59', NULL, 'Trắng', '', NULL, NULL, 0.00, 4140000.00, NULL, 3450000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 14:48:56', '2026-07-23 14:48:56'),
	(201, 240, 'giay-tennis-babolat-propulse-fury-3-all-court-men-chinh-hang-30s26208b-2051-den-trang--LuJw', 'OCNZWHNHBWBEC53', NULL, 'Đen trắng', '', NULL, NULL, 0.00, 4140000.00, NULL, 3450000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 14:49:39', '2026-07-23 14:49:39'),
	(202, 239, 'giay-tennis-asics-solution-swift-ff-2-trang-chinh-hang-1042a265104-trang--XyYo', 'OCNUKXSPOLZVM97', NULL, 'Trắng', '', NULL, NULL, 0.00, 2868000.00, NULL, 2390000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 14:50:11', '2026-07-23 14:50:11'),
	(203, 238, 'giay-tennis-asics-gel-challenger-15-hong-chinh-hang-1042a294700-hong--CU6b', 'OCN89KJOCY56P58', NULL, 'Hồng', '', NULL, NULL, 0.00, 2998800.00, NULL, 2499000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 14:51:29', '2026-07-23 14:51:29'),
	(204, 237, 'giay-babolat-jet-tere-2-all-court-men-chinh-hang-30s26649c-7023-xanh--PpX9', 'OCNZNYWWR71TI64', NULL, 'Xanh', '', NULL, NULL, 0.00, 3348000.00, NULL, 2790000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 14:51:58', '2026-07-23 14:51:58'),
	(205, 236, 'giay-tennis-jet-tere-2-all-court-women-chinh-hang-31s26651c-4144-xanh--S1oQ', 'OCN9QLPBZHAVD33', NULL, 'Xanh', '', NULL, NULL, 0.00, 3540000.00, NULL, 2950000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 14:52:34', '2026-07-23 14:52:34'),
	(206, 235, 'giay-babolat-jet-tere-2-all-court-women-chinh-hang-3a1f25a651-5072-nau--bJ84', 'OCN88AWIJNSWP50', NULL, 'Nâu', '', NULL, NULL, 0.00, 3480000.00, NULL, 2900000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 14:53:19', '2026-07-23 14:53:19'),
	(207, 234, 'giay-babolat-sfx-evo-all-court-wimbledon-men-chinh-hang-ba30s26938c-1001-trang--VOKL', 'OCNE9CYKIA4YO37', NULL, 'Trắng', '', NULL, NULL, 0.00, 3718800.00, NULL, 3099000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 14:53:49', '2026-07-23 14:53:49'),
	(208, 233, 'vot-tennis-babolat-evo-aero-pink-275gr-chinh-hang-101506-trang--oanl', 'OCNTC8LSYTUVE89', NULL, 'Trắng', '', NULL, NULL, 0.00, 4499000.00, NULL, 4249000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 14:54:53', '2026-07-23 14:54:53'),
	(209, 232, 'vot-tennis-wilson-hyper-hammer-53-242gr-blk-2-chinh-hang-wr152111u2-trang--1XeD', 'OCNGJYPYITP5B53', NULL, 'Trắng', '', NULL, NULL, 0.00, 4150000.00, NULL, 3599000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 14:56:13', '2026-07-23 14:56:13'),
	(210, 231, 'vot-tennis-wilson-hyper-hammer-23-237gr-blk-2-chinh-hang-wr151911u2-trang--JqUT', 'OCNENS4TTOZQH75', NULL, 'Trắng', '', NULL, NULL, 0.00, 4350000.00, NULL, 3799000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 14:56:40', '2026-07-23 14:56:40'),
	(211, 228, 'vot-tennis-babolat-pure-drive-98-305gr-chinh-hang-101474-trang--JYOX', 'OCN8AFGIQBL4L64', NULL, 'Trắng', '', NULL, NULL, 0.00, 5799000.00, NULL, 5149000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 14:57:15', '2026-07-23 14:57:15'),
	(212, 227, 'cap-vot-tennis-babolat-pure-drive-98-305g-x2-chinh-hang-101472-trang--zJgE', 'OCNKBHCVAV8MH13', NULL, 'Trắng', '', NULL, NULL, 0.00, 11199000.00, NULL, 9999000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 14:57:47', '2026-07-23 14:57:47'),
	(213, 229, 'vot-tennis-wilson-hyper-hammer-23-237gr-burblk-2-chinh-hang-wr136411u2-trang--cW1m', 'OCNEITMJ1FNLL68', NULL, 'Trắng', '', NULL, NULL, 0.00, 4350000.00, NULL, 3799000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 14:58:44', '2026-07-23 14:58:44'),
	(214, 230, 'vot-tennis-wilson-hyper-hammer-23-237gr-blkbur-2-chinh-hang-wr136211u2-trang--z8qv', 'OCNOLVOSKCIRF78', NULL, 'Trắng', '', NULL, NULL, 0.00, 4350000.00, NULL, 3799000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 14:59:22', '2026-07-23 14:59:22'),
	(215, 226, 'vot-tennis-babolat-pure-strike-1619-305gr-chinh-hang-101472-trang--Ldnu', 'OCNB4ON7VTSP911', NULL, 'Trắng', '', NULL, NULL, 0.00, 4599000.00, NULL, 4149000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:08:10', '2026-07-23 15:08:10'),
	(216, 225, 'vot-tennis-babolat-pure-strike-1820-305gr-chinh-hang-101404-trang--5ZHX', 'OCNGESUGEYGCV84', NULL, 'Trắng', '', NULL, NULL, 0.00, 4599000.00, NULL, 4149000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:08:31', '2026-07-23 15:08:31'),
	(217, 224, 'vot-tennis-babolat-pure-strike-lite-gen-4-265gr-2024-chinh-hang-101528-trang--8lTL', 'OCNA13NQDNEJX66', NULL, 'Trắng', '', NULL, NULL, 0.00, 6118800.00, NULL, 5099000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:08:59', '2026-07-23 15:08:59'),
	(218, 223, 'vot-tennis-babolat-pure-strike-lite-vs-310gr-chinh-hang-101470-trang--w8jS', 'OCNC5SXBUTZSR24', NULL, 'Trắng', '', NULL, NULL, 0.00, 5199000.00, NULL, 4599000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:09:17', '2026-07-23 15:09:17'),
	(219, 222, 'cap-vot-tennis-babolat-pure-strike-lite-vs-310gr-x2-chinh-hang-101458-trang--NheX', 'OCNBSQX9FMINS63', NULL, 'Trắng', '', NULL, NULL, 0.00, 10399000.00, NULL, 9150000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:48:08', '2026-07-23 15:48:08'),
	(220, 221, 'vot-tennis-babolat-boost-strike-285gr-chinh-hang-121247-trang--Eg2h', 'OCNDT8LERFSP328', NULL, 'Trắng', '', NULL, NULL, 0.00, 2799000.00, NULL, 2649000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:48:34', '2026-07-23 15:48:34'),
	(221, 220, 'vot-tennis-wilson-hyper-hammer-23-237gr-rry-chinh-hang-wr151811u2-trang--EfxZ', 'OCNQ1G78MYT2U17', NULL, 'Trắng', '', NULL, NULL, 0.00, 4350000.00, NULL, 3799000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:48:52', '2026-07-23 15:48:52'),
	(222, 219, 'vot-tennis-babolat-boost-drive-white-260gr-chinh-hang-121265-trang--kXwM', 'OCNAZMMB54RIT23', NULL, 'Trắng', '', NULL, NULL, 0.00, 3358800.00, NULL, 2799000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:49:10', '2026-07-23 15:49:10'),
	(223, 218, 'vot-tennis-babolat-boost-aero-pink-260gr-chinh-hang-121253-trang--EqNt', 'OCNF25MHURSW767', NULL, 'Trắng', '', NULL, NULL, 0.00, 3358800.00, NULL, 2799000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:49:25', '2026-07-23 15:49:25'),
	(224, 217, 'vot-tennis-babolat-evo-drive-gen-2-unstrung-270gr-chinh-hang-101545-trang--jcIy', 'OCNAIK07WHOTZ28', NULL, 'Trắng', '', NULL, NULL, 0.00, 4750800.00, NULL, 3959000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:49:41', '2026-07-23 15:49:41'),
	(225, 216, 'vot-tennis-babolat-evo-aero-lite-gen-2-pink-chinh-hang-102565-trang--Nk8o', 'OCNRLDHAMYAPP34', NULL, 'Trắng', '', NULL, NULL, 0.00, 5638800.00, NULL, 4699000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:49:55', '2026-07-23 15:49:55'),
	(226, 215, 'vot-tennis-babolat-pure-drive-lite-270gr-chinh-hang-101555-trang--045N', 'OCNE5OBIUBMOC45', NULL, 'Trắng', '', NULL, NULL, 0.00, 5998800.00, NULL, 4999000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:50:09', '2026-07-23 15:50:09'),
	(227, 214, 'vot-tennis-babolat-pure-strike-team-gen-4-285gr-chinh-hang-101580-trang--SkWi', 'OCN4RDMRBVMSM91', NULL, 'Trắng', '', NULL, NULL, 0.00, 5998800.00, NULL, 4999000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:50:25', '2026-07-23 15:50:25'),
	(228, 213, 'balo-pickleball-joola-vision-ii-deluxe-black-den--kd0o', 'OCN565ZPUXXSQ10', NULL, 'Đen', '', NULL, NULL, 0.00, 2388000.00, NULL, 1990000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:50:46', '2026-07-23 15:50:46'),
	(229, 212, 'balo-pickleball-joola-vision-ii-blue-xanh--nDCw', 'OCNWOU6VYNFDY30', NULL, 'Xanh', '', NULL, NULL, 0.00, 1798800.00, NULL, 1499000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:51:02', '2026-07-23 15:51:02'),
	(230, 211, 'balo-pickleball-joola-vision-ii-petrol-teal-chinh-hang-xanh--fAGC', 'OCNCKR87PKPVD61', NULL, 'Xanh', '', NULL, NULL, 0.00, 1798800.00, NULL, 1499000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:51:17', '2026-07-23 15:51:17'),
	(231, 210, 'balo-pickleball-zocker-cam-den-chinh-hang-den--l09z', 'OCNNZYQSWTK0330', NULL, 'Đen', '', NULL, NULL, 0.00, 2820000.00, NULL, 2350000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:51:37', '2026-07-23 15:51:37'),
	(232, 208, 'balo-pickleball-joola-agassi-vision-ii-backpack-chinh-hang-den-do--E5A6', 'OCNYJUD5PFZSR55', NULL, 'Đen đỏ', '', NULL, NULL, 0.00, 1860000.00, NULL, 1550000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:52:13', '2026-07-23 15:52:13'),
	(233, 207, 'giay-pickleball-lining-akpv001-2-chinh-hang-xanh--q1cc', 'OCNRLI2JQSGJE12', NULL, 'Xanh', '', NULL, NULL, 0.00, 3120000.00, NULL, 2600000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:52:29', '2026-07-23 15:52:29'),
	(234, 206, 'giay-pickleball-lining-akpv001-3-chinh-hang-den--LizH', 'OCNWSHBJMXLB551', NULL, 'Đen', '', NULL, NULL, 0.00, 3120000.00, NULL, 2600000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:52:50', '2026-07-23 15:52:50'),
	(235, 205, 'giay-pickleball-lining-akpw001-1-chinh-hang-trang--6wiO', 'OCNNWNKHLCQ9K69', NULL, 'Trắng', '', NULL, NULL, 0.00, 2640000.00, NULL, 2200000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:53:22', '2026-07-23 15:53:22'),
	(236, 204, 'giay-pickleball-lining-akpw001-2-chinh-hang-den--Uk3J', 'OCNE6MFI87HD063', NULL, 'Đen', '', NULL, NULL, 0.00, 2640000.00, NULL, 2200000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:54:26', '2026-07-23 15:54:26'),
	(237, 203, 'giay-pickleball-adidas-courtquick-gold-metallicgold-metallic-chinh-hang-ki3590-trang--SeuZ', 'OCNUROEGLEAHH19', NULL, 'Trắng ', '', NULL, NULL, 0.00, 2868000.00, NULL, 2390000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:54:49', '2026-07-23 15:54:49'),
	(238, 202, 'set-vot-pickleball-kumpoo-surpass-mystery-2-gz-chinh-hang-trang--mrZ8', 'OCN0JZVTI0CNC69', NULL, 'Trắng', '', NULL, NULL, 0.00, 1978800.00, NULL, 1649000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:55:18', '2026-07-23 15:55:18'),
	(239, 201, 'vot-pickleball-selkirk-vanguard-power-air-epic-selkirk-red-chinh-hang-demo-den-do--2b7Z', 'OCNJ8TQZX5KCC44', NULL, 'Đen đỏ', '', NULL, NULL, 0.00, 5748000.00, NULL, 4790000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:55:40', '2026-07-23 15:55:40'),
	(240, 200, 'vot-pickleball-kaiwin-rocket-pro-diamond-16mm-chinh-hang-den--x44e', 'OCNVSESZUSPJT42', NULL, 'Đen', '', NULL, NULL, 0.00, 3238800.00, NULL, 2699000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:56:07', '2026-07-23 15:56:07'),
	(241, 199, 'ao-cau-long-yonex-rm3232-dark-eclipse-chinh-hang-den-xanh--9dLX', 'OCNC82XUFEPO023', NULL, 'Đen Xanh', '', NULL, NULL, 0.00, 178800.00, NULL, 149000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:56:27', '2026-07-23 15:56:27'),
	(242, 198, 'ao-cau-long-yonex-rm3232-white-chinh-hang-trang-xanh--uQzP', 'OCNHTHOYM5CHC69', NULL, 'Trắng Xanh', '', NULL, NULL, 0.00, 178800.00, NULL, 149000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:56:54', '2026-07-23 15:56:54'),
	(243, 197, 'ao-cau-long-yonex-rm3235-white-chinh-hang-trang-cam--N0A3', 'OCNMYFOJCPVWF74', NULL, 'Trắng Cam', '', NULL, NULL, 0.00, 178800.00, NULL, 149000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:57:14', '2026-07-23 15:57:14'),
	(244, 196, 'ao-cau-long-yonex-rm3235-black-chinh-hang-den-xanh--BrWe', 'OCNEIMQ9S9KG045', NULL, 'Đen Xanh', '', NULL, NULL, 0.00, 178800.00, NULL, 149000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:57:37', '2026-07-23 15:57:37'),
	(245, 195, 'ao-cau-long-yonex-rm3235-dark-eclipse-chinh-hang-xanh--DxIC', 'OCN4AMS2PYHUS19', NULL, 'Xanh', '', NULL, NULL, 0.00, 178800.00, NULL, 149000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:57:59', '2026-07-23 15:57:59'),
	(246, 194, 'ao-cau-long-yonex-rm3239-jet-black-chinh-hang-den--ewY1', 'OCNRBTHAS9FLF92', NULL, 'Đen', '', NULL, NULL, 0.00, 178800.00, NULL, 149000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:58:19', '2026-07-23 15:58:19'),
	(247, 193, 'ao-cau-long-yonex-rm3239-white-chinh-hang-trang--Ct0a', 'OCNKLH7GMOLLF71', NULL, 'Trắng', '', NULL, NULL, 0.00, 178800.00, NULL, 149000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:58:39', '2026-07-23 15:58:39'),
	(248, 192, 'giay-cau-long-lining-ayau005-4-chinh-hang-trang-tim--6p7c', 'OCN1FSTZE8YT314', NULL, 'Trắng tím', '', NULL, NULL, 0.00, 3000000.00, NULL, 2500000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:59:01', '2026-07-23 15:59:01'),
	(249, 191, 'giay-cau-long-lining-aytu025-5-chinh-hang-cam--o4Qi', 'OCN8JPV3RWDXD46', NULL, 'Cam', '', NULL, NULL, 0.00, 1560000.00, NULL, 1300000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 15:59:23', '2026-07-23 15:59:23'),
	(250, 190, 'giay-cau-long-lining-ayzw007-1-chinh-hang-trang--yUmc', 'OCNEJ2NRMOUWU40', NULL, 'Trắng', '', NULL, NULL, 0.00, 2748000.00, NULL, 2290000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 16:04:05', '2026-07-23 16:04:05'),
	(251, 189, 'giay-cau-long-lining-ayzw007-2-chinh-hang-den--Aufy', 'OCNVZN5ESAF5H92', NULL, 'Đen', '', NULL, NULL, 0.00, 2748000.00, NULL, 2290000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 16:04:22', '2026-07-23 16:04:22'),
	(252, 188, 'giay-cau-long-lining-ayzw007-3-chinh-hang-trang--zdQ3', 'OCNPMV1UHAUOY67', NULL, 'Trắng', '', NULL, NULL, 0.00, 2748000.00, NULL, 2290000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 16:04:45', '2026-07-23 16:04:45'),
	(253, 187, 'giay-cau-long-lining-aytw003-2-chinh-hang-trang-tim--hnWd', 'OCNOXJNEUSR4213', NULL, 'Trắng tím', '', NULL, NULL, 0.00, 1320000.00, NULL, 1100000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 16:05:05', '2026-07-23 16:05:05'),
	(254, 186, 'giay-cau-long-lining-ayau003-1-chinh-hang-trang-gold--2UDd', 'OCNGHXWLH0GC590', NULL, 'Trắng & Gold', '', NULL, NULL, 0.00, 3120000.00, NULL, 2600000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 16:05:33', '2026-07-23 16:05:33'),
	(255, 185, 'giay-cau-long-victor-p9200-cls-ac-trang-den-chinh-hang-trang--134g', 'OCNCUH5HFKQO789', NULL, 'Trắng', '', NULL, NULL, 0.00, 1620000.00, NULL, 1350000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 16:05:57', '2026-07-23 16:05:57'),
	(256, 184, 'giay-cau-long-victor-a970-cadvam-trang-chinh-hang-trang--Xr49', 'OCNKBSELG2S0F84', NULL, 'Trắng', '', NULL, NULL, 0.00, 3480000.00, NULL, 2900000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 16:06:17', '2026-07-23 16:06:17'),
	(257, 183, 'combo-mua-vot-cau-long-victor-tk-f-ultra-tang-vot-victor-ars-a-vot-victor-tk-ryuga-cls-combo--h1mq', 'OCNIZEXDZJIDH81', NULL, 'combo', '', NULL, NULL, 0.00, 6972000.00, NULL, 5810000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 16:06:42', '2026-07-23 16:06:42'),
	(258, 182, 'combo-mua-vot-cau-long-victor--combo--v8XS', 'OCNL1ZRRRL5IE90', NULL, 'combo ', '', NULL, NULL, 0.00, 6396000.00, NULL, 5330000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 23:51:33', '2026-07-23 23:51:33'),
	(259, 179, 'combo-mua-vot-cau-long-victor--combo--MJhb', 'OCNAV277FJPNO90', NULL, 'Combo', '', NULL, NULL, 0.00, 6996000.00, NULL, 5830000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-23 23:56:47', '2026-07-23 23:56:47'),
	(260, 180, 'combo-mua-vot-cau-long-victor-ars-9-tang-vot-victor-ars-9-vot-pickleball-kawasaki-galaxy-combo--SiGF', 'OCNKNPBXQQNCI88', NULL, 'combo', '', NULL, NULL, 0.00, 3180000.00, NULL, 2650000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-24 00:01:37', '2026-07-24 00:01:37'),
	(261, 178, 'combo-mua-set-vot-cau-long-victor-tk-cny-gb-tang-v-combo--dcSw', 'OCNY3TMRW7QTM46', NULL, 'Combo', '', NULL, NULL, 0.00, 7236000.00, NULL, 6030000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-24 00:24:28', '2026-07-24 00:24:28'),
	(262, 176, 'combo-mua-vot-cau-long-victor-tk-tty-ultima-tang-v-combo--2zro', 'OCNMALQXCPM0M77', NULL, 'Combo', '', NULL, NULL, 0.00, 7236000.00, NULL, 6030000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-24 00:26:27', '2026-07-24 00:26:27'),
	(263, 177, 'combo-mua-vot-cau-long-victor-ryuga-ii-tang-vot-dx-combo--I8w9', 'OCNGPA73TMFPF65', NULL, 'combo', '', NULL, NULL, 0.00, 6384000.00, NULL, 5320000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-24 00:27:02', '2026-07-24 00:27:02'),
	(264, 175, 'combo-mua-vot-cau-long-victor-mjolnir-metallic-tan-combo--L9Ih', 'OCNDXIWYK5VDO25', NULL, 'combo', '', NULL, NULL, 0.00, 7500000.00, NULL, 6250000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-24 00:28:46', '2026-07-24 00:28:46'),
	(265, 174, 'combo-mua-vot-cau-long-iron-man-gb-tang-vot-tk-hmr-combo--Fnhk', 'OCNE8WVUEBCDN95', NULL, 'combo', '', NULL, NULL, 0.00, 8556000.00, NULL, 7130000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-24 00:29:01', '2026-07-24 00:29:01'),
	(266, 173, 'vot-cau-long-vnb-v200-xanh-chinh-hang-den--nlbR', 'OCNI0KG3SW2HS66', NULL, 'Đen ', '', NULL, NULL, 0.00, 634800.00, NULL, 529000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-24 00:29:24', '2026-07-24 00:29:24'),
	(267, 172, 'vot-cau-long-vnb-v200-do-chinh-hang-den--lc05', 'OCNGLQLVCPT4Q56', NULL, 'Đen ', '', NULL, NULL, 0.00, 634800.00, NULL, 529000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-24 00:29:41', '2026-07-24 00:29:41'),
	(268, 171, 'vot-cau-long-vnb-carbon-training-150g-chinh-hang-xanh--C70s', 'OCNDQK0GDENBN68', NULL, 'Xanh', '', NULL, NULL, 0.00, 837600.00, NULL, 698000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-24 00:30:09', '2026-07-24 00:30:09'),
	(269, 170, 'vot-cau-long-vnb-v200i-hong-chinh-hang-hong--itVO', 'OCNO4AT73U9SS85', NULL, 'Hồng', '', NULL, NULL, 0.00, 634800.00, NULL, 529000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-24 00:30:35', '2026-07-24 00:30:35'),
	(270, 169, 'vot-cau-long-vnb-tc88c-chinh-hang-trang--jxbU', 'OCNUWSIEN5DC550', NULL, 'Trắng', '', NULL, NULL, 0.00, 958800.00, NULL, 799000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-24 00:31:43', '2026-07-24 00:31:43'),
	(271, 168, 'vot-cau-long-vnb-tc88b-chinh-hang-trang--BP8h', 'OCNMM9S5GBJBD81', NULL, 'Trắng', '', NULL, NULL, 0.00, 958800.00, NULL, 799000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-24 00:32:17', '2026-07-24 00:32:17'),
	(272, 167, 'vot-cau-long-vnb-v88-cam-chinh-hang-do-den--HhJr', 'OCNWZIC9AWXBE31', NULL, 'Đỏ đen', '', NULL, NULL, 0.00, 765600.00, NULL, 638000.00, NULL, NULL, 10, 0, 0, NULL, 'active', '2026-07-24 00:32:43', '2026-07-24 00:32:43'),
	(273, 166, 'vot-cau-long-vnb-v88-xanh-chinh-hang-xanh--jYzQ', 'OCNRHUIHOIL6K91', NULL, 'Xanh ', '', NULL, NULL, 0.00, 765600.00, NULL, 638000.00, NULL, NULL, 9, 0, 0, NULL, 'active', '2026-07-24 00:33:08', '2026-07-25 00:41:07');

-- Dumping structure for table ocean_db.products
CREATE TABLE IF NOT EXISTS `products` (
  `product_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint unsigned DEFAULT NULL,
  `brand_id` bigint unsigned DEFAULT NULL,
  `seller_id` bigint unsigned DEFAULT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(220) COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `thumbnail_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_type` enum('simple','variant') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'variant',
  `status` enum('draft','active','inactive','out_of_stock') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `min_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `max_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `rating_avg` decimal(3,2) NOT NULL DEFAULT '0.00',
  `rating_count` int NOT NULL DEFAULT '0',
  `view_count` int NOT NULL DEFAULT '0',
  `sold_count` int NOT NULL DEFAULT '0',
  `weight` int NOT NULL DEFAULT '500' COMMENT 'Trọng lượng thực tế (gram)',
  `published_at` datetime DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`product_id`),
  UNIQUE KEY `products_slug_unique` (`slug`),
  KEY `products_brand_id_foreign` (`brand_id`),
  KEY `products_seller_id_foreign` (`seller_id`),
  KEY `products_category_id_foreign` (`category_id`),
  CONSTRAINT `products_brand_id_foreign` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`brand_id`) ON DELETE SET NULL,
  CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL,
  CONSTRAINT `products_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=247 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.products: ~101 rows (approximately)
DELETE FROM `products`;
INSERT INTO `products` (`product_id`, `category_id`, `brand_id`, `seller_id`, `name`, `slug`, `short_description`, `description`, `thumbnail_url`, `product_type`, `status`, `is_featured`, `min_price`, `max_price`, `rating_avg`, `rating_count`, `view_count`, `sold_count`, `weight`, `published_at`, `deleted_at`, `created_at`, `updated_at`) VALUES
	(1, NULL, 1, NULL, 'Vợt cầu lông BR160', 'vot-cau-long-br160', 'Vợt cầu lông người lớn BR160 phù hợp cho các buổi tập đầu tiên, cân bằng dễ kiểm soát và khung chắc tay.', '<div class="product-description"><h3>Mô tả sản phẩm</h3><p>Vợt cầu lông người lớn BR160 phù hợp cho các buổi tập đầu tiên, cân bằng dễ kiểm soát và khung chắc tay.</p><h3>Điểm nổi bật</h3><ul><li>Khung nhôm kết hợp thân thép cho độ bền cao ở tần suất chơi cơ bản.</li><li>Thiết kế isometric giúp vùng điểm ngọt rộng, dễ sửa lỗi khi đánh lệch tâm.</li><li>Độ cân bằng trung tính giúp người mới làm quen kỹ thuật nhanh hơn.</li></ul><h3>Thông số nhanh</h3><ul><li><strong>Nguồn giá:</strong> Decathlon Việt Nam</li><li><strong>Cập nhật giá:</strong> 05/06/2026</li><li><strong>Mã tham chiếu:</strong> 8545288</li></ul><p><em>Giá seed tham chiếu theo dữ liệu công khai được tổng hợp ngày 05/06/2026.</em></p></div>', NULL, 'variant', 'active', 1, 299000.00, 299000.00, 4.60, 86, 1860, 312, 500, '2026-06-05 10:54:43', '2026-07-24 00:35:18', '2026-06-05 10:54:43', '2026-07-24 00:35:18'),
	(2, NULL, 1, NULL, 'Vợt cầu lông BR Sensation 190 Blue', 'vot-cau-long-br-sensation-190-blue', 'BR Sensation 190 Blue nổi bật ở trọng lượng nhẹ 87g, hỗ trợ người chơi mới tạo lực dễ hơn và bớt mỏi tay.', '<div class="product-description"><h3>Mô tả sản phẩm</h3><p>BR Sensation 190 Blue nổi bật ở trọng lượng nhẹ 87g, hỗ trợ người chơi mới tạo lực dễ hơn và bớt mỏi tay.</p><h3>Điểm nổi bật</h3><ul><li>Thân vợt mềm giúp người mới dễ tạo lực khi cổ tay chưa khỏe.</li><li>Điểm ngọt rộng để tăng độ ổn định trong các pha chạm cầu đầu tiên.</li><li>Phối màu xanh hiện đại, dễ nhận diện khi lên sân.</li></ul><h3>Thông số nhanh</h3><ul><li><strong>Nguồn giá:</strong> Decathlon Việt Nam</li><li><strong>Cập nhật giá:</strong> 05/06/2026</li><li><strong>Mã tham chiếu:</strong> 8981537</li></ul><p><em>Giá seed tham chiếu theo dữ liệu công khai được tổng hợp ngày 05/06/2026.</em></p></div>', NULL, 'variant', 'active', 1, 299000.00, 299000.00, 4.70, 124, 2410, 406, 500, '2026-06-05 10:54:43', '2026-07-24 00:35:31', '2026-06-05 10:54:43', '2026-07-24 00:35:31'),
	(3, NULL, 1, NULL, 'Vợt cầu lông BR Sensation 530 Green Black', 'vot-cau-long-br-sensation-530-green-black', 'BR Sensation 530 Green Black là vợt graphite 100% cân bằng đều, hợp người chơi phong trào cần kiểm soát tốt.', '<div class="product-description"><h3>Mô tả sản phẩm</h3><p>BR Sensation 530 Green Black là vợt graphite 100% cân bằng đều, hợp người chơi phong trào cần kiểm soát tốt.</p><h3>Điểm nổi bật</h3><ul><li>Khung isometric tăng diện tích tiếp xúc cầu trong các pha phòng thủ nhanh.</li><li>Trọng lượng 87g và cân bằng đều tạo cảm giác linh hoạt trong điều cầu.</li><li>Cấu trúc graphite 100% cho độ ổn định tốt hơn ở nhịp tập thường xuyên.</li></ul><h3>Thông số nhanh</h3><ul><li><strong>Nguồn giá:</strong> Decathlon Việt Nam</li><li><strong>Cập nhật giá:</strong> 05/06/2026</li><li><strong>Mã tham chiếu:</strong> 8736758</li></ul><p><em>Giá seed tham chiếu theo dữ liệu công khai được tổng hợp ngày 05/06/2026.</em></p></div>', NULL, 'variant', 'active', 1, 769000.00, 769000.00, 4.80, 74, 1988, 184, 500, '2026-06-05 10:54:43', '2026-07-24 00:35:27', '2026-06-05 10:54:43', '2026-07-24 00:35:27'),
	(4, NULL, 1, NULL, 'Vợt cầu lông BR Perform 590 Purple', 'vot-cau-long-br-perform-590-purple', 'BR Perform 590 Purple thiên về lực đánh và cảm giác cầu rõ ràng hơn, phù hợp người chơi trung cấp muốn nâng nhịp tấn công.', '<div class="product-description"><h3>Mô tả sản phẩm</h3><p>BR Perform 590 Purple thiên về lực đánh và cảm giác cầu rõ ràng hơn, phù hợp người chơi trung cấp muốn nâng nhịp tấn công.</p><h3>Điểm nổi bật</h3><ul><li>Thân vợt 6.6 mm siêu mỏng cải thiện phản hồi và khả năng truyền lực.</li><li>Điểm cân bằng nặng đầu hỗ trợ các pha đập cầu có độ xuyên tốt hơn.</li><li>Khung chịu lực căng cao phù hợp người chơi đã có nền kỹ thuật.</li></ul><h3>Thông số nhanh</h3><ul><li><strong>Nguồn giá:</strong> Decathlon Việt Nam</li><li><strong>Cập nhật giá:</strong> 05/06/2026</li><li><strong>Mã tham chiếu:</strong> 8862675</li></ul><p><em>Giá seed tham chiếu theo dữ liệu công khai được tổng hợp ngày 05/06/2026.</em></p></div>', NULL, 'variant', 'active', 1, 709000.00, 709000.00, 4.90, 66, 2160, 142, 500, '2026-06-05 10:54:43', '2026-07-24 00:35:24', '2026-06-05 10:54:43', '2026-07-24 00:35:24'),
	(5, NULL, 1, NULL, 'Vợt cầu lông BR 500 White', 'vot-cau-long-br-500-white', 'BR 500 White cân bằng giữa độ linh hoạt và kiểm soát, là lựa chọn hợp lý cho người chơi đã quen kỹ thuật cơ bản.', '<div class="product-description"><h3>Mô tả sản phẩm</h3><p>BR 500 White cân bằng giữa độ linh hoạt và kiểm soát, là lựa chọn hợp lý cho người chơi đã quen kỹ thuật cơ bản.</p><h3>Điểm nổi bật</h3><ul><li>Khung và thân graphite 100% cho cảm giác đánh thoát và chính xác.</li><li>Màu trắng thanh lịch, dễ lên concept hình ảnh bán hàng.</li><li>Phù hợp người chơi phong trào muốn chuyển từ vợt entry lên dòng dễ đánh hơn.</li></ul><h3>Thông số nhanh</h3><ul><li><strong>Nguồn giá:</strong> Decathlon blog / bảng tham chiếu</li><li><strong>Cập nhật giá:</strong> 05/06/2026</li><li><strong>Giá tham chiếu:</strong> 399.000 đ</li></ul><p><em>Giá seed tham chiếu theo dữ liệu công khai được tổng hợp ngày 05/06/2026.</em></p></div>', NULL, 'variant', 'active', 0, 399000.00, 399000.00, 4.60, 58, 1540, 133, 500, '2026-06-05 10:54:43', '2026-07-24 00:35:21', '2026-06-05 10:54:43', '2026-07-24 00:35:21'),
	(6, NULL, 4, NULL, 'Vợt cầu lông BR Discover', 'vot-cau-long-br-discover', 'BR Discover là lựa chọn dễ tiếp cận cho học sinh, sinh viên và người chơi gia đình cần một cây vợt bền, dễ làm quen.', '<div class="product-description"><h3>Mô tả sản phẩm</h3><p>BR Discover là lựa chọn dễ tiếp cận cho học sinh, sinh viên và người chơi gia đình cần một cây vợt bền, dễ làm quen.</p><h3>Điểm nổi bật</h3><ul><li>Khung tiêu chuẩn giúp ổn định khi tiếp cầu lệch tâm.</li><li>Thiết kế gọn, dễ bán theo combo vợt + cầu + quấn cán.</li><li>Giá tham chiếu thấp, phù hợp làm sản phẩm entry trong catalog.</li></ul><h3>Thông số nhanh</h3><ul><li><strong>Nguồn giá:</strong> Decathlon blog / bảng tham chiếu</li><li><strong>Cập nhật giá:</strong> 05/06/2026</li><li><strong>Giá tham chiếu:</strong> 245.000 đ</li></ul><p><em>Giá seed tham chiếu theo dữ liệu công khai được tổng hợp ngày 05/06/2026.</em></p></div>', NULL, 'variant', 'active', 0, 245000.00, 245000.00, 4.50, 91, 1782, 360, 500, '2026-06-05 10:54:43', '2026-07-24 00:35:14', '2026-06-05 10:54:43', '2026-07-24 00:35:14'),
	(7, NULL, 1, NULL, 'Giày cầu lông BS Sensation 500 White Blue', 'giay-cau-long-bs-sensation-500-white-blue', 'BS Sensation 500 trắng/xanh dương là đôi giày trẻ em dễ đi, bám sàn và giữ chân khá ổn cho các buổi tập cầu lông đầu tiên.', '<div class="product-description"><h3>Mô tả sản phẩm</h3><p>BS Sensation 500 trắng/xanh dương là đôi giày trẻ em dễ đi, bám sàn và giữ chân khá ổn cho các buổi tập cầu lông đầu tiên.</p><h3>Điểm nổi bật</h3><ul><li>Form ôm gọn, phù hợp bước di chuyển ngang cơ bản trên sân trong nhà.</li><li>Phối trắng xanh sạch mắt, thuận lợi cho trưng bày cùng vợt và phụ kiện.</li><li>Mức giá trung cấp dễ ghép combo cho người chơi nhỏ tuổi.</li></ul><h3>Thông số nhanh</h3><ul><li><strong>Nguồn giá:</strong> Decathlon Việt Nam</li><li><strong>Cập nhật giá:</strong> 05/06/2026</li><li><strong>Mã tham chiếu:</strong> 8867425</li></ul><p><em>Giá seed tham chiếu theo dữ liệu công khai được tổng hợp ngày 05/06/2026.</em></p></div>', NULL, 'variant', 'active', 0, 599000.00, 599000.00, 4.70, 39, 870, 61, 500, '2026-06-05 10:54:43', '2026-07-24 00:35:10', '2026-06-05 10:54:43', '2026-07-24 00:35:10'),
	(8, NULL, 1, NULL, 'Giày cầu lông BS Lite 560 White Sea Blue', 'giay-cau-long-bs-lite-560-white-sea-blue', 'BS Lite 560 trắng/xanh biển là mẫu giày trẻ em cao hơn một nấc về giảm chấn, độ thoáng và độ ổn định khi di chuyển nhanh.', '<div class="product-description"><h3>Mô tả sản phẩm</h3><p>BS Lite 560 trắng/xanh biển là mẫu giày trẻ em cao hơn một nấc về giảm chấn, độ thoáng và độ ổn định khi di chuyển nhanh.</p><h3>Điểm nổi bật</h3><ul><li>Đệm gót DHN kết hợp EVA giúp hấp thụ lực tốt hơn ở bước chạm đất.</li><li>Cấu trúc M hỗ trợ ổn định khi đổi hướng ngang trên sân.</li><li>Upper thoáng khí và trọng lượng nhẹ giúp mang lâu vẫn dễ chịu.</li></ul><h3>Thông số nhanh</h3><ul><li><strong>Nguồn giá:</strong> Decathlon Việt Nam</li><li><strong>Cập nhật giá:</strong> 05/06/2026</li><li><strong>Mã tham chiếu:</strong> 8804495</li></ul><p><em>Giá seed tham chiếu theo dữ liệu công khai được tổng hợp ngày 05/06/2026.</em></p></div>', NULL, 'variant', 'active', 1, 1299000.00, 1299000.00, 4.80, 24, 620, 42, 500, '2026-06-05 10:54:43', '2026-07-24 00:35:08', '2026-06-05 10:54:43', '2026-07-24 00:35:08'),
	(9, NULL, 2, NULL, 'Bóng chuyền đa dụng BV Crystal Orange', 'bong-chuyen-da-dung-bv-crystal-orange', 'BV Crystal Orange là mẫu bóng nhẹ size 4, phù hợp trẻ em và người mới tập làm quen các pha chuyền cơ bản.', '<div class="product-description"><h3>Mô tả sản phẩm</h3><p>BV Crystal Orange là mẫu bóng nhẹ size 4, phù hợp trẻ em và người mới tập làm quen các pha chuyền cơ bản.</p><h3>Điểm nổi bật</h3><ul><li>Khối lượng nhẹ giúp rally dễ hơn với người chơi mới.</li><li>Chất liệu mềm tạo cảm giác chạm bóng dễ chịu, ít sợ tay.</li><li>Dùng linh hoạt trong nhà hoặc ngoài bãi cát.</li></ul><h3>Thông số nhanh</h3><ul><li><strong>Nguồn giá:</strong> Decathlon Việt Nam</li><li><strong>Cập nhật giá:</strong> 05/06/2026</li><li><strong>Mã tham chiếu:</strong> 8973757</li></ul><p><em>Giá seed tham chiếu theo dữ liệu công khai được tổng hợp ngày 05/06/2026.</em></p></div>', NULL, 'simple', 'active', 0, 99000.00, 99000.00, 4.40, 68, 1280, 284, 500, '2026-06-05 10:54:43', '2026-07-24 00:35:05', '2026-06-05 10:54:43', '2026-07-24 00:35:05'),
	(10, NULL, 2, NULL, 'Bóng chuyền bãi biển BV100 Classic Turquoise', 'bong-chuyen-bai-bien-bv100-classic-turquoise', 'BV100 Classic Turquoise là bóng chuyền bãi biển size 5 cho nhu cầu giải trí, ưu tiên độ mềm và cảm giác chạm bóng thân thiện.', '<div class="product-description"><h3>Mô tả sản phẩm</h3><p>BV100 Classic Turquoise là bóng chuyền bãi biển size 5 cho nhu cầu giải trí, ưu tiên độ mềm và cảm giác chạm bóng thân thiện.</p><h3>Điểm nổi bật</h3><ul><li>Bề mặt mềm giúp người mới tự tin khi chuyền và đỡ bóng.</li><li>Kích thước size 5 phù hợp nhóm bạn chơi bãi biển hoặc sân cát.</li><li>Thiết kế màu xanh biển dễ nhận diện trong concept summer sport.</li></ul><h3>Thông số nhanh</h3><ul><li><strong>Nguồn giá:</strong> Decathlon Việt Nam</li><li><strong>Cập nhật giá:</strong> 05/06/2026</li><li><strong>Mã tham chiếu:</strong> 8816711</li></ul><p><em>Giá seed tham chiếu theo dữ liệu công khai được tổng hợp ngày 05/06/2026.</em></p></div>', NULL, 'simple', 'active', 0, 259000.00, 259000.00, 4.60, 44, 968, 112, 500, '2026-06-05 10:54:43', '2026-07-24 00:35:01', '2026-06-05 10:54:43', '2026-07-24 00:35:01'),
	(11, NULL, 2, NULL, 'Bóng chuyền VB500 Classic White Blue', 'bong-chuyen-vb500-classic-white-blue', 'VB500 Classic là mẫu bóng dành cho người chơi trung cấp cần cảm giác bóng ổn định hơn ở sân trong nhà và các trận giao hữu.', '<div class="product-description"><h3>Mô tả sản phẩm</h3><p>VB500 Classic là mẫu bóng dành cho người chơi trung cấp cần cảm giác bóng ổn định hơn ở sân trong nhà và các trận giao hữu.</p><h3>Điểm nổi bật</h3><ul><li>Lớp phủ laminate cho cảm giác bóng đều và bền hơn khi tập thường xuyên.</li><li>Trọng lượng theo chuẩn chính thức giúp làm quen cảm giác thi đấu tốt hơn.</li><li>Tông trắng xanh rất hợp danh mục bóng chuyền indoor.</li></ul><h3>Thông số nhanh</h3><ul><li><strong>Nguồn giá:</strong> Decathlon Việt Nam</li><li><strong>Cập nhật giá:</strong> 05/06/2026</li><li><strong>Mã tham chiếu:</strong> 8927919</li></ul><p><em>Giá seed tham chiếu theo dữ liệu công khai được tổng hợp ngày 05/06/2026.</em></p></div>', NULL, 'simple', 'active', 1, 479000.00, 479000.00, 4.80, 52, 1365, 167, 500, '2026-06-05 10:54:43', '2026-07-24 00:34:20', '2026-06-05 10:54:43', '2026-07-24 00:34:20'),
	(12, NULL, 2, NULL, 'Băng bảo vệ gối bóng chuyền VKP100 Black', 'bang-bao-ve-goi-bong-chuyen-vkp100-black', 'VKP100 là băng bảo vệ gối cơ bản dành cho người mới tập bóng chuyền, ưu tiên sự thoải mái và tự tin khi lao người cứu bóng.', '<div class="product-description"><h3>Mô tả sản phẩm</h3><p>VKP100 là băng bảo vệ gối cơ bản dành cho người mới tập bóng chuyền, ưu tiên sự thoải mái và tự tin khi lao người cứu bóng.</p><h3>Điểm nổi bật</h3><ul><li>Đệm foam 20 mm giảm khó chịu khi chạm sàn ở mức cơ bản.</li><li>Chất liệu co giãn dễ ôm chân, phù hợp nhiều thể trạng.</li><li>Sản phẩm tiện để bán kèm bóng và lưới tập gia đình.</li></ul><h3>Thông số nhanh</h3><ul><li><strong>Nguồn giá:</strong> Decathlon Việt Nam</li><li><strong>Cập nhật giá:</strong> 05/06/2026</li><li><strong>Mã tham chiếu:</strong> 8670049</li></ul><p><em>Giá seed tham chiếu theo dữ liệu công khai được tổng hợp ngày 05/06/2026.</em></p></div>', NULL, 'variant', 'active', 0, 199000.00, 199000.00, 4.50, 37, 842, 95, 500, '2026-06-05 10:54:43', '2026-07-24 00:34:58', '2026-06-05 10:54:43', '2026-07-24 00:34:58'),
	(13, NULL, 2, NULL, 'Bộ lưới bóng chuyền bãi biển BV500 Yellow', 'bo-luoi-bong-chuyen-bai-bien-bv500-yellow', 'BV500 Yellow là bộ lưới bóng chuyền bãi biển phù hợp cho nhóm bạn, resort hoặc sân cộng đồng cần set-up nhanh và gọn.', '<div class="product-description"><h3>Mô tả sản phẩm</h3><p>BV500 Yellow là bộ lưới bóng chuyền bãi biển phù hợp cho nhóm bạn, resort hoặc sân cộng đồng cần set-up nhanh và gọn.</p><h3>Điểm nổi bật</h3><ul><li>Tổng thể gọn trong một bộ, dễ vận chuyển và dựng sân ngoài trời.</li><li>Phù hợp nhu cầu beach volley giải trí và tập luyện bán chuyên.</li><li>Màu vàng nổi bật giúp bộ hình ảnh sản phẩm bắt mắt trên nền trắng.</li></ul><h3>Thông số nhanh</h3><ul><li><strong>Nguồn giá:</strong> Decathlon Việt Nam</li><li><strong>Cập nhật giá:</strong> 05/06/2026</li><li><strong>Mã tham chiếu:</strong> 8480571</li></ul><p><em>Giá seed tham chiếu theo dữ liệu công khai được tổng hợp ngày 05/06/2026.</em></p></div>', NULL, 'simple', 'active', 1, 2499000.00, 2499000.00, 4.80, 18, 510, 28, 500, '2026-06-05 10:54:43', '2026-07-24 00:34:55', '2026-06-05 10:54:43', '2026-07-24 00:34:55'),
	(14, NULL, 2, NULL, 'Bộ sân bóng chuyền bãi biển BV900 Official', 'bo-san-bong-chuyen-bai-bien-bv900-official', 'BV900 Official là bộ sân beach volley hoàn chỉnh cho nhu cầu setup nghiêm túc hơn, có kèm đường biên và balo đựng.', '<div class="product-description"><h3>Mô tả sản phẩm</h3><p>BV900 Official là bộ sân beach volley hoàn chỉnh cho nhu cầu setup nghiêm túc hơn, có kèm đường biên và balo đựng.</p><h3>Điểm nổi bật</h3><ul><li>Có 3 mức chiều cao chính thức 2.24 m, 2.35 m và 2.43 m.</li><li>Dựng và tháo tương đối nhanh khi có 2 người hỗ trợ.</li><li>Phù hợp sân cát cộng đồng, bãi biển sự kiện và CLB phong trào.</li></ul><h3>Thông số nhanh</h3><ul><li><strong>Nguồn giá:</strong> Decathlon Việt Nam</li><li><strong>Cập nhật giá:</strong> 05/06/2026</li><li><strong>Mã tham chiếu:</strong> 8408762</li></ul><p><em>Giá seed tham chiếu theo dữ liệu công khai được tổng hợp ngày 05/06/2026.</em></p></div>', NULL, 'simple', 'active', 1, 2999000.00, 2999000.00, 5.00, 1, 402, 17, 500, '2026-06-05 10:54:43', '2026-07-24 00:34:52', '2026-06-05 10:54:43', '2026-07-24 00:34:52'),
	(15, 4, 3, NULL, 'Pickleball Paddle 100 Black', 'pickleball-paddle-100-black', 'Pickleball Paddle 100 Black hướng tới người mới tập thường xuyên, dễ cầm và cho cảm giác chạm bóng êm tay.', '<div class="product-description"><h3>Mô tả sản phẩm</h3><p>Pickleball Paddle 100 Black hướng tới người mới tập thường xuyên, dễ cầm và cho cảm giác chạm bóng êm tay.</p><h3>Điểm nổi bật</h3><ul><li>Trọng lượng 230g giúp thao tác dễ và ít mỏi khi tập thời gian dài.</li><li>Mặt vợt sợi thủy tinh kết hợp carbon, lõi polypropylene cho độ êm tốt.</li><li>Màu đen tối giản phù hợp trưng bày cùng set bóng hoặc túi.</li></ul><h3>Thông số nhanh</h3><ul><li><strong>Nguồn giá:</strong> Decathlon Việt Nam</li><li><strong>Cập nhật giá:</strong> 05/06/2026</li><li><strong>Mã tham chiếu:</strong> 8767131</li></ul><p><em>Giá seed tham chiếu theo dữ liệu công khai được tổng hợp ngày 05/06/2026.</em></p></div>', NULL, 'simple', 'active', 0, 399000.00, 399000.00, 4.60, 47, 1488, 173, 500, '2026-06-05 10:54:43', '2026-07-24 00:34:49', '2026-06-05 10:54:43', '2026-07-24 00:34:49'),
	(16, 4, 1, NULL, 'Vợt Pickleball Kuikma Open Blue', 'vot-pickleball-kuikma-open-blue', 'Kuikma Open Blue là mẫu vợt pickleball bán chạy cho người mới chơi thường xuyên, cân bằng tốt giữa lực và độ dễ điều khiển.', '<div class="product-description"><h3>Mô tả sản phẩm</h3><p>Kuikma Open Blue là mẫu vợt pickleball bán chạy cho người mới chơi thường xuyên, cân bằng tốt giữa lực và độ dễ điều khiển.</p><h3>Điểm nổi bật</h3><ul><li>Độ dày 16 mm cho lực đánh vững và cảm giác bóng chắc hơn dòng mỏng.</li><li>Trọng lượng 225g giữ được sự linh hoạt ở các pha phản xạ gần lưới.</li><li>Thiết kế xanh dương dễ lên ảnh packshot phông trắng.</li></ul><h3>Thông số nhanh</h3><ul><li><strong>Nguồn giá:</strong> Decathlon Việt Nam</li><li><strong>Cập nhật giá:</strong> 05/06/2026</li><li><strong>Mã tham chiếu:</strong> 8941064</li></ul><p><em>Giá seed tham chiếu theo dữ liệu công khai được tổng hợp ngày 05/06/2026.</em></p></div>', NULL, 'simple', 'active', 1, 699000.00, 699000.00, 4.80, 58, 1825, 144, 500, '2026-06-05 10:54:43', '2026-07-24 00:34:45', '2026-06-05 10:54:43', '2026-07-24 00:34:45'),
	(17, 4, 4, NULL, 'Bộ 2 vợt pickleball + 2 bóng + túi Play', 'bo-2-vot-pickleball-2-bong-tui-play', 'Set Play gồm 2 vợt, 2 bóng và 1 túi đựng, cực hợp để người mới bước vào pickleball mà không cần mua lẻ nhiều món.', '<div class="product-description"><h3>Mô tả sản phẩm</h3><p>Set Play gồm 2 vợt, 2 bóng và 1 túi đựng, cực hợp để người mới bước vào pickleball mà không cần mua lẻ nhiều món.</p><h3>Điểm nổi bật</h3><ul><li>Combo đủ đồ để hai người vào sân ngay sau khi mở hộp.</li><li>Vợt 220g khá thân thiện với người lớn lẫn thiếu niên.</li><li>Rất phù hợp làm sản phẩm featured ở landing page pickleball.</li></ul><h3>Thông số nhanh</h3><ul><li><strong>Nguồn giá:</strong> Decathlon Việt Nam</li><li><strong>Cập nhật giá:</strong> 05/06/2026</li><li><strong>Mã tham chiếu:</strong> 8969343</li></ul><p><em>Giá seed tham chiếu theo dữ liệu công khai được tổng hợp ngày 05/06/2026.</em></p></div>', NULL, 'simple', 'active', 1, 1099000.00, 1099000.00, 4.90, 33, 1056, 87, 500, '2026-06-05 10:54:43', '2026-07-24 00:34:40', '2026-06-05 10:54:43', '2026-07-24 00:34:40'),
	(18, 4, 5, NULL, 'Vợt Pickleball EliteX 16MM Blue', 'vot-pickleball-elitex-16mm-blue', 'EliteX 16MM Blue nằm ở phân khúc cao hơn, hướng đến người chơi pickleball muốn cảm giác kiểm soát, độ ổn định và độ bám mặt vợt tốt.', '<div class="product-description"><h3>Mô tả sản phẩm</h3><p>EliteX 16MM Blue nằm ở phân khúc cao hơn, hướng đến người chơi pickleball muốn cảm giác kiểm soát, độ ổn định và độ bám mặt vợt tốt.</p><h3>Điểm nổi bật</h3><ul><li>Lõi tổ ong ElasticPP dày 16 mm cho cảm giác bóng đầm và chắc.</li><li>Bề mặt carbon phủ gốm tăng độ bám bóng khi tạo xoáy.</li><li>Hợp người chơi đã có nền tảng và muốn nâng cấp cảm giác thi đấu.</li></ul><h3>Thông số nhanh</h3><ul><li><strong>Nguồn giá:</strong> Decathlon Việt Nam</li><li><strong>Cập nhật giá:</strong> 05/06/2026</li><li><strong>Mã tham chiếu:</strong> 9010633</li></ul><p><em>Giá seed tham chiếu theo dữ liệu công khai được tổng hợp ngày 05/06/2026.</em></p></div>', NULL, 'simple', 'active', 1, 4500000.00, 4500000.00, 5.00, 1, 740, 29, 500, '2026-06-05 10:54:43', '2026-07-24 00:34:36', '2026-06-05 10:54:43', '2026-07-24 00:34:36'),
	(19, 4, 3, NULL, 'Giày tennis/pickleball nam All Court Light Grey Blue', 'giay-tennispickleball-nam-all-court-light-grey-blue', 'Mẫu giày all-court xám nhạt/xanh dương của Artengo phù hợp người chơi pickleball nam cần độ bám và cảm giác đổi hướng nhanh.', '<div class="product-description"><h3>Mô tả sản phẩm</h3><p>Mẫu giày all-court xám nhạt/xanh dương của Artengo phù hợp người chơi pickleball nam cần độ bám và cảm giác đổi hướng nhanh.</p><h3>Điểm nổi bật</h3><ul><li>Đế đa mặt sân cho cảm giác bám và chuyển hướng linh hoạt.</li><li>Form thể thao gọn, dễ phối cùng quần short hoặc set pickleball cơ bản.</li><li>Tông xám nhạt/xanh dương rất hợp chụp packshot nền trắng.</li></ul><h3>Thông số nhanh</h3><ul><li><strong>Nguồn giá:</strong> Decathlon Việt Nam</li><li><strong>Cập nhật giá:</strong> 05/06/2026</li><li><strong>Mã tham chiếu:</strong> 8750854</li></ul><p><em>Giá seed tham chiếu theo dữ liệu công khai được tổng hợp ngày 05/06/2026.</em></p></div>', NULL, 'variant', 'active', 1, 1399000.00, 1399000.00, 4.80, 41, 1322, 76, 500, '2026-06-05 10:54:43', '2026-07-24 00:34:32', '2026-06-05 10:54:43', '2026-07-24 00:34:32'),
	(20, 4, 3, NULL, 'Giày pickleball nam Essential White', 'giay-pickleball-nam-essential-white', 'Giày Essential White là lựa chọn mở đầu dễ tiếp cận cho người chơi pickleball cần một đôi giày sáng màu, dễ phối và đủ ổn định.', '<p>sssss</p>', 'products/thumbnails/OKDXJ1J3zG2IjETN2RlZMvbc0Ciw7A0Wta243chf.webp', 'variant', 'active', 0, 539000.00, 539000.00, 4.50, 28, 996, 63, 500, '2026-06-05 10:54:43', '2026-07-24 00:35:33', '2026-06-05 10:54:43', '2026-07-24 00:35:33'),
	(166, 7, NULL, 1, 'Vợt cầu lông VNB V88 xanh chính hãng', 'vot-cau-long-vnb-v88-xanh-chinh-hang', NULL, NULL, '/storage/products/65fcc5ca-40a3-4ca6-9302-11d75a3a3602.webp', 'variant', 'active', 0, 638000.00, 638000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:24:47', '2026-07-24 00:33:08'),
	(167, 7, NULL, 1, 'Vợt cầu lông VNB V88 cam chính hãng', 'vot-cau-long-vnb-v88-cam-chinh-hang', NULL, NULL, '/storage/products/98b5a886-a3f4-4249-a290-eff59e0a7545.webp', 'variant', 'active', 0, 638000.00, 638000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:25:01', '2026-07-24 00:32:43'),
	(168, 7, NULL, 1, 'Vợt cầu lông VNB TC88B chính hãng', 'vot-cau-long-vnb-tc88b-chinh-hang', NULL, NULL, '/storage/products/84d741b5-f214-44fd-9313-b2e499d2c023.webp', 'variant', 'active', 0, 799000.00, 799000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:26:00', '2026-07-24 00:32:17'),
	(169, 7, NULL, 1, 'Vợt cầu lông VNB TC88C chính hãng', 'vot-cau-long-vnb-tc88c-chinh-hang', NULL, NULL, '/storage/products/438154c9-5e99-49b6-8322-a78ab56d85a0.webp', 'variant', 'active', 0, 799000.00, 799000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:26:14', '2026-07-24 00:31:43'),
	(170, 7, NULL, 1, 'Vợt cầu lông VNB V200i Hồng chính hãng', 'vot-cau-long-vnb-v200i-hong-chinh-hang', NULL, NULL, '/storage/products/e2ea55d1-130e-4cdc-9af6-47cfd37619d5.webp', 'variant', 'active', 0, 529000.00, 529000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:26:26', '2026-07-24 00:30:35'),
	(171, 7, NULL, 1, 'Vợt cầu lông VNB Carbon Training 150g chính hãng', 'vot-cau-long-vnb-carbon-training-150g-chinh-hang', NULL, NULL, '/storage/products/dfe9856e-053a-4fb9-a651-324a2332cdaa.webp', 'variant', 'active', 0, 698000.00, 698000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:26:40', '2026-07-24 00:30:09'),
	(172, 7, NULL, 1, 'Vợt cầu lông VNB V200 Đỏ chính hãng', 'vot-cau-long-vnb-v200-do-chinh-hang', NULL, NULL, '/storage/products/3f4dd8f6-a1c5-475f-baa8-8fc08955fbeb.webp', 'variant', 'active', 0, 529000.00, 529000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:26:54', '2026-07-24 00:29:41'),
	(173, 7, NULL, 1, 'Vợt cầu lông VNB V200 Xanh chính hãng', 'vot-cau-long-vnb-v200-xanh-chinh-hang', NULL, NULL, '/storage/products/bdd70cbf-7bce-4345-8ab9-2a672a5abb25.webp', 'variant', 'active', 0, 529000.00, 529000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:27:06', '2026-07-24 00:29:24'),
	(174, 7, NULL, 1, 'Combo Mua Vợt Cầu Lông IRON MAN GB Tặng Vợt TK HMR Pro + Vợt Victor ARS 8000 + Vợt Victor DX1A + Túi Victor 2605', 'combo-mua-vot-cau-long-iron-man-gb-tang-vot-tk-hmr-pro-vot-victor-ars-8000-vot-victor-dx1a-tui-victor-2605', NULL, NULL, '/storage/products/86fac134-f2ca-457a-ab14-bbeed3bd689e.webp', 'variant', 'active', 0, 7130000.00, 7130000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:27:17', '2026-07-24 00:29:01'),
	(175, 7, NULL, 1, 'Combo Mua Vợt Cầu Lông Victor Mjolnir Metallic Tặng Vợt Victor ARS 8000 + Vợt Victor DX1A + Vợt Victor Ryuga CLS + Túi Victor 2605', 'combo-mua-vot-cau-long-victor-mjolnir-metallic-tang-vot-victor-ars-8000-vot-victor-dx1a-vot-victor-ryuga-cls-tui-victor-2605', NULL, NULL, '/storage/products/899492e4-ba06-428b-a9ed-545c68b579b1.webp', 'variant', 'active', 0, 6250000.00, 6250000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:27:28', '2026-07-24 00:28:46'),
	(176, 7, NULL, 1, 'Combo Mua Vợt Cầu Lông Victor TK - TTY Ultima Tặng Vợt Victor DX1A + Vợt Victor TK HMR Pro + Túi Victor 2605', 'combo-mua-vot-cau-long-victor-tk-tty-ultima-tang-vot-victor-dx1a-vot-victor-tk-hmr-pro-tui-victor-2605', NULL, NULL, '/storage/products/3ffcd1b7-bc96-4a66-acab-c038fe912d17.webp', 'variant', 'active', 0, 6030000.00, 6030000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:27:36', '2026-07-24 00:26:27'),
	(177, 7, NULL, 1, 'Combo Mua Vợt Cầu Lông Victor Ryuga II Tặng Vợt DX1A + Vợt Victor TK HMR Pro +Túi Victor 2605', 'combo-mua-vot-cau-long-victor-ryuga-ii-tang-vot-dx1a-vot-victor-tk-hmr-pro-tui-victor-2605', NULL, NULL, '/storage/products/ca359c4c-8df3-4b59-8f06-c7c34bc54ab9.webp', 'variant', 'active', 0, 5320000.00, 5320000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:27:47', '2026-07-24 00:27:02'),
	(178, 7, NULL, 1, 'Combo Mua Set Vợt Cầu Lông Victor TK-CNY GB Tặng Vợt Victor ARS 8000 + Vợt Victor ARS A + Vợt Victor TK Ryuga CLS + Túi Victor 2605', 'combo-mua-set-vot-cau-long-victor-tk-cny-gb-tang-vot-victor-ars-8000-vot-victor-ars-a-vot-victor-tk-ryuga-cls-tui-victor-2605', NULL, NULL, '/storage/products/f971b034-a350-488d-bb3f-8d7dee4d100e.webp', 'variant', 'active', 0, 6030000.00, 6030000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:27:58', '2026-07-24 00:24:28'),
	(179, 7, NULL, 1, 'Combo Mua Vợt Cầu Lông Victor ARS 100X Tặng Vợt Victor TK Ryuga CLS + Vợt Victor ARS 8000 + Vợt Victor ARS 9', 'combo-mua-vot-cau-long-victor-ars-100x-tang-vot-victor-tk-ryuga-cls-vot-victor-ars-8000-vot-victor-ars-9', NULL, NULL, '/storage/products/e3c18f97-af88-4b3a-9e49-79e4e77728b9.webp', 'variant', 'active', 0, 5830000.00, 5830000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:28:09', '2026-07-23 23:56:47'),
	(180, 7, NULL, 1, 'Combo Mua Vợt Cầu Lông Victor ARS 9 Tặng Vợt Victor ARS 9 + Vợt Pickleball Kawasaki Galaxy', 'combo-mua-vot-cau-long-victor-ars-9-tang-vot-victor-ars-9-vot-pickleball-kawasaki-galaxy', NULL, NULL, '/storage/products/f5b95888-b98b-41c7-87d3-68f6e18f81fc.webp', 'variant', 'active', 0, 2650000.00, 2650000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:28:19', '2026-07-24 00:01:37'),
	(181, 7, NULL, 1, 'Combo Mua Vợt Cầu Lông Victor JS12II Tặng Vợt Victor TK Ryuga CLS + Vợt Victor ARS 8000 + Vợt Victor ARS 9', 'combo-mua-vot-cau-long-victor-js12ii-tang-vot-victor-tk-ryuga-cls-vot-victor-ars-8000-vot-victor-ars-9', '', '', '/storage/products/090e6016-437f-46d6-9d13-4f0ac818c574.webp', 'variant', 'active', 0, 5130000.00, 6156000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:28:31', '2026-07-21 15:28:32'),
	(182, 7, NULL, 1, 'Combo Mua Vợt Cầu Lông Victor DX10 Metallic Tặng Vợt Victor ARS 9 + Vợt Victor ARS 8000 + Vợt Victor TK HMR Pro', 'combo-mua-vot-cau-long-victor-dx10-metallic-tang-vot-victor-ars-9-vot-victor-ars-8000-vot-victor-tk-hmr-pro', NULL, NULL, '/storage/products/48b421bc-62e9-4955-a7e1-defcb212f66a.webp', 'variant', 'active', 0, 5330000.00, 5330000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:28:42', '2026-07-23 23:51:33'),
	(183, 7, NULL, 1, 'Combo Mua Vợt Cầu Lông Victor TK-F Ultra Tặng Vợt Victor ARS A + Vợt Victor TK Ryuga CLS', 'combo-mua-vot-cau-long-victor-tk-f-ultra-tang-vot-victor-ars-a-vot-victor-tk-ryuga-cls', NULL, NULL, '/storage/products/e95099e7-8d65-40a0-b124-b7707e071aed.webp', 'variant', 'active', 0, 5810000.00, 5810000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:28:54', '2026-07-23 16:06:42'),
	(184, 8, NULL, 1, 'Giày cầu lông Victor A970 cADV/AM - Trắng chính hãng', 'giay-cau-long-victor-a970-cadvam-trang-chinh-hang', NULL, NULL, '/storage/products/b2091180-a285-4355-8fed-92ba8c763bbb.webp', 'variant', 'active', 0, 2900000.00, 2900000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:30:40', '2026-07-23 16:06:18'),
	(185, 8, NULL, 1, 'Giày Cầu Lông Victor P9200 CLS AC - Trắng đen chính hãng', 'giay-cau-long-victor-p9200-cls-ac-trang-den-chinh-hang', NULL, NULL, '/storage/products/04aa7e03-3342-4a64-a244-d3e4f37ff3c1.webp', 'variant', 'active', 0, 1350000.00, 1350000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:30:55', '2026-07-23 16:05:57'),
	(186, 8, NULL, 1, 'Giày cầu lông Lining AYAU003-1 chính hãng', 'giay-cau-long-lining-ayau003-1-chinh-hang', NULL, NULL, '/storage/products/98970777-c1f6-475f-93da-8e2234116d35.webp', 'variant', 'active', 0, 2600000.00, 2600000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:33:10', '2026-07-23 16:05:33'),
	(187, 8, NULL, 1, 'Giày cầu lông Lining AYTW003-2 chính hãng', 'giay-cau-long-lining-aytw003-2-chinh-hang', NULL, NULL, '/storage/products/963eb6ad-d588-4729-a89b-e1c16d13e0b5.webp', 'variant', 'active', 0, 1100000.00, 1100000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:33:26', '2026-07-23 16:05:05'),
	(188, 8, NULL, 1, 'Giày cầu lông Lining AYZW007-3 chính hãng', 'giay-cau-long-lining-ayzw007-3-chinh-hang', NULL, NULL, '/storage/products/73e54e86-3760-47ae-a10d-2654a32fd0e5.webp', 'variant', 'active', 0, 2290000.00, 2290000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:33:43', '2026-07-23 16:04:45'),
	(189, 8, NULL, 1, 'Giày cầu lông Lining AYZW007-2 chính hãng', 'giay-cau-long-lining-ayzw007-2-chinh-hang', NULL, NULL, '/storage/products/3f43b297-c483-465d-9d58-e9bca5283d40.webp', 'variant', 'active', 0, 2290000.00, 2290000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:33:58', '2026-07-23 16:04:22'),
	(190, 8, NULL, 1, 'Giày cầu lông Lining AYZW007-1 chính hãng', 'giay-cau-long-lining-ayzw007-1-chinh-hang', NULL, NULL, '/storage/products/3210a0d5-e4eb-4592-ae29-c246deee7342.webp', 'variant', 'active', 0, 2290000.00, 2290000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:34:15', '2026-07-23 16:04:06'),
	(191, 8, NULL, 1, 'Giày cầu lông Lining AYTU025-5 chính hãng', 'giay-cau-long-lining-aytu025-5-chinh-hang', NULL, NULL, '/storage/products/6f148e06-4505-48c7-9e97-225e06c6eddb.webp', 'variant', 'active', 0, 1300000.00, 1300000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:34:30', '2026-07-23 15:59:23'),
	(192, 8, NULL, 1, 'Giày cầu lông Lining AYAU005-4 chính hãng', 'giay-cau-long-lining-ayau005-4-chinh-hang', NULL, NULL, '/storage/products/991a02dc-c652-46c8-a7c9-59096d787e60.webp', 'variant', 'active', 0, 2500000.00, 2500000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:34:46', '2026-07-23 15:59:01'),
	(193, 9, NULL, 1, 'Áo cầu lông Yonex RM3239 - White chính hãng', 'ao-cau-long-yonex-rm3239-white-chinh-hang', NULL, NULL, '/storage/products/026f9252-4579-4f19-a72a-740bfeb619ee.webp', 'variant', 'active', 0, 149000.00, 149000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:35:08', '2026-07-23 15:58:39'),
	(194, 9, NULL, 1, 'Áo cầu lông Yonex RM3239 - Jet Black chính hãng', 'ao-cau-long-yonex-rm3239-jet-black-chinh-hang', NULL, NULL, '/storage/products/c28c22f6-378b-44ec-a990-d274d23105ce.webp', 'variant', 'active', 0, 149000.00, 149000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:35:22', '2026-07-23 15:58:19'),
	(195, 9, NULL, 1, 'Áo cầu lông Yonex RM3235 - Dark Eclipse chính hãng', 'ao-cau-long-yonex-rm3235-dark-eclipse-chinh-hang', NULL, NULL, '/storage/products/6aa3cc68-484a-402d-ab0c-791c6ca156f9.webp', 'variant', 'active', 0, 149000.00, 149000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:35:37', '2026-07-23 15:57:59'),
	(196, 9, NULL, 1, 'Áo cầu lông Yonex RM3235 - Black chính hãng', 'ao-cau-long-yonex-rm3235-black-chinh-hang', NULL, NULL, '/storage/products/89ae4a7a-b5e6-4f3b-bb8a-51951bd15360.webp', 'variant', 'active', 0, 149000.00, 149000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:35:50', '2026-07-23 15:57:37'),
	(197, 9, NULL, 1, 'Áo cầu lông Yonex RM3235 - White chính hãng', 'ao-cau-long-yonex-rm3235-white-chinh-hang', NULL, NULL, '/storage/products/e1b0f6f0-a316-43b4-9d7d-4a20f68d6690.webp', 'variant', 'active', 0, 149000.00, 149000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:36:04', '2026-07-23 15:57:14'),
	(198, 9, NULL, 1, 'Áo cầu lông Yonex RM3232 - White chính hãng', 'ao-cau-long-yonex-rm3232-white-chinh-hang', NULL, NULL, '/storage/products/f9f64a37-36f3-417e-8443-1172e1e67c45.webp', 'variant', 'active', 0, 149000.00, 149000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:36:19', '2026-07-23 15:56:54'),
	(199, 9, NULL, 1, 'Áo cầu lông Yonex RM3232 - Dark Eclipse chính hãng', 'ao-cau-long-yonex-rm3232-dark-eclipse-chinh-hang', NULL, NULL, '/storage/products/24f742be-420d-4939-b248-b8a7b2ceb79f.webp', 'variant', 'active', 0, 149000.00, 149000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:36:30', '2026-07-23 15:56:27'),
	(200, 11, NULL, 1, 'Vợt Pickleball Kaiwin Rocket Pro Diamond 16mm chính hãng', 'vot-pickleball-kaiwin-rocket-pro-diamond-16mm-chinh-hang', NULL, NULL, '/storage/products/d7f4be6c-68fe-44f8-a890-6a31ee755f98.webp', 'variant', 'active', 0, 2699000.00, 2699000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:36:49', '2026-07-23 15:56:07'),
	(201, 11, NULL, 1, 'Vợt Pickleball Selkirk Vanguard Power Air - Epic Selkirk Red chính hãng (Demo)', 'vot-pickleball-selkirk-vanguard-power-air-epic-selkirk-red-chinh-hang-demo', NULL, NULL, '/storage/products/b803f2f5-e032-467f-b956-9e44e73d9a13.jpg', 'variant', 'active', 0, 4790000.00, 4790000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:38:40', '2026-07-23 15:55:40'),
	(202, 11, NULL, 1, 'Set vợt Pickleball Kumpoo Surpass Mystery 2 GZ chính hãng', 'set-vot-pickleball-kumpoo-surpass-mystery-2-gz-chinh-hang', NULL, NULL, '/storage/products/d179fa9f-8201-41f6-a466-cbecabce0610.webp', 'variant', 'active', 0, 1649000.00, 1649000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:39:59', '2026-07-23 15:55:18'),
	(203, 12, NULL, 1, 'Giày Pickleball Adidas Courtquick - Gold Metallic/Gold Metallic chính hãng (KI3590)', 'giay-pickleball-adidas-courtquick-gold-metallicgold-metallic-chinh-hang-ki3590', NULL, NULL, '/storage/products/8787c4f0-cca6-4927-8698-aec3a96a24c9.webp', 'variant', 'active', 0, 2390000.00, 2390000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:41:28', '2026-07-23 15:54:49'),
	(204, 12, NULL, 1, 'Giày Pickleball Lining AKPW001-2 chính hãng', 'giay-pickleball-lining-akpw001-2-chinh-hang', NULL, NULL, '/storage/products/582e3d25-efe3-4399-bf6b-3b138c9a4008.webp', 'variant', 'active', 0, 2200000.00, 2200000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:43:51', '2026-07-23 15:54:27'),
	(205, 12, NULL, 1, 'Giày Pickleball Lining AKPW001-1 chính hãng', 'giay-pickleball-lining-akpw001-1-chinh-hang', NULL, NULL, '/storage/products/291dc1fb-00e1-4873-9296-b0aefbf3007c.webp', 'variant', 'active', 0, 2200000.00, 2200000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:44:04', '2026-07-23 15:53:22'),
	(206, 12, NULL, 1, 'Giày Pickleball Lining AKPV001-3 chính hãng', 'giay-pickleball-lining-akpv001-3-chinh-hang', NULL, NULL, '/storage/products/0c3ebf11-0b73-40f9-b38a-ab98c07949df.webp', 'variant', 'active', 0, 2600000.00, 2600000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:44:16', '2026-07-23 15:52:50'),
	(207, 12, NULL, 1, 'Giày Pickleball Lining AKPV001-2 chính hãng', 'giay-pickleball-lining-akpv001-2-chinh-hang', NULL, NULL, '/storage/products/1d36d70c-ddc2-4850-9023-523dce116210.webp', 'variant', 'active', 0, 2600000.00, 2600000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:44:29', '2026-07-23 15:52:29'),
	(208, 13, NULL, 1, 'Balo Pickleball Joola Agassi Vision II Backpack chính hãng', 'balo-pickleball-joola-agassi-vision-ii-backpack-chinh-hang', NULL, NULL, '/storage/products/da7ca016-4548-4d7f-be7b-6206570120fa.webp', 'variant', 'active', 0, 1550000.00, 1550000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:48:19', '2026-07-23 15:52:13'),
	(209, 13, NULL, 1, 'Balo Pickleball Zocker - Đỏ đen chính hãng', 'balo-pickleball-zocker-do-den-chinh-hang', '', '', '/storage/products/743617ef-9b47-4de4-9f38-04323f78566b.webp', 'variant', 'active', 0, 2350000.00, 2820000.00, 0.00, 0, 0, 0, 500, NULL, '2026-07-23 15:51:51', '2026-07-21 15:48:29', '2026-07-23 15:51:51'),
	(210, 13, NULL, 1, 'Balo Pickleball Zocker - Cam đen chính hãng', 'balo-pickleball-zocker-cam-den-chinh-hang', NULL, NULL, '/storage/products/6eda8763-24b6-419c-a422-61bb1b3583a1.webp', 'variant', 'active', 0, 2350000.00, 2350000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:48:39', '2026-07-23 15:51:37'),
	(211, 13, NULL, 1, 'Balo Pickleball Joola Vision II Petrol Teal chính hãng', 'balo-pickleball-joola-vision-ii-petrol-teal-chinh-hang', NULL, NULL, '/storage/products/ebbd4249-52a2-414f-bf99-445fd7f7ff56.webp', 'variant', 'active', 0, 1499000.00, 1499000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:49:16', '2026-07-23 15:51:17'),
	(212, 13, NULL, 1, 'Balo Pickleball Joola Vision II Blue', 'balo-pickleball-joola-vision-ii-blue', NULL, NULL, '/storage/products/fc411bc5-40f3-4c90-baa5-814a0ca72bcf.webp', 'variant', 'active', 0, 1499000.00, 1499000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:51:01', '2026-07-23 15:51:02'),
	(213, 13, NULL, 1, 'Balo Pickleball Joola Vision II Deluxe (Black)', 'balo-pickleball-joola-vision-ii-deluxe-black', NULL, NULL, '/storage/products/c990050d-3cf0-4a87-93e3-3bd338be5695.webp', 'variant', 'active', 0, 1990000.00, 1990000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:51:21', '2026-07-23 15:50:46'),
	(214, 15, NULL, 1, 'Vợt Tennis Babolat Pure Strike Team Gen 4 285gr chính hãng (101580)', 'vot-tennis-babolat-pure-strike-team-gen-4-285gr-chinh-hang-101580', NULL, NULL, '/storage/products/95ce6218-a99b-477b-adcb-f103dacec50a.webp', 'variant', 'active', 0, 4999000.00, 4999000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:51:54', '2026-07-23 15:50:25'),
	(215, 15, NULL, 1, 'Vợt Tennis Babolat Pure Drive Lite 270gr chính hãng (101555)', 'vot-tennis-babolat-pure-drive-lite-270gr-chinh-hang-101555', NULL, NULL, '/storage/products/1c2169e4-4ba2-4d74-a442-6be9e4d8bc45.webp', 'variant', 'active', 0, 4999000.00, 4999000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:52:18', '2026-07-23 15:50:09'),
	(216, 15, NULL, 1, 'Vợt Tennis Babolat Evo Aero Lite Gen 2 Pink chính hãng (102565)', 'vot-tennis-babolat-evo-aero-lite-gen-2-pink-chinh-hang-102565', NULL, NULL, '/storage/products/fb4b72fa-7524-46d2-8430-2fbfa7784505.webp', 'variant', 'active', 0, 4699000.00, 4699000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:52:30', '2026-07-23 15:49:55'),
	(217, 15, NULL, 1, 'Vợt Tennis Babolat Evo Drive Gen 2 Unstrung 270gr chính hãng (101545)', 'vot-tennis-babolat-evo-drive-gen-2-unstrung-270gr-chinh-hang-101545', NULL, NULL, '/storage/products/ee5ace7c-ff72-488a-8dca-4cb71bc96e2d.webp', 'variant', 'active', 0, 3959000.00, 3959000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:52:42', '2026-07-23 15:49:41'),
	(218, 15, NULL, 1, 'Vợt Tennis Babolat Boost Aero Pink 260gr chính hãng (121253)', 'vot-tennis-babolat-boost-aero-pink-260gr-chinh-hang-121253', NULL, NULL, '/storage/products/741bd223-d9d8-4bc4-966d-1a71862d1890.webp', 'variant', 'active', 0, 2799000.00, 2799000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:52:55', '2026-07-23 15:49:25'),
	(219, 15, NULL, 1, 'Vợt Tennis Babolat Boost Drive White 260gr chính hãng (121265)', 'vot-tennis-babolat-boost-drive-white-260gr-chinh-hang-121265', NULL, NULL, '/storage/products/a2b31c5f-03f1-4653-977a-12fa8d762391.webp', 'variant', 'active', 0, 2799000.00, 2799000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:53:07', '2026-07-23 15:49:10'),
	(220, 15, NULL, 1, 'Vợt tennis Wilson Hyper Hammer 2.3 (237gr) Rry chính hãng - WR151811U2', 'vot-tennis-wilson-hyper-hammer-23-237gr-rry-chinh-hang-wr151811u2', NULL, NULL, '/storage/products/e0fd87d5-9750-40e7-b013-725d0960b173.webp', 'variant', 'active', 0, 3799000.00, 3799000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:53:21', '2026-07-23 15:48:52'),
	(221, 15, NULL, 1, 'Vợt tennis Babolat Boost Strike 285gr chính hãng (121247)', 'vot-tennis-babolat-boost-strike-285gr-chinh-hang-121247', NULL, NULL, '/storage/products/089c1258-3614-4cdb-9377-f9f303d55397.webp', 'variant', 'active', 0, 2649000.00, 2649000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:53:36', '2026-07-23 15:48:34'),
	(222, 15, NULL, 1, 'Cặp vợt tennis Babolat Pure Strike Lite VS 310gr X2 chính hãng (101458)', 'cap-vot-tennis-babolat-pure-strike-lite-vs-310gr-x2-chinh-hang-101458', NULL, NULL, '/storage/products/ae7b2ea7-0a3a-4aa5-a467-4c1e4b172ae6.webp', 'variant', 'active', 0, 9150000.00, 9150000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:53:52', '2026-07-23 15:48:08'),
	(223, 15, NULL, 1, 'Vợt tennis Babolat Pure Strike Lite VS 310gr chính hãng (101470)', 'vot-tennis-babolat-pure-strike-lite-vs-310gr-chinh-hang-101470', NULL, NULL, '/storage/products/bf5a2fc5-864d-4407-8e5b-1c52bd55e8a5.webp', 'variant', 'active', 0, 4599000.00, 4599000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:54:06', '2026-07-23 15:09:17'),
	(224, 15, NULL, 1, 'Vợt tennis Babolat Pure Strike Lite Gen 4 265gr 2024 chính hãng (101528)', 'vot-tennis-babolat-pure-strike-lite-gen-4-265gr-2024-chinh-hang-101528', NULL, NULL, '/storage/products/d8dbd9e1-d9f3-403e-a1fc-d955b8213c80.webp', 'variant', 'active', 0, 5099000.00, 5099000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:54:21', '2026-07-23 15:08:59'),
	(225, 15, NULL, 1, 'Vợt tennis Babolat Pure Strike 18/20 305gr chính hãng (101404)', 'vot-tennis-babolat-pure-strike-1820-305gr-chinh-hang-101404', NULL, NULL, '/storage/products/f918d114-f114-4ae6-bb87-e065a0f7240f.webp', 'variant', 'active', 0, 4149000.00, 4149000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:54:37', '2026-07-23 15:08:31'),
	(226, 15, NULL, 1, 'Vợt tennis Babolat Pure Strike 16/19 305gr chính hãng (101472)', 'vot-tennis-babolat-pure-strike-1619-305gr-chinh-hang-101472', NULL, NULL, '/storage/products/72b710a6-2931-4dd7-a229-887e527bcf80.webp', 'variant', 'active', 0, 4149000.00, 4149000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:54:50', '2026-07-23 15:08:10'),
	(227, 15, NULL, 1, 'Cặp vợt tennis Babolat Pure Drive 98 305g X2 chính hãng (101472)', 'cap-vot-tennis-babolat-pure-drive-98-305g-x2-chinh-hang-101472', NULL, NULL, '/storage/products/51a6e7b7-5305-4a6f-918e-c032ab28deb4.webp', 'variant', 'active', 0, 9999000.00, 9999000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:55:05', '2026-07-23 14:57:47'),
	(228, 15, NULL, 1, 'Vợt tennis Babolat Pure Drive 98 305gr chính hãng (101474)', 'vot-tennis-babolat-pure-drive-98-305gr-chinh-hang-101474', NULL, NULL, '/storage/products/0bc5530f-8d10-4e8e-9bb6-0ffa33f197e3.webp', 'variant', 'active', 0, 5149000.00, 5149000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:55:19', '2026-07-23 14:57:15'),
	(229, 15, NULL, 1, 'Vợt Tennis Wilson Hyper Hammer 2.3 (237gr) BUR/BLK 2 chính hãng - WR136411U2', 'vot-tennis-wilson-hyper-hammer-23-237gr-burblk-2-chinh-hang-wr136411u2', NULL, NULL, '/storage/products/ae50e509-93b4-42c1-95f8-594a069de502.webp', 'variant', 'active', 0, 3799000.00, 3799000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:55:35', '2026-07-23 14:58:44'),
	(230, 15, NULL, 1, 'Vợt Tennis Wilson Hyper Hammer 2.3 (237gr) BLK/BUR 2 chính hãng - WR136211U2', 'vot-tennis-wilson-hyper-hammer-23-237gr-blkbur-2-chinh-hang-wr136211u2', NULL, NULL, '/storage/products/1ce16836-a1a8-4de6-a67d-fe20a066de2a.webp', 'variant', 'active', 0, 3799000.00, 3799000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:55:48', '2026-07-23 14:59:22'),
	(231, 15, NULL, 1, 'Vợt Tennis Wilson Hyper Hammer 2.3 (237gr) BLK 2 chính hãng - WR151911U2', 'vot-tennis-wilson-hyper-hammer-23-237gr-blk-2-chinh-hang-wr151911u2', NULL, NULL, '/storage/products/6d3d7a08-6537-49cb-bb84-efe9ba55b580.webp', 'variant', 'active', 0, 3799000.00, 3799000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:56:02', '2026-07-23 14:56:40'),
	(232, 15, NULL, 1, 'Vợt Tennis Wilson Hyper Hammer 5.3 (242gr) BLK 2 chính hãng - WR152111U2', 'vot-tennis-wilson-hyper-hammer-53-242gr-blk-2-chinh-hang-wr152111u2', NULL, NULL, '/storage/products/723631b9-da75-430f-9139-5adc4eda9010.webp', 'variant', 'active', 0, 3599000.00, 3599000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:56:15', '2026-07-23 14:56:13'),
	(233, 15, NULL, 1, 'Vợt Tennis Babolat Evo Aero Pink 275gr chính hãng (101506)', 'vot-tennis-babolat-evo-aero-pink-275gr-chinh-hang-101506', NULL, NULL, '/storage/products/bc10ff10-9e4f-4691-be69-35094c5c5a29.webp', 'variant', 'active', 0, 4249000.00, 4249000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:56:30', '2026-07-23 14:54:53'),
	(234, 16, NULL, 1, 'Giày Babolat SFX Evo All Court Wimbledon Men chính hãng (BA30S26938C-1001)', 'giay-babolat-sfx-evo-all-court-wimbledon-men-chinh-hang-ba30s26938c-1001', NULL, NULL, '/storage/products/dec84be1-6c20-4761-b833-c4642e34bd07.webp', 'variant', 'active', 0, 3099000.00, 3099000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:56:45', '2026-07-23 14:53:49'),
	(235, 16, NULL, 1, 'Giày Babolat Jet Tere 2 All Court Women chính hãng (3A1F25A651-5072)', 'giay-babolat-jet-tere-2-all-court-women-chinh-hang-3a1f25a651-5072', NULL, NULL, '/storage/products/c21465d3-ee4e-48f0-b0f5-9de5659e9e19.webp', 'variant', 'active', 0, 2900000.00, 2900000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:56:57', '2026-07-23 14:53:19'),
	(236, 16, NULL, 1, 'Giày Tennis Jet Tere 2 All Court Women chính hãng (31S26651C-4144)', 'giay-tennis-jet-tere-2-all-court-women-chinh-hang-31s26651c-4144', NULL, NULL, '/storage/products/f597118c-9f07-4e42-98cb-5cb40a5055d0.webp', 'variant', 'active', 0, 2950000.00, 2950000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:57:11', '2026-07-23 14:52:34'),
	(237, 16, NULL, 1, 'Giày Babolat Jet Tere 2 All Court Men chính hãng (30S26649C-7023)', 'giay-babolat-jet-tere-2-all-court-men-chinh-hang-30s26649c-7023', NULL, NULL, '/storage/products/d158df86-8f08-4508-bee9-4d67d6e273e7.webp', 'variant', 'active', 0, 2790000.00, 2790000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:57:23', '2026-07-23 14:51:58'),
	(238, 16, NULL, 1, 'Giày Tennis Asics Gel-Challenger 15 - Hồng chính hãng (1042A294.700)', 'giay-tennis-asics-gel-challenger-15-hong-chinh-hang-1042a294700', NULL, NULL, '/storage/products/98fb592b-d53c-4b26-bb90-f6822a73e54f.webp', 'variant', 'active', 0, 2499000.00, 2499000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:57:37', '2026-07-23 14:51:29'),
	(239, 16, NULL, 1, 'Giày Tennis Asics Solution Swift FF 2 - Trắng chính hãng (1042A265.104)', 'giay-tennis-asics-solution-swift-ff-2-trang-chinh-hang-1042a265104', NULL, NULL, '/storage/products/36304e2a-b1f4-453d-91e4-3a995a9cacd5.webp', 'variant', 'active', 0, 2390000.00, 2390000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:57:51', '2026-07-23 14:50:11'),
	(240, 16, NULL, 1, 'Giày Tennis Babolat Propulse Fury 3 All Court Men chính hãng (30S26208B–2051)', 'giay-tennis-babolat-propulse-fury-3-all-court-men-chinh-hang-30s26208b-2051', NULL, NULL, '/storage/products/f5801e74-1129-4d64-a1c3-00c68f34e191.webp', 'variant', 'active', 0, 3450000.00, 3450000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:58:07', '2026-07-23 14:49:39'),
	(241, 16, NULL, 1, 'Giày Tennis Babolat Propulse Fury 3 All Court Men chính hãng (30S26208B–1069)', 'giay-tennis-babolat-propulse-fury-3-all-court-men-chinh-hang-30s26208b-1069', NULL, NULL, '/storage/products/e5b0a026-fa49-426f-b002-157181539369.webp', 'variant', 'active', 0, 3450000.00, 3450000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:58:43', '2026-07-23 14:48:56'),
	(242, 16, NULL, 1, 'Giày Tennis Babolat Jet Mach 4 All Court Women chính hãng (26630B–4150)', 'giay-tennis-babolat-jet-mach-4-all-court-women-chinh-hang-26630b-4150', NULL, NULL, '/storage/products/1742a8b7-d948-4e83-a225-6b6215d25d8b.jpg', 'variant', 'active', 0, 3750000.00, 3750000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:59:33', '2026-07-23 14:48:09'),
	(243, 16, NULL, 1, 'Giày Tennis Babolat Jet Mach 4 All Court Men chính hãng (26629B–2036)', 'giay-tennis-babolat-jet-mach-4-all-court-men-chinh-hang-26629b-2036', NULL, NULL, '/storage/products/e8636c70-8076-4eab-b6c5-4f0e657f010b.webp', 'variant', 'active', 0, 3750000.00, 3750000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 15:59:59', '2026-07-23 14:47:23'),
	(244, 16, NULL, 1, 'Giày Tennis Babolat Jet Mach 4 All Court Men chính hãng (26629B–1115)', 'giay-tennis-babolat-jet-mach-4-all-court-men-chinh-hang-26629b-1115', NULL, NULL, '/storage/products/d41541cd-9dde-4a7e-a5ed-c82d2fd83883.webp', 'variant', 'active', 0, 3750000.00, 3750000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 16:00:42', '2026-07-23 14:46:53'),
	(245, 16, NULL, 1, 'Giày Tennis Babolat Jet Mach 4 All Court Men chính hãng (26629B–5050)', 'giay-tennis-babolat-jet-mach-4-all-court-men-chinh-hang-26629b-5050', NULL, NULL, '/storage/products/41474de7-cbf1-4a30-928b-076d78b3b448.webp', 'variant', 'active', 0, 3750000.00, 3750000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 16:01:13', '2026-07-23 14:46:27'),
	(246, 16, NULL, 1, 'Giày tennis Babolat Jet Mach 3 All Court Junior chính hãng (26883A–5081)', 'giay-tennis-babolat-jet-mach-3-all-court-junior-chinh-hang-26883a-5081', NULL, NULL, '/storage/products/bd2b354d-365e-4a75-9b8f-b82092002cc4.webp', 'variant', 'active', 0, 2099000.00, 2099000.00, 0.00, 0, 0, 0, 500, NULL, NULL, '2026-07-21 16:01:36', '2026-07-23 14:45:57');

-- Dumping structure for table ocean_db.promotion_categories
CREATE TABLE IF NOT EXISTS `promotion_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `promotion_id` bigint unsigned NOT NULL,
  `category_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `promotion_categories_promotion_id_category_id_unique` (`promotion_id`,`category_id`),
  KEY `promotion_categories_category_id_foreign` (`category_id`),
  CONSTRAINT `promotion_categories_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_categories_promotion_id_foreign` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`promotion_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.promotion_categories: ~0 rows (approximately)
DELETE FROM `promotion_categories`;

-- Dumping structure for table ocean_db.promotion_products
CREATE TABLE IF NOT EXISTS `promotion_products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `promotion_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `promotion_products_promotion_id_product_id_unique` (`promotion_id`,`product_id`),
  KEY `promotion_products_product_id_foreign` (`product_id`),
  CONSTRAINT `promotion_products_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_products_promotion_id_foreign` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`promotion_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.promotion_products: ~0 rows (approximately)
DELETE FROM `promotion_products`;

-- Dumping structure for table ocean_db.promotion_usages
CREATE TABLE IF NOT EXISTS `promotion_usages` (
  `promotion_usage_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `promotion_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `used_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`promotion_usage_id`),
  KEY `promotion_usages_user_id_foreign` (`user_id`),
  KEY `promotion_usages_promotion_id_user_id_index` (`promotion_id`,`user_id`),
  CONSTRAINT `promotion_usages_promotion_id_foreign` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`promotion_id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_usages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.promotion_usages: ~0 rows (approximately)
DELETE FROM `promotion_usages`;

-- Dumping structure for table ocean_db.promotions
CREATE TABLE IF NOT EXISTS `promotions` (
  `promotion_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_type` enum('percent','fixed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_value` decimal(12,2) NOT NULL,
  `min_order_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `max_discount_amount` decimal(12,2) DEFAULT NULL,
  `start_at` datetime NOT NULL,
  `end_at` datetime NOT NULL,
  `usage_limit` int DEFAULT NULL,
  `usage_limit_per_user` int DEFAULT NULL,
  `used_count` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`promotion_id`),
  UNIQUE KEY `promotions_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.promotions: ~0 rows (approximately)
DELETE FROM `promotions`;

-- Dumping structure for table ocean_db.recently_viewed_products
CREATE TABLE IF NOT EXISTS `recently_viewed_products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `session_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` bigint unsigned NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.recently_viewed_products: ~11 rows (approximately)
DELETE FROM `recently_viewed_products`;
INSERT INTO `recently_viewed_products` (`id`, `user_id`, `session_id`, `product_id`, `viewed_at`, `created_at`, `updated_at`) VALUES
	(1, 9, NULL, 8, '2026-07-13 10:42:44', '2026-07-13 17:42:44', '2026-07-13 17:42:44'),
	(2, 8, NULL, 19, '2026-07-20 19:46:13', '2026-07-20 18:31:20', '2026-07-20 19:46:13'),
	(3, 1, NULL, 9, '2026-07-21 16:09:21', '2026-07-21 23:09:21', '2026-07-21 23:09:21'),
	(4, 1, NULL, 200, '2026-07-21 16:15:11', '2026-07-21 23:15:11', '2026-07-21 23:15:11'),
	(5, 1, NULL, 18, '2026-07-22 00:29:49', '2026-07-22 00:29:25', '2026-07-22 00:29:49'),
	(6, 10, NULL, 241, '2026-07-22 01:54:29', '2026-07-22 08:54:29', '2026-07-22 08:54:29'),
	(7, 10, NULL, 1, '2026-07-22 02:00:01', '2026-07-22 09:00:01', '2026-07-22 09:00:01'),
	(8, 9, NULL, 246, '2026-07-23 14:22:20', '2026-07-23 14:22:14', '2026-07-23 14:22:20'),
	(9, 9, NULL, 236, '2026-07-23 08:00:09', '2026-07-23 15:00:09', '2026-07-23 15:00:09'),
	(10, 1, NULL, 242, '2026-07-23 17:35:52', '2026-07-24 00:35:52', '2026-07-24 00:35:52'),
	(11, 1, NULL, 246, '2026-07-23 17:48:33', '2026-07-24 00:48:33', '2026-07-24 00:48:33');

-- Dumping structure for table ocean_db.return_requests
CREATE TABLE IF NOT EXISTS `return_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `images` json DEFAULT NULL,
  `status` enum('pending','approved','rejected','received','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `admin_note` text COLLATE utf8mb4_unicode_ci,
  `refund_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `refund_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `refund_status` enum('none','pending','success','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `requested_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `refunded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `return_requests_order_id_status_index` (`order_id`,`status`),
  KEY `return_requests_user_id_status_index` (`user_id`,`status`),
  CONSTRAINT `return_requests_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `return_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.return_requests: ~1 rows (approximately)
DELETE FROM `return_requests`;
INSERT INTO `return_requests` (`id`, `order_id`, `user_id`, `reason`, `description`, `images`, `status`, `admin_note`, `refund_amount`, `refund_method`, `refund_status`, `requested_at`, `approved_at`, `rejected_at`, `received_at`, `refunded_at`, `created_at`, `updated_at`) VALUES
	(1, 17, 1, 'Sản phẩm không đúng mô tả', '<p>ssss</p>', '["return-requests/1d0L7T8GZyQtkEpZbr6Rr4mASZ7XgYkpfBmdcD9s.jpg"]', 'pending', NULL, 0.00, NULL, 'none', '2026-07-24 00:23:24', NULL, NULL, NULL, NULL, '2026-07-24 00:23:24', '2026-07-24 00:23:24');

-- Dumping structure for table ocean_db.rewards
CREATE TABLE IF NOT EXISTS `rewards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `points_required` int NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'voucher',
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.rewards: ~1 rows (approximately)
DELETE FROM `rewards`;
INSERT INTO `rewards` (`id`, `name`, `description`, `points_required`, `type`, `image`, `created_at`, `updated_at`) VALUES
	(1, 'Lê Văn Vũ kk', '<p>hehehe</p>', 100, 'item', NULL, '2026-07-24 23:56:28', '2026-07-24 23:56:28');

-- Dumping structure for table ocean_db.search_histories
CREATE TABLE IF NOT EXISTS `search_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `session_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keyword` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `results_count` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.search_histories: ~27 rows (approximately)
DELETE FROM `search_histories`;
INSERT INTO `search_histories` (`id`, `user_id`, `session_id`, `keyword`, `results_count`, `created_at`, `updated_at`) VALUES
	(1, 5, NULL, 'giày', 1, '2026-06-29 16:45:29', '2026-06-29 16:45:29'),
	(2, NULL, 'sess_vjdx15wsmm1sl4tjugae8', 'dd', 0, '2026-07-11 02:22:36', '2026-07-11 02:22:36'),
	(3, NULL, 'sess_vjdx15wsmm1sl4tjugae8', '2iiq', 0, '2026-07-11 02:22:38', '2026-07-11 02:22:38'),
	(4, NULL, 'sess_vjdx15wsmm1sl4tjugae8', 'b', 0, '2026-07-11 02:22:40', '2026-07-11 02:22:40'),
	(5, NULL, 'sess_vjdx15wsmm1sl4tjugae8', 'bo', 0, '2026-07-11 02:22:40', '2026-07-11 02:22:40'),
	(6, NULL, 'sess_vjdx15wsmm1sl4tjugae8', 'vợt', 0, '2026-07-11 02:22:41', '2026-07-11 02:22:41'),
	(7, NULL, 'sess_vjdx15wsmm1sl4tjugae8', 'giày', 4, '2026-07-11 02:22:45', '2026-07-11 02:40:24'),
	(8, NULL, 'sess_vjdx15wsmm1sl4tjugae8', 'ca', 1, '2026-07-11 02:22:55', '2026-07-11 02:22:55'),
	(9, NULL, 'sess_vjdx15wsmm1sl4tjugae8', 'cầi', 0, '2026-07-11 02:22:55', '2026-07-11 02:22:55'),
	(10, NULL, 'sess_vjdx15wsmm1sl4tjugae8', 'cầu lông', 0, '2026-07-11 02:22:57', '2026-07-11 02:22:57'),
	(11, NULL, 'sess_vjdx15wsmm1sl4tjugae8', 'Áo', 0, '2026-07-11 02:23:08', '2026-07-11 02:23:08'),
	(12, NULL, 'sess_vjdx15wsmm1sl4tjugae8', 'sss', 0, '2026-07-12 22:47:43', '2026-07-12 22:47:43'),
	(13, NULL, 'sess_vjdx15wsmm1sl4tjugae8', 'gia', 4, '2026-07-12 22:47:46', '2026-07-12 22:47:46'),
	(14, NULL, 'sess_vjdx15wsmm1sl4tjugae8', 'gi', 4, '2026-07-12 22:47:46', '2026-07-12 22:47:46'),
	(15, NULL, 'sess_vjdx15wsmm1sl4tjugae8', 'ssss', 0, '2026-07-13 18:30:21', '2026-07-13 18:30:21'),
	(16, 1, NULL, 'Vợt Pickleball EliteX 16MM Blue', 1, '2026-07-22 00:29:23', '2026-07-22 00:29:23'),
	(17, 10, NULL, 'd', 25, '2026-07-22 09:10:32', '2026-07-22 09:10:32'),
	(18, 1, NULL, 'vợt', 41, '2026-07-24 00:44:48', '2026-07-24 00:44:48'),
	(19, 1, NULL, 'quần', 0, '2026-07-24 00:48:14', '2026-07-24 00:48:14'),
	(20, 1, NULL, 'a', 80, '2026-07-24 00:48:16', '2026-07-24 00:48:16'),
	(21, 1, NULL, 'áo sơ mi', 0, '2026-07-24 00:52:39', '2026-07-24 00:52:39'),
	(22, 1, NULL, 'guiaf', 0, '2026-07-24 00:52:43', '2026-07-24 00:52:43'),
	(23, 1, NULL, 'gui', 0, '2026-07-24 00:52:43', '2026-07-24 00:52:43'),
	(24, 1, NULL, 'gu', 1, '2026-07-24 00:52:44', '2026-07-24 00:52:44'),
	(25, 1, NULL, 'g', 78, '2026-07-24 00:52:44', '2026-07-24 00:52:44'),
	(26, 1, NULL, 'giyaf', 0, '2026-07-24 00:52:47', '2026-07-24 00:52:47'),
	(27, 1, NULL, 'giày', 27, '2026-07-24 00:52:49', '2026-07-24 00:52:49');

-- Dumping structure for table ocean_db.sessions
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.sessions: ~0 rows (approximately)
DELETE FROM `sessions`;

-- Dumping structure for table ocean_db.shift_assignments
CREATE TABLE IF NOT EXISTS `shift_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `user_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `work_shift_id` bigint unsigned NOT NULL,
  `day_of_week` tinyint NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sa_unique` (`user_id`,`user_type`,`work_shift_id`,`day_of_week`),
  KEY `shift_assignments_work_shift_id_foreign` (`work_shift_id`),
  KEY `sa_user_day_idx` (`user_id`,`user_type`,`day_of_week`),
  CONSTRAINT `shift_assignments_work_shift_id_foreign` FOREIGN KEY (`work_shift_id`) REFERENCES `work_shifts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.shift_assignments: ~0 rows (approximately)
DELETE FROM `shift_assignments`;

-- Dumping structure for table ocean_db.shipping_zones
CREATE TABLE IF NOT EXISTS `shipping_zones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provinces` text COLLATE utf8mb4_unicode_ci,
  `shipping_fee` int unsigned NOT NULL DEFAULT '0',
  `free_ship_threshold` int unsigned DEFAULT NULL,
  `delivery_time` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` int unsigned NOT NULL DEFAULT '50',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.shipping_zones: ~0 rows (approximately)
DELETE FROM `shipping_zones`;

-- Dumping structure for table ocean_db.tickets
CREATE TABLE IF NOT EXISTS `tickets` (
  `ticket_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL COMMENT 'Người tạo khiếu nại',
  `order_id` bigint unsigned DEFAULT NULL COMMENT 'Đơn hàng liên quan',
  `product_id` bigint unsigned DEFAULT NULL COMMENT 'Sản phẩm liên quan',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Lý do khiếu nại',
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Mô tả chi tiết',
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ảnh minh chứng',
  `status` enum('pending','processing','resolved','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'Trạng thái xử lý',
  `admin_reply` text COLLATE utf8mb4_unicode_ci COMMENT 'Phản hồi từ admin',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`ticket_id`),
  KEY `tickets_user_id_foreign` (`user_id`),
  KEY `tickets_order_id_foreign` (`order_id`),
  KEY `tickets_product_id_foreign` (`product_id`),
  CONSTRAINT `tickets_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL,
  CONSTRAINT `tickets_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE SET NULL,
  CONSTRAINT `tickets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.tickets: ~0 rows (approximately)
DELETE FROM `tickets`;

-- Dumping structure for table ocean_db.user_bank_accounts
CREATE TABLE IF NOT EXISTS `user_bank_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `bank_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_short_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_bin` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_bank_account` (`user_id`,`bank_bin`,`account_number`),
  KEY `user_bank_accounts_user_id_index` (`user_id`),
  CONSTRAINT `user_bank_accounts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.user_bank_accounts: ~0 rows (approximately)
DELETE FROM `user_bank_accounts`;

-- Dumping structure for table ocean_db.user_coupons
CREATE TABLE IF NOT EXISTS `user_coupons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `coupon_id` bigint unsigned NOT NULL,
  `used_count` int NOT NULL DEFAULT '0',
  `is_saved` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_coupons_user_id_coupon_id_unique` (`user_id`,`coupon_id`),
  KEY `user_coupons_coupon_id_foreign` (`coupon_id`),
  CONSTRAINT `user_coupons_coupon_id_foreign` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_coupons_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.user_coupons: ~3 rows (approximately)
DELETE FROM `user_coupons`;
INSERT INTO `user_coupons` (`id`, `user_id`, `coupon_id`, `used_count`, `is_saved`, `created_at`, `updated_at`) VALUES
	(1, 5, 2, 0, 1, '2026-06-16 23:31:41', '2026-06-16 23:31:41'),
	(2, 1, 15, 0, 1, '2026-06-29 17:28:07', '2026-06-29 17:28:07'),
	(3, 1, 18, 0, 1, '2026-06-29 17:28:09', '2026-06-29 17:28:09');

-- Dumping structure for table ocean_db.user_devices
CREATE TABLE IF NOT EXISTS `user_devices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `fcm_token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_devices_fcm_token_unique` (`fcm_token`),
  KEY `user_devices_user_id_foreign` (`user_id`),
  CONSTRAINT `user_devices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.user_devices: ~1 rows (approximately)
DELETE FROM `user_devices`;
INSERT INTO `user_devices` (`id`, `user_id`, `fcm_token`, `device_type`, `created_at`, `updated_at`) VALUES
	(1, 1, 'e8-8XHFOQ-m6y9Q4DReNyU:APA91bH8B7PTxgECoSN5vCo-L2_b7CnW6KpB-flF9r1tQTTUS150NoiiPefQgB9bUDC1YLpIdxhI2sOUDWSb6SzMCw2j5H9Ia8unFQ1opQhcrA5fhFEbvjw', 'android', '2026-06-29 14:04:13', '2026-06-29 17:18:50');

-- Dumping structure for table ocean_db.user_rewards
CREATE TABLE IF NOT EXISTS `user_rewards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `reward_id` bigint unsigned NOT NULL,
  `points_spent` int NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_rewards_reward_id_foreign` (`reward_id`),
  KEY `user_rewards_user_id_foreign` (`user_id`),
  CONSTRAINT `user_rewards_reward_id_foreign` FOREIGN KEY (`reward_id`) REFERENCES `rewards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_rewards_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.user_rewards: ~0 rows (approximately)
DELETE FROM `user_rewards`;

-- Dumping structure for table ocean_db.users
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `google_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `facebook_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_payment_method` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Preferred payment method for quick order: cod, bank_transfer',
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_url` text COLLATE utf8mb4_unicode_ci,
  `date_of_birth` date DEFAULT NULL,
  `reward_points` int unsigned NOT NULL DEFAULT '0',
  `role` enum('admin','staff','customer','seller') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'customer',
  `status` enum('active','inactive','banned') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `referral_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referred_by` bigint unsigned DEFAULT NULL,
  `affiliate_registered_at` timestamp NULL DEFAULT NULL,
  `is_affiliate` tinyint(1) NOT NULL DEFAULT '0',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_phone_unique` (`phone`),
  UNIQUE KEY `users_google_id_unique` (`google_id`),
  UNIQUE KEY `users_facebook_id_unique` (`facebook_id`),
  UNIQUE KEY `users_referral_code_unique` (`referral_code`),
  KEY `users_referred_by_foreign` (`referred_by`),
  CONSTRAINT `users_referred_by_foreign` FOREIGN KEY (`referred_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.users: ~10 rows (approximately)
DELETE FROM `users`;
INSERT INTO `users` (`user_id`, `full_name`, `email`, `google_id`, `facebook_id`, `phone`, `default_payment_method`, `password`, `avatar_url`, `date_of_birth`, `reward_points`, `role`, `status`, `referral_code`, `referred_by`, `affiliate_registered_at`, `is_affiliate`, `email_verified_at`, `last_login_at`, `remember_token`, `created_at`, `updated_at`, `deleted_at`) VALUES
	(1, 'Super Admin', 'admin123@gmail.com', NULL, NULL, NULL, NULL, '$2y$12$V2s7MlUiAz74yM5MBMckv.8gs9QsI5xXyf83BLaab409UQSNMZDPe', NULL, NULL, 798, 'admin', 'active', NULL, NULL, NULL, 0, NULL, NULL, NULL, '2026-06-05 10:54:43', '2026-06-05 10:54:43', NULL),
	(2, 'Normal User', 'user123@gmail.com', NULL, NULL, NULL, NULL, '$2y$12$Skujw7akizUJ6YNpPW8vte.6DHXhIjsqLw.G1iQZDWFUmyUfM2SbS', NULL, NULL, 0, 'customer', 'inactive', NULL, NULL, NULL, 0, NULL, NULL, NULL, '2026-06-05 10:54:43', '2026-06-24 22:14:59', NULL),
	(3, 'Nguyễn Văn An', 'nguyen.an@demo.com', NULL, NULL, '0911000001', NULL, '$2y$12$efwdtNptUyzf07zhbo8JyuFourhW4NDBPKw00pPiCvXT1NABT/2im', NULL, NULL, 0, 'customer', 'active', NULL, NULL, NULL, 0, NULL, NULL, NULL, '2026-06-05 10:54:46', '2026-06-05 10:54:46', NULL),
	(4, 'Trần Thị Bình', 'tran.binh@demo.com', NULL, NULL, '0911000002', NULL, '$2y$12$ZvmFXdsfF1Ex/V5RyvjemuRWlu.FeuRDlfJYqG5bYE1/t8lx7S08u', NULL, NULL, 0, 'customer', 'active', NULL, NULL, NULL, 0, NULL, NULL, NULL, '2026-06-05 10:54:46', '2026-06-05 10:54:46', NULL),
	(5, 'Lê Hoàng Cường', 'abc@abc.com', NULL, NULL, '0911000003', NULL, '$2y$12$60WFCo9yVd681hBKNZnoI.g1NWnOqTjKkY7raY3w6sxgptSPVIJCK', NULL, NULL, 601, 'customer', 'active', NULL, NULL, NULL, 0, NULL, NULL, NULL, '2026-06-05 10:54:46', '2026-06-16 23:31:08', NULL),
	(6, 'Phạm Minh Đức', 'pham.duc@demo.com', NULL, NULL, '0911000004', NULL, '$2y$12$YC3fHX0jaTJ2i9fgSvSdv.gOtl3IT9OCUz9EfZvAlpHe3XZeGiBwW', NULL, NULL, 0, 'customer', 'active', NULL, NULL, NULL, 0, NULL, NULL, NULL, '2026-06-05 10:54:46', '2026-06-05 10:54:46', NULL),
	(7, 'Võ Thanh Em', 'vo.em@demo.com', NULL, NULL, '0911000005', NULL, '$2y$12$KvuC8gfXQ.q5AQ5A0w5uDecVyjn3KDGLyPeBj/64pqRXdXfWwk.6i', NULL, NULL, 0, 'seller', 'active', NULL, NULL, NULL, 0, NULL, NULL, NULL, '2026-06-05 10:54:47', '2026-06-14 22:30:27', NULL),
	(8, 'Bình Bùi', 'buichibinh2401@gmail.com', '100844933645081010564', NULL, NULL, NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocLfseae0mNWUj7rtucwa9QjADlgELXciwtrcywd8NfzS_miMmJY_w=s96-c', '2005-06-07', 608, 'customer', 'active', NULL, NULL, NULL, 0, NULL, NULL, NULL, '2026-06-05 14:47:50', '2026-06-06 14:15:23', NULL),
	(9, 'Bình Bùi Chí', 'binhbcpk03952@gmail.com', '104788898162764409228', NULL, NULL, NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocKGfuvcn0RC_plRZhAr2JJvmMHmwgQLCHKPwxz8asbTYi0UmQ=s96-c', NULL, 50, 'admin', 'active', NULL, NULL, NULL, 0, NULL, NULL, NULL, '2026-06-06 14:01:52', '2026-06-24 22:15:12', NULL),
	(10, '222222222222', 'binhbcpk03953@gmail.com', NULL, NULL, NULL, NULL, '$2y$12$3SFFncSfLsFqccThNHlUrOJ1UnWJ0qE4jb7P1.7YFTvlDZsNUGlTS', NULL, NULL, 0, 'customer', 'active', NULL, NULL, NULL, 0, NULL, NULL, NULL, '2026-07-22 08:50:38', '2026-07-22 08:50:38', NULL);

-- Dumping structure for table ocean_db.wallet_deposits
CREATE TABLE IF NOT EXISTS `wallet_deposits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `deposit_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `method` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','completed','failed','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `gateway_transaction_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gateway_response` json DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wallet_deposits_deposit_code_unique` (`deposit_code`),
  KEY `wallet_deposits_user_id_status_index` (`user_id`,`status`),
  KEY `wallet_deposits_deposit_code_index` (`deposit_code`),
  CONSTRAINT `wallet_deposits_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.wallet_deposits: ~2 rows (approximately)
DELETE FROM `wallet_deposits`;
INSERT INTO `wallet_deposits` (`id`, `user_id`, `deposit_code`, `amount`, `method`, `status`, `gateway_transaction_id`, `gateway_response`, `completed_at`, `created_at`, `updated_at`) VALUES
	(1, 1, 'WDPLJJMJSU8GJ', 2000000.00, 'bank_transfer', 'pending', NULL, NULL, NULL, '2026-06-24 22:19:19', '2026-06-24 22:19:19'),
	(2, 1, 'WDPIBPO5LGS6Q', 50000.00, 'bank_transfer', 'pending', NULL, NULL, NULL, '2026-06-24 22:20:19', '2026-06-24 22:20:19');

-- Dumping structure for table ocean_db.wallet_transactions
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `transaction_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `wallet_id` bigint unsigned NOT NULL,
  `transaction_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('deposit','commission','refund','loyalty_convert','promo_credit','order_discount','booking_payment','adjustment','withdrawal') COLLATE utf8mb4_unicode_ci NOT NULL,
  `balance_type` enum('deposit','commission') COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction` enum('credit','debit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `balance_before` decimal(15,2) NOT NULL,
  `balance_after` decimal(15,2) NOT NULL,
  `reference_type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','completed','failed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`transaction_id`),
  UNIQUE KEY `wallet_transactions_transaction_code_unique` (`transaction_code`),
  KEY `wtx_wallet_type_idx` (`wallet_id`,`type`),
  KEY `wtx_wallet_date_idx` (`wallet_id`,`created_at`),
  KEY `wtx_reference_idx` (`reference_type`,`reference_id`),
  KEY `wtx_wallet_balance_type_idx` (`wallet_id`,`balance_type`),
  CONSTRAINT `wallet_transactions_wallet_id_foreign` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`wallet_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.wallet_transactions: ~0 rows (approximately)
DELETE FROM `wallet_transactions`;

-- Dumping structure for table ocean_db.wallet_withdrawals
CREATE TABLE IF NOT EXISTS `wallet_withdrawals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `withdrawal_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `fee` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_deducted` decimal(15,2) NOT NULL,
  `bank_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_account_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_account_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('processing','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'processing',
  `note` text COLLATE utf8mb4_unicode_ci,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wallet_withdrawals_withdrawal_code_unique` (`withdrawal_code`),
  KEY `wallet_withdrawals_user_id_status_index` (`user_id`,`status`),
  CONSTRAINT `wallet_withdrawals_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.wallet_withdrawals: ~0 rows (approximately)
DELETE FROM `wallet_withdrawals`;

-- Dumping structure for table ocean_db.wallets
CREATE TABLE IF NOT EXISTS `wallets` (
  `wallet_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `deposit_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `commission_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `frozen_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_deposited` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_commission` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_used` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` enum('active','frozen','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `pin_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`wallet_id`),
  UNIQUE KEY `wallets_user_id_unique` (`user_id`),
  CONSTRAINT `wallets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.wallets: ~4 rows (approximately)
DELETE FROM `wallets`;
INSERT INTO `wallets` (`wallet_id`, `user_id`, `deposit_balance`, `commission_balance`, `frozen_balance`, `total_deposited`, `total_commission`, `total_used`, `status`, `pin_hash`, `created_at`, `updated_at`) VALUES
	(1, 8, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'active', NULL, '2026-06-22 13:36:30', '2026-06-22 13:36:30'),
	(2, 1, 10000000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'active', NULL, '2026-06-24 22:18:10', '2026-06-24 22:18:10'),
	(4, 9, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'active', NULL, '2026-07-13 17:37:22', '2026-07-13 17:37:22'),
	(6, 10, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'active', NULL, '2026-07-22 09:09:28', '2026-07-22 09:09:28');

-- Dumping structure for table ocean_db.work_locations
CREATE TABLE IF NOT EXISTS `work_locations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `radius_meters` int unsigned NOT NULL DEFAULT '200',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `work_locations_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.work_locations: ~0 rows (approximately)
DELETE FROM `work_locations`;

-- Dumping structure for table ocean_db.work_shifts
CREATE TABLE IF NOT EXISTS `work_shifts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `early_buffer_minutes` int NOT NULL DEFAULT '30',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ocean_db.work_shifts: ~0 rows (approximately)
DELETE FROM `work_shifts`;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
