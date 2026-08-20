-- ==============================================================================
-- SQL MIGRATION CHO GIAI ĐOẠN 5: GAMIFICATION & LOYALTY
-- Chạy script này trên Server CSDL (phpMyAdmin / MySQL Client)
-- ==============================================================================

-- 1. Bổ sung trường điểm danh vào bảng users
ALTER TABLE `users` 
ADD COLUMN `last_check_in_at` TIMESTAMP NULL DEFAULT NULL AFTER `reward_points`,
ADD COLUMN `check_in_streak` INT NOT NULL DEFAULT 0 AFTER `last_check_in_at`;

-- 2. Tạo bảng danh sách phần thưởng Vòng quay may mắn
CREATE TABLE IF NOT EXISTS `lucky_wheel_prizes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `type` VARCHAR(255) NOT NULL,
  `value` INT NOT NULL DEFAULT 0,
  `probability` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `color` VARCHAR(255) NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Chèn dữ liệu mẫu cho Vòng quay may mắn
INSERT INTO `lucky_wheel_prizes` (`name`, `type`, `value`, `probability`, `color`, `is_active`, `created_at`, `updated_at`) VALUES
('10 Xu', 'points', 10, 30.00, '#FFC107', 1, NOW(), NOW()),
('Chúc bạn MM lần sau', 'empty', 0, 40.00, '#E0E0E0', 1, NOW(), NOW()),
('50 Xu', 'points', 50, 15.00, '#FF9800', 1, NOW(), NOW()),
('Voucher 10%', 'voucher', 10, 5.00, '#4CAF50', 1, NOW(), NOW()),
('100 Xu', 'points', 100, 8.00, '#F44336', 1, NOW(), NOW()),
('Voucher 20%', 'voucher', 20, 2.00, '#9C27B0', 1, NOW(), NOW());
