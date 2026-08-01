<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExtraSportClothesSeeder extends Seeder
{
    private string $now;

    public function run(): void
    {
        $this->now = Carbon::now()->toDateTimeString();

        $catMap = $this->getCategories();
        $this->seedProducts($catMap);

        echo "✅ ExtraSportClothesSeeder hoàn tất: 10 sản phẩm quần áo thể thao đã được thêm.\n";
    }

    private function getCategories(): array
    {
        $catMap = [];
        $children = ['ao-the-thao-nam', 'ao-the-thao-nu', 'quan-the-thao'];

        foreach ($children as $slug) {
            $child = DB::table('categories')->where('slug', $slug)->first();
            if ($child) {
                $catMap[$slug] = $child->category_id;
            } else {
                echo "⚠️ Category not found: {$slug}. Please run SportProductSeeder first.\n";
            }
        }

        return $catMap;
    }

    private function seedProducts(array $catMap): void
    {
        $allProducts = $this->getProductsData();

        $nike = DB::table('brands')->where('slug', 'nike')->first();
        $adidas = DB::table('brands')->where('slug', 'adidas')->first();
        $defaultBrand = DB::table('brands')->first();

        $brandNikeId = $nike ? $nike->brand_id : ($defaultBrand ? $defaultBrand->brand_id : null);
        $brandAdidasId = $adidas ? $adidas->brand_id : ($defaultBrand ? $defaultBrand->brand_id : null);

        foreach ($allProducts as $catSlug => $products) {
            $categoryId = $catMap[$catSlug] ?? null;
            if (! $categoryId) {
                continue;
            }

            foreach ($products as $index => $p) {
                $brandId = ($p[2] == 1) ? $brandNikeId : $brandAdidasId;
                $slug = Str::slug($p[0]);

                if (DB::table('products')->where('slug', $slug)->exists()) {
                    $slug .= '-'.Str::random(4);
                }

                $imagePath = $p[5] ?? null;

                $productId = DB::table('products')->insertGetId([
                    'category_id' => $categoryId,
                    'brand_id' => $brandId,
                    'seller_id' => null,
                    'name' => $p[0],
                    'slug' => $slug,
                    'short_description' => $p[3],
                    'description' => $this->buildDescription($p[0]),
                    'thumbnail_url' => $imagePath,
                    'product_type' => 'variant',
                    'status' => 'active',
                    'is_featured' => $p[4] ?? false,
                    'min_price' => $p[1],
                    'max_price' => $p[1],
                    'rating_avg' => round(mt_rand(42, 50) / 10, 1),
                    'rating_count' => mt_rand(30, 150),
                    'view_count' => mt_rand(400, 2000),
                    'sold_count' => mt_rand(15, 100),
                    'published_at' => $this->now,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);

                $variants = $this->generateVariants($productId, $slug, $p[1], $catSlug);

                $prices = array_column($variants, 'price');
                if (! empty($prices)) {
                    DB::table('products')->where('product_id', $productId)->update([
                        'min_price' => min($prices),
                        'max_price' => max($prices),
                        'product_type' => count($variants) > 1 ? 'variant' : 'simple',
                    ]);
                }

                $this->createImages($productId, $p[0], $imagePath);
            }
        }
    }

    private function generateVariants(int $productId, string $slug, int $basePrice, string $catSlug): array
    {
        $colors = ['Đen', 'Xám Khói', 'Trắng'];
        $sizes = ['S', 'M', 'L', 'XL'];
        $variants = [];

        foreach (array_slice($colors, 0, 2) as $color) {
            foreach ($sizes as $size) {
                $variance = mt_rand(-5, 5) * 10000;
                $price = max($basePrice + $variance, 80000);

                $skuParts = [$slug, Str::slug($color), Str::slug($size)];
                $sku = implode('-', $skuParts);

                $attempt = 0;
                $originalSku = $sku;
                while (DB::table('product_variants')->where('sku', $sku)->exists()) {
                    $sku = $originalSku.'-'.Str::random(3);
                    if (++$attempt > 5) {
                        break;
                    }
                }

                $barcode = 'CLOTH'.strtoupper(Str::random(7)).mt_rand(10, 99);

                DB::table('product_variants')->insert([
                    'product_id' => $productId,
                    'sku' => $sku,
                    'barcode' => $barcode,
                    'variant_name' => trim(($color ?? '').' - '.($size ?? ''), ' -'),
                    'color' => $color,
                    'size' => $size,
                    'material' => 'Vải thể thao cao cấp',
                    'weight_gram' => mt_rand(200, 450),
                    'cost_price' => round($price * 0.55),
                    'price' => $price,
                    'compare_at_price' => round($price * 1.25),
                    'stock' => mt_rand(20, 150),
                    'reserved_stock' => 0,
                    'safety_stock' => 5,
                    'image_url' => null,
                    'status' => 'active',
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);

                $variants[] = ['price' => $price];
            }
        }

        return $variants;
    }

    private function createImages(int $productId, string $name, ?string $imagePath): void
    {
        if (! $imagePath) {
            return;
        }

        DB::table('product_images')->insert([
            'product_id' => $productId,
            'variant_id' => null,
            'image_url' => $imagePath,
            'alt_text' => $name.' mặt trước',
            'is_main' => 1,
            'sort_order' => 0,
            'created_at' => $this->now,
        ]);

        DB::table('product_images')->insert([
            'product_id' => $productId,
            'variant_id' => null,
            'image_url' => str_replace('.jpg', '_back.jpg', $imagePath),
            'alt_text' => $name.' mặt sau',
            'is_main' => 0,
            'sort_order' => 1,
            'created_at' => $this->now,
        ]);
    }

    private function buildDescription(string $name): string
    {
        return '<div class="product-description">'
            .'<h3>Chi tiết sản phẩm</h3>'
            .'<p>Sản phẩm <strong>'.$name.'</strong> mang phong cách năng động, hiện đại. Thích hợp cho cả việc tập luyện thể thao lẫn phối đồ đi chơi hằng ngày.</p>'
            .'<ul>'
            .'<li>Độ co giãn 4 chiều, thoải mái khi vận động mạnh.</li>'
            .'<li>Chống tia UV bảo vệ da khi tập luyện ngoài trời.</li>'
            .'<li>Giữ form cực tốt sau nhiều lần giặt.</li>'
            .'</ul>'
            .'</div>';
    }

    private function getProductsData(): array
    {
        return [
            /* ───────── ÁO THỂ THAO NAM (4) ───────── */
            'ao-the-thao-nam' => [
                ['Áo Khoác Gió Thể Thao Nam Chống Nước', 450000, 1, 'Áo khoác gió siêu nhẹ, chống nước nhẹ, có mũ trùm đầu.', true, 'products/unique/sport_021_khoac_gio_nam.jpg'],
                ['Áo Thun Thể Thao Nam Tay Dài Thu Đông', 290000, 2, 'Áo thun dài tay giữ nhiệt tốt cho mùa lạnh, co giãn 4 chiều.', false, 'products/unique/sport_022_ao_dai_tay_nam.jpg'],
                ['Áo Polo Thể Thao Nam Kháng Khuẩn', 320000, 1, 'Polo thể thao cổ bẻ lịch sự, công nghệ kháng khuẩn khử mùi.', true, 'products/unique/sport_023_polo_the_thao_nam.jpg'],
                ['Áo Hoodie Nam Thể Thao Năng Động', 550000, 2, 'Áo hoodie nỉ bông thể thao, form rộng thoải mái, túi bụng tiện lợi.', false, 'products/unique/sport_024_hoodie_nam.jpg'],
            ],

            /* ───────── ÁO THỂ THAO NỮ (3) ───────── */
            'ao-the-thao-nu' => [
                ['Áo Khoác Nỉ Thể Thao Nữ Cổ Lọ', 420000, 1, 'Áo khoác nỉ thể thao nữ mềm mịn, có khóa kéo cổ, giữ ấm cực tốt.', true, 'products/unique/sport_025_khoac_ni_nu.jpg'],
                ['Áo Thun Nữ Tập Yoga Dáng Suông', 240000, 2, 'Áo tập yoga dáng suông rủ mềm mại, thoáng mát, xẻ tà nhẹ.', false, 'products/unique/sport_026_ao_yoga_nu.jpg'],
                ['Áo Thể Thao Nữ Dài Tay Chống Nắng', 280000, 1, 'Áo thể thao nữ dài tay chống UV, có xỏ ngón bảo vệ bàn tay.', true, 'products/unique/sport_027_ao_chong_nang_nu.jpg'],
            ],

            /* ───────── QUẦN THỂ THAO (3) ───────── */
            'quan-the-thao' => [
                ['Quần Jogger Nam Thể Thao Vải Nỉ', 380000, 1, 'Quần jogger nam ống bo, dây rút hông, túi khóa kéo an toàn.', true, 'products/unique/sport_028_jogger_nam.jpg'],
                ['Quần Dài Tập Gym Nữ Lưng V', 310000, 2, 'Quần tập gym nữ cạp cao thiết kế chữ V tôn vòng 2, không đường may.', false, 'products/unique/sport_029_quan_tap_gym_nu_v.jpg'],
                ['Quần Đùi Thể Thao Nữ 2 Lớp', 250000, 1, 'Quần short nữ 2 lớp an toàn tuyệt đối khi chạy bộ và tập gym.', true, 'products/unique/sport_030_short_nu_2lop.jpg'],
            ],
        ];
    }
}
