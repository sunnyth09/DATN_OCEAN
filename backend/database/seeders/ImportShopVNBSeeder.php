<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportShopVNBSeeder extends Seeder
{
    /**
     * Mapping tên thương hiệu nhận diện từ tên sản phẩm shopvnb
     */
    private array $brandKeywords = [
        'Yonex' => 'Yonex',
        'Victor' => 'Victor',
        'Li-Ning' => 'Li-Ning',
        'Lining' => 'Li-Ning',
        'Mizuno' => 'Mizuno',
        'VNB' => 'VNB',
        'Kawasaki' => 'Kawasaki',
        'Apacs' => 'Apacs',
        'Hundred' => 'Hundred',
        'Proace' => 'Proace',
        'Kumpoo' => 'Kumpoo',
        'Kamito' => 'Kamito',
        'Wilson' => 'Wilson',
        'Head' => 'Head',
        'Babolat' => 'Babolat',
        'Dunlop' => 'Dunlop',
        'Prince' => 'Prince',
        'VS' => 'VS',
        'Flypower' => 'Flypower',
        'Adidas' => 'Adidas',
        'Nike' => 'Nike',
    ];

    /**
     * Thêm các size phổ biến theo loại sản phẩm
     */
    private array $sizesByType = [
        'vợt' => [''],         // Vợt thường không có size
        'giày' => ['38', '39', '40', '41', '42', '43'],
        'áo' => ['S', 'M', 'L', 'XL', 'XXL'],
        'quần' => ['S', 'M', 'L', 'XL'],
        'túi' => [''],
        'phụ kiện' => [''],
    ];

    public function run(): void
    {
        $jsonPath = storage_path('app/shopvnb_products.json');

        if (! file_exists($jsonPath)) {
            $this->command->error("❌ Không tìm thấy file: {$jsonPath}");
            $this->command->info('   Hãy chạy: php artisan scrape:shopvnb trước');

            return;
        }

        $products = json_decode(file_get_contents($jsonPath), true);

        if (empty($products)) {
            $this->command->error('❌ File JSON rỗng hoặc lỗi format.');

            return;
        }

        $this->command->info('📦 Bắt đầu import '.count($products).' sản phẩm từ shopvnb...');

        // Bước 1: Xóa data cũ
        $this->command->comment('🗑️  Xóa dữ liệu sản phẩm cũ...');
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        ProductImage::truncate();
        ProductVariant::truncate();
        // Xóa vĩnh viễn cả soft deleted
        DB::table('products')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        $this->command->info('   ✅ Đã xóa sạch.');

        // Bước 2: Đảm bảo brands tồn tại
        $this->ensureBrands($products);

        // Cache category & brand
        $categoryMap = Category::pluck('category_id', 'name')->toArray();
        $brandMap = Brand::pluck('brand_id', 'name')->toArray();

        // Bước 3: Import từng sản phẩm
        $imported = 0;
        $errors = 0;

        foreach ($products as $data) {
            try {
                $this->importProduct($data, $categoryMap, $brandMap);
                $imported++;
            } catch (\Throwable $e) {
                $errors++;
                $this->command->warn("   ⚠️ Lỗi [{$data['name']}]: ".$e->getMessage());
            }
        }

        $this->command->newLine();
        $this->command->info('🎉 Import hoàn tất!');
        $this->command->info("   ✅ Thành công: {$imported} sản phẩm");
        if ($errors > 0) {
            $this->command->warn("   ⚠️ Lỗi: {$errors} sản phẩm");
        }
    }

    private function ensureBrands(array $products): void
    {
        $detectedBrands = [];

        foreach ($products as $p) {
            $brand = $this->detectBrand($p['name']);
            if ($brand && ! in_array($brand, $detectedBrands)) {
                $detectedBrands[] = $brand;
            }
        }

        foreach ($detectedBrands as $brandName) {
            Brand::firstOrCreate(
                ['name' => $brandName],
                [
                    'slug' => Str::slug($brandName),
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('   🏷️  Đã đảm bảo '.count($detectedBrands).' thương hiệu.');
    }

    private function detectBrand(string $productName): ?string
    {
        foreach ($this->brandKeywords as $keyword => $brandName) {
            // Dùng word boundary để tránh match sai (ví dụ "VNB" trong "VNBSports")
            if (preg_match('/\b'.preg_quote($keyword, '/').'\b/iu', $productName)) {
                return $brandName;
            }
        }

        return null;
    }

    private function detectProductType(string $name): string
    {
        $nameLower = mb_strtolower($name);

        if (str_contains($nameLower, 'vợt') || str_contains($nameLower, 'vot')) {
            return 'vợt';
        }
        if (str_contains($nameLower, 'giày') || str_contains($nameLower, 'giay')) {
            return 'giày';
        }
        if (str_contains($nameLower, 'áo') || str_contains($nameLower, 'ao')) {
            return 'áo';
        }
        if (str_contains($nameLower, 'quần') || str_contains($nameLower, 'quan')) {
            return 'quần';
        }
        if (str_contains($nameLower, 'túi') || str_contains($nameLower, 'balo') || str_contains($nameLower, 'ba lô')) {
            return 'túi';
        }

        return 'phụ kiện';
    }

    private function importProduct(array $data, array $categoryMap, array $brandMap): void
    {
        $name = trim($data['name']);
        $price = max((int) $data['price'], 100000); // Giá tối thiểu 100k
        $imageUrl = $data['image_url'] ?? '';
        $category = $data['category_local'] ?? 'Phụ kiện thể thao';

        // Tìm category_id
        $categoryId = $categoryMap[$category] ?? null;
        if (! $categoryId) {
            // Fallback: tạo category mới
            $cat = Category::firstOrCreate(
                ['name' => $category],
                [
                    'slug' => Str::slug($category),
                    'is_active' => true,
                    'sort_order' => 99,
                ]
            );
            $categoryId = $cat->category_id;
        }

        // Detect brand
        $brandName = $this->detectBrand($name);
        $brandId = $brandName ? ($brandMap[$brandName] ?? null) : null;

        // Tạo slug unique
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;
        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter++;
        }

        // Tạo product
        $product = Product::create([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'seller_id' => 1, // Admin là seller mặc định
            'name' => $name,
            'slug' => $slug,
            'short_description' => "Sản phẩm {$name} chính hãng, chất lượng cao.",
            'description' => $this->generateDescription($name, $brandName, $price),
            'thumbnail_url' => $imageUrl,
            'product_type' => 'simple',
            'status' => 'active',
            'is_featured' => rand(0, 100) < 20, // 20% featured
            'min_price' => $price,
            'max_price' => $price,
            'rating_avg' => round(rand(35, 50) / 10, 1), // 3.5 - 5.0
            'rating_count' => rand(5, 200),
            'view_count' => rand(50, 5000),
            'sold_count' => rand(10, 500),
            'weight' => 0,
            'published_at' => now()->subDays(rand(1, 90)),
        ]);

        // Tạo ảnh chính
        if ($imageUrl) {
            ProductImage::create([
                'product_id' => $product->product_id,
                'image_url' => $imageUrl,
                'alt_text' => $name,
                'is_main' => 1,
                'sort_order' => 1,
            ]);
        }

        // Tạo variants
        $this->createVariants($product, $price, $name);
    }

    private function createVariants(Product $product, int $basePrice, string $name): void
    {
        $type = $this->detectProductType($name);
        $sizes = $this->sizesByType[$type] ?? [''];

        // Tạo giá compare (giá gốc cao hơn 15-30%)
        $comparePrice = (int) ($basePrice * (1 + rand(15, 30) / 100));

        // Nếu sản phẩm có size → tạo nhiều variants
        foreach ($sizes as $size) {
            // Biến thể giá nhẹ theo size (size lớn hơn đắt hơn ~5%)
            $sizeIndex = array_search($size, $sizes);
            $priceAdj = $sizeIndex > 0 ? $sizeIndex * (int) ($basePrice * 0.02) : 0;
            $finalPrice = $basePrice + $priceAdj;

            $sku = strtoupper(Str::slug($name, '-'));
            if ($size) {
                $sku .= '-'.strtoupper($size);
            }
            // Giữ sku dưới 50 ký tự
            $sku = substr($sku, 0, 45);

            ProductVariant::create([
                'product_id' => $product->product_id,
                'sku' => $sku.'-'.rand(100, 999),
                'variant_name' => $size ?: 'Mặc định',
                'color' => null,
                'size' => $size ?: null,
                'price' => $finalPrice,
                'compare_at_price' => $comparePrice + $priceAdj,
                'stock' => rand(5, 100),
                'reserved_stock' => 0,
                'safety_stock' => 3,
                'status' => 'active',
            ]);
        }

        // Cập nhật min/max price
        $variants = $product->variants()->get();
        $product->update([
            'min_price' => $variants->min('price'),
            'max_price' => $variants->max('price'),
        ]);
    }

    private function generateDescription(string $name, ?string $brand, int $price): string
    {
        $brandText = $brand ? "thương hiệu {$brand}" : 'chất lượng cao';

        return <<<HTML
<div class="product-description">
    <h3>Giới thiệu về {$name}</h3>
    <p>{$name} là sản phẩm {$brandText}, được thiết kế dành cho người chơi thể thao từ nghiệp dư đến chuyên nghiệp. Sản phẩm đảm bảo chính hãng 100%, có tem bảo hành từ nhà sản xuất.</p>

    <h4>Đặc điểm nổi bật</h4>
    <ul>
        <li>Chất lượng đảm bảo, hàng chính hãng</li>
        <li>Thiết kế hiện đại, phù hợp xu hướng</li>
        <li>Độ bền cao, phù hợp cho thi đấu và tập luyện</li>
        <li>Được nhiều vận động viên tin dùng</li>
    </ul>

    <h4>Chính sách bảo hành</h4>
    <p>Bảo hành chính hãng 12 tháng. Đổi trả miễn phí trong 7 ngày nếu sản phẩm lỗi từ nhà sản xuất.</p>
</div>
HTML;
    }
}
