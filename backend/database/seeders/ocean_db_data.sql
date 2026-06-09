-- MySQL dump 10.13  Distrib 8.0.44, for Linux (x86_64)
--
-- Host: localhost    Database: ocean_db
-- ------------------------------------------------------
-- Server version	8.0.44

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `addresses`
--

LOCK TABLES `addresses` WRITE;
/*!40000 ALTER TABLE `addresses` DISABLE KEYS */;
/*!40000 ALTER TABLE `addresses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `brands`
--

LOCK TABLES `brands` WRITE;
/*!40000 ALTER TABLE `brands` DISABLE KEYS */;
INSERT INTO `brands` (`brand_id`, `name`, `slug`, `description`, `logo_url`, `is_active`, `created_at`, `updated_at`) VALUES (1,'Yonex','yonex','Thuong hieu cau long noi tieng',NULL,1,'2026-03-14 10:01:31','2026-03-14 10:01:31'),(2,'Mizuno','mizuno','Thuong hieu giay the thao',NULL,1,'2026-03-14 10:01:31','2026-03-14 10:01:31'),(3,'Li-Ning','li-ning','Thuong hieu the thao',NULL,1,'2026-03-14 10:01:31','2026-03-14 10:01:31'),(4,'Victor','victor','Thuong hieu cau long va phu kien',NULL,1,'2026-03-14 10:01:31','2026-03-14 10:01:31');
/*!40000 ALTER TABLE `brands` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `cart_items`
--

LOCK TABLES `cart_items` WRITE;
/*!40000 ALTER TABLE `cart_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `cart_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `carts`
--

LOCK TABLES `carts` WRITE;
/*!40000 ALTER TABLE `carts` DISABLE KEYS */;
/*!40000 ALTER TABLE `carts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` (`category_id`, `parent_id`, `name`, `slug`, `description`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES (1,NULL,'Giày','giay','Danh muc giay the thao',1,1,'2026-03-14 10:01:31','2026-03-14 10:01:31'),(2,NULL,'Vợt','vot','Danh muc vot the thao',2,1,'2026-03-14 10:01:31','2026-03-14 10:01:31'),(3,NULL,'Quần áo','quan-ao','Danh muc quan ao the thao',3,1,'2026-03-14 10:01:31','2026-03-14 10:01:31'),(4,NULL,'Phụ kiện','phu-kien','Danh muc phu kien the thao',4,1,'2026-03-14 10:01:31','2026-03-14 10:01:31');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `favorites`
--

LOCK TABLES `favorites` WRITE;
/*!40000 ALTER TABLE `favorites` DISABLE KEYS */;
/*!40000 ALTER TABLE `favorites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `inventory_transactions`
--

LOCK TABLES `inventory_transactions` WRITE;
/*!40000 ALTER TABLE `inventory_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_03_14_010505_create_brands_table',1),(5,'2026_03_14_010702_create_categories_table',1),(6,'2026_03_14_010714_create_addresses_table',1),(7,'2026_03_14_010722_create_products_table',1),(8,'2026_03_14_010729_create_product_variants_table',1),(9,'2026_03_14_010739_create_product_images_table',1),(10,'2026_03_14_010747_create_carts_table',1),(11,'2026_03_14_010755_create_cart_items_table',1),(12,'2026_03_14_010805_create_favorites_table',1),(13,'2026_03_14_010812_create_promotions_table',1),(14,'2026_03_14_010819_create_promotion_categories_table',1),(15,'2026_03_14_010826_create_promotion_products_table',1),(16,'2026_03_14_010834_create_promotion_usages_table',1),(17,'2026_03_14_010841_create_orders_table',1),(18,'2026_03_14_010848_create_order_items_table',1),(19,'2026_03_14_010856_create_order_status_histories_table',1),(20,'2026_03_14_010903_create_payments_table',1),(21,'2026_03_14_010911_create_inventory_transactions_table',1),(22,'2026_03_14_010921_create_product_comments_table',1),(23,'2026_03_17_022148_create_personal_access_tokens_table',2);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `order_status_histories`
--

LOCK TABLES `order_status_histories` WRITE;
/*!40000 ALTER TABLE `order_status_histories` DISABLE KEYS */;
/*!40000 ALTER TABLE `order_status_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (1,'App\\Models\\User',2,'auth_token','3b7c3ff9fb6be19d39c9b3111d3dbbc8a0252c040b8d399a8dbd1c168762671e','[\"*\"]',NULL,NULL,'2026-03-17 10:13:16','2026-03-17 10:13:16'),(2,'App\\Models\\User',2,'auth_token','a438153dc4cffe0dafc34e327901d2ca7fdbecef6cf0fc4473eaea9dd930c6a1','[\"*\"]','2026-03-17 10:16:56',NULL,'2026-03-17 10:14:54','2026-03-17 10:16:56');
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `product_comments`
--

LOCK TABLES `product_comments` WRITE;
/*!40000 ALTER TABLE `product_comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `product_images`
--

LOCK TABLES `product_images` WRITE;
/*!40000 ALTER TABLE `product_images` DISABLE KEYS */;
INSERT INTO `product_images` (`image_id`, `product_id`, `variant_id`, `image_url`, `alt_text`, `is_main`, `sort_order`, `created_at`) VALUES (1,1,NULL,'products/yonex-nanoflare-1000-game-main.webp','Vợt cầu lông Yonex Nanoflare 1000 Game',1,1,'2026-03-14 10:01:32'),(2,2,NULL,'products/mizuno-wave-claw-2-main.webp','Giày cầu lông Mizuno Wave Claw 2',1,1,'2026-03-14 10:01:32'),(3,3,NULL,'products/li-ning-tournament-2026-main.webp','Áo cầu lông Li-Ning Tournament 2026',1,1,'2026-03-14 10:01:32'),(4,4,NULL,'products/victor-br5611-main.webp','Túi cầu lông Victor BR5611',1,1,'2026-03-14 10:01:32'),(5,5,NULL,'products/yonex-ac102ex-main.webp','Quấn cán Yonex AC102EX Super Grap',1,1,'2026-03-14 10:01:32');
/*!40000 ALTER TABLE `product_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `product_variants`
--

LOCK TABLES `product_variants` WRITE;
/*!40000 ALTER TABLE `product_variants` DISABLE KEYS */;
INSERT INTO `product_variants` (`variant_id`, `product_id`, `sku`, `barcode`, `variant_name`, `color`, `size`, `material`, `weight_gram`, `cost_price`, `price`, `compare_at_price`, `stock`, `reserved_stock`, `safety_stock`, `image_url`, `status`, `created_at`, `updated_at`) VALUES (1,1,'YONEX-NF1000G-4U5',NULL,'Lightning Yellow / 4U5','Lightning Yellow','4U5','HM Graphite',83,2200000.00,2650000.00,2890000.00,12,0,2,'products/yonex-nanoflare-1000-game-4u5.webp','active','2026-03-14 10:01:31','2026-03-14 10:01:31'),(2,1,'YONEX-NF1000G-4U6',NULL,'Lightning Yellow / 4U6','Lightning Yellow','4U6','HM Graphite',82,2180000.00,2590000.00,2890000.00,10,0,2,'products/yonex-nanoflare-1000-game-4u6.webp','active','2026-03-14 10:01:31','2026-03-14 10:01:31'),(3,2,'MIZUNO-WC2-WHBL-41',NULL,'White Blue / 41','White Blue','41','Mesh + Rubber',620,1550000.00,1890000.00,2190000.00,8,0,2,'products/mizuno-wave-claw-2-41.webp','active','2026-03-14 10:01:32','2026-03-14 10:01:32'),(4,2,'MIZUNO-WC2-WHBL-42',NULL,'White Blue / 42','White Blue','42','Mesh + Rubber',640,1580000.00,1950000.00,2190000.00,9,0,2,'products/mizuno-wave-claw-2-42.webp','active','2026-03-14 10:01:32','2026-03-14 10:01:32'),(5,2,'MIZUNO-WC2-WHBL-43',NULL,'White Blue / 43','White Blue','43','Mesh + Rubber',655,1600000.00,1990000.00,2190000.00,7,0,2,'products/mizuno-wave-claw-2-43.webp','active','2026-03-14 10:01:32','2026-03-14 10:01:32'),(6,3,'LINING-TOUR2026-RED-M',NULL,'Red / M','Red','M','Polyester',155,180000.00,289000.00,349000.00,20,0,3,'products/li-ning-tournament-2026-red-m.webp','active','2026-03-14 10:01:32','2026-03-14 10:01:32'),(7,3,'LINING-TOUR2026-RED-L',NULL,'Red / L','Red','L','Polyester',160,180000.00,289000.00,349000.00,18,0,3,'products/li-ning-tournament-2026-red-l.webp','active','2026-03-14 10:01:32','2026-03-14 10:01:32'),(8,3,'LINING-TOUR2026-BLUE-XL',NULL,'Blue / XL','Blue','XL','Polyester',165,190000.00,319000.00,359000.00,15,0,3,'products/li-ning-tournament-2026-blue-xl.webp','active','2026-03-14 10:01:32','2026-03-14 10:01:32'),(9,4,'VICTOR-BR5611-BLACK',NULL,'Black / Standard','Black','Standard','PU + Polyester',900,520000.00,690000.00,790000.00,11,0,2,'products/victor-br5611-black.webp','active','2026-03-14 10:01:32','2026-03-14 10:01:32'),(10,4,'VICTOR-BR5611-BLUE',NULL,'Blue / Standard','Blue','Standard','PU + Polyester',900,550000.00,750000.00,820000.00,9,0,2,'products/victor-br5611-blue.webp','active','2026-03-14 10:01:32','2026-03-14 10:01:32'),(11,5,'YONEX-AC102EX-WHITE',NULL,'White / Free Size','White','Free Size','PU',25,18000.00,35000.00,45000.00,50,0,10,'products/yonex-ac102ex-white.webp','active','2026-03-14 10:01:32','2026-03-14 10:01:32'),(12,5,'YONEX-AC102EX-BLACK',NULL,'Black / Free Size','Black','Free Size','PU',25,18000.00,35000.00,45000.00,60,0,10,'products/yonex-ac102ex-black.webp','active','2026-03-14 10:01:32','2026-03-14 10:01:32'),(13,5,'YONEX-AC102EX-PINK',NULL,'Pink / Free Size','Pink','Free Size','PU',25,20000.00,39000.00,49000.00,40,0,10,'products/yonex-ac102ex-pink.webp','active','2026-03-14 10:01:32','2026-03-14 10:01:32');
/*!40000 ALTER TABLE `product_variants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` (`product_id`, `category_id`, `brand_id`, `seller_id`, `name`, `slug`, `short_description`, `description`, `thumbnail_url`, `product_type`, `status`, `is_featured`, `min_price`, `max_price`, `rating_avg`, `rating_count`, `view_count`, `sold_count`, `published_at`, `deleted_at`, `created_at`, `updated_at`) VALUES (1,2,1,1,'Vợt cầu lông Yonex Nanoflare 1000 Game','vot-cau-long-yonex-nanoflare-1000-game','Dòng vợt công thủ toàn diện, trợ lực tốt, phù hợp người chơi phong trào.','Yonex Nanoflare 1000 Game phù hợp người chơi phong trào và bán chuyên. Tốc độ vung nhanh, khung ổn định, hỗ trợ phản tạt và điều cầu tốt.','products/yonex-nanoflare-1000-game-main.webp','variant','active',1,2590000.00,2650000.00,0.00,0,0,0,'2026-03-14 10:01:31',NULL,'2026-03-14 10:01:31','2026-03-14 10:01:31'),(2,1,2,1,'Giày cầu lông Mizuno Wave Claw 2','giay-cau-long-mizuno-wave-claw-2','Giày cầu lông ôm chân, bám sân tốt, phù hợp đánh đơn và đôi.','Mizuno Wave Claw 2 mang lại cảm giác chắc chân, êm ái và ổn định khi di chuyển tốc độ cao.','products/mizuno-wave-claw-2-main.webp','variant','active',1,1890000.00,1990000.00,0.00,0,0,0,'2026-03-14 10:01:31',NULL,'2026-03-14 10:01:31','2026-03-14 10:01:31'),(3,3,3,1,'Áo cầu lông Li-Ning Tournament 2026','ao-cau-long-li-ning-tournament-2026','Áo thể thao thoáng khí, thấm hút mồ hôi, form mặc trẻ trung.','Li-Ning Tournament 2026 phù hợp cho tập luyện và thi đấu. Chất vải nhẹ, co giãn tốt, thoát mồ hôi nhanh.','products/li-ning-tournament-2026-main.webp','variant','active',0,289000.00,319000.00,0.00,0,0,0,'2026-03-14 10:01:31',NULL,'2026-03-14 10:01:31','2026-03-14 10:01:31'),(4,4,4,1,'Túi cầu lông Victor BR5611','tui-cau-long-victor-br5611','Túi cầu lông nhiều ngăn, đựng được vợt, giày và phụ kiện.','Victor BR5611 là mẫu túi thể thao tiện dụng với nhiều ngăn, quai đeo chắc chắn, chất liệu bền.','products/victor-br5611-main.webp','variant','active',0,690000.00,750000.00,0.00,0,0,0,'2026-03-14 10:01:31',NULL,'2026-03-14 10:01:31','2026-03-14 10:01:31'),(5,4,1,1,'Quấn cán Yonex AC102EX Super Grap','quan-can-yonex-ac102ex-super-grap','Quấn cán mềm, bám tay tốt, thấm mồ hôi, phù hợp nhiều loại vợt.','Yonex AC102EX Super Grap có độ bám tốt, cảm giác cầm chắc tay và độ bền ổn định.','products/yonex-ac102ex-main.webp','variant','active',0,35000.00,39000.00,0.00,0,0,0,'2026-03-14 10:01:31',NULL,'2026-03-14 10:01:31','2026-03-14 10:01:31');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `promotion_categories`
--

LOCK TABLES `promotion_categories` WRITE;
/*!40000 ALTER TABLE `promotion_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `promotion_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `promotion_products`
--

LOCK TABLES `promotion_products` WRITE;
/*!40000 ALTER TABLE `promotion_products` DISABLE KEYS */;
/*!40000 ALTER TABLE `promotion_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `promotion_usages`
--

LOCK TABLES `promotion_usages` WRITE;
/*!40000 ALTER TABLE `promotion_usages` DISABLE KEYS */;
/*!40000 ALTER TABLE `promotion_usages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `promotions`
--

LOCK TABLES `promotions` WRITE;
/*!40000 ALTER TABLE `promotions` DISABLE KEYS */;
/*!40000 ALTER TABLE `promotions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES ('GoIlpFFdIkrHIwy2me3B9eiV3UbjTFnC1MvMpPYG',NULL,'172.30.0.1','PostmanRuntime/7.52.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoiRjVHRFlDUnFHbmNYZlJ2b3dPZjZPMXQ1TlJSZ3NtZDhCNlJpY3BKcSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly9sb2NhbGhvc3Q6ODM4MyI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1773742564),('ijQrdZr45GWCelEKTlHivjSNwhqA0vVSPSMdx25j',NULL,'172.30.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiM1FSQ3lDYnQ4TndQbmpMbnBvb0FZUGp1Y1NMWVR5UkVVQnJuSTFyWCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly9sb2NhbGhvc3Q6ODM4MyI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1773739930),('v9O6GAQvNOo4XVLIzG2LAGM501Lkf7EdPGfIDx7q',NULL,'172.30.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiNGVaZWVRc2hBbnZ6TmRyRm9pZ3dMbHZLcVJyTWlpRjRRUERXMENvcCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly9sb2NhbGhvc3Q6ODM4MyI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1773482955);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` (`user_id`, `full_name`, `email`, `phone`, `password`, `avatar_url`, `role`, `status`, `email_verified_at`, `last_login_at`, `remember_token`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'Seller Demo','seller_demo@shop.com','0909999999','$2y$10$examplehashsellerdemo',NULL,'seller','active',NULL,NULL,NULL,'2026-03-14 10:01:31','2026-03-14 10:01:31',NULL),(2,'bống','bongdan@gamil.com',NULL,'$2y$12$X3W/WeY7oLSk3TLWIzaVA.aBAG1gSrflB1VYqYowjgabbWbSTivoC',NULL,'customer','active',NULL,NULL,NULL,'2026-03-17 10:08:13','2026-03-17 10:08:13',NULL),(3,'Super Admin','admin123@gmail.com',NULL,'$2y$12$5yHnb89k6TEPycYjwmSoHegl.vP3cz6CyGJgMgf/dMRFiQg5EA.2C',NULL,'admin','active',NULL,NULL,NULL,'2026-03-18 01:46:49','2026-03-18 01:46:49',NULL),(4,'duong','bong@gamil.com',NULL,'$2y$12$MVsW1AkaI5yTbRMphxcMwulAh/rIpUy3jUO5YVdQUCdborDhzQtWC',NULL,'customer','active',NULL,NULL,NULL,'2026-03-18 01:49:16','2026-03-18 07:18:51',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-18  7:56:09
