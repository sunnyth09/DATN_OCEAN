<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MoreProductSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // Category IDs: Giày nike=6, adidas=7, Vợt Yonex=8, Victor=9, Li-Ning=10,
        // Áo=11, Quần=12, Váy=13, Balo=14, Grip=15, Cầu=16
        // Brand IDs: Yonex=1, Mizuno=2, Li-Ning=3, Victor=4

        $products = [
            // === VỢT THÊM (8 cây) ===
            [
                'category_id' => 8, 'brand_id' => 1,
                'name' => 'Vợt cầu lông Yonex Astrox 88D Pro', 'slug' => 'vot-yonex-astrox-88d-pro',
                'short_description' => 'Vợt tấn công dòng Astrox 88D dành cho người đánh đôi vị trí sau, smash cực mạnh.',
                'min_price' => 3690000, 'max_price' => 3690000, 'is_featured' => true,
                'variants' => [
                    ['sku' => 'YNX-AX88DP-4U', 'variant_name' => '4U G5', 'color' => 'Camel Gold', 'size' => '4U', 'price' => 3690000, 'stock' => 10],
                ],
            ],
            [
                'category_id' => 8, 'brand_id' => 1,
                'name' => 'Vợt cầu lông Yonex Astrox 88S Pro', 'slug' => 'vot-yonex-astrox-88s-pro',
                'short_description' => 'Vợt tốc độ dòng Astrox 88S dành cho người đánh đôi vị trí trước, kiểm soát lưới.',
                'min_price' => 3690000, 'max_price' => 3690000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'YNX-AX88SP-4U', 'variant_name' => '4U G5', 'color' => 'Silver/Black', 'size' => '4U', 'price' => 3690000, 'stock' => 8],
                ],
            ],
            [
                'category_id' => 8, 'brand_id' => 1,
                'name' => 'Vợt cầu lông Yonex Nanoflare 700', 'slug' => 'vot-yonex-nanoflare-700',
                'short_description' => 'Vợt nhẹ tốc độ cao dòng Nanoflare, khung Torayca, phù hợp phong cách nhanh nhẹn.',
                'min_price' => 2590000, 'max_price' => 2590000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'YNX-NF700-4U', 'variant_name' => '4U G5', 'color' => 'Blue/Green', 'size' => '4U', 'price' => 2590000, 'stock' => 14],
                ],
            ],
            [
                'category_id' => 9, 'brand_id' => 4,
                'name' => 'Vợt cầu lông Victor Jetspeed S 12F', 'slug' => 'vot-victor-jetspeed-s12f',
                'short_description' => 'Vợt tốc độ Victor dòng Jetspeed, khung PYROFIL, vung vợt cực nhanh.',
                'min_price' => 2990000, 'max_price' => 2990000, 'is_featured' => true,
                'variants' => [
                    ['sku' => 'VIC-JS12F-4U', 'variant_name' => '4U G5', 'color' => 'Đen/Cam', 'size' => '4U', 'price' => 2990000, 'stock' => 9],
                ],
            ],
            [
                'category_id' => 9, 'brand_id' => 4,
                'name' => 'Vợt cầu lông Victor DriveX 9X', 'slug' => 'vot-victor-drivex-9x',
                'short_description' => 'Vợt toàn diện Victor DriveX, cân bằng tấn công và phòng thủ hoàn hảo.',
                'min_price' => 2490000, 'max_price' => 2490000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'VIC-DX9X-4U', 'variant_name' => '4U G5', 'color' => 'Navy/Gold', 'size' => '4U', 'price' => 2490000, 'stock' => 11],
                ],
            ],
            [
                'category_id' => 10, 'brand_id' => 3,
                'name' => 'Vợt cầu lông Li-Ning Halbertec 9000', 'slug' => 'vot-li-ning-halbertec-9000',
                'short_description' => 'Vợt tấn công đỉnh cao Li-Ning, dùng bởi nhiều VĐV quốc tế trong giải vô địch.',
                'min_price' => 3490000, 'max_price' => 3490000, 'is_featured' => true,
                'variants' => [
                    ['sku' => 'LN-HB9000-4U', 'variant_name' => '4U G5', 'color' => 'Red/Black', 'size' => '4U', 'price' => 3490000, 'stock' => 6],
                ],
            ],
            [
                'category_id' => 10, 'brand_id' => 3,
                'name' => 'Vợt cầu lông Li-Ning Turbocharging 75D', 'slug' => 'vot-li-ning-turbocharging-75d',
                'short_description' => 'Vợt dành cho người mới chơi, nhẹ tay, dễ điều khiển, giá tốt.',
                'min_price' => 1290000, 'max_price' => 1290000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'LN-TC75D-4U', 'variant_name' => '4U G5', 'color' => 'White/Blue', 'size' => '4U', 'price' => 1290000, 'stock' => 25],
                ],
            ],
            [
                'category_id' => 10, 'brand_id' => 3,
                'name' => 'Vợt cầu lông Li-Ning Windstorm 72', 'slug' => 'vot-li-ning-windstorm-72',
                'short_description' => 'Vợt siêu nhẹ 72g dòng Windstorm, cảm giác cầm như không, vung nhanh.',
                'min_price' => 1890000, 'max_price' => 1890000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'LN-WS72-5U', 'variant_name' => '5U G6', 'color' => 'Cyan', 'size' => '5U', 'price' => 1890000, 'stock' => 18],
                ],
            ],

            // === GIÀY THÊM (4 đôi) ===
            [
                'category_id' => 6, 'brand_id' => 1,
                'name' => 'Giày cầu lông Yonex Aerus Z2', 'slug' => 'giay-yonex-aerus-z2',
                'short_description' => 'Giày siêu nhẹ Yonex Aerus Z2, chỉ 260g, đệm Power Cushion Plus, bám sân cực tốt.',
                'min_price' => 3390000, 'max_price' => 3590000, 'is_featured' => true,
                'variants' => [
                    ['sku' => 'YNX-AZ2-41M', 'variant_name' => '41 - Mint', 'color' => 'Mint', 'size' => '41', 'price' => 3390000, 'stock' => 7],
                    ['sku' => 'YNX-AZ2-42M', 'variant_name' => '42 - Mint', 'color' => 'Mint', 'size' => '42', 'price' => 3390000, 'stock' => 9],
                    ['sku' => 'YNX-AZ2-43B', 'variant_name' => '43 - Black', 'color' => 'Black', 'size' => '43', 'price' => 3590000, 'stock' => 5],
                ],
            ],
            [
                'category_id' => 6, 'brand_id' => 3,
                'name' => 'Giày cầu lông Li-Ning Saga II Pro', 'slug' => 'giay-li-ning-saga-ii-pro',
                'short_description' => 'Giày thi đấu Li-Ning Saga II Pro, đế carbon chống xoắn, hỗ trợ bước chân.',
                'min_price' => 2190000, 'max_price' => 2190000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'LN-SAGA2P-42', 'variant_name' => '42 - Trắng/Đỏ', 'color' => 'White/Red', 'size' => '42', 'price' => 2190000, 'stock' => 12],
                    ['sku' => 'LN-SAGA2P-43', 'variant_name' => '43 - Trắng/Đỏ', 'color' => 'White/Red', 'size' => '43', 'price' => 2190000, 'stock' => 10],
                ],
            ],
            [
                'category_id' => 7, 'brand_id' => 4,
                'name' => 'Giày cầu lông Victor A922 LTD', 'slug' => 'giay-victor-a922-ltd',
                'short_description' => 'Giày giới hạn Victor A922, đệm ENERGYMAX, ổn định bàn chân khi di chuyển ngang.',
                'min_price' => 1990000, 'max_price' => 1990000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'VIC-A922-42W', 'variant_name' => '42 - Trắng', 'color' => 'Trắng', 'size' => '42', 'price' => 1990000, 'stock' => 8],
                    ['sku' => 'VIC-A922-43W', 'variant_name' => '43 - Trắng', 'color' => 'Trắng', 'size' => '43', 'price' => 1990000, 'stock' => 6],
                ],
            ],
            [
                'category_id' => 7, 'brand_id' => 1,
                'name' => 'Giày cầu lông Yonex SHB Comfort Z3', 'slug' => 'giay-yonex-shb-comfort-z3',
                'short_description' => 'Giày Yonex Comfort Z3, form rộng thoải mái, phù hợp bàn chân người Việt.',
                'min_price' => 2590000, 'max_price' => 2790000, 'is_featured' => true,
                'variants' => [
                    ['sku' => 'YNX-CZ3-41N', 'variant_name' => '41 - Navy', 'color' => 'Navy', 'size' => '41', 'price' => 2590000, 'stock' => 10],
                    ['sku' => 'YNX-CZ3-42N', 'variant_name' => '42 - Navy', 'color' => 'Navy', 'size' => '42', 'price' => 2590000, 'stock' => 12],
                    ['sku' => 'YNX-CZ3-44R', 'variant_name' => '44 - Đỏ', 'color' => 'Red', 'size' => '44', 'price' => 2790000, 'stock' => 4],
                ],
            ],

            // === QUẦN ÁO THÊM (6 cái) ===
            [
                'category_id' => 11, 'brand_id' => 1,
                'name' => 'Áo cầu lông Yonex 10566 Game Shirt', 'slug' => 'ao-yonex-10566-game',
                'short_description' => 'Áo thi đấu Yonex 10566, công nghệ Very Cool Dry giữ mát cơ thể suốt trận.',
                'min_price' => 690000, 'max_price' => 690000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'YNX-10566-MBL', 'variant_name' => 'M - Xanh', 'color' => 'Xanh', 'size' => 'M', 'price' => 690000, 'stock' => 16],
                    ['sku' => 'YNX-10566-LBL', 'variant_name' => 'L - Xanh', 'color' => 'Xanh', 'size' => 'L', 'price' => 690000, 'stock' => 14],
                ],
            ],
            [
                'category_id' => 11, 'brand_id' => 3,
                'name' => 'Áo cầu lông Li-Ning AAYS377 Nam', 'slug' => 'ao-li-ning-aays377',
                'short_description' => 'Áo thể thao Li-Ning nam, chất liệu polyester co giãn, thiết kế năng động.',
                'min_price' => 420000, 'max_price' => 420000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'LN-AAYS377-MO', 'variant_name' => 'M - Cam', 'color' => 'Cam', 'size' => 'M', 'price' => 420000, 'stock' => 28],
                    ['sku' => 'LN-AAYS377-LO', 'variant_name' => 'L - Cam', 'color' => 'Cam', 'size' => 'L', 'price' => 420000, 'stock' => 24],
                    ['sku' => 'LN-AAYS377-XLG', 'variant_name' => 'XL - Xanh lá', 'color' => 'Xanh lá', 'size' => 'XL', 'price' => 420000, 'stock' => 20],
                ],
            ],
            [
                'category_id' => 12, 'brand_id' => 3,
                'name' => 'Quần cầu lông Li-Ning AAPS135', 'slug' => 'quan-li-ning-aaps135',
                'short_description' => 'Quần short Li-Ning nam, vải nhẹ thoáng, túi hai bên có khoá kéo.',
                'min_price' => 320000, 'max_price' => 320000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'LN-AAPS135-MB', 'variant_name' => 'M - Đen', 'color' => 'Đen', 'size' => 'M', 'price' => 320000, 'stock' => 30],
                    ['sku' => 'LN-AAPS135-LB', 'variant_name' => 'L - Đen', 'color' => 'Đen', 'size' => 'L', 'price' => 320000, 'stock' => 26],
                ],
            ],
            [
                'category_id' => 13, 'brand_id' => 4,
                'name' => 'Váy cầu lông Victor K-31300 Nữ', 'slug' => 'vay-victor-k31300',
                'short_description' => 'Váy thể thao Victor nữ, có quần lót tích hợp, co giãn thoải mái.',
                'min_price' => 460000, 'max_price' => 460000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'VIC-K31300-SW', 'variant_name' => 'S - Trắng', 'color' => 'Trắng', 'size' => 'S', 'price' => 460000, 'stock' => 14],
                    ['sku' => 'VIC-K31300-MN', 'variant_name' => 'M - Navy', 'color' => 'Navy', 'size' => 'M', 'price' => 460000, 'stock' => 12],
                ],
            ],

            // === PHỤ KIỆN THÊM (6 cái) ===
            [
                'category_id' => 14, 'brand_id' => 3,
                'name' => 'Túi vợt cầu lông Li-Ning ABJT015 9 ngăn', 'slug' => 'tui-li-ning-abjt015',
                'short_description' => 'Túi vợt 9 ngăn Li-Ning siêu rộng, ngăn cách nhiệt bảo vệ vợt, có ngăn giày riêng.',
                'min_price' => 2290000, 'max_price' => 2290000, 'is_featured' => true,
                'variants' => [
                    ['sku' => 'LN-ABJT015-RD', 'variant_name' => 'Đỏ/Đen', 'color' => 'Red/Black', 'size' => null, 'price' => 2290000, 'stock' => 6],
                ],
            ],
            [
                'category_id' => 15, 'brand_id' => 1,
                'name' => 'Cuốn cán Yonex AC108EX Towel Grip', 'slug' => 'cuon-can-yonex-ac108ex-towel',
                'short_description' => 'Cuốn cán dạng vải khăn bông Yonex, hút mồ hôi tuyệt đối, cảm giác dày dặn.',
                'min_price' => 95000, 'max_price' => 95000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'YNX-AC108-Y', 'variant_name' => 'Vàng', 'color' => 'Vàng', 'size' => null, 'price' => 95000, 'stock' => 70],
                    ['sku' => 'YNX-AC108-R', 'variant_name' => 'Đỏ', 'color' => 'Đỏ', 'size' => null, 'price' => 95000, 'stock' => 65],
                ],
            ],
            [
                'category_id' => 16, 'brand_id' => 4,
                'name' => 'Quả cầu lông Victor Master Ace (ống 12 quả)', 'slug' => 'cau-victor-master-ace',
                'short_description' => 'Cầu lông lông vũ Victor Master Ace, tốc độ bay ổn định, bền bỉ, giá cạnh tranh.',
                'min_price' => 420000, 'max_price' => 420000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'VIC-MA-77', 'variant_name' => 'Tốc độ 77', 'color' => null, 'size' => '77', 'price' => 420000, 'stock' => 45],
                ],
            ],
            [
                'category_id' => 16, 'brand_id' => 1,
                'name' => 'Quả cầu lông Yonex Mavis 350 nhựa (ống 6 quả)', 'slug' => 'cau-yonex-mavis-350',
                'short_description' => 'Cầu lông nhựa Yonex Mavis 350, bền hơn cầu lông vũ 5 lần, phù hợp tập luyện.',
                'min_price' => 180000, 'max_price' => 180000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'YNX-MV350-G', 'variant_name' => 'Xanh lá (Chậm)', 'color' => 'Green', 'size' => null, 'price' => 180000, 'stock' => 55],
                    ['sku' => 'YNX-MV350-R', 'variant_name' => 'Đỏ (Trung bình)', 'color' => 'Red', 'size' => null, 'price' => 180000, 'stock' => 60],
                ],
            ],
            [
                'category_id' => 15, 'brand_id' => 3,
                'name' => 'Cuốn cán Li-Ning GP1000 (hộp 10 cuốn)', 'slug' => 'cuon-can-li-ning-gp1000',
                'short_description' => 'Cuốn cán PU Li-Ning GP1000, mỏng nhẹ, bám tay, siêu tiết kiệm khi mua nguyên hộp.',
                'min_price' => 190000, 'max_price' => 190000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'LN-GP1000-10W', 'variant_name' => 'Trắng (10 cuốn)', 'color' => 'Trắng', 'size' => null, 'price' => 190000, 'stock' => 40],
                ],
            ],
            [
                'category_id' => 14, 'brand_id' => 1,
                'name' => 'Balo cầu lông Yonex BA82212T Active', 'slug' => 'balo-yonex-ba82212t',
                'short_description' => 'Balo Yonex Active, chứa được 2 vợt, ngăn đựng giày riêng, chống thấm nước.',
                'min_price' => 990000, 'max_price' => 990000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'YNX-BA82212T-BK', 'variant_name' => 'Đen', 'color' => 'Black', 'size' => null, 'price' => 990000, 'stock' => 15],
                    ['sku' => 'YNX-BA82212T-NV', 'variant_name' => 'Navy', 'color' => 'Navy', 'size' => null, 'price' => 990000, 'stock' => 10],
                ],
            ],
        ];

        foreach ($products as $productData) {
            $variants = $productData['variants'];
            unset($productData['variants']);

            $productId = DB::table('products')->insertGetId(array_merge($productData, [
                'product_type' => count($variants) > 1 ? 'variant' : 'simple',
                'status' => 'active',
                'rating_avg' => round(mt_rand(35, 50) / 10, 1),
                'rating_count' => mt_rand(10, 300),
                'view_count' => mt_rand(50, 5000),
                'sold_count' => mt_rand(5, 500),
                'published_at' => $now,
                'thumbnail_url' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            foreach ($variants as $variant) {
                DB::table('product_variants')->insert(array_merge($variant, [
                    'product_id' => $productId,
                    'cost_price' => round($variant['price'] * 0.6),
                    'compare_at_price' => round($variant['price'] * 1.2),
                    'reserved_stock' => 0,
                    'safety_stock' => 3,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        echo '✅ Seeded thêm: '.count($products)." sản phẩm mới\n";
    }
}
