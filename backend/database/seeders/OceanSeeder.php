<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use Illuminate\Support\Str;

class OceanSeeder extends Seeder
{
    public function run()
    {
        // Tắt kiểm tra khóa ngoại để truncate
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Xóa dữ liệu cũ
        ProductImage::truncate();
        ProductVariant::truncate();
        Product::truncate();
        Category::truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Tạo 20 danh mục
        $categoriesInfo = [
            'Áo' => ['Áo thun', 'Áo sơ mi', 'Áo khoác'],
            'Quần' => ['Quần jeans', 'Quần tây', 'Quần short', 'Quần kaki'],
            'Váy' => ['Váy chữ A', 'Váy xếp ly', 'Váy midi'],
            'Đầm' => ['Đầm dự tiệc', 'Đầm công sở'],
            'Phụ kiện' => ['Túi xách', 'Mũ nón', 'Thắt lưng']
        ];

        $allCategoriesList = [];

        $sortOrder = 1;
        foreach ($categoriesInfo as $parentName => $children) {
            $parentCat = Category::create([
                'name' => $parentName,
                'slug' => Str::slug($parentName),
                'description' => 'Khám phá bộ sưu tập ' . $parentName . ' phong cách thời thượng, thiết kế tối giản, dễ mặc cho mọi lứa tuổi.',
                'sort_order' => $sortOrder++,
                'is_active' => 1,
            ]);

            foreach ($children as $childName) {
                $childCat = Category::create([
                    'parent_id' => $parentCat->category_id,
                    'name' => $childName,
                    'slug' => Str::slug($childName),
                    'description' => $childName . ' - Định hình cá tính của bạn với thiết kế mới nhất.',
                    'sort_order' => $sortOrder++,
                    'is_active' => 1,
                ]);
                $allCategoriesList[] = [
                    'id' => $childCat->category_id,
                    'name' => $childName
                ];
            }
        }

        // Tạo 100 sản phẩm
        $colors = ['Đen', 'Trắng', 'Be', 'Xám', 'Xanh Navy'];
        $sizes = ['S', 'M', 'L', 'XL'];
        $materials = ['Cotton', 'Linen', 'Denim', 'Kaki', 'Polyester'];

        $styles = ['Cổ Tròn', 'Cổ Tim', 'Cổ Lọ', 'Cổ Bẻ', 'Dáng Rộng', 'Ôm Body', 'Tay Dài', 'Tay Ngắn', 'Không Tay', 'Họa Tiết', 'Kẻ Caro', 'Chấm Bi', 'Trơn basic', 'Ống Suông', 'Ống Loe', 'Eo Cao', 'Thắt Nơ', 'Bèo Nhún'];
        $adjectives = ['Thời Thượng', 'Tối Giản', 'Phong Cách', 'Cá Tính', 'Dễ Phối', 'Thanh Lịch', 'Trẻ Trung', 'Năng Động', 'Sang Trọng', 'Cổ Điển', 'Hàn Quốc', 'Châu Âu', 'Gợi Mời', 'Bụi Bặm', 'Đường Phố', 'Thể Thao', 'Dịu Dàng'];
        $suffixes = ['Cao Cấp', 'Siêu Hot', 'Bản Giới Hạn', 'Bộ Sưu Tập Mới', 'Mùa Hè', 'Mùa Đông', 'Mùa Thu', 'Mẫu Độc Quyền', 'Phiên Bản Kỷ Niệm', 'Thiết Kế Riot'];

        for ($i = 1; $i <= 100; $i++) {
            $isVariable = $i % 2 === 0; // Một nửa có biến thể, một nửa đơn
            $catInfo = $allCategoriesList[array_rand($allCategoriesList)];
            $catId = $catInfo['id'];
            
            // Lựa chọn ngẫu nhiên có lấy style hay không (70% tỉ lệ có)
            $style = rand(1, 100) <= 70 ? $styles[array_rand($styles)] : '';
            // Lựa chọn ngẫu nhiên có lấy suffix hay không (50% tỉ lệ có)
            $suffix = rand(1, 100) <= 50 ? $suffixes[array_rand($suffixes)] : '';
            $adj = $adjectives[array_rand($adjectives)];
            
            $nameParts = array_filter([$catInfo['name'], $style, $adj, $suffix]);
            $productName = implode(' ', $nameParts) . ' - SP' . str_pad($i, 3, '0', STR_PAD_LEFT);
            $basePrice = rand(150, 800) * 1000;
            
            $productType = $isVariable ? 'variant' : 'simple';

            $product = Product::create([
                'category_id' => $catId,
                'name' => $productName,
                'slug' => Str::slug($productName) . '-' . time() . rand(10, 99),
                'short_description' => 'Định hình cá tính của bạn với bộ sưu tập thời trang mới nhất. Thiết kế tối giản, dễ mặc, dễ phối cho mọi lứa tuổi.',
                'description' => '<p>Đây là sản phẩm thuộc bộ sưu tập Phong Cách Thời Thượng, được thiết kế tối giản giúp bạn dễ dàng phối đồ.</p><p>Sản phẩm phù hợp cho mọi lứa tuổi, mang lại cảm giác thoải mái và tự tin.</p>',
                'thumbnail_url' => 'https://picsum.photos/800/800?random=' . rand(1, 1000),
                'product_type' => $productType,
                'status' => 'active',
                'is_featured' => rand(0, 1),
                'min_price' => $basePrice,
                'max_price' => $isVariable ? $basePrice + 100000 : $basePrice,
                'rating_avg' => rand(30, 50) / 10,
                'rating_count' => rand(10, 500),
                'view_count' => rand(100, 2000),
                'sold_count' => rand(0, 500),
                'published_at' => now(),
            ]);

            // Thêm hình ảnh chính cho sản phẩm
            ProductImage::create([
                'product_id' => $product->product_id,
                'image_url' => $product->thumbnail_url,
                'alt_text' => $productName,
                'sort_order' => 1,
                'is_main' => 1,
            ]);
            
            // Thêm vài hình ảnh phụ
            for ($img = 2; $img <= 3; $img++) {
                 ProductImage::create([
                    'product_id' => $product->product_id,
                    'image_url' => 'https://picsum.photos/800/800?random=' . rand(1001, 2000),
                    'alt_text' => $productName . ' ' . $img,
                    'sort_order' => $img,
                    'is_main' => 0,
                ]);
            }

            if (!$isVariable) {
                // Sản phẩm đơn (simple)
                ProductVariant::create([
                    'product_id' => $product->product_id,
                    'sku' => 'SKU-' . $product->product_id . '-00',
                    'variant_name' => 'Mặc định',
                    'price' => $basePrice,
                    'compare_at_price' => $basePrice * 1.2,
                    'stock' => rand(10, 100),
                    'status' => 'active',
                    'image_url' => $product->thumbnail_url,
                ]);
            } else {
                // Sản phẩm có biến thể (variable)
                // Lấy 2 màu và 2 kích thước ngẫu nhiên
                $prodColors = array_rand(array_flip($colors), 2);
                $prodSizes = array_rand(array_flip($sizes), 2);
                $prodMaterial = $materials[array_rand($materials)];

                foreach ($prodColors as $color) {
                    foreach ($prodSizes as $size) {
                        $variantPrice = $basePrice + rand(0, 5) * 10000;
                        ProductVariant::create([
                            'product_id' => $product->product_id,
                            'sku' => 'SKU-' . $product->product_id . '-' . Str::slug($color . '-' . $size),
                            'variant_name' => $color . ' - ' . $size,
                            'color' => $color,
                            'size' => $size,
                            'material' => $prodMaterial,
                            'price' => $variantPrice,
                            'compare_at_price' => $variantPrice * 1.3,
                            'stock' => rand(5, 50),
                            'status' => 'active',
                            'image_url' => 'https://picsum.photos/800/800?random=' . rand(2001, 3000),
                        ]);
                    }
                }
            }
        }
    }
}
