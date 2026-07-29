<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoryProductSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // ========== 1. THÊM DANH MỤC CON ==========
        // Vợt (parent_id = 2)
        $votYonexId = DB::table('categories')->insertGetId([
            'parent_id' => 2, 'name' => 'Vợt Yonex', 'slug' => 'vot-yonex',
            'description' => 'Vợt cầu lông Yonex chính hãng', 'sort_order' => 1, 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $votVictorId = DB::table('categories')->insertGetId([
            'parent_id' => 2, 'name' => 'Vợt Victor', 'slug' => 'vot-victor',
            'description' => 'Vợt cầu lông Victor chính hãng', 'sort_order' => 2, 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $votLiningId = DB::table('categories')->insertGetId([
            'parent_id' => 2, 'name' => 'Vợt Li-Ning', 'slug' => 'vot-li-ning',
            'description' => 'Vợt cầu lông Li-Ning chính hãng', 'sort_order' => 3, 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // Quần áo (parent_id = 3)
        $aoId = DB::table('categories')->insertGetId([
            'parent_id' => 3, 'name' => 'Áo cầu lông', 'slug' => 'ao-cau-long',
            'description' => 'Áo thể thao cầu lông', 'sort_order' => 1, 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $quanId = DB::table('categories')->insertGetId([
            'parent_id' => 3, 'name' => 'Quần cầu lông', 'slug' => 'quan-cau-long',
            'description' => 'Quần thể thao cầu lông', 'sort_order' => 2, 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $vatId = DB::table('categories')->insertGetId([
            'parent_id' => 3, 'name' => 'Váy cầu lông', 'slug' => 'vay-cau-long',
            'description' => 'Váy thể thao cầu lông nữ', 'sort_order' => 3, 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // Phụ kiện (parent_id = 4)
        $baloId = DB::table('categories')->insertGetId([
            'parent_id' => 4, 'name' => 'Balo & Túi', 'slug' => 'balo-tui',
            'description' => 'Balo, túi đựng vợt cầu lông', 'sort_order' => 1, 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $gripId = DB::table('categories')->insertGetId([
            'parent_id' => 4, 'name' => 'Grip & Cuốn cán', 'slug' => 'grip-cuon-can',
            'description' => 'Cuốn cán, grip vợt cầu lông', 'sort_order' => 2, 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $cauId = DB::table('categories')->insertGetId([
            'parent_id' => 4, 'name' => 'Cầu lông', 'slug' => 'cau-long-qua-cau',
            'description' => 'Quả cầu lông các loại', 'sort_order' => 3, 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // ========== 2. THÊM SẢN PHẨM ==========
        // Brand IDs: Yonex=1, Mizuno=2, Li-Ning=3, Victor=4
        $products = [
            // --- VỢT ---
            [
                'category_id' => $votYonexId, 'brand_id' => 1,
                'name' => 'Vợt cầu lông Yonex Astrox 99 Pro', 'slug' => 'vot-yonex-astrox-99-pro',
                'short_description' => 'Vợt tấn công đỉnh cao với công nghệ Rotational Generator System, dành cho người chơi chuyên nghiệp.',
                'min_price' => 3890000, 'max_price' => 3890000, 'is_featured' => true,
                'variants' => [
                    ['sku' => 'YNX-AX99P-4UG5', 'variant_name' => '4U G5', 'color' => 'Cherry Sunburst', 'size' => '4U', 'price' => 3890000, 'stock' => 15],
                ],
            ],
            [
                'category_id' => $votYonexId, 'brand_id' => 1,
                'name' => 'Vợt cầu lông Yonex Nanoflare 800', 'slug' => 'vot-yonex-nanoflare-800',
                'short_description' => 'Vợt tốc độ siêu nhẹ với khung Torayca M40X, phù hợp lối đánh phòng thủ phản công.',
                'min_price' => 3290000, 'max_price' => 3290000, 'is_featured' => true,
                'variants' => [
                    ['sku' => 'YNX-NF800-4UG5', 'variant_name' => '4U G5', 'color' => 'Matte Blue', 'size' => '4U', 'price' => 3290000, 'stock' => 12],
                ],
            ],
            [
                'category_id' => $votYonexId, 'brand_id' => 1,
                'name' => 'Vợt cầu lông Yonex Arcsaber 11 Pro', 'slug' => 'vot-yonex-arcsaber-11-pro',
                'short_description' => 'Vợt toàn diện cân bằng tấn công và phòng thủ, công nghệ Pocketing Booster.',
                'min_price' => 3590000, 'max_price' => 3590000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'YNX-ARC11P-3UG5', 'variant_name' => '3U G5', 'color' => 'Silver/Black', 'size' => '3U', 'price' => 3590000, 'stock' => 8],
                ],
            ],
            [
                'category_id' => $votVictorId, 'brand_id' => 4,
                'name' => 'Vợt cầu lông Victor Thruster K Falcon', 'slug' => 'vot-victor-thruster-k-falcon',
                'short_description' => 'Vợt tấn công nặng đầu với công nghệ FREE CORE, cho cú đập sấm sét.',
                'min_price' => 2890000, 'max_price' => 2890000, 'is_featured' => true,
                'variants' => [
                    ['sku' => 'VIC-TKF-4UG5', 'variant_name' => '4U G5', 'color' => 'Black/Gold', 'size' => '4U', 'price' => 2890000, 'stock' => 10],
                ],
            ],
            [
                'category_id' => $votVictorId, 'brand_id' => 4,
                'name' => 'Vợt cầu lông Victor Auraspeed 90S', 'slug' => 'vot-victor-auraspeed-90s',
                'short_description' => 'Vợt tốc độ với khung AERO-SWORD, thích hợp cho đánh đôi tốc chiến.',
                'min_price' => 2690000, 'max_price' => 2690000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'VIC-AS90S-4UG5', 'variant_name' => '4U G5', 'color' => 'White/Red', 'size' => '4U', 'price' => 2690000, 'stock' => 14],
                ],
            ],
            [
                'category_id' => $votLiningId, 'brand_id' => 3,
                'name' => 'Vợt cầu lông Li-Ning Axforce 80', 'slug' => 'vot-li-ning-axforce-80',
                'short_description' => 'Vợt tấn công mạnh mẽ với thân vợt cứng, dùng bởi nhiều tay vợt chuyên nghiệp.',
                'min_price' => 3190000, 'max_price' => 3190000, 'is_featured' => true,
                'variants' => [
                    ['sku' => 'LN-AXF80-4UG5', 'variant_name' => '4U G5', 'color' => 'Black/Green', 'size' => '4U', 'price' => 3190000, 'stock' => 9],
                ],
            ],
            [
                'category_id' => $votLiningId, 'brand_id' => 3,
                'name' => 'Vợt cầu lông Li-Ning Bladex 900 Moon', 'slug' => 'vot-li-ning-bladex-900-moon',
                'short_description' => 'Vợt toàn diện dòng Bladex, cân bằng lực đập và tốc độ vung vợt.',
                'min_price' => 2990000, 'max_price' => 2990000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'LN-BX900M-4UG5', 'variant_name' => '4U G5', 'color' => 'Purple/Silver', 'size' => '4U', 'price' => 2990000, 'stock' => 11],
                ],
            ],

            // --- GIÀY (category 6=nike, 7=adidas) ---
            [
                'category_id' => 6, 'brand_id' => 1,
                'name' => 'Giày cầu lông Yonex Power Cushion 65Z3', 'slug' => 'giay-yonex-65z3',
                'short_description' => 'Giày cầu lông cao cấp với đệm Power Cushion+, bám sân tuyệt vời.',
                'min_price' => 2790000, 'max_price' => 3190000, 'is_featured' => true,
                'variants' => [
                    ['sku' => 'YNX-65Z3-41W', 'variant_name' => '41 - Trắng', 'color' => 'White', 'size' => '41', 'price' => 2790000, 'stock' => 6],
                    ['sku' => 'YNX-65Z3-42W', 'variant_name' => '42 - Trắng', 'color' => 'White', 'size' => '42', 'price' => 2790000, 'stock' => 8],
                    ['sku' => 'YNX-65Z3-43B', 'variant_name' => '43 - Đen', 'color' => 'Black/Red', 'size' => '43', 'price' => 3190000, 'stock' => 5],
                ],
            ],
            [
                'category_id' => 7, 'brand_id' => 2,
                'name' => 'Giày cầu lông Mizuno Wave Fang Pro', 'slug' => 'giay-mizuno-wave-fang-pro',
                'short_description' => 'Giày cầu lông Mizuno với công nghệ Wave Plate, hỗ trợ di chuyển nhanh nhẹn.',
                'min_price' => 2490000, 'max_price' => 2490000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'MZN-WFP-42W', 'variant_name' => '42 - Trắng/Xanh', 'color' => 'White/Blue', 'size' => '42', 'price' => 2490000, 'stock' => 10],
                    ['sku' => 'MZN-WFP-43W', 'variant_name' => '43 - Trắng/Xanh', 'color' => 'White/Blue', 'size' => '43', 'price' => 2490000, 'stock' => 7],
                ],
            ],

            // --- ÁO CẦU LÔNG ---
            [
                'category_id' => $aoId, 'brand_id' => 1,
                'name' => 'Áo cầu lông Yonex 10512 Tournament', 'slug' => 'ao-yonex-10512-tournament',
                'short_description' => 'Áo thi đấu Yonex chính hãng, chất liệu thoáng mát, thấm hút mồ hôi nhanh.',
                'min_price' => 590000, 'max_price' => 590000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'YNX-10512-MW', 'variant_name' => 'M - Trắng', 'color' => 'Trắng', 'size' => 'M', 'price' => 590000, 'stock' => 20],
                    ['sku' => 'YNX-10512-LW', 'variant_name' => 'L - Trắng', 'color' => 'Trắng', 'size' => 'L', 'price' => 590000, 'stock' => 18],
                    ['sku' => 'YNX-10512-XLB', 'variant_name' => 'XL - Xanh', 'color' => 'Xanh Navy', 'size' => 'XL', 'price' => 590000, 'stock' => 15],
                ],
            ],
            [
                'category_id' => $aoId, 'brand_id' => 3,
                'name' => 'Áo cầu lông Li-Ning AAYT023', 'slug' => 'ao-li-ning-aayt023',
                'short_description' => 'Áo thi đấu Li-Ning AT DRY, thoáng khí giữ cơ thể luôn khô ráo.',
                'min_price' => 490000, 'max_price' => 490000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'LN-AAYT023-MR', 'variant_name' => 'M - Đỏ', 'color' => 'Đỏ', 'size' => 'M', 'price' => 490000, 'stock' => 25],
                    ['sku' => 'LN-AAYT023-LR', 'variant_name' => 'L - Đỏ', 'color' => 'Đỏ', 'size' => 'L', 'price' => 490000, 'stock' => 22],
                ],
            ],
            [
                'category_id' => $aoId, 'brand_id' => 4,
                'name' => 'Áo cầu lông Victor T-30008', 'slug' => 'ao-victor-t30008',
                'short_description' => 'Áo thể thao Victor chất liệu Eco Fiber, thân thiện môi trường.',
                'min_price' => 450000, 'max_price' => 450000, 'is_featured' => true,
                'variants' => [
                    ['sku' => 'VIC-T30008-MB', 'variant_name' => 'M - Đen', 'color' => 'Đen', 'size' => 'M', 'price' => 450000, 'stock' => 30],
                    ['sku' => 'VIC-T30008-LB', 'variant_name' => 'L - Đen', 'color' => 'Đen', 'size' => 'L', 'price' => 450000, 'stock' => 28],
                    ['sku' => 'VIC-T30008-XLW', 'variant_name' => 'XL - Trắng', 'color' => 'Trắng', 'size' => 'XL', 'price' => 450000, 'stock' => 20],
                ],
            ],

            // --- QUẦN CẦU LÔNG ---
            [
                'category_id' => $quanId, 'brand_id' => 1,
                'name' => 'Quần cầu lông Yonex 15105 Short', 'slug' => 'quan-yonex-15105-short',
                'short_description' => 'Quần ngắn thể thao Yonex, co giãn 4 chiều, siêu nhẹ cho di chuyển.',
                'min_price' => 390000, 'max_price' => 390000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'YNX-15105-MB', 'variant_name' => 'M - Đen', 'color' => 'Đen', 'size' => 'M', 'price' => 390000, 'stock' => 35],
                    ['sku' => 'YNX-15105-LB', 'variant_name' => 'L - Đen', 'color' => 'Đen', 'size' => 'L', 'price' => 390000, 'stock' => 30],
                ],
            ],
            [
                'category_id' => $quanId, 'brand_id' => 4,
                'name' => 'Quần cầu lông Victor R-30202', 'slug' => 'quan-victor-r30202',
                'short_description' => 'Quần short Victor cạp chun, túi khoá kéo tiện lợi.',
                'min_price' => 350000, 'max_price' => 350000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'VIC-R30202-MN', 'variant_name' => 'M - Navy', 'color' => 'Navy', 'size' => 'M', 'price' => 350000, 'stock' => 22],
                    ['sku' => 'VIC-R30202-LN', 'variant_name' => 'L - Navy', 'color' => 'Navy', 'size' => 'L', 'price' => 350000, 'stock' => 20],
                ],
            ],

            // --- VÁY CẦU LÔNG ---
            [
                'category_id' => $vatId, 'brand_id' => 1,
                'name' => 'Váy cầu lông Yonex 26101 Skort', 'slug' => 'vay-yonex-26101-skort',
                'short_description' => 'Váy thể thao nữ Yonex có quần lót bên trong, cool dry thoáng mát.',
                'min_price' => 490000, 'max_price' => 490000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'YNX-26101-SW', 'variant_name' => 'S - Trắng', 'color' => 'Trắng', 'size' => 'S', 'price' => 490000, 'stock' => 15],
                    ['sku' => 'YNX-26101-MP', 'variant_name' => 'M - Hồng', 'color' => 'Hồng', 'size' => 'M', 'price' => 490000, 'stock' => 12],
                ],
            ],

            // --- BALO & TÚI ---
            [
                'category_id' => $baloId, 'brand_id' => 1,
                'name' => 'Túi vợt cầu lông Yonex BAG2226 6 ngăn', 'slug' => 'tui-yonex-bag2226',
                'short_description' => 'Túi vợt 6 ngăn Yonex, ngăn giày riêng, chống nóng bảo vệ dây căng.',
                'min_price' => 1890000, 'max_price' => 1890000, 'is_featured' => true,
                'variants' => [
                    ['sku' => 'YNX-BAG2226-B', 'variant_name' => 'Đen/Vàng', 'color' => 'Black/Yellow', 'size' => null, 'price' => 1890000, 'stock' => 8],
                ],
            ],
            [
                'category_id' => $baloId, 'brand_id' => 4,
                'name' => 'Balo cầu lông Victor BR9611', 'slug' => 'balo-victor-br9611',
                'short_description' => 'Balo thể thao Victor chống thấm, tích hợp ngăn laptop.',
                'min_price' => 1290000, 'max_price' => 1290000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'VIC-BR9611-CB', 'variant_name' => 'Xanh Coban', 'color' => 'Coban Blue', 'size' => null, 'price' => 1290000, 'stock' => 12],
                ],
            ],

            // --- GRIP ---
            [
                'category_id' => $gripId, 'brand_id' => 1,
                'name' => 'Cuốn cán vợt Yonex AC102EX Super Grap (3 cuốn)', 'slug' => 'cuon-can-yonex-ac102ex-3',
                'short_description' => 'Cuốn cán vợt bán chạy nhất thế giới, chống trơn tuyệt đối.',
                'min_price' => 85000, 'max_price' => 85000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'YNX-AC102-3W', 'variant_name' => 'Trắng', 'color' => 'Trắng', 'size' => null, 'price' => 85000, 'stock' => 100],
                    ['sku' => 'YNX-AC102-3B', 'variant_name' => 'Đen', 'color' => 'Đen', 'size' => null, 'price' => 85000, 'stock' => 80],
                    ['sku' => 'YNX-AC102-3Y', 'variant_name' => 'Vàng', 'color' => 'Vàng', 'size' => null, 'price' => 85000, 'stock' => 60],
                ],
            ],
            [
                'category_id' => $gripId, 'brand_id' => 4,
                'name' => 'Cuốn cán vợt Victor GR262 (hộp 5 cuốn)', 'slug' => 'cuon-can-victor-gr262',
                'short_description' => 'Cuốn cán Victor PU mềm, hút mồ hôi tốt.',
                'min_price' => 120000, 'max_price' => 120000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'VIC-GR262-5W', 'variant_name' => 'Trắng', 'color' => 'Trắng', 'size' => null, 'price' => 120000, 'stock' => 50],
                ],
            ],

            // --- CẦU LÔNG ---
            [
                'category_id' => $cauId, 'brand_id' => 1,
                'name' => 'Quả cầu lông Yonex AS50 (ống 12 quả)', 'slug' => 'cau-yonex-as50',
                'short_description' => 'Cầu lông lông vũ cao cấp Yonex AS50, tiêu chuẩn thi đấu quốc tế.',
                'min_price' => 690000, 'max_price' => 690000, 'is_featured' => true,
                'variants' => [
                    ['sku' => 'YNX-AS50-77', 'variant_name' => 'Tốc độ 77', 'color' => null, 'size' => '77', 'price' => 690000, 'stock' => 40],
                    ['sku' => 'YNX-AS50-78', 'variant_name' => 'Tốc độ 78', 'color' => null, 'size' => '78', 'price' => 690000, 'stock' => 35],
                ],
            ],
            [
                'category_id' => $cauId, 'brand_id' => 3,
                'name' => 'Quả cầu lông Li-Ning A+300 (ống 12 quả)', 'slug' => 'cau-li-ning-a300',
                'short_description' => 'Cầu lông Li-Ning A+300, lông vũ bền, bay ổn định, giá tốt.',
                'min_price' => 390000, 'max_price' => 390000, 'is_featured' => false,
                'variants' => [
                    ['sku' => 'LN-APLUS300-77', 'variant_name' => 'Tốc độ 77', 'color' => null, 'size' => '77', 'price' => 390000, 'stock' => 60],
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

        echo '✅ Seeded: '.count($products)." sản phẩm + danh mục con cho Vợt, Quần áo, Phụ kiện\n";
    }
}
