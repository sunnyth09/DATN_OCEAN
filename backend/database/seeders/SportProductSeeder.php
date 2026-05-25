<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SportProductSeeder extends Seeder
{
    private string $now;

    /* ──────────────── MAIN ──────────────── */
    public function run(): void
    {
        $this->now = Carbon::now()->toDateTimeString();

        $catMap = $this->seedCategories();
        $this->seedProducts($catMap);

        echo "✅ SportProductSeeder hoàn tất: 20 sản phẩm đồ thể thao đã được thêm.\n";
    }

    /* ──────────────── CATEGORIES ──────────────── */
    private function seedCategories(): array
    {
        $catMap = []; // slug => category_id

        // Parent
        $parentSlug = 'do-the-thao';
        $parent = DB::table('categories')->where('slug', $parentSlug)->first();
        if (!$parent) {
            $parentId = DB::table('categories')->insertGetId([
                'parent_id'   => null,
                'name'        => 'Đồ thể thao',
                'slug'        => $parentSlug,
                'image'       => null,
                'description' => 'Trang phục và phụ kiện thể thao chuyên nghiệp',
                'sort_order'  => 10,
                'is_active'   => 1,
                'created_at'  => $this->now,
                'updated_at'  => $this->now,
            ]);
        } else {
            $parentId = $parent->category_id;
        }

        $children = [
            'Áo thể thao nam' => 'Áo tập gym, chạy bộ nam',
            'Áo thể thao nữ' => 'Áo tập, bra thể thao nữ',
            'Quần thể thao' => 'Quần short, jogger thể thao',
            'Giày thể thao' => 'Giày chạy bộ, giày đá bóng, training',
            'Phụ kiện thể thao' => 'Balo, bình nước, thảm yoga, găng tay',
        ];

        foreach ($children as $childName => $childDesc) {
            $childSlug = Str::slug($childName);
            $child = DB::table('categories')->where('slug', $childSlug)->first();

            if (!$child) {
                $childId = DB::table('categories')->insertGetId([
                    'parent_id'   => $parentId,
                    'name'        => $childName,
                    'slug'        => $childSlug,
                    'image'       => null,
                    'description' => $childDesc,
                    'sort_order'  => 1,
                    'is_active'   => 1,
                    'created_at'  => $this->now,
                    'updated_at'  => $this->now,
                ]);
                $catMap[$childSlug] = $childId;
            } else {
                $catMap[$childSlug] = $child->category_id;
            }
        }

        return $catMap;
    }

    /* ──────────────── PRODUCTS ──────────────── */
    private function seedProducts(array $catMap): void
    {
        $allProducts = $this->getProductsData();
        $counter = 0;
        
        // Retrieve Nike and Adidas brand ids, fallback to first brand if not found
        $nike = DB::table('brands')->where('slug', 'nike')->first();
        $adidas = DB::table('brands')->where('slug', 'adidas')->first();
        $defaultBrand = DB::table('brands')->first();
        
        $brandNikeId = $nike ? $nike->brand_id : ($defaultBrand ? $defaultBrand->brand_id : null);
        $brandAdidasId = $adidas ? $adidas->brand_id : ($defaultBrand ? $defaultBrand->brand_id : null);

        foreach ($allProducts as $catSlug => $products) {
            $categoryId = $catMap[$catSlug] ?? null;
            if (!$categoryId) {
                echo "⚠️ Category not found: {$catSlug}\n";
                continue;
            }

            foreach ($products as $index => $p) {
                $counter++;
                // $p = [name, price, brand_id_var, short_desc, featured, image_path]
                $brandId = ($p[2] == 1) ? $brandNikeId : $brandAdidasId;

                $slug = Str::slug($p[0]);

                // Ensure unique slug
                if (DB::table('products')->where('slug', $slug)->exists()) {
                    $slug .= '-' . Str::random(4);
                }

                $imagePath = $p[5] ?? null;

                $productId = DB::table('products')->insertGetId([
                    'category_id'       => $categoryId,
                    'brand_id'          => $brandId,
                    'seller_id'         => null,
                    'name'              => $p[0],
                    'slug'              => $slug,
                    'short_description' => $p[3],
                    'description'       => $this->buildDescription($p[0], $catSlug),
                    'thumbnail_url'     => $imagePath,
                    'product_type'      => 'variant',
                    'status'            => 'active',
                    'is_featured'       => $p[4] ?? false,
                    'min_price'         => $p[1],
                    'max_price'         => $p[1],
                    'rating_avg'        => round(mt_rand(40, 50) / 10, 1),
                    'rating_count'      => mt_rand(50, 200),
                    'view_count'        => mt_rand(500, 3000),
                    'sold_count'        => mt_rand(20, 150),
                    'published_at'      => $this->now,
                    'created_at'        => $this->now,
                    'updated_at'        => $this->now,
                ]);

                // ── Variants ──
                $variants = $this->generateVariants($productId, $slug, $p[1], $catSlug);

                // Update min/max price
                $prices = array_column($variants, 'price');
                if (!empty($prices)) {
                    DB::table('products')->where('product_id', $productId)->update([
                        'min_price'    => min($prices),
                        'max_price'    => max($prices),
                        'product_type' => count($variants) > 1 ? 'variant' : 'simple',
                    ]);
                }

                // ── Images (1 main image, 1-2 sub images) ──
                $this->createImages($productId, $p[0], $imagePath, $index);
            }
        }
    }

    private function generateVariants(int $productId, string $slug, int $basePrice, string $catSlug): array
    {
        $config = $this->getVariantConfig($catSlug);
        $variants = [];

        foreach ($config['colors'] as $color) {
            foreach ($config['sizes'] as $size) {
                $variance = mt_rand(-5, 5) * 10000;
                $price = max($basePrice + $variance, 50000);

                $skuParts = [$slug];
                if ($color) $skuParts[] = Str::slug($color);
                if ($size) $skuParts[] = Str::slug($size);
                $sku = implode('-', $skuParts);

                // Ensure unique SKU
                $attempt = 0;
                $originalSku = $sku;
                while (DB::table('product_variants')->where('sku', $sku)->exists()) {
                    $sku = $originalSku . '-' . Str::random(3);
                    if (++$attempt > 5) break;
                }

                $barcode = 'SPORT' . strtoupper(Str::random(8)) . mt_rand(10, 99);

                DB::table('product_variants')->insert([
                    'product_id'       => $productId,
                    'sku'              => $sku,
                    'barcode'          => $barcode,
                    'variant_name'     => trim(($color ?? '') . ' - ' . ($size ?? ''), ' -'),
                    'color'            => $color,
                    'size'             => $size,
                    'material'         => $config['material'] ?? null,
                    'weight_gram'      => $config['weight'] ?? null,
                    'cost_price'       => round($price * 0.6),
                    'price'            => $price,
                    'compare_at_price' => round($price * 1.2),
                    'stock'            => mt_rand(10, 100),
                    'reserved_stock'   => 0,
                    'safety_stock'     => 5,
                    'image_url'        => null,
                    'status'           => 'active',
                    'created_at'       => $this->now,
                    'updated_at'       => $this->now,
                ]);

                $variants[] = ['price' => $price];
            }
        }

        return $variants;
    }

    private function getVariantConfig(string $catSlug): array
    {
        if (Str::contains($catSlug, ['ao-', 'quan-'])) {
            return [
                'colors'   => ['Đen', 'Xanh Navy'],
                'sizes'    => ['M', 'L', 'XL'],
                'material' => 'Polyester Dry-Fit',
                'weight'   => mt_rand(150, 300),
            ];
        }

        if (Str::contains($catSlug, ['giay-'])) {
            return [
                'colors'   => ['Trắng', 'Đỏ'],
                'sizes'    => ['40', '41', '42'],
                'material' => 'Vải Mesh thoáng khí',
                'weight'   => mt_rand(400, 600),
            ];
        }

        return [
            'colors'   => ['Đen'],
            'sizes'    => [null],
            'material' => 'Tổng hợp',
            'weight'   => mt_rand(100, 1000),
        ];
    }

    private function createImages(int $productId, string $name, ?string $imagePath, int $index): void
    {
        if (!$imagePath) return;

        // Main image
        DB::table('product_images')->insert([
            'product_id' => $productId,
            'variant_id' => null,
            'image_url'  => $imagePath,
            'alt_text'   => $name . ' chính',
            'is_main'    => 1,
            'sort_order' => 0,
            'created_at' => $this->now,
        ]);

        // Sub image 1
        DB::table('product_images')->insert([
            'product_id' => $productId,
            'variant_id' => null,
            'image_url'  => str_replace('.jpg', '_sub1.jpg', $imagePath),
            'alt_text'   => $name . ' góc 1',
            'is_main'    => 0,
            'sort_order' => 1,
            'created_at' => $this->now,
        ]);

        // Sub image 2
        DB::table('product_images')->insert([
            'product_id' => $productId,
            'variant_id' => null,
            'image_url'  => str_replace('.jpg', '_sub2.jpg', $imagePath),
            'alt_text'   => $name . ' góc 2',
            'is_main'    => 0,
            'sort_order' => 2,
            'created_at' => $this->now,
        ]);
    }

    private function buildDescription(string $name, string $catSlug): string
    {
        return '<div class="product-description">'
            . '<h3>Mô tả sản phẩm</h3>'
            . '<p><strong>' . $name . '</strong> là sản phẩm thể thao cao cấp, được thiết kế chuyên dụng giúp tối ưu hiệu suất tập luyện. '
            . 'Chất liệu thoáng mát, độ bền cao, phù hợp với cường độ vận động mạnh.</p>'
            . '<h3>Đặc điểm nổi bật</h3><ul>'
            . '<li>Công nghệ thoát mồ hôi nhanh chóng</li>'
            . '<li>Thiết kế ôm sát nhưng vẫn đảm bảo linh hoạt</li>'
            . '<li>Đường may chắc chắn, không gây cọ xát da</li>'
            . '<li>Thiết kế thời trang thể thao năng động</li></ul>'
            . '<h3>Hướng dẫn bảo quản</h3><ul>'
            . '<li>Giặt lạnh, không dùng hóa chất tẩy rửa mạnh</li>'
            . '<li>Không ủi trực tiếp lên logo/họa tiết</li>'
            . '<li>Phơi nơi thoáng mát, tránh nắng gắt</li></ul>'
            . '</div>';
    }

    private function getProductsData(): array
    {
        return [
            /* ───────── ÁO THỂ THAO NAM (2) ───────── */
            'ao-the-thao-nam' => [
                ['Áo thun thể thao nam Dry-Fit Pro', 350000, 1, 'Áo thun thể thao nam thấm hút mồ hôi cực tốt, phù hợp tập gym và chạy bộ.', true, 'products/unique/sport_001_ao_nam_dryfit.jpg'],
                ['Áo tank top nam tập Gym Muscle', 250000, 2, 'Áo ba lỗ thể thao form rộng thoải mái, khoe cơ bắp tối đa.', false, 'products/unique/sport_002_ao_nam_tanktop.jpg'],
            ],

            /* ───────── ÁO THỂ THAO NỮ (2) ───────── */
            'ao-the-thao-nu' => [
                ['Áo Bra thể thao nữ High Impact', 290000, 1, 'Sport bra hỗ trợ tối đa cho các bài tập cardio cường độ cao.', true, 'products/unique/sport_003_ao_nu_bra.jpg'],
                ['Áo thun crop top thể thao nữ', 220000, 2, 'Áo croptop tập gym chất liệu co giãn 4 chiều, thiết kế trẻ trung.', false, 'products/unique/sport_004_ao_nu_croptop.jpg'],
            ],

            /* ───────── QUẦN THỂ THAO (2) ───────── */
            'quan-the-thao' => [
                ['Quần short thể thao nam 2 lớp', 280000, 1, 'Quần short tập gym nam có lớp lót bảo vệ bên trong, túi khóa tiện lợi.', true, 'products/unique/sport_005_quan_short_nam.jpg'],
                ['Quần legging tập Yoga nữ nâng mông', 320000, 2, 'Quần legging cạp cao ôm sát, chất vải dày dặn không lộ, tôn dáng.', false, 'products/unique/sport_006_quan_legging_nu.jpg'],
            ],

            /* ───────── GIÀY THỂ THAO (4) ───────── */
            'giay-the-thao' => [
                ['Giày chạy bộ nam Ultra Run Pro', 1850000, 1, 'Giày chạy bộ siêu nhẹ, đế cao su đàn hồi bám đường tốt.', true, 'products/unique/sport_007_giay_chay_nam.jpg'],
                ['Giày chạy bộ nữ Cloud Cushion', 1750000, 2, 'Thiết kế êm ái, bảo vệ cổ chân, phù hợp cho mọi cự ly chạy.', false, 'products/unique/sport_008_giay_chay_nu.jpg'],
                ['Giày đá bóng sân cỏ nhân tạo TF', 1250000, 1, 'Giày bóng đá đinh dăm TF bám sân, upper mềm tăng cảm giác bóng.', true, 'products/unique/sport_009_giay_da_bong_1.jpg'],
                ['Giày đá bóng sân cỏ tự nhiên FG', 2150000, 2, 'Giày đinh cao FG chuyên nghiệp cho tốc độ bứt phá trên sân cỏ thật.', false, 'products/unique/sport_010_giay_da_bong_2.jpg'],
            ],

            /* ───────── PHỤ KIỆN THỂ THAO (10) ───────── */
            'phu-kien-the-thao' => [
                ['Balo thể thao đa năng chống nước', 550000, 1, 'Balo thể thao rộng rãi, ngăn đựng giày riêng, chất liệu trượt nước.', true, 'products/unique/sport_011_balo_the_thao.jpg'],
                ['Túi trống thể thao Gym Bag', 450000, 2, 'Túi xách tập gym nhỏ gọn, vừa vặn đồ tập và phụ kiện.', false, 'products/unique/sport_012_tui_trong_gym.jpg'],
                ['Bình nước thể thao inox giữ nhiệt', 290000, 1, 'Bình nước 800ml giữ lạnh 24h, thiết kế nắp chống tràn thông minh.', true, 'products/unique/sport_013_binh_nuoc_the_thao.jpg'],
                ['Bình lắc Whey Shaker 600ml', 150000, 2, 'Bình lắc sữa tăng cơ chuyên dụng, có màng lọc đánh tan bột.', false, 'products/unique/sport_014_binh_lac_whey.jpg'],
                ['Găng tay tập Gym bảo vệ cổ tay', 180000, 1, 'Găng tay hở ngón chống chai tay, đai quấn hỗ trợ cổ tay vững chắc.', true, 'products/unique/sport_015_gang_tay_gym.jpg'],
                ['Găng tay thủ môn chuyên nghiệp', 450000, 2, 'Găng tay bóng đá có mút dày bám dính, xương chống lật ngón.', false, 'products/unique/sport_016_gang_tay_thu_mon.jpg'],
                ['Thảm Yoga TPE cao cấp 8mm', 350000, 1, 'Thảm tập yoga chất liệu TPE an toàn, chống trơn trượt tuyệt đối.', true, 'products/unique/sport_017_tham_yoga_tpe.jpg'],
                ['Thảm Yoga du lịch siêu mỏng 1.5mm', 420000, 2, 'Thảm yoga gấp gọn tiện lợi, phủ cao su bám dính cao.', false, 'products/unique/sport_018_tham_yoga_gap.jpg'],
                ['Vợt cầu lông Carbon siêu nhẹ', 1150000, 1, 'Vợt cầu lông khung carbon cường độ cao, trợ lực tốt.', true, 'products/unique/sport_019_vot_cau_long.jpg'],
                ['Con lăn tập cơ bụng AB Wheel', 250000, 2, 'Dụng cụ tập bụng con lăn đôi chắc chắn, kèm thảm lót gối.', false, 'products/unique/sport_020_con_lan_tap_bung.jpg'],
            ],
        ];
    }
}
