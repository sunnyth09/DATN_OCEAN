SET FOREIGN_KEY_CHECKS=0;

-- Xóa toàn bộ danh mục cũ (thời trang)
DELETE FROM categories;

-- Reset auto increment
ALTER TABLE categories AUTO_INCREMENT = 1;

-- Tạo danh mục cha (hiển thị trên navbar)
INSERT INTO categories (category_id, parent_id, name, slug, image, description, sort_order, is_active, created_at, updated_at) VALUES
(1, NULL, 'Cầu lông',    'cau-long',    NULL, 'Dụng cụ và phụ kiện cầu lông chính hãng', 1, 1, NOW(), NOW()),
(2, NULL, 'Bóng chuyền', 'bong-chuyen', NULL, 'Dụng cụ và trang bị bóng chuyền',         2, 1, NOW(), NOW()),
(3, NULL, 'Pickleball',  'pickleball',  NULL, 'Vợt, bóng và phụ kiện Pickleball',        3, 1, NOW(), NOW()),
(4, NULL, 'Phụ kiện',    'phu-kien',    NULL, 'Phụ kiện thể thao đa dạng',               4, 1, NOW(), NOW());

-- Tạo danh mục con cho Cầu lông
INSERT INTO categories (parent_id, name, slug, image, description, sort_order, is_active, created_at, updated_at) VALUES
(1, 'Vợt cầu lông',       'vot-cau-long',       NULL, 'Vợt cầu lông các thương hiệu', 1, 1, NOW(), NOW()),
(1, 'Giày cầu lông',      'giay-cau-long',      NULL, 'Giày chuyên dụng cầu lông',     2, 1, NOW(), NOW()),
(1, 'Quả cầu lông',       'qua-cau-long',       NULL, 'Quả cầu lông thi đấu & tập',   3, 1, NOW(), NOW()),
(1, 'Túi vợt cầu lông',   'tui-vot-cau-long',   NULL, 'Túi đựng vợt cầu lông',        4, 1, NOW(), NOW()),
(1, 'Dây căng vợt',       'day-cang-vot',       NULL, 'Dây căng vợt các loại',         5, 1, NOW(), NOW());

-- Tạo danh mục con cho Bóng chuyền
INSERT INTO categories (parent_id, name, slug, image, description, sort_order, is_active, created_at, updated_at) VALUES
(2, 'Bóng chuyền',        'qua-bong-chuyen',    NULL, 'Bóng chuyền thi đấu & tập', 1, 1, NOW(), NOW()),
(2, 'Giày bóng chuyền',   'giay-bong-chuyen',   NULL, 'Giày chuyên dụng bóng chuyền', 2, 1, NOW(), NOW()),
(2, 'Bảo hộ bóng chuyền', 'bao-ho-bong-chuyen', NULL, 'Bảo vệ đầu gối, khuỷu tay', 3, 1, NOW(), NOW()),
(2, 'Lưới bóng chuyền',   'luoi-bong-chuyen',   NULL, 'Lưới bóng chuyền các loại',  4, 1, NOW(), NOW());

-- Tạo danh mục con cho Pickleball
INSERT INTO categories (parent_id, name, slug, image, description, sort_order, is_active, created_at, updated_at) VALUES
(3, 'Vợt Pickleball',     'vot-pickleball',     NULL, 'Vợt Pickleball các hãng',   1, 1, NOW(), NOW()),
(3, 'Bóng Pickleball',    'bong-pickleball',    NULL, 'Bóng Pickleball indoor/outdoor', 2, 1, NOW(), NOW()),
(3, 'Giày Pickleball',    'giay-pickleball',    NULL, 'Giày chuyên dụng Pickleball', 3, 1, NOW(), NOW());

-- Tạo danh mục con cho Phụ kiện
INSERT INTO categories (parent_id, name, slug, image, description, sort_order, is_active, created_at, updated_at) VALUES
(4, 'Băng mồ hôi',        'bang-mo-hoi',        NULL, 'Băng tay, băng đầu thể thao', 1, 1, NOW(), NOW()),
(4, 'Quấn cán vợt',       'quan-can-vot',       NULL, 'Quấn cán vợt các loại',        2, 1, NOW(), NOW()),
(4, 'Balo & Túi thể thao','balo-tui-the-thao',  NULL, 'Balo, túi đựng đồ thể thao',  3, 1, NOW(), NOW()),
(4, 'Bình nước',           'binh-nuoc',          NULL, 'Bình giữ nhiệt thể thao',     4, 1, NOW(), NOW()),
(4, 'Quần áo thể thao',   'quan-ao-the-thao',   NULL, 'Trang phục tập luyện',         5, 1, NOW(), NOW());

SET FOREIGN_KEY_CHECKS=1;
