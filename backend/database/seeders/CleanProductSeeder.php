<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CleanProductSeeder extends Seeder
{
    private string $now;

    /* ──────────────── MAIN ──────────────── */
    public function run(): void
    {
        $this->now = Carbon::now()->toDateTimeString();

        $this->cleanup();
        $this->seedBrands();
        $catMap = $this->seedCategories();
        $this->seedProducts($catMap);

        echo "✅ CleanProductSeeder hoàn tất: 7 brands, 25 categories, 100 sản phẩm\n";
    }

    /* ──────────────── CLEANUP ──────────────── */
    private function cleanup(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Clear related data that references products
        DB::table('cart_items')->truncate();
        DB::table('favorites')->truncate();
        DB::table('product_comments')->truncate();

        // Clear flash sale items if table exists
        try { DB::table('flash_sale_items')->truncate(); } catch (\Throwable $e) {}

        // Core product tables
        DB::table('product_images')->truncate();
        DB::table('product_variants')->truncate();
        DB::table('products')->truncate();
        DB::table('categories')->truncate();
        DB::table('brands')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        echo "🧹 Đã xóa sạch dữ liệu cũ\n";
    }

    /* ──────────────── BRANDS ──────────────── */
    private function seedBrands(): void
    {
        $brands = [
            ['name' => 'Nike',        'slug' => 'nike',        'description' => 'Thương hiệu thể thao hàng đầu thế giới'],
            ['name' => 'Adidas',      'slug' => 'adidas',      'description' => 'Thời trang và thể thao từ Đức'],
            ['name' => 'Uniqlo',      'slug' => 'uniqlo',      'description' => 'Thời trang cơ bản chất lượng Nhật Bản'],
            ['name' => 'Zara',        'slug' => 'zara',        'description' => 'Thời trang nhanh Tây Ban Nha'],
            ['name' => 'H&M',         'slug' => 'h-m',         'description' => 'Thời trang bình dân Thụy Điển'],
            ['name' => 'Gucci',       'slug' => 'gucci',       'description' => 'Thời trang cao cấp Ý'],
            ['name' => 'Local Brand', 'slug' => 'local-brand', 'description' => 'Thương hiệu Việt Nam chất lượng'],
        ];

        foreach ($brands as $b) {
            DB::table('brands')->insert(array_merge($b, [
                'is_active'  => 1,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]));
        }
    }

    /* ──────────────── CATEGORIES ──────────────── */
    private function seedCategories(): array
    {
        $tree = [
            'Thời trang Nam' => [
                'desc' => 'Bộ sưu tập thời trang nam phong cách, lịch lãm',
                'children' => [
                    'Áo thun nam'    => 'Áo thun nam đa dạng kiểu dáng',
                    'Áo sơ mi nam'   => 'Áo sơ mi nam thanh lịch, công sở',
                    'Áo polo nam'    => 'Áo polo nam thể thao, lịch sự',
                    'Áo khoác nam'   => 'Áo khoác nam giữ ấm, phong cách',
                    'Quần jeans nam' => 'Quần jeans nam form đẹp, bền bỉ',
                    'Quần tây nam'   => 'Quần tây nam công sở chuẩn form',
                    'Quần short nam' => 'Quần short nam thoáng mát năng động',
                ],
            ],
            'Thời trang Nữ' => [
                'desc' => 'Thời trang nữ nổi bật, thanh lịch và quyến rũ',
                'children' => [
                    'Áo thun nữ'    => 'Áo thun nữ trẻ trung, phong cách',
                    'Áo kiểu nữ'    => 'Áo kiểu nữ thanh lịch, nữ tính',
                    'Đầm & Váy'     => 'Đầm váy nữ đa phong cách',
                    'Quần jeans nữ' => 'Quần jeans nữ tôn dáng',
                    'Quần tây nữ'   => 'Quần tây nữ công sở thanh lịch',
                ],
            ],
            'Giày dép' => [
                'desc' => 'Giày dép đa dạng phong cách, chất lượng cao',
                'children' => [
                    'Giày sneaker'    => 'Giày sneaker thể thao thời thượng',
                    'Giày tây & lười' => 'Giày tây, giày lười nam lịch lãm',
                    'Dép & Sandal'    => 'Dép sandal thoải mái, mùa hè',
                ],
            ],
            'Phụ kiện' => [
                'desc' => 'Phụ kiện thời trang hoàn thiện outfit',
                'children' => [
                    'Túi xách & Balo' => 'Túi xách, balo thời trang tiện dụng',
                    'Thắt lưng'       => 'Thắt lưng da cao cấp các kiểu',
                    'Ví & Bóp'        => 'Ví da nam nữ đẳng cấp',
                ],
            ],
            'Trang sức & Đồng hồ' => [
                'desc' => 'Đồng hồ, mắt kính nâng tầm phong cách',
                'children' => [
                    'Đồng hồ'  => 'Đồng hồ thời trang nam nữ',
                    'Mắt kính' => 'Kính mát thời trang chống UV',
                ],
            ],
        ];

        // Category → first product image mapping for category thumbnails
        $catImageMap = [
            'ao-thun-nam'     => 'products/unique/p001_thun_nam_trang.png',
            'ao-so-mi-nam'    => 'products/unique/p007_somi_trang_slim.jpg',
            'ao-polo-nam'     => 'products/unique/p012_polo_den_classic.jpg',
            'ao-khoac-nam'    => 'products/unique/p016_khoac_bomber_xanhreu.jpg',
            'quan-jeans-nam'  => 'products/unique/p021_jeans_nam_slim_xanhden.jpg',
            'quan-tay-nam'    => 'products/unique/p026_quantay_den_slim.png',
            'quan-short-nam'  => 'products/unique/p030_short_kaki_be.png',
            'ao-thun-nu'      => 'products/unique/p034_thun_nu_croptop.png',
            'ao-kieu-nu'      => 'products/unique/p040_kieu_nu_tayphong.jpg',
            'dam-vay'         => 'products/unique/p045_dam_midi_hoa.jpg',
            'quan-jeans-nu'   => 'products/unique/p051_jeans_nu_ongrong.jpg',
            'quan-tay-nu'     => 'products/unique/p055_quantay_nu_suong.jpg',
            'giay-sneaker'    => 'products/unique/p060_sneaker_trang_air.jpg',
            'giay-tay-luoi'   => 'products/unique/p068_giay_luoi_da_nau.jpg',
            'dep-sandal'      => 'products/unique/p073_dep_quaingang_den.jpg',
            'tui-xach-balo'   => 'products/unique/p078_balo_laptop_den.jpg',
            'that-lung'       => 'products/unique/p084_thatlang_da_den_auto.jpg',
            'vi-bop'          => 'products/unique/p088_vi_nam_da_nau.jpg',
            'dong-ho'         => 'products/unique/p092_dongho_nam_thep_bac.jpg',
            'mat-kinh'        => 'products/unique/p097_kinh_aviator_xanh.jpg',
        ];

        $catMap = []; // slug => category_id
        $sort = 1;

        foreach ($tree as $parentName => $info) {
            $parentSlug = Str::slug($parentName);
            $parentId = DB::table('categories')->insertGetId([
                'parent_id'   => null,
                'name'        => $parentName,
                'slug'        => $parentSlug,
                'image'       => null,
                'description' => $info['desc'],
                'sort_order'  => $sort++,
                'is_active'   => 1,
                'created_at'  => $this->now,
                'updated_at'  => $this->now,
            ]);

            foreach ($info['children'] as $childName => $childDesc) {
                $childSlug = Str::slug($childName);
                $childId = DB::table('categories')->insertGetId([
                    'parent_id'   => $parentId,
                    'name'        => $childName,
                    'slug'        => $childSlug,
                    'image'       => $catImageMap[$childSlug] ?? null,
                    'description' => $childDesc,
                    'sort_order'  => $sort++,
                    'is_active'   => 1,
                    'created_at'  => $this->now,
                    'updated_at'  => $this->now,
                ]);
                $catMap[$childSlug] = $childId;
            }
        }

        return $catMap;
    }

    /* ──────────────── PRODUCTS ──────────────── */
    private function seedProducts(array $catMap): void
    {
        $allProducts = $this->getProductsData();
        $counter = 0;

        foreach ($allProducts as $catSlug => $products) {
            $categoryId = $catMap[$catSlug] ?? null;
            if (!$categoryId) {
                echo "⚠️ Category not found: {$catSlug}\n";
                continue;
            }

            foreach ($products as $p) {
                $counter++;
                // $p = [name, price, brand_id, short_desc, featured, image_path]
                $slug = Str::slug($p[0]);

                // Ensure unique slug
                if (DB::table('products')->where('slug', $slug)->exists()) {
                    $slug .= '-' . Str::random(4);
                }

                $imagePath = $p[5] ?? null;

                $productId = DB::table('products')->insertGetId([
                    'category_id'       => $categoryId,
                    'brand_id'          => $p[2],
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
                    'rating_avg'        => round(mt_rand(35, 50) / 10, 1),
                    'rating_count'      => mt_rand(10, 500),
                    'view_count'        => mt_rand(100, 8000),
                    'sold_count'        => mt_rand(5, 600),
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

                // ── Images (main = product's unique image) ──
                $this->createImages($productId, $p[0], $imagePath);
            }
        }

        echo "📦 Seeded: {$counter} sản phẩm\n";
    }

    /**
     * Tự động tạo biến thể dựa trên loại danh mục
     */
    private function generateVariants(int $productId, string $slug, int $basePrice, string $catSlug): array
    {
        $config = $this->getVariantConfig($catSlug);
        $variants = [];

        foreach ($config['colors'] as $color) {
            foreach ($config['sizes'] as $size) {
                $variance = mt_rand(-5, 10) * 10000;
                $price = max($basePrice + $variance, 100000);

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

                $barcode = 'OCN' . strtoupper(Str::random(10)) . mt_rand(10, 99);

                DB::table('product_variants')->insert([
                    'product_id'       => $productId,
                    'sku'              => $sku,
                    'barcode'          => $barcode,
                    'variant_name'     => trim(($color ?? '') . ' - ' . ($size ?? ''), ' -'),
                    'color'            => $color,
                    'size'             => $size,
                    'material'         => $config['material'] ?? null,
                    'weight_gram'      => $config['weight'] ?? null,
                    'cost_price'       => round($price * 0.55),
                    'price'            => $price,
                    'compare_at_price' => round($price * 1.3),
                    'stock'            => mt_rand(8, 80),
                    'reserved_stock'   => 0,
                    'safety_stock'     => 3,
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

    /**
     * Cấu hình biến thể theo loại sản phẩm
     */
    private function getVariantConfig(string $catSlug): array
    {
        $clothColors = ['Trắng', 'Đen', 'Xám', 'Navy', 'Be'];
        $clothSizes  = ['S', 'M', 'L', 'XL'];
        $shoeSizes   = ['39', '40', '41', '42', '43'];

        // Áo, quần, đầm váy → 2 màu × 2 size
        if (Str::contains($catSlug, ['ao-', 'quan-', 'dam-'])) {
            shuffle($clothColors);
            shuffle($clothSizes);
            return [
                'colors'   => array_slice($clothColors, 0, 2),
                'sizes'    => array_slice($clothSizes, 0, 2),
                'material' => collect(['Cotton', 'Cotton Pha', 'Polyester', 'Linen', 'Kaki'])->random(),
                'weight'   => mt_rand(150, 400),
            ];
        }

        // Giày, dép → 1 màu × 3 size
        if (Str::contains($catSlug, ['giay-', 'dep-'])) {
            shuffle($shoeSizes);
            return [
                'colors'   => [null],
                'sizes'    => array_slice($shoeSizes, 0, 3),
                'material' => 'Da tổng hợp',
                'weight'   => mt_rand(300, 700),
            ];
        }

        // Phụ kiện → 2 màu, không size
        shuffle($clothColors);
        return [
            'colors'   => array_slice($clothColors, 0, 2),
            'sizes'    => [null],
            'material' => Str::contains($catSlug, ['vi-', 'that-']) ? 'Da bò' : null,
            'weight'   => mt_rand(50, 300),
        ];
    }

    /**
     * Tạo product_images cho sản phẩm — CHỈ dùng ảnh riêng của sản phẩm
     */
    private function createImages(int $productId, string $name, ?string $imagePath): void
    {
        if (!$imagePath) return;

        // Main image
        DB::table('product_images')->insert([
            'product_id' => $productId,
            'variant_id' => null,
            'image_url'  => $imagePath,
            'alt_text'   => $name,
            'is_main'    => 1,
            'sort_order' => 0,
            'created_at' => $this->now,
        ]);
    }

    /**
     * Tạo mô tả HTML cho sản phẩm
     */
    private function buildDescription(string $name, string $catSlug): string
    {
        $careGuide = '<h3>Hướng dẫn bảo quản</h3><ul>'
            . '<li>Giặt máy ở nhiệt độ thường (30°C)</li>'
            . '<li>Không sử dụng chất tẩy mạnh</li>'
            . '<li>Phơi trong bóng râm, tránh ánh nắng trực tiếp</li>'
            . '<li>Ủi ở nhiệt độ thấp nếu cần</li></ul>';

        if (Str::contains($catSlug, ['giay-', 'dep-'])) {
            $careGuide = '<h3>Hướng dẫn bảo quản</h3><ul>'
                . '<li>Lau sạch bằng khăn ẩm sau mỗi lần sử dụng</li>'
                . '<li>Bảo quản nơi khô ráo, thoáng mát</li>'
                . '<li>Sử dụng túi chống ẩm khi cất giữ lâu ngày</li></ul>';
        }

        return '<div class="product-description">'
            . '<h3>Mô tả sản phẩm</h3>'
            . '<p>' . $name . ' – sản phẩm chính hãng tại Ocean Shop. '
            . 'Thiết kế hiện đại, chất liệu cao cấp, phù hợp nhiều phong cách.</p>'
            . '<h3>Đặc điểm nổi bật</h3><ul>'
            . '<li>Chất liệu cao cấp, bền đẹp theo thời gian</li>'
            . '<li>Thiết kế hiện đại, dễ phối đồ</li>'
            . '<li>Form dáng chuẩn, tôn dáng người mặc</li>'
            . '<li>Phù hợp nhiều dịp: công sở, đi chơi, dạo phố</li></ul>'
            . $careGuide
            . '</div>';
    }

    /* ════════════════════════════════════════════════════════════════
     *  100 SẢN PHẨM — [tên, giá, brand_id, mô_tả_ngắn, featured, image_path]
     *  Brand: 1=Nike, 2=Adidas, 3=Uniqlo, 4=Zara, 5=H&M, 6=Gucci, 7=Local
     *  Mỗi sản phẩm có ẢNH RIÊNG trong products/unique/
     * ════════════════════════════════════════════════════════════════ */
    private function getProductsData(): array
    {
        return [
            /* ───────── ÁO THUN NAM (6) ───────── */
            'ao-thun-nam' => [
                ['Áo thun nam cổ tròn cotton trắng basic', 199000, 3, 'Áo thun nam basic cotton 100%, form regular fit thoáng mát, dễ phối đồ hàng ngày.', true, 'products/unique/p001_thun_nam_trang.png'],
                ['Áo thun nam oversize đen streetwear', 249000, 5, 'Áo thun oversize phong cách đường phố, cotton pha spandex co giãn nhẹ.', false, 'products/unique/p002_thun_nam_den_oversize.png'],
                ['Áo thun nam in họa tiết tropical', 279000, 4, 'Áo thun nam in nhiệt hình tropical, chất vải mềm mại, mùa hè năng động.', false, 'products/unique/p003_thun_nam_tropical.png'],
                ['Áo thun nam thể thao dry-fit xám', 349000, 1, 'Áo thun thể thao công nghệ Dry-Fit thấm hút nhanh, thoáng khí khi tập luyện.', true, 'products/unique/p004_thun_nam_dryfit_xam.png'],
                ['Áo thun nam henley tay ngắn kem', 289000, 7, 'Áo thun henley cổ trụ 3 cúc, cotton premium, phong cách casual lịch sự.', false, 'products/unique/p005_thun_nam_henley_kem.png'],
                ['Áo thun nam cổ V xám melange', 219000, 3, 'Áo thun cổ V basic, chất vải cotton mềm mịn, phù hợp mặc hàng ngày.', false, 'products/unique/p006_thun_nam_vneck_xam.png'],
            ],

            /* ───────── ÁO SƠ MI NAM (5) ───────── */
            'ao-so-mi-nam' => [
                ['Áo sơ mi nam slim fit trắng công sở', 399000, 3, 'Sơ mi trắng slim fit vải poplin cao cấp, chuẩn phong cách công sở.', true, 'products/unique/p007_somi_trang_slim.jpg'],
                ['Áo sơ mi nam oxford xanh nhạt', 449000, 4, 'Sơ mi oxford dệt chéo, màu xanh nhạt thanh lịch, mặc cả tuần không chán.', false, 'products/unique/p008_somi_oxford_xanh.jpg'],
                ['Áo sơ mi nam linen tay dài be', 489000, 3, 'Sơ mi linen tự nhiên, thoáng mát mùa hè, form regular fit thoải mái.', false, 'products/unique/p009_somi_linen_be.jpg'],
                ['Áo sơ mi nam flannel kẻ caro đỏ', 429000, 5, 'Sơ mi flannel kẻ caro, chất vải dày dặn ấm áp, phong cách Âu Mỹ.', false, 'products/unique/p010_somi_flannel_caro.jpg'],
                ['Áo sơ mi nam denim wash xanh', 459000, 4, 'Sơ mi denim wash nhẹ, phong cách bụi bặm, dễ kết hợp nhiều outfit.', true, 'products/unique/p011_somi_denim_xanh.jpg'],
            ],

            /* ───────── ÁO POLO NAM (4) ───────── */
            'ao-polo-nam' => [
                ['Áo polo nam classic pique đen', 359000, 7, 'Polo classic chất pique cotton, cổ bẻ cứng cáp, form regular truyền thống.', true, 'products/unique/p012_polo_den_classic.jpg'],
                ['Áo polo nam thể thao viền tương phản', 399000, 2, 'Polo thể thao với viền cổ và tay tương phản, dry-fit nhanh khô.', false, 'products/unique/p013_polo_thethao.jpg'],
                ['Áo polo nam cotton navy', 329000, 3, 'Polo nam cotton premium, màu navy trầm ổn, phù hợp công sở smart casual.', false, 'products/unique/p014_polo_navy.jpg'],
                ['Áo polo nam slim fit trắng sọc', 379000, 4, 'Polo slim fit kẻ sọc mảnh, thiết kế tinh tế cho phong cách lịch lãm.', false, 'products/unique/p015_polo_trang_soc.jpg'],
            ],

            /* ───────── ÁO KHOÁC NAM (5) ───────── */
            'ao-khoac-nam' => [
                ['Áo khoác bomber nam xanh rêu', 699000, 4, 'Bomber jacket classic, chất vải nylon cao cấp, lót bông ấm áp.', true, 'products/unique/p016_khoac_bomber_xanhreu.jpg'],
                ['Áo hoodie nam zip up xám đậm', 549000, 2, 'Hoodie zip up French Terry, mũ trùm kép, túi kangaroo tiện lợi.', true, 'products/unique/p017_hoodie_zipup_xam.jpg'],
                ['Áo khoác gió nam siêu nhẹ đen', 499000, 1, 'Khoác gió ultra-light có thể gập gọn, chống nước nhẹ, trọng lượng chỉ 150g.', false, 'products/unique/p018_khoac_gio_den.jpg'],
                ['Áo khoác denim nam wash medium', 649000, 4, 'Khoác denim trucker fit, wash medium blue, vintage style Americana.', false, 'products/unique/p019_khoac_denim_wash.jpg'],
                ['Áo len nam cổ tròn xanh đậm', 459000, 3, 'Áo len merino blend cổ tròn, dệt kim mịn, lý tưởng cho mùa thu đông.', false, 'products/unique/p020_aolen_xanhtham.jpg'],
            ],

            /* ───────── QUẦN JEANS NAM (5) ───────── */
            'quan-jeans-nam' => [
                ['Quần jeans nam slim fit xanh đen', 499000, 4, 'Jeans slim fit co giãn, wash xanh đen lịch lãm, phù hợp công sở lẫn dạo phố.', true, 'products/unique/p021_jeans_nam_slim_xanhden.jpg'],
                ['Quần jeans nam straight fit medium', 479000, 3, 'Jeans straight fit truyền thống, wash medium vừa phải, bền đẹp.', false, 'products/unique/p022_jeans_nam_straight.jpg'],
                ['Quần jeans nam skinny rách gối đen', 529000, 5, 'Jeans skinny rách gối có độ co giãn cao, phong cách trẻ trung bụi bặm.', false, 'products/unique/p023_jeans_nam_skinny_rach.jpg'],
                ['Quần jeans nam relax fit xanh nhạt', 459000, 3, 'Jeans relax fit thoải mái, wash xanh nhạt summer vibes.', false, 'products/unique/p024_jeans_nam_relax_nhat.jpg'],
                ['Quần jeans nam tapered xám khói', 519000, 4, 'Jeans tapered fit form chuẩn từ hông xuống, wash xám khói hiện đại.', true, 'products/unique/p025_jeans_nam_tapered_xam.jpg'],
            ],

            /* ───────── QUẦN TÂY NAM (4) ───────── */
            'quan-tay-nam' => [
                ['Quần tây nam công sở đen slim fit', 429000, 3, 'Quần tây đen slimfit, vải polyester pha wool, mặc lên đứng form.', true, 'products/unique/p026_quantay_den_slim.png'],
                ['Quần âu nam xám ghi regular fit', 399000, 4, 'Quần âu xám ghi form regular, có ly, phù hợp diện công sở hàng ngày.', false, 'products/unique/p027_quantay_xam_regular.png'],
                ['Quần kaki nam be ống đứng', 379000, 3, 'Quần kaki chino ống đứng, cotton co giãn, smart casual phong cách.', false, 'products/unique/p028_quan_kaki_chino.png'],
                ['Quần tây nam navy wool blend', 549000, 6, 'Quần tây navy cao cấp vải wool blend, dáng slim, cạp sâu sang trọng.', false, 'products/unique/p029_quantay_navy_wool.png'],
            ],

            /* ───────── QUẦN SHORT NAM (4) ───────── */
            'quan-short-nam' => [
                ['Quần short nam kaki be', 289000, 3, 'Quần short kaki ống rộng vừa, cotton thoáng mát, phong cách lịch sự.', false, 'products/unique/p030_short_kaki_be.png'],
                ['Quần short jeans nam xanh wash', 329000, 5, 'Quần short jeans wash nhẹ, gấu xắn casual, phong cách hè năng động.', false, 'products/unique/p031_short_jeans_xanh.png'],
                ['Quần short nam thể thao đen', 249000, 1, 'Quần short thể thao vải mesh thoáng, có túi khóa kéo, dry-fit.', true, 'products/unique/p032_short_thethao_den.png'],
                ['Quần short nam linen trắng', 319000, 3, 'Quần short linen tự nhiên, lưng chun thoải mái, mùa hè mát mẻ.', false, 'products/unique/p033_short_linen_trang.png'],
            ],

            /* ───────── ÁO THUN NỮ (6) ───────── */
            'ao-thun-nu' => [
                ['Áo thun nữ crop top trắng basic', 179000, 5, 'Crop top nữ basic cotton, form ôm vừa, dễ phối với quần cạp cao.', true, 'products/unique/p034_thun_nu_croptop.png'],
                ['Áo thun nữ oversize đen cá tính', 229000, 4, 'Áo thun oversize đen unisex, chất cotton dày dặn, phong cách tomboy.', false, 'products/unique/p035_thun_nu_den_oversize.png'],
                ['Áo thun nữ baby tee hồng pastel', 199000, 5, 'Baby tee ngắn ôm body, màu hồng pastel ngọt ngào, Y2K vibes.', false, 'products/unique/p036_thun_nu_hong_babytee.png'],
                ['Áo thun nữ in vintage graphic xám', 249000, 7, 'Áo thun in graphic vintage retro, wash xám nhẹ, phong cách grunge.', false, 'products/unique/p037_thun_nu_graphic_xam.jpg'],
                ['Áo thun nữ tay dài ôm body be', 219000, 3, 'Áo thun tay dài cotton lycra, form ôm body, lớp base layer hoàn hảo.', true, 'products/unique/p038_thun_nu_taydai_be.jpg'],
                ['Áo thun nữ cổ tròn cotton navy basic', 189000, 3, 'Áo thun nữ cotton cổ tròn, màu navy tối giản, dễ phối mọi outfit.', false, 'products/unique/p039_thun_nu_navy.jpg'],
            ],

            /* ───────── ÁO KIỂU NỮ (5) ───────── */
            'ao-kieu-nu' => [
                ['Áo kiểu nữ tay phồng trắng thêu hoa', 389000, 4, 'Áo kiểu tay phồng thêu hoa nhí, cổ vuông, chất vải poplin thanh lịch.', true, 'products/unique/p040_kieu_nu_tayphong.jpg'],
                ['Áo sơ mi nữ lụa satin hồng', 459000, 4, 'Sơ mi lụa satin bóng, đường may tinh tế, nữ tính và sang trọng.', false, 'products/unique/p041_somi_nu_lua_hong.jpg'],
                ['Áo peplum nữ xanh mint thanh lịch', 349000, 5, 'Áo peplum tôn eo, xanh mint tươi mát, phù hợp công sở và sự kiện.', false, 'products/unique/p042_peplum_xanhmint.jpg'],
                ['Áo kiểu nữ cổ vuông ren kem', 329000, 4, 'Áo kiểu cổ vuông viền ren, chất vải crepe mềm mại, tone kem nhẹ nhàng.', false, 'products/unique/p043_kieu_nu_covuong_kem.jpg'],
                ['Áo blouse nữ chấm bi đen trắng', 369000, 5, 'Blouse cổ nơ họa tiết chấm bi, kinh điển thanh lịch, dễ mặc mọi dịp.', true, 'products/unique/p044_blouse_chambi.jpg'],
            ],

            /* ───────── ĐẦM & VÁY (6) ───────── */
            'dam-vay' => [
                ['Đầm midi nữ hoa nhí vintage', 489000, 4, 'Đầm midi in hoa nhí vintage, eo thắt dây, vải chiffon bay bổng.', true, 'products/unique/p045_dam_midi_hoa.jpg'],
                ['Váy chữ A nữ jeans xanh nhạt', 429000, 5, 'Váy chữ A jeans cạp cao, dáng xòe nhẹ, năng động nhưng vẫn nữ tính.', false, 'products/unique/p046_vay_chuA_jeans.jpg'],
                ['Đầm maxi nữ voan trắng đi biển', 549000, 4, 'Đầm maxi voan trắng tung bay, cổ chữ V, hoàn hảo cho kỳ nghỉ biển.', true, 'products/unique/p047_dam_maxi_trang.jpg'],
                ['Đầm công sở nữ đen suông thanh lịch', 459000, 3, 'Đầm suông đen tối giản, vải ponte dày dặn, sang trọng chốn văn phòng.', false, 'products/unique/p048_dam_congso_den.jpg'],
                ['Váy xếp ly nữ tennis trắng', 349000, 1, 'Váy xếp ly mini phong cách tennis, cạp cao tôn chân, trendy và năng động.', false, 'products/unique/p049_vay_xeply_tennis.jpg'],
                ['Đầm cocktail nữ lệch vai đỏ', 599000, 6, 'Đầm cocktail lệch vai quyến rũ, vải satin đỏ rượu, dự tiệc đẳng cấp.', true, 'products/unique/p050_dam_cocktail_do.jpg'],
            ],

            /* ───────── QUẦN JEANS NỮ (4) ───────── */
            'quan-jeans-nu' => [
                ['Quần jeans nữ ống rộng xanh nhạt', 479000, 4, 'Jeans nữ ống rộng cạp cao, wash xanh nhạt retro, thoải mái trendy.', true, 'products/unique/p051_jeans_nu_ongrong.jpg'],
                ['Quần jeans nữ skinny đen cạp cao', 449000, 3, 'Jeans skinny đen co giãn cạp cao, tôn dáng hoàn hảo, mặc được nhiều outfit.', false, 'products/unique/p052_jeans_nu_skinny_den.jpg'],
                ['Quần jeans nữ mom fit vintage', 459000, 5, 'Jeans mom fit dáng rộng vừa, wash xanh vintage 90s, cá tính retro.', false, 'products/unique/p053_jeans_nu_momfit.jpg'],
                ['Quần jeans nữ flare ống loe wash', 499000, 4, 'Jeans flare ống loe quyến rũ, cạp cao kéo dài chân, 70s comeback.', true, 'products/unique/p054_jeans_nu_flare.jpg'],
            ],

            /* ───────── QUẦN TÂY NỮ (5) ───────── */
            'quan-tay-nu' => [
                ['Quần tây nữ ống suông đen công sở', 399000, 3, 'Quần tây đen ống suông, cạp cao lưng chun, thanh lịch và thoải mái.', true, 'products/unique/p055_quantay_nu_suong.jpg'],
                ['Quần culottes nữ be lưng chun', 349000, 5, 'Quần culottes ống rộng 7/8, lưng chun thoải mái, vải rũ mát mẻ.', false, 'products/unique/p056_culottes_be.jpg'],
                ['Quần baggy nữ xám nhẹ thanh lịch', 379000, 4, 'Quần baggy form rộng thanh lịch, vải tốt giữ phom, mặc công sở hoặc dạo phố.', false, 'products/unique/p057_baggy_xamnhe.jpg'],
                ['Quần palazzo nữ kem ống rộng', 429000, 4, 'Quần palazzo ống rộng bay bổng, chất vải rũ, tôn dáng cao ráo.', false, 'products/unique/p058_palazzo_kem.jpg'],
                ['Quần legging nữ đen thể thao', 299000, 1, 'Quần legging đen cạp cao co giãn 4 chiều, thấm hút mồ hôi, tập gym hoặc dạo phố.', false, 'products/unique/p059_legging_den.jpg'],
            ],

            /* ───────── GIÀY SNEAKER (8) ───────── */
            'giay-sneaker' => [
                ['Giày sneaker nam trắng Air classic', 1890000, 1, 'Sneaker trắng classic với đệm Air, đế cao su bền, icon thời trang đường phố.', true, 'products/unique/p060_sneaker_trang_air.jpg'],
                ['Giày sneaker nữ hồng pastel', 1290000, 1, 'Sneaker nữ tone hồng pastel nhẹ nhàng, đế foam êm ái, form mảnh nữ tính.', false, 'products/unique/p061_sneaker_nu_hong.jpg'],
                ['Giày thể thao nam running đen đỏ', 1690000, 2, 'Giày chạy bộ đệm Boost siêu nhẹ, upper mesh thoáng, grip tốt mọi mặt đường.', true, 'products/unique/p062_giay_running_denvo.jpg'],
                ['Giày sneaker platform nữ trắng', 1490000, 1, 'Sneaker platform đế dày 4cm, trắng tinh khôi, hack chiều cao tôn dáng.', false, 'products/unique/p063_sneaker_platform_trang.jpg'],
                ['Giày sneaker nam da trắng retro', 1590000, 2, 'Sneaker da trắng phong cách retro 80s, đế gum, bền đẹp theo thời gian.', true, 'products/unique/p064_sneaker_retro_trang.jpg'],
                ['Giày thể thao nam ultraboost đen', 2290000, 2, 'Ultraboost full đen, đệm Boost full-length, upper Primeknit ôm chân.', false, 'products/unique/p065_ultraboost_den.jpg'],
                ['Giày sneaker nam canvas navy', 890000, 7, 'Sneaker canvas navy phong cách vintage, đế vulcanized, nhẹ nhàng dễ mang.', false, 'products/unique/p066_sneaker_canvas_navy.jpg'],
                ['Giày sneaker nữ slip-on trắng', 790000, 3, 'Slip-on trắng tiện lợi, không cần buộc dây, phù hợp mặc hàng ngày.', false, 'products/unique/p067_slipon_nu_trang.jpg'],
            ],

            /* ───────── GIÀY TÂY & LƯỜI (5) ───────── */
            'giay-tay-luoi' => [
                ['Giày lười nam da bò nâu', 1290000, 7, 'Giày lười da bò thật, đế tự nhiên, mang thoải mái, sang trọng lịch lãm.', true, 'products/unique/p068_giay_luoi_da_nau.jpg'],
                ['Giày tây nam oxford đen bóng', 1590000, 6, 'Oxford đen bóng da bê, mũi nhọn lịch sự, đế da khâu tay thủ công.', false, 'products/unique/p069_oxford_den_bong.jpg'],
                ['Giày loafer nam da lộn xám', 1190000, 4, 'Loafer da lộn (suede) xám đá, phong cách casual sang trọng.', false, 'products/unique/p070_loafer_daluon_xam.jpg'],
                ['Giày derby nam nâu đế cao su', 1390000, 7, 'Derby 3 lỗ xỏ dây, da bò nâu bóng, đế cao su chống trượt.', true, 'products/unique/p071_derby_nau_decaosu.jpg'],
                ['Giày mọi nam da nâu nhạt', 990000, 7, 'Giày mọi da bò mềm, không dây, mang trượt vào dễ dàng.', false, 'products/unique/p072_giay_moi_nau_nhat.jpg'],
            ],

            /* ───────── DÉP & SANDAL (5) ───────── */
            'dep-sandal' => [
                ['Dép quai ngang nam đen đế dày', 490000, 2, 'Dép quai ngang đế dày êm ái, chất liệu EVA nhẹ, phong cách thể thao.', true, 'products/unique/p073_dep_quaingang_den.jpg'],
                ['Sandal nữ quai chéo nâu da', 590000, 4, 'Sandal nữ quai chéo da tổng hợp, đế cork tự nhiên, thoải mái dạo phố.', false, 'products/unique/p074_sandal_nu_nauda.jpg'],
                ['Dép lê nam thể thao trắng', 350000, 1, 'Dép lê thể thao đệm foam siêu nhẹ, logo nổi, phục hồi chân sau tập.', false, 'products/unique/p075_dep_le_thethao.jpg'],
                ['Sandal nữ đế xuồng kem', 690000, 4, 'Sandal đế xuồng 7cm, quai ngang elegance, tăng chiều cao tôn dáng.', false, 'products/unique/p076_sandal_nu_dexuong.jpg'],
                ['Dép birken nam nâu da lộn', 520000, 7, 'Dép birkenstock 2 quai da lộn, đế cork thiên nhiên, thoải mái suốt ngày.', false, 'products/unique/p077_dep_birken_nau.jpg'],
            ],

            /* ───────── TÚI XÁCH & BALO (6) ───────── */
            'tui-xach-balo' => [
                ['Balo laptop nam đen chống nước', 690000, 7, 'Balo laptop 15.6 inch, vải Oxford chống nước, ngăn chống sốc, nhiều ngăn tiện ích.', true, 'products/unique/p078_balo_laptop_den.jpg'],
                ['Túi tote nữ canvas be', 349000, 3, 'Tote bag canvas dày dặn, quai da tổng hợp chắc chắn, dung tích lớn.', false, 'products/unique/p079_tui_tote_canvas_be.jpg'],
                ['Túi đeo chéo nam da đen', 490000, 7, 'Túi đeo chéo compact da tổng hợp, vừa điện thoại, ví, nhiều ngăn.', false, 'products/unique/p080_tui_deocheo_da.jpg'],
                ['Balo mini nữ thời trang hồng', 450000, 5, 'Balo mini nữ da PU hồng pastel, size nhỏ gọn, đi chơi dạo phố thời thượng.', true, 'products/unique/p081_balo_mini_nu_hong.jpg'],
                ['Túi clutch nữ dạ tiệc ánh kim', 590000, 6, 'Clutch dạ tiệc ánh kim sang trọng, khóa snap, dây đeo xích mảnh.', false, 'products/unique/p082_tui_clutch_anhkim.jpg'],
                ['Túi messenger nam vải dù navy', 420000, 2, 'Túi messenger vải dù chống nước, quai đeo chéo điều chỉnh, phong cách active.', false, 'products/unique/p083_tui_messenger_navy.jpg'],
            ],

            /* ───────── THẮT LƯNG (4) ───────── */
            'that-lung' => [
                ['Thắt lưng nam da bò khóa tự động đen', 390000, 7, 'Thắt lưng da bò thật, khóa tự động không lỗ, bản to 3.5cm lịch sự.', true, 'products/unique/p084_thatlang_da_den_auto.jpg'],
                ['Thắt lưng nam da khóa kim bạc nâu', 350000, 7, 'Thắt lưng da nâu khóa kim truyền thống, phong cách classic bền đẹp.', false, 'products/unique/p085_thatlang_nau_khoakim.jpg'],
                ['Thắt lưng nữ bản nhỏ da trắng', 249000, 4, 'Thắt lưng nữ bản 2cm, da PU trắng, làm điểm nhấn cho đầm và quần cạp cao.', false, 'products/unique/p086_thatlang_nu_trang.jpg'],
                ['Thắt lưng nam canvas tactical xanh', 199000, 7, 'Thắt lưng vải canvas quân sự, khóa nhựa gài nhanh, nhẹ và bền.', false, 'products/unique/p087_thatlang_canvas_xanh.jpg'],
            ],

            /* ───────── VÍ & BÓP (4) ───────── */
            'vi-bop' => [
                ['Ví nam da bò gập đôi nâu', 490000, 7, 'Ví da bò gập đôi, 6 ngăn thẻ, ngăn tiền có khóa, thiết kế gọn gàng.', true, 'products/unique/p088_vi_nam_da_nau.jpg'],
                ['Ví nữ cầm tay dây kéo hồng', 390000, 4, 'Ví nữ cầm tay khóa kéo, chất da PU mềm, ngăn đựng điện thoại tiện lợi.', false, 'products/unique/p089_vi_nu_hong_daykeo.jpg'],
                ['Ví cardholder nam da đen', 290000, 3, 'Cardholder siêu mỏng 6 ngăn thẻ, da thật, bỏ túi quần thoải mái.', false, 'products/unique/p090_cardholder_den.jpg'],
                ['Ví dài nữ ngăn điện thoại be', 450000, 5, 'Ví dài nữ khóa snap, ngăn điện thoại, nhiều ngăn thẻ và tiền, tone be nhã nhặn.', true, 'products/unique/p091_vi_dai_nu_be.jpg'],
            ],

            /* ───────── ĐỒNG HỒ (5) ───────── */
            'dong-ho' => [
                ['Đồng hồ nam dây thép mặt tròn bạc', 2490000, 7, 'Đồng hồ nam dây thép không gỉ, mặt tròn 40mm, kính sapphire chống xước.', true, 'products/unique/p092_dongho_nam_thep_bac.jpg'],
                ['Đồng hồ nam dây da mặt vuông đen', 1890000, 7, 'Đồng hồ mặt vuông dây da bê Ý, máy Nhật Miyota, phong cách tinh tế.', false, 'products/unique/p093_dongho_dayda_matvuong.jpg'],
                ['Đồng hồ nữ dây kim loại vàng hồng', 1990000, 6, 'Đồng hồ nữ mạ vàng hồng, mặt 32mm tinh xảo, sang trọng đi tiệc.', true, 'products/unique/p094_dongho_nu_vanghong.jpg'],
                ['Đồng hồ thể thao nam digital đen', 1290000, 1, 'Đồng hồ thể thao digital, chống nước 5ATM, đếm ngược, đèn LED.', false, 'products/unique/p095_dongho_digital_den.jpg'],
                ['Đồng hồ nam chronograph dây thép đen', 2890000, 7, 'Chronograph 3 mặt phụ, dây thép IP đen, vỏ 42mm mạnh mẽ nam tính.', true, 'products/unique/p096_dongho_chronograph.jpg'],
            ],

            /* ───────── MẮT KÍNH (4) ───────── */
            'mat-kinh' => [
                ['Kính mát nam aviator tròng xanh', 890000, 7, 'Kính aviator gọng kim loại, tròng polarized chống chói, phong cách phi công.', true, 'products/unique/p097_kinh_aviator_xanh.jpg'],
                ['Kính mát nữ cat-eye đen thanh lịch', 790000, 6, 'Kính cat-eye gọng acetate đen, tròng gradient, quyến rũ nữ tính.', false, 'products/unique/p098_kinh_cateye_den.jpg'],
                ['Kính mát unisex wayfarer nâu rùa', 690000, 7, 'Kính wayfarer gọng nâu rùa classic, tròng UV400, unisex dễ mặc.', true, 'products/unique/p099_kinh_wayfarer_nau.jpg'],
                ['Kính mát nam sport wrap đen', 590000, 1, 'Kính thể thao wrap-around, tròng polarized, chống gió bụi khi chạy xe.', false, 'products/unique/p100_kinh_sport_den.jpg'],
            ],
        ];
    }
}
