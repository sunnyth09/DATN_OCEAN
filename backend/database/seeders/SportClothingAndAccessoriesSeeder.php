<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * SportClothingAndAccessoriesSeeder
 *
 * Thêm dữ liệu mẫu cho 4 danh mục thể thao:
 *  - Quần áo thể thao (áo, quần tập luyện)
 *  - Giày thể thao (running, gym, đa năng)
 *  - Dụng cụ thể thao (bóng, vợt bóng bàn, …)
 *  - Phụ kiện thể thao (bảo hộ, băng đeo, bình nước, …)
 */
class SportClothingAndAccessoriesSeeder extends Seeder
{
    private string $now;

    public function run(): void
    {
        $this->now = Carbon::now()->toDateTimeString();

        $brandMap = $this->seedBrands();
        $categoryMap = $this->seedCategories();

        foreach ($this->products() as $product) {
            $this->upsertProduct($product, $brandMap, $categoryMap);
        }

        echo "✅ SportClothingAndAccessoriesSeeder hoàn tất: 4 danh mục, 32 sản phẩm.\n";
    }

    /* ─────────────────────── BRANDS ─────────────────────── */
    private function seedBrands(): array
    {
        $brands = [
            ['name' => 'Nike',        'slug' => 'nike',         'description' => 'Thương hiệu thể thao hàng đầu thế giới từ Mỹ.'],
            ['name' => 'Adidas',      'slug' => 'adidas',       'description' => 'Thể thao & lifestyle đến từ Đức.'],
            ['name' => 'Puma',        'slug' => 'puma',         'description' => 'Thể thao năng động, thời trang từ Đức.'],
            ['name' => 'Li-Ning',     'slug' => 'li-ning',      'description' => 'Thương hiệu thể thao hàng đầu Trung Quốc.'],
            ['name' => 'Decathlon',   'slug' => 'decathlon',    'description' => 'Dụng cụ thể thao đa năng, giá tốt.'],
            ['name' => 'Under Armour', 'slug' => 'under-armour', 'description' => 'Trang phục hiệu suất cao cho vận động viên.'],
            ['name' => 'Wilson',      'slug' => 'wilson',       'description' => 'Dụng cụ thể thao chuyên nghiệp từ Mỹ.'],
            ['name' => 'Ocean Sport', 'slug' => 'ocean-sport',  'description' => 'Thương hiệu thể thao nội địa chất lượng cao.'],
        ];

        $map = [];
        foreach ($brands as $brand) {
            $existing = DB::table('brands')->where('slug', $brand['slug'])->first();
            $payload = [
                'name' => $brand['name'],
                'slug' => $brand['slug'],
                'description' => $brand['description'],
                'logo_url' => null,
                'is_active' => 1,
                'updated_at' => $this->now,
            ];
            if ($existing) {
                DB::table('brands')->where('brand_id', $existing->brand_id)->update($payload);
                $map[$brand['slug']] = $existing->brand_id;
            } else {
                $payload['created_at'] = $this->now;
                $map[$brand['slug']] = DB::table('brands')->insertGetId($payload);
            }
        }

        return $map;
    }

    /* ─────────────────────── CATEGORIES ─────────────────────── */
    private function seedCategories(): array
    {
        $parentId = $this->upsertCategory(null, 'Thể thao & Sức khỏe', 'the-thao-suc-khoe',
            'Danh mục tổng hợp quần áo, giày và dụng cụ thể thao.', null, 70);

        $children = [
            ['name' => 'Quần áo thể thao', 'slug' => 'quan-ao-the-thao',  'description' => 'Áo tập, quần tập, bộ đồ thể thao nam nữ.', 'sort_order' => 71, 'palette' => ['#6366F1', '#EC4899']],
            ['name' => 'Giày thể thao',    'slug' => 'giay-the-thao',     'description' => 'Giày chạy bộ, giày gym, giày đa năng.',      'sort_order' => 72, 'palette' => ['#F59E0B', '#EF4444']],
            ['name' => 'Dụng cụ thể thao', 'slug' => 'dung-cu-the-thao', 'description' => 'Tạ tay, bóng, vợt, dây nhảy và thiết bị.', 'sort_order' => 73, 'palette' => ['#10B981', '#3B82F6']],
            ['name' => 'Phụ kiện thể thao', 'slug' => 'phu-kien-the-thao', 'description' => 'Bảo hộ, băng cổ tay, bình nước, túi gym.', 'sort_order' => 74, 'palette' => ['#0EA5E9', '#8B5CF6']],
        ];

        $map = [];
        foreach ($children as $child) {
            $imgPath = 'products/sport-lifestyle/categories/'.$child['slug'].'.svg';
            Storage::disk('public')->put($imgPath, $this->buildCategorySvg($child['name'], $child['palette']));
            $map[$child['slug']] = $this->upsertCategory($parentId, $child['name'], $child['slug'], $child['description'], $imgPath, $child['sort_order']);
        }

        return $map;
    }

    private function upsertCategory(?int $parentId, string $name, string $slug, string $description, ?string $imagePath, int $sortOrder): int
    {
        $existing = DB::table('categories')->where('slug', $slug)->first();
        $payload = ['parent_id' => $parentId, 'name' => $name, 'slug' => $slug, 'image' => $imagePath, 'description' => $description, 'sort_order' => $sortOrder, 'is_active' => 1, 'updated_at' => $this->now];
        if ($existing) {
            DB::table('categories')->where('category_id', $existing->category_id)->update($payload);

            return $existing->category_id;
        }
        $payload['created_at'] = $this->now;

        return DB::table('categories')->insertGetId($payload);
    }

    /* ─────────────────────── UPSERT PRODUCT ─────────────────────── */
    private function upsertProduct(array $product, array $brandMap, array $categoryMap): void
    {
        $slug = $product['slug'] ?? Str::slug($product['name']);
        $categoryId = $categoryMap[$product['category']];
        $brandId = $brandMap[$product['brand']];
        $assets = $this->generateProductAssets($product, $slug);
        $prices = array_column($product['variants'], 'price');

        $payload = [
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'seller_id' => null,
            'name' => $product['name'],
            'slug' => $slug,
            'short_description' => $product['short_description'],
            'description' => $this->buildProductDescription($product),
            'thumbnail_url' => $assets['main'],
            'product_type' => count($product['variants']) > 1 ? 'variant' : 'simple',
            'status' => 'active',
            'is_featured' => $product['is_featured'] ? 1 : 0,
            'min_price' => min($prices),
            'max_price' => max($prices),
            'rating_avg' => $product['rating_avg'],
            'rating_count' => $product['rating_count'],
            'view_count' => $product['view_count'],
            'sold_count' => $product['sold_count'],
            'published_at' => $this->now,
            'updated_at' => $this->now,
        ];

        $existing = DB::table('products')->where('slug', $slug)->first();
        if ($existing) {
            DB::table('products')->where('product_id', $existing->product_id)->update($payload);
            $productId = $existing->product_id;
        } else {
            $payload['created_at'] = $this->now;
            $productId = DB::table('products')->insertGetId($payload);
        }

        DB::table('product_images')->where('product_id', $productId)->delete();
        DB::table('product_variants')->where('product_id', $productId)->delete();

        foreach ($product['variants'] as $index => $variant) {
            DB::table('product_variants')->insert([
                'product_id' => $productId,
                'sku' => $this->ensureUniqueSku($variant['sku']),
                'barcode' => $this->buildBarcode($product['category'], $slug, $index),
                'variant_name' => $variant['variant_name'],
                'color' => $variant['color'],
                'size' => $variant['size'] ?? null,
                'material' => $variant['material'],
                'weight_gram' => $variant['weight_gram'],
                'cost_price' => (int) round($variant['price'] * 0.68),
                'price' => $variant['price'],
                'compare_at_price' => $variant['compare_at_price'],
                'stock' => $variant['stock'],
                'reserved_stock' => 0,
                'safety_stock' => $variant['safety_stock'],
                'image_url' => $assets['variant'],
                'status' => 'active',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }

        DB::table('product_images')->insert([
            ['product_id' => $productId, 'variant_id' => null, 'image_url' => $assets['main'],  'alt_text' => $product['name'].' - ảnh chính', 'is_main' => 1, 'sort_order' => 0, 'created_at' => $this->now],
            ['product_id' => $productId, 'variant_id' => null, 'image_url' => $assets['angle'], 'alt_text' => $product['name'].' - góc nghiêng', 'is_main' => 0, 'sort_order' => 1, 'created_at' => $this->now],
            ['product_id' => $productId, 'variant_id' => null, 'image_url' => $assets['detail'], 'alt_text' => $product['name'].' - chi tiết',  'is_main' => 0, 'sort_order' => 2, 'created_at' => $this->now],
        ]);

        DB::table('products')->where('product_id', $productId)->update(['min_price' => min($prices), 'max_price' => max($prices), 'thumbnail_url' => $assets['main'], 'updated_at' => $this->now]);
    }

    /* ─────────────────────── ASSETS ─────────────────────── */
    private function generateProductAssets(array $product, string $slug): array
    {
        $dir = 'products/sport-lifestyle/'.$product['category'].'/'.$slug;
        $main = $dir.'/main.svg';
        $angle = $dir.'/angle.svg';
        $detail = $dir.'/detail.svg';
        $variant = $dir.'/variant.svg';

        Storage::disk('public')->put($main, $this->buildPackshotSvg($product, 'main'));
        Storage::disk('public')->put($angle, $this->buildPackshotSvg($product, 'angle'));
        Storage::disk('public')->put($detail, $this->buildPackshotSvg($product, 'detail'));
        Storage::disk('public')->put($variant, $this->buildPackshotSvg($product, 'variant'));

        return compact('main', 'angle', 'detail', 'variant');
    }

    private function buildPackshotSvg(array $product, string $mode): string
    {
        $accentA = $product['palette'][0];
        $accentB = $product['palette'][1];
        $brand = $this->svgEscape(Str::upper($product['brand_label']));
        $catLabel = $this->svgEscape(Str::upper($product['category_label']));
        $name = $this->svgEscape($product['name']);
        $subtitle = $this->svgEscape($product['image_caption']);
        $price = number_format($product['base_price'], 0, ',', '.').' đ';
        $shape = $this->renderShape($product['asset_type'], $product['palette'], $mode);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1400" height="1400" viewBox="0 0 1400 1400">
  <defs>
    <linearGradient id="hero" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$accentA}"/>
      <stop offset="100%" stop-color="{$accentB}"/>
    </linearGradient>
    <filter id="shadow"><feDropShadow dx="0" dy="20" stdDeviation="22" flood-color="#0f172a" flood-opacity="0.12"/></filter>
  </defs>
  <rect width="1400" height="1400" rx="40" fill="#ffffff"/>
  <rect x="68" y="68" width="200" height="48" rx="24" fill="#F8FAFC" stroke="#E2E8F0"/>
  <text x="168" y="99" text-anchor="middle" font-family="Arial,sans-serif" font-size="20" font-weight="700" fill="#0F172A">{$brand}</text>
  <rect x="1116" y="68" width="216" height="48" rx="24" fill="url(#hero)"/>
  <text x="1224" y="99" text-anchor="middle" font-family="Arial,sans-serif" font-size="20" font-weight="700" fill="#ffffff">{$catLabel}</text>
  <ellipse cx="700" cy="860" rx="360" ry="68" fill="#E2E8F0" opacity="0.9"/>
  <g filter="url(#shadow)">{$shape}</g>
  <text x="92" y="1145" font-family="Arial,sans-serif" font-size="52" font-weight="700" fill="#0F172A">{$name}</text>
  <text x="92" y="1192" font-family="Arial,sans-serif" font-size="26" fill="#475569">{$subtitle}</text>
  <rect x="92" y="1232" width="220" height="64" rx="32" fill="#0F172A"/>
  <text x="202" y="1273" text-anchor="middle" font-family="Arial,sans-serif" font-size="28" font-weight="700" fill="#ffffff">{$price}</text>
  <text x="1308" y="1285" text-anchor="end" font-family="Arial,sans-serif" font-size="20" fill="#94A3B8">Ocean Sport</text>
</svg>
SVG;
    }

    private function renderShape(string $type, array $p, string $mode): string
    {
        $a = $p[0];
        $b = $p[1];

        return match ($type) {
            'sport-shirt' => $this->shirtSvg($a, $b, $mode),
            'sport-pants' => $this->pantsSvg($a, $b, $mode),
            'sport-shoes' => $this->shoesSvg($a, $b, $mode),
            'dumbbell' => $this->dumbbellSvg($a, $b, $mode),
            'jump-rope' => $this->jumpRopeSvg($a, $b, $mode),
            'table-tennis' => $this->tableTennisSvg($a, $b, $mode),
            'football' => $this->footballSvg($a, $b, $mode),
            'yoga-mat' => $this->yogaMatSvg($a, $b, $mode),
            'water-bottle' => $this->waterBottleSvg($a, $b, $mode),
            'wrist-support' => $this->wristSupportSvg($a, $b, $mode),
            'gym-bag' => $this->gymBagSvg($a, $b, $mode),
            'knee-guard' => $this->kneeGuardSvg($a, $b, $mode),
            default => '<circle cx="700" cy="640" r="220" fill="url(#hero)"/>',
        };
    }

    private function shirtSvg(string $a, string $b, string $m): string
    {
        $r = $m === 'angle' ? 'rotate(-5 700 640)' : 'rotate(0)';

        return "<g transform=\"{$r}\"><path d=\"M460 380 L340 480 L420 540 L440 920 H960 L980 540 L1060 480 L940 380 C900 440 800 470 700 470 C600 470 500 440 460 380Z\" fill=\"{$a}\"/><path d=\"M460 380 C500 440 600 470 700 470 C800 470 900 440 940 380 L960 400 C920 460 810 490 700 490 C590 490 480 460 440 400Z\" fill=\"{$b}\" opacity=\"0.6\"/><rect x=\"620\" y=\"380\" width=\"160\" height=\"58\" rx=\"29\" fill=\"#fff\" opacity=\"0.3\"/><path d=\"M440 920 H960 L975 960 H425Z\" fill=\"{$b}\" opacity=\"0.8\"/></g>";
    }

    private function pantsSvg(string $a, string $b, string $m): string
    {
        $s = $m === 'detail' ? -20 : 0;

        return "<g transform=\"translate(0 {$s})\"><path d=\"M420 400 H980 L960 680 L850 920 H770 L700 720 L630 920 H550 L440 680Z\" fill=\"{$a}\"/><path d=\"M420 400 H980 L970 440 H430Z\" fill=\"{$b}\"/><path d=\"M700 400 V720\" stroke=\"#ffffff\" stroke-width=\"10\" stroke-linecap=\"round\" opacity=\"0.3\"/><ellipse cx=\"700\" cy=\"418\" rx=\"60\" ry=\"12\" fill=\"{$b}\" opacity=\"0.6\"/></g>";
    }

    private function shoesSvg(string $a, string $b, string $m): string
    {
        $ty = $m === 'detail' ? 20 : 0;

        return "<g transform=\"translate(100 {$ty})\"><path d=\"M120 700 C200 630 380 590 580 630 L700 680 C748 690 788 724 788 770 C788 816 744 840 700 840 H126 C74 840 40 810 40 770 C40 742 58 716 88 702Z\" fill=\"{$a}\"/><path d=\"M170 684 C260 630 380 612 540 652 L640 680 C670 688 695 706 710 730 H158 C151 712 155 696 170 684Z\" fill=\"{$b}\" opacity=\"0.9\"/><path d=\"M84 770 H790 C786 818 750 852 706 852 H130 C82 852 48 822 40 770Z\" fill=\"#111827\"/><path d=\"M148 782 H708\" stroke=\"#F8FAFC\" stroke-width=\"12\" stroke-linecap=\"round\" opacity=\"0.9\"/><path d=\"M260 668 L346 714\" stroke=\"#E2E8F0\" stroke-width=\"12\" stroke-linecap=\"round\"/><path d=\"M320 648 L406 704\" stroke=\"#E2E8F0\" stroke-width=\"12\" stroke-linecap=\"round\"/><path d=\"M380 638 L466 694\" stroke=\"#E2E8F0\" stroke-width=\"12\" stroke-linecap=\"round\"/></g>";
    }

    private function dumbbellSvg(string $a, string $b, string $m): string
    {
        $r = $m === 'angle' ? 'rotate(-15 700 640)' : 'rotate(0)';

        return "<g transform=\"{$r}\"><rect x=\"240\" y=\"600\" width=\"920\" height=\"80\" rx=\"40\" fill=\"{$b}\"/><rect x=\"200\" y=\"500\" width=\"180\" height=\"280\" rx=\"36\" fill=\"{$a}\"/><rect x=\"1020\" y=\"500\" width=\"180\" height=\"280\" rx=\"36\" fill=\"{$a}\"/><rect x=\"160\" y=\"550\" width=\"80\" height=\"180\" rx=\"20\" fill=\"#1E293B\"/><rect x=\"1160\" y=\"550\" width=\"80\" height=\"180\" rx=\"20\" fill=\"#1E293B\"/></g>";
    }

    private function jumpRopeSvg(string $a, string $b, string $m): string
    {
        $s = $m === 'detail' ? 0 : 30;

        return "<g transform=\"translate(0 {$s})\"><path d=\"M350 420 C400 340 560 320 700 380 C840 440 980 440 1040 380\" fill=\"none\" stroke=\"{$a}\" stroke-width=\"24\" stroke-linecap=\"round\"/><path d=\"M350 500 C400 580 500 620 700 620 C900 620 1000 580 1050 500\" fill=\"none\" stroke=\"{$b}\" stroke-width=\"20\" stroke-linecap=\"round\" opacity=\"0.7\"/><rect x=\"300\" y=\"380\" width=\"60\" height=\"180\" rx=\"30\" fill=\"#1E293B\"/><rect x=\"1040\" y=\"380\" width=\"60\" height=\"180\" rx=\"30\" fill=\"#1E293B\"/><circle cx=\"330\" cy=\"370\" r=\"22\" fill=\"{$a}\"/><circle cx=\"1070\" cy=\"370\" r=\"22\" fill=\"{$a}\"/></g>";
    }

    private function tableTennisSvg(string $a, string $b, string $m): string
    {
        $r = $m === 'angle' ? 'rotate(-20 700 640)' : 'rotate(-10 700 640)';

        return "<g transform=\"{$r}\"><ellipse cx=\"660\" cy=\"560\" rx=\"220\" ry=\"240\" fill=\"{$a}\"/><ellipse cx=\"660\" cy=\"560\" rx=\"200\" ry=\"220\" fill=\"{$b}\" opacity=\"0.3\"/><rect x=\"836\" y=\"750\" width=\"80\" height=\"220\" rx=\"38\" fill=\"#1E293B\"/><circle cx=\"980\" cy=\"520\" r=\"54\" fill=\"#ffffff\" stroke=\"{$a}\" stroke-width=\"12\"/></g>";
    }

    private function footballSvg(string $a, string $b, string $m): string
    {
        $r = $m === 'detail' ? 260 : 230;

        return "<g><circle cx=\"700\" cy=\"640\" r=\"{$r}\" fill=\"#F8FAFC\" stroke=\"#1E293B\" stroke-width=\"18\"/><polygon points=\"700,400 780,460 760,550 640,550 620,460\" fill=\"{$a}\"/><polygon points=\"500,660 560,590 640,620 640,710 570,740\" fill=\"{$a}\"/><polygon points=\"900,660 840,590 760,620 760,710 830,740\" fill=\"{$a}\"/><polygon points=\"620,870 640,790 760,790 780,870 700,920\" fill=\"{$a}\"/></g>";
    }

    private function yogaMatSvg(string $a, string $b, string $m): string
    {
        $r = $m === 'angle' ? 'rotate(-12 700 700)' : 'rotate(-6 700 700)';

        return "<g transform=\"{$r}\"><rect x=\"240\" y=\"440\" width=\"920\" height=\"380\" rx=\"48\" fill=\"{$a}\"/><rect x=\"240\" y=\"440\" width=\"920\" height=\"60\" rx=\"48\" fill=\"{$b}\" opacity=\"0.7\"/><rect x=\"240\" y=\"760\" width=\"920\" height=\"60\" fill=\"{$b}\" opacity=\"0.7\"/><path d=\"M280 540 H1120 M280 600 H1120 M280 660 H1120 M280 720 H1120\" stroke=\"#ffffff\" stroke-width=\"5\" opacity=\"0.2\"/></g>";
    }

    private function waterBottleSvg(string $a, string $b, string $m): string
    {
        $r = $m === 'angle' ? 'rotate(-8 700 640)' : 'rotate(0)';

        return "<g transform=\"{$r}\"><rect x=\"590\" y=\"340\" width=\"220\" height=\"40\" rx=\"12\" fill=\"#CBD5E1\"/><rect x=\"610\" y=\"290\" width=\"180\" height=\"60\" rx=\"20\" fill=\"{$b}\"/><path d=\"M570 380 C540 400 520 440 520 500 V840 C520 880 556 910 600 910 H800 C844 910 880 880 880 840 V500 C880 440 860 400 830 380Z\" fill=\"{$a}\"/><path d=\"M570 380 C540 400 520 440 520 500 V540 H880 V500 C880 440 860 400 830 380Z\" fill=\"{$b}\" opacity=\"0.7\"/><rect x=\"540\" y=\"620\" width=\"320\" height=\"16\" rx=\"8\" fill=\"#ffffff\" opacity=\"0.25\"/><rect x=\"540\" y=\"680\" width=\"320\" height=\"12\" rx=\"6\" fill=\"#ffffff\" opacity=\"0.15\"/></g>";
    }

    private function wristSupportSvg(string $a, string $b, string $m): string
    {
        $s = $m === 'detail' ? 20 : 0;

        return "<g transform=\"translate(0 {$s})\"><rect x=\"470\" y=\"440\" width=\"240\" height=\"300\" rx=\"100\" fill=\"{$a}\"/><rect x=\"760\" y=\"440\" width=\"240\" height=\"300\" rx=\"100\" fill=\"{$b}\"/><ellipse cx=\"590\" cy=\"590\" rx=\"80\" ry=\"110\" fill=\"#ffffff\" opacity=\"0.2\"/><ellipse cx=\"880\" cy=\"590\" rx=\"80\" ry=\"110\" fill=\"#ffffff\" opacity=\"0.2\"/></g>";
    }

    private function gymBagSvg(string $a, string $b, string $m): string
    {
        $r = $m === 'angle' ? 'rotate(-6 700 680)' : 'rotate(0)';

        return "<g transform=\"{$r}\"><rect x=\"320\" y=\"480\" width=\"760\" height=\"500\" rx=\"80\" fill=\"{$a}\"/><rect x=\"320\" y=\"480\" width=\"760\" height=\"90\" rx=\"80\" fill=\"{$b}\" opacity=\"0.7\"/><path d=\"M500 480 C500 420 540 380 700 380 C860 380 900 420 900 480\" fill=\"none\" stroke=\"{$b}\" stroke-width=\"36\" stroke-linecap=\"round\"/><rect x=\"560\" y=\"620\" width=\"280\" height=\"180\" rx=\"20\" fill=\"#ffffff\" opacity=\"0.15\"/><circle cx=\"700\" cy=\"612\" r=\"18\" fill=\"#1E293B\"/></g>";
    }

    private function kneeGuardSvg(string $a, string $b, string $m): string
    {
        $s = $m === 'angle' ? 16 : 0;

        return "<g transform=\"translate(0 {$s})\"><rect x=\"474\" y=\"454\" width=\"168\" height=\"330\" rx=\"82\" fill=\"{$a}\"/><rect x=\"760\" y=\"454\" width=\"168\" height=\"330\" rx=\"82\" fill=\"{$b}\"/><ellipse cx=\"558\" cy=\"620\" rx=\"58\" ry=\"86\" fill=\"#ffffff\" opacity=\"0.22\"/><ellipse cx=\"844\" cy=\"620\" rx=\"58\" ry=\"86\" fill=\"#ffffff\" opacity=\"0.22\"/></g>";
    }

    private function buildCategorySvg(string $title, array $palette): string
    {
        $t = $this->svgEscape($title);
        [$a, $b] = $palette;

        return "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"1200\" height=\"900\" viewBox=\"0 0 1200 900\"><defs><linearGradient id=\"cat\" x1=\"0\" y1=\"0\" x2=\"1\" y2=\"1\"><stop offset=\"0%\" stop-color=\"{$a}\"/><stop offset=\"100%\" stop-color=\"{$b}\"/></linearGradient></defs><rect width=\"1200\" height=\"900\" rx=\"40\" fill=\"#ffffff\"/><rect x=\"90\" y=\"90\" width=\"1020\" height=\"720\" rx=\"36\" fill=\"url(#cat)\" opacity=\"0.12\"/><circle cx=\"362\" cy=\"452\" r=\"160\" fill=\"url(#cat)\" opacity=\"0.9\"/><circle cx=\"844\" cy=\"332\" r=\"86\" fill=\"#ffffff\" opacity=\"0.7\"/><circle cx=\"782\" cy=\"566\" r=\"112\" fill=\"#ffffff\" opacity=\"0.5\"/><text x=\"90\" y=\"760\" font-family=\"Arial,sans-serif\" font-size=\"72\" font-weight=\"700\" fill=\"#0F172A\">{$t}</text><text x=\"90\" y=\"814\" font-family=\"Arial,sans-serif\" font-size=\"28\" fill=\"#475569\">Ocean Sport collection</text></svg>";
    }

    /* ─────────────────────── HELPERS ─────────────────────── */
    private function buildProductDescription(array $p): string
    {
        $f = '';
        foreach ($p['features'] as $feat) {
            $f .= '<li>'.e($feat).'</li>';
        }
        $s = '';
        foreach ($p['specs'] as $k => $v) {
            $s .= '<li><strong>'.e($k).':</strong> '.e($v).'</li>';
        }

        return '<div class="product-description"><h3>Mô tả sản phẩm</h3><p>'.e($p['short_description']).'</p><h3>Điểm nổi bật</h3><ul>'.$f.'</ul><h3>Thông số nhanh</h3><ul>'.$s.'</ul><p><em>Dữ liệu tổng hợp ngày 05/07/2026.</em></p></div>';
    }

    private function ensureUniqueSku(string $sku): string
    {
        $candidate = Str::upper($sku);
        $suffix = 1;
        while (DB::table('product_variants')->where('sku', $candidate)->exists()) {
            $candidate = Str::upper($sku).'-X'.$suffix++;
        }

        return $candidate;
    }

    private function buildBarcode(string $category, string $slug, int $index): string
    {
        $prefix = match ($category) {
            'quan-ao-the-thao' => 'CLT',
            'giay-the-thao' => 'SHO',
            'dung-cu-the-thao' => 'EQP',
            default => 'ACC',
        };

        return $prefix.strtoupper(substr(md5($slug.$index), 0, 10));
    }

    private function svgEscape(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /* ─── Variant builders ─── */
    private function colorSizeVariants(string $skuBase, array $colors, array $sizes, string $material, int $wg, int $price, int $compareAt, int $stock = 12, int $safety = 2): array
    {
        $variants = [];
        foreach ($colors as $color) {
            $cs = Str::slug($color);
            foreach ($sizes as $size) {
                $variants[] = ['sku' => "$skuBase-$cs-$size", 'variant_name' => "$color / Size $size", 'color' => $color, 'size' => (string) $size, 'material' => $material, 'weight_gram' => $wg, 'price' => $price, 'compare_at_price' => $compareAt, 'stock' => $stock, 'safety_stock' => $safety];
            }
        }

        return $variants;
    }

    private function sizeVariants(string $skuBase, string $color, array $sizes, string $material, int $wg, int $price, int $compareAt, int $stock = 20, int $safety = 3): array
    {
        return array_map(fn ($sz) => ['sku' => "$skuBase-$sz", 'variant_name' => "Size $sz", 'color' => $color, 'size' => (string) $sz, 'material' => $material, 'weight_gram' => $wg, 'price' => $price, 'compare_at_price' => $compareAt, 'stock' => $stock, 'safety_stock' => $safety], $sizes);
    }

    private function colorVariants(string $skuBase, array $colors, string $material, int $wg, int $price, int $compareAt, int $stock = 15, int $safety = 3): array
    {
        return array_map(fn ($c) => ['sku' => $skuBase.'-'.Str::slug($c), 'variant_name' => $c, 'color' => $c, 'size' => null, 'material' => $material, 'weight_gram' => $wg, 'price' => $price, 'compare_at_price' => $compareAt, 'stock' => $stock, 'safety_stock' => $safety], $colors);
    }

    /* ═══════════════════════════════════════════════════════
       PRODUCT DATA – 32 sản phẩm
    ═══════════════════════════════════════════════════════ */
    private function products(): array
    {
        return [

            // ══ QUẦN ÁO (10) ══
            ['name' => 'Áo thun tập gym Nike Dri-FIT', 'brand' => 'nike', 'brand_label' => 'Nike', 'category' => 'quan-ao-the-thao', 'category_label' => 'Quần áo', 'asset_type' => 'sport-shirt', 'image_caption' => 'Thoáng khí, thấm mồ hôi tối ưu.', 'palette' => ['#2563EB', '#111827'], 'base_price' => 399000, 'is_featured' => true, 'rating_avg' => 4.8, 'rating_count' => 214, 'view_count' => 4200, 'sold_count' => 630, 'short_description' => 'Áo thun gym Nike Dri-FIT với công nghệ thoát hơi nước, giữ khô ráo suốt buổi tập.', 'features' => ['Vải Dri-FIT thấm hút mồ hôi nhanh, thoáng khí cả ngày.', 'Fit ôm vừa phải, không bó chặt khi vận động.', 'Kháng tia UV nhẹ, an toàn khi tập ngoài trời.'], 'specs' => ['Chất liệu' => 'Polyester 100% Dri-FIT', 'Kiểu dáng' => 'Slim fit', 'Mã' => 'NK-DRFT-001'], 'variants' => $this->colorSizeVariants('nk-drft', ['Xanh dương', 'Đen', 'Trắng'], ['S', 'M', 'L', 'XL'], 'Polyester 100%', 180, 399000, 499000)],

            ['name' => 'Áo tập luyện Adidas Techfit', 'brand' => 'adidas', 'brand_label' => 'Adidas', 'category' => 'quan-ao-the-thao', 'category_label' => 'Quần áo', 'asset_type' => 'sport-shirt', 'image_caption' => 'Công nghệ nén cơ hỗ trợ hiệu suất cao.', 'palette' => ['#111827', '#EF4444'], 'base_price' => 459000, 'is_featured' => true, 'rating_avg' => 4.7, 'rating_count' => 178, 'view_count' => 3800, 'sold_count' => 490, 'short_description' => 'Áo Adidas Techfit dệt nén cơ nhẹ, ôm theo chuyển động, lý tưởng cho gym và chạy bộ.', 'features' => ['Vải nén nhẹ hỗ trợ cơ bắp, giảm rung lắc khi vận động cường độ cao.', 'AEROREADY hút ẩm tốt hơn cotton thông thường.', 'Logo Adidas phản quang, an toàn hơn khi chạy tối.'], 'specs' => ['Chất liệu' => 'Polyester tái chế 88%', 'Kiểu dáng' => 'Compression', 'Mã' => 'AD-TF-002'], 'variants' => $this->colorSizeVariants('ad-tf', ['Đen/Đỏ', 'Trắng/Đen', 'Xám'], ['S', 'M', 'L', 'XL', '2XL'], 'Polyester tái chế', 190, 459000, 549000)],

            ['name' => 'Áo polo thể thao Under Armour Tech', 'brand' => 'under-armour', 'brand_label' => 'Under Armour', 'category' => 'quan-ao-the-thao', 'category_label' => 'Quần áo', 'asset_type' => 'sport-shirt', 'image_caption' => 'Phong cách lịch lãm, hiệu suất thể thao.', 'palette' => ['#1D4ED8', '#F59E0B'], 'base_price' => 529000, 'is_featured' => false, 'rating_avg' => 4.6, 'rating_count' => 92, 'view_count' => 2100, 'sold_count' => 215, 'short_description' => 'Áo polo Under Armour Tech vừa lịch lãm vừa thoáng mát, phù hợp cả sân golf lẫn phòng gym.', 'features' => ['UA Tech fabric thoáng khí hơn cotton truyền thống.', 'Thiết kế polo cổ điển, dễ phối đồ công sở hay cuối tuần.', 'UPF 30+ bảo vệ da khi tập ngoài trời.'], 'specs' => ['Chất liệu' => 'Polyester UA Tech', 'Mã' => 'UA-POLO-003'], 'variants' => $this->colorSizeVariants('ua-polo', ['Xanh navy', 'Xám đậm', 'Trắng'], ['S', 'M', 'L', 'XL'], 'Polyester UA Tech', 220, 529000, 649000)],

            ['name' => 'Áo ba lỗ tập gym Puma Train Fav', 'brand' => 'puma', 'brand_label' => 'Puma', 'category' => 'quan-ao-the-thao', 'category_label' => 'Quần áo', 'asset_type' => 'sport-shirt', 'image_caption' => 'Thoáng mát tối đa cho ngày tập nặng.', 'palette' => ['#F59E0B', '#1E293B'], 'base_price' => 329000, 'is_featured' => false, 'rating_avg' => 4.5, 'rating_count' => 67, 'view_count' => 1450, 'sold_count' => 189, 'short_description' => 'Áo ba lỗ Puma Train Fav thoáng tối đa với phần nách mở rộng và vải dryCELL.', 'features' => ['dryCELL hút ẩm nhanh, thoáng khí tốt trong môi trường nhiều mồ hôi.', 'Nách mở rộng cho phép vận động vai tự do khi tập đẩy hoặc kéo.', 'Nhẹ và co giãn 4 chiều, thoải mái mặc cả ngày gym.'], 'specs' => ['Chất liệu' => 'Polyester dryCELL', 'Mã' => 'PM-TRN-004'], 'variants' => $this->colorSizeVariants('pm-trn', ['Vàng/Đen', 'Đen', 'Đỏ'], ['S', 'M', 'L', 'XL'], 'Polyester dryCELL', 160, 329000, 399000)],

            ['name' => 'Quần shorts tập gym Nike Flex', 'brand' => 'nike', 'brand_label' => 'Nike', 'category' => 'quan-ao-the-thao', 'category_label' => 'Quần áo', 'asset_type' => 'sport-pants', 'image_caption' => 'Co giãn 4 chiều, tự do vận động.', 'palette' => ['#16A34A', '#111827'], 'base_price' => 449000, 'is_featured' => true, 'rating_avg' => 4.8, 'rating_count' => 156, 'view_count' => 3200, 'sold_count' => 411, 'short_description' => 'Quần shorts Nike Flex co giãn 4 chiều với lót lưới thoáng mát, lý tưởng cho mọi bài tập.', 'features' => ['Dri-FIT thấm hút mồ hôi, giữ khô ráo suốt buổi tập.', 'Lưng thun rộng, chỉnh được bằng dây rút nội.', 'Túi bên sâu giữ điện thoại an toàn khi chạy.'], 'specs' => ['Chất liệu' => 'Polyester Dri-FIT', 'Độ dài' => '7 inch', 'Mã' => 'NK-FLEX-005'], 'variants' => $this->colorSizeVariants('nk-flex', ['Xanh lá', 'Đen', 'Xám'], ['S', 'M', 'L', 'XL', '2XL'], 'Polyester Dri-FIT', 240, 449000, 549000)],

            ['name' => 'Quần legging nữ Adidas Believe This', 'brand' => 'adidas', 'brand_label' => 'Adidas', 'category' => 'quan-ao-the-thao', 'category_label' => 'Quần áo', 'asset_type' => 'sport-pants', 'image_caption' => 'Ôm dáng, nâng đỡ hoàn hảo cho nữ.', 'palette' => ['#7C3AED', '#EC4899'], 'base_price' => 589000, 'is_featured' => true, 'rating_avg' => 4.9, 'rating_count' => 298, 'view_count' => 5600, 'sold_count' => 742, 'short_description' => 'Quần legging Adidas Believe This ôm khít, không trong suốt, tôn dáng tối ưu cho mọi bài yoga và gym.', 'features' => ['Vải AEROREADY ôm vừa, không xếp ly, không trong suốt.', 'Cạp cao định hình eo, tôn dáng và thoải mái suốt buổi tập.', 'Co giãn 4 chiều cho bài squat, deadlift hay yoga.'], 'specs' => ['Chất liệu' => 'Polyester/Elastane AEROREADY', 'Mã' => 'AD-BTV-006'], 'variants' => $this->colorSizeVariants('ad-btv', ['Tím', 'Đen', 'Hồng'], ['XS', 'S', 'M', 'L', 'XL'], 'Polyester/Elastane', 280, 589000, 749000)],

            ['name' => 'Bộ đồ tập yoga nữ Ocean Sport', 'brand' => 'ocean-sport', 'brand_label' => 'Ocean Sport', 'category' => 'quan-ao-the-thao', 'category_label' => 'Quần áo', 'asset_type' => 'sport-pants', 'image_caption' => 'Set áo + quần yoga mềm mại, thoáng mát.', 'palette' => ['#0EA5E9', '#6EE7B7'], 'base_price' => 399000, 'is_featured' => false, 'rating_avg' => 4.6, 'rating_count' => 84, 'view_count' => 1980, 'sold_count' => 210, 'short_description' => 'Bộ đồ tập yoga Ocean Sport chất liệu mềm mịn, ôm vừa, màu sắc tươi sáng phù hợp mọi vóc dáng.', 'features' => ['Chất liệu nylon mềm mịn, không gây kích ứng da khi tập lâu.', 'Bộ set đồng màu dễ phối, tiện lợi khi ra phố sau tập.', 'Giá hợp lý, lý tưởng làm quà tặng.'], 'specs' => ['Gồm' => 'Áo crop + Quần legging', 'Mã' => 'OS-YOGA-007'], 'variants' => $this->sizeVariants('os-yoga', 'Xanh biển/Xanh lá', ['S', 'M', 'L', 'XL'], 'Nylon/Spandex', 310, 399000, 499000)],

            ['name' => 'Áo khoác chạy bộ Li-Ning Windbreaker', 'brand' => 'li-ning', 'brand_label' => 'Li-Ning', 'category' => 'quan-ao-the-thao', 'category_label' => 'Quần áo', 'asset_type' => 'sport-shirt', 'image_caption' => 'Chắn gió nhẹ, thoáng khí khi chạy.', 'palette' => ['#EF4444', '#111827'], 'base_price' => 699000, 'is_featured' => false, 'rating_avg' => 4.7, 'rating_count' => 63, 'view_count' => 1620, 'sold_count' => 148, 'short_description' => 'Áo khoác windbreaker Li-Ning nhẹ, gấp gọn túi, chắn gió hiệu quả cho buổi chạy sáng.', 'features' => ['Vải ripstop chắn gió, thoáng nhẹ hơn áo khoác dày mùa đông.', 'Gấp gọn vào túi nhỏ đi kèm, tiện mang theo bất kỳ đâu.', 'Mũ liền có thể cuộn gọn khi không dùng.'], 'specs' => ['Chất liệu' => 'Polyester Ripstop', 'Mã' => 'LN-WB-008'], 'variants' => $this->colorSizeVariants('ln-wb', ['Đỏ/Đen', 'Xanh navy'], ['S', 'M', 'L', 'XL'], 'Polyester Ripstop', 320, 699000, 849000)],

            ['name' => 'Quần jogger tập gym Puma Essential', 'brand' => 'puma', 'brand_label' => 'Puma', 'category' => 'quan-ao-the-thao', 'category_label' => 'Quần áo', 'asset_type' => 'sport-pants', 'image_caption' => 'Thoải mái, năng động cho mọi buổi tập.', 'palette' => ['#F97316', '#1E293B'], 'base_price' => 479000, 'is_featured' => false, 'rating_avg' => 4.5, 'rating_count' => 112, 'view_count' => 2340, 'sold_count' => 267, 'short_description' => 'Quần jogger Puma Essential co giãn tốt, túi khóa kéo tiện lợi, phù hợp gym lẫn dạo phố.', 'features' => ['Vải bông pha polyester mềm mại, thoáng mát hơn cotton nguyên chất.', 'Gấu quần bo kết hợp ống quần ôm tạo kiểu dáng năng động.', 'Túi khóa kéo 2 bên đảm bảo đồ không rơi khi vận động.'], 'specs' => ['Chất liệu' => 'Cotton 60%/Polyester 40%', 'Mã' => 'PM-JOG-009'], 'variants' => $this->colorSizeVariants('pm-jog', ['Cam/Đen', 'Đen', 'Xám'], ['S', 'M', 'L', 'XL', '2XL'], 'Cotton/Polyester', 380, 479000, 599000)],

            ['name' => 'Áo tập nữ Under Armour HeatGear', 'brand' => 'under-armour', 'brand_label' => 'Under Armour', 'category' => 'quan-ao-the-thao', 'category_label' => 'Quần áo', 'asset_type' => 'sport-shirt', 'image_caption' => 'Làm mát hiệu quả trong thời tiết nóng.', 'palette' => ['#EC4899', '#111827'], 'base_price' => 499000, 'is_featured' => true, 'rating_avg' => 4.8, 'rating_count' => 187, 'view_count' => 3900, 'sold_count' => 524, 'short_description' => 'Áo nữ UA HeatGear với công nghệ làm mát, ôm sát cơ thể, thoáng mát tối ưu cho gym mùa hè.', 'features' => ['HeatGear® giúp cơ thể mát hơn trong thời tiết nóng.', 'Lớp vải mỏng, nhẹ nhàng, không cảm giác bí bách.', 'Đường may phẳng không gây kích ứng khi vận động nhiều.'], 'specs' => ['Chất liệu' => 'Microfiber HeatGear', 'Mã' => 'UA-HG-010'], 'variants' => $this->colorSizeVariants('ua-hg', ['Hồng', 'Đen', 'Trắng'], ['XS', 'S', 'M', 'L', 'XL'], 'Microfiber HeatGear', 165, 499000, 629000)],

            // ══ GIÀY (5) ══
            ['name' => 'Giày chạy bộ Nike Air Zoom Pegasus 41', 'brand' => 'nike', 'brand_label' => 'Nike', 'category' => 'giay-the-thao', 'category_label' => 'Giày thể thao', 'asset_type' => 'sport-shoes', 'image_caption' => 'Đệm Air Zoom phản lực, bước chạy nhẹ hơn.', 'palette' => ['#2563EB', '#F97316'], 'base_price' => 2990000, 'is_featured' => true, 'rating_avg' => 4.9, 'rating_count' => 342, 'view_count' => 7200, 'sold_count' => 856, 'short_description' => 'Nike Air Zoom Pegasus 41 với đệm React và Air Zoom, mang cảm giác phản lực nhẹ nhàng cho cự ly dài.', 'features' => ['Đệm Air Zoom Unit tích hợp phần trước, tăng phản lực tức thì.', 'React foam êm ái và nhẹ, duy trì năng lượng suốt hành trình dài.', 'Upper Flyknit thoáng khí, ôm chân tự nhiên không cứng.'], 'specs' => ['Loại' => 'Running', 'Đế' => 'React + Air Zoom', 'Mã' => 'NK-PEG41'], 'variants' => $this->colorSizeVariants('nk-peg41', ['Xanh/Cam', 'Đen/Trắng'], ['39', '40', '41', '42', '43', '44'], 'Flyknit + React foam', 280, 2990000, 3490000)],

            ['name' => 'Giày gym Adidas Dropset Trainer 2', 'brand' => 'adidas', 'brand_label' => 'Adidas', 'category' => 'giay-the-thao', 'category_label' => 'Giày thể thao', 'asset_type' => 'sport-shoes', 'image_caption' => 'Ổn định tuyệt đối cho bài squat nặng.', 'palette' => ['#111827', '#EF4444'], 'base_price' => 2490000, 'is_featured' => true, 'rating_avg' => 4.8, 'rating_count' => 221, 'view_count' => 5400, 'sold_count' => 612, 'short_description' => 'Adidas Dropset Trainer 2 thiết kế gót thấp phẳng, tối ưu độ ổn định khi tập tạ nặng.', 'features' => ['Gót bằng phẳng và rộng, phù hợp squat, deadlift và bench press.', 'Vùng gót ổn định tăng cứng, giảm nguy cơ chấn thương.', 'Upper lưới thoáng khí kết hợp TPU cứng giữ form chân.'], 'specs' => ['Loại' => 'Cross-training', 'Đế' => 'Lightmotion + Rubber', 'Mã' => 'AD-DROP2'], 'variants' => $this->colorSizeVariants('ad-drop2', ['Đen/Đỏ', 'Trắng/Đen'], ['39', '40', '41', '42', '43', '44', '45'], 'Mesh + TPU', 350, 2490000, 2990000)],

            ['name' => 'Giày chạy bộ Decathlon Kiprun KS900', 'brand' => 'decathlon', 'brand_label' => 'Decathlon', 'category' => 'giay-the-thao', 'category_label' => 'Giày thể thao', 'asset_type' => 'sport-shoes', 'image_caption' => 'Giá tốt, êm chân cho người mới chạy.', 'palette' => ['#10B981', '#111827'], 'base_price' => 1290000, 'is_featured' => false, 'rating_avg' => 4.6, 'rating_count' => 188, 'view_count' => 3600, 'sold_count' => 445, 'short_description' => 'Kiprun KS900 của Decathlon là lựa chọn tốt cho người mới chạy, êm ái và bền bỉ ở tầm giá hợp lý.', 'features' => ['Đế EVA dày êm ái, phù hợp cự ly trung bình 5-15km.', 'Upper lưới thoáng giúp chân không bí dù chạy lâu.', 'Trọng lượng nhẹ hơn 10% so với dòng tiền nhiệm.'], 'specs' => ['Loại' => 'Road Running', 'Đế' => 'EVA foam', 'Mã' => 'DC-KS900'], 'variants' => $this->colorSizeVariants('dc-ks900', ['Xanh lá/Đen', 'Đen/Xanh'], ['38', '39', '40', '41', '42', '43', '44'], 'Mesh + EVA', 260, 1290000, 1590000)],

            ['name' => 'Giày đa năng Puma Softride Enzo', 'brand' => 'puma', 'brand_label' => 'Puma', 'category' => 'giay-the-thao', 'category_label' => 'Giày thể thao', 'asset_type' => 'sport-shoes', 'image_caption' => 'Mềm mại từng bước, phong cách hàng ngày.', 'palette' => ['#F59E0B', '#1E293B'], 'base_price' => 1490000, 'is_featured' => false, 'rating_avg' => 4.7, 'rating_count' => 142, 'view_count' => 2800, 'sold_count' => 318, 'short_description' => 'Puma Softride Enzo với công nghệ SoftFoam+ mang lại cảm giác đệm siêu mềm, lý tưởng đi bộ và dạo phố.', 'features' => ['SoftFoam+ đệm siêu êm, phù hợp đứng nhiều và đi bộ dài.', 'Knit upper co giãn nhẹ, ôm chân thoải mái không cần buộc dây.', 'Đế cao su ngoài chống trơn trượt trên nhiều bề mặt.'], 'specs' => ['Loại' => 'Lifestyle/Walking', 'Đế' => 'SoftFoam+', 'Mã' => 'PM-SFTE'], 'variants' => $this->colorSizeVariants('pm-sfte', ['Vàng/Đen', 'Đen', 'Trắng'], ['38', '39', '40', '41', '42', '43'], 'Knit + EVA SoftFoam', 270, 1490000, 1890000)],

            ['name' => 'Giày chạy trail Li-Ning Furious Rider', 'brand' => 'li-ning', 'brand_label' => 'Li-Ning', 'category' => 'giay-the-thao', 'category_label' => 'Giày thể thao', 'asset_type' => 'sport-shoes', 'image_caption' => 'Bám địa hình, an toàn mọi cung đường.', 'palette' => ['#EF4444', '#111827'], 'base_price' => 1790000, 'is_featured' => false, 'rating_avg' => 4.6, 'rating_count' => 76, 'view_count' => 1920, 'sold_count' => 203, 'short_description' => 'Li-Ning Furious Rider với đế bám địa hình sâu, phù hợp trail running và trekking nhẹ.', 'features' => ['Đế gai cao su bám địa hình đất, đá dăm tốt.', 'Upper chống nước nhẹ, phù hợp đường ẩm ướt buổi sáng.', 'Cổ giày cao bảo vệ mắt cá chân khi leo dốc.'], 'specs' => ['Loại' => 'Trail Running', 'Đế' => 'Rubber Trail', 'Mã' => 'LN-FR-001'], 'variants' => $this->colorSizeVariants('ln-fr', ['Đỏ/Đen', 'Xanh/Vàng'], ['39', '40', '41', '42', '43', '44'], 'TPU + Mesh chống nước', 320, 1790000, 2190000)],

            // ══ DỤNG CỤ (9) ══
            ['name' => 'Tạ tay cao su 5kg Ocean Sport', 'brand' => 'ocean-sport', 'brand_label' => 'Ocean Sport', 'category' => 'dung-cu-the-thao', 'category_label' => 'Dụng cụ', 'asset_type' => 'dumbbell', 'image_caption' => 'Bọc cao su chống trơn, an toàn khi tập.', 'palette' => ['#10B981', '#1E293B'], 'base_price' => 299000, 'is_featured' => true, 'rating_avg' => 4.7, 'rating_count' => 234, 'view_count' => 4800, 'sold_count' => 712, 'short_description' => 'Tạ tay bọc cao su 5kg Ocean Sport, cầm chắc tay, không gây tiếng ồn khi thả xuống sàn.', 'features' => ['Lõi sắt nguyên khối, bọc cao su dày chống trơn và bảo vệ sàn nhà.', 'Màu sắc phân biệt theo trọng lượng, dễ nhận diện nhanh.', 'Bán lẻ hoặc theo cặp, linh hoạt mua bổ sung.'], 'specs' => ['Trọng lượng' => '5 kg/cái', 'Chất liệu' => 'Sắt + Cao su', 'Mã' => 'OS-DB5'], 'variants' => $this->colorVariants('os-db5', ['Xanh lá 5kg'], 'Sắt + Cao su', 5000, 299000, 349000, 30, 5)],

            ['name' => 'Tạ tay cao su 10kg Decathlon', 'brand' => 'decathlon', 'brand_label' => 'Decathlon', 'category' => 'dung-cu-the-thao', 'category_label' => 'Dụng cụ', 'asset_type' => 'dumbbell', 'image_caption' => 'Nặng hơn cho bài tập trung cấp.', 'palette' => ['#3B82F6', '#1E293B'], 'base_price' => 479000, 'is_featured' => false, 'rating_avg' => 4.6, 'rating_count' => 134, 'view_count' => 2900, 'sold_count' => 384, 'short_description' => 'Tạ tay bọc cao su 10kg Decathlon chịu lực tốt, lõi sắt bền, phù hợp tập tăng cơ tại nhà hoặc phòng gym.', 'features' => ['Cao su bọc dày chống bẩn, bảo vệ sàn và giảm ồn.', 'Tay cầm khía chống trơn, thoải mái giữ tay.', 'Trọng lượng ổn định, không bị chênh lệch giữa hai đầu.'], 'specs' => ['Trọng lượng' => '10 kg/cái', 'Chất liệu' => 'Sắt + Cao su', 'Mã' => 'DC-DB10'], 'variants' => $this->colorVariants('dc-db10', ['Xanh dương 10kg'], 'Sắt + Cao su', 10000, 479000, 569000, 20, 3)],

            ['name' => 'Bộ tạ tay 2-10kg + giá đỡ Decathlon', 'brand' => 'decathlon', 'brand_label' => 'Decathlon', 'category' => 'dung-cu-the-thao', 'category_label' => 'Dụng cụ', 'asset_type' => 'dumbbell', 'image_caption' => 'Bộ 5 đôi tạ tập toàn thân tại nhà.', 'palette' => ['#6366F1', '#1E293B'], 'base_price' => 1490000, 'is_featured' => true, 'rating_avg' => 4.8, 'rating_count' => 178, 'view_count' => 3900, 'sold_count' => 534, 'short_description' => 'Bộ tạ 5 đôi Decathlon 2/4/6/8/10kg, bọc cao su màu sắc, kèm giá đỡ thép tiết kiệm diện tích.', 'features' => ['Đủ mức tạ từ nhẹ đến trung bình, lý tưởng tập toàn thân tại nhà.', 'Giá đỡ thép đi kèm, gọn gàng và dễ lấy.', 'Cao su bọc dày không phai màu, bền theo thời gian.'], 'specs' => ['Gồm' => '5 đôi 2/4/6/8/10kg + giá đỡ', 'Mã' => 'DC-DBSET'], 'variants' => $this->colorVariants('dc-dbset', ['Đa màu (bộ 5 đôi)'], 'Sắt + Cao su', 40000, 1490000, 1890000, 20, 3)],

            ['name' => 'Dây nhảy có đếm số Ocean Sport Pro', 'brand' => 'ocean-sport', 'brand_label' => 'Ocean Sport', 'category' => 'dung-cu-the-thao', 'category_label' => 'Dụng cụ', 'asset_type' => 'jump-rope', 'image_caption' => 'Màn hình LED đếm vòng, calo tự động.', 'palette' => ['#EF4444', '#1E293B'], 'base_price' => 179000, 'is_featured' => false, 'rating_avg' => 4.5, 'rating_count' => 312, 'view_count' => 5200, 'sold_count' => 898, 'short_description' => 'Dây nhảy Ocean Sport Pro với màn hình LED đếm vòng và calo, dây PVC chỉnh dài được.', 'features' => ['Màn hình LED hiển thị vòng nhảy, thời gian và calo tiêu thụ.', 'Dây PVC nhẹ, vòng quay trơn tru ở tốc độ cao.', 'Vòng bi kép chống xoắn, chịu lực tốt.'], 'specs' => ['Chất liệu' => 'PVC + nhôm', 'Chiều dài' => 'Điều chỉnh 2.8-3.0m', 'Mã' => 'OS-JRP'], 'variants' => $this->colorVariants('os-jrp', ['Đỏ/Đen', 'Xanh/Đen', 'Hồng'], 'PVC + Nhôm', 300, 179000, 229000)],

            ['name' => 'Vợt bóng bàn Wilson Tour Series', 'brand' => 'wilson', 'brand_label' => 'Wilson', 'category' => 'dung-cu-the-thao', 'category_label' => 'Dụng cụ', 'asset_type' => 'table-tennis', 'image_caption' => 'Kiểm soát tốt, phản xạ nhạy bén.', 'palette' => ['#EF4444', '#1E293B'], 'base_price' => 389000, 'is_featured' => false, 'rating_avg' => 4.6, 'rating_count' => 87, 'view_count' => 2100, 'sold_count' => 231, 'short_description' => 'Vợt bóng bàn Wilson Tour Series cân bằng giữa tấn công và kiểm soát, phù hợp người chơi phong trào.', 'features' => ['Lớp mút dày vừa (1.8mm) cân bằng tốc độ và kiểm soát.', 'Cán ergonomic vừa tay, thoải mái khi chơi lâu.', 'Phù hợp người chơi phong trào và bán chuyên.'], 'specs' => ['Loại' => 'All-round', 'Độ dày mút' => '1.8mm', 'Mã' => 'WL-TTS'], 'variants' => $this->colorVariants('wl-tts', ['Đỏ/Đen'], 'Gỗ + Cao su', 180, 389000, 479000, 20, 3)],

            ['name' => 'Bóng đá số 5 Decathlon Kipsta F500', 'brand' => 'decathlon', 'brand_label' => 'Decathlon', 'category' => 'dung-cu-the-thao', 'category_label' => 'Dụng cụ', 'asset_type' => 'football', 'image_caption' => 'Bóng tiêu chuẩn cho sân cỏ nhân tạo.', 'palette' => ['#111827', '#F59E0B'], 'base_price' => 249000, 'is_featured' => false, 'rating_avg' => 4.5, 'rating_count' => 254, 'view_count' => 4100, 'sold_count' => 667, 'short_description' => 'Bóng đá Decathlon F500 số 5 thích hợp sân cỏ nhân tạo, giữ tốc độ và hình dạng tốt sau nhiều trận.', 'features' => ['Vỏ polyuréthane bền, chịu được bề mặt cỏ nhân tạo.', 'Giữ hình dạng tốt sau nhiều giờ chơi.', 'Phù hợp tiêu chuẩn thi đấu phong trào.'], 'specs' => ['Kích cỡ' => 'Số 5', 'Chất liệu' => 'PU', 'Mã' => 'DC-F500'], 'variants' => $this->colorVariants('dc-f500', ['Đen/Vàng', 'Trắng/Đen'], 'PU + Cao su', 450, 249000, 299000, 40, 5)],

            ['name' => 'Thảm yoga TPE 6mm Ocean Sport', 'brand' => 'ocean-sport', 'brand_label' => 'Ocean Sport', 'category' => 'dung-cu-the-thao', 'category_label' => 'Dụng cụ', 'asset_type' => 'yoga-mat', 'image_caption' => 'Không trơn, không mùi, bảo vệ khớp gối.', 'palette' => ['#6366F1', '#A78BFA'], 'base_price' => 259000, 'is_featured' => true, 'rating_avg' => 4.7, 'rating_count' => 289, 'view_count' => 5100, 'sold_count' => 743, 'short_description' => 'Thảm yoga TPE 6mm Ocean Sport, không trơn hai mặt, nhẹ và cuộn gọn tiện mang đến lớp.', 'features' => ['Chất liệu TPE thân thiện môi trường, không PVC độc hại.', 'Hai mặt chống trơn, phù hợp cả sàn gỗ và gạch men.', 'Cuộn gọn và kèm dây đeo, tiện mang theo.'], 'specs' => ['Kích thước' => '183x61cm', 'Độ dày' => '6mm', 'Chất liệu' => 'TPE', 'Mã' => 'OS-YM6'], 'variants' => $this->colorVariants('os-ym6', ['Tím/Xanh', 'Xanh lá/Đen', 'Hồng/Đen'], 'TPE', 900, 259000, 329000)],

            ['name' => 'Bóng tập gym wall ball 5kg Decathlon', 'brand' => 'decathlon', 'brand_label' => 'Decathlon', 'category' => 'dung-cu-the-thao', 'category_label' => 'Dụng cụ', 'asset_type' => 'football', 'image_caption' => 'Nảy ít, lý tưởng bài ném tường CrossFit.', 'palette' => ['#F97316', '#111827'], 'base_price' => 499000, 'is_featured' => false, 'rating_avg' => 4.6, 'rating_count' => 64, 'view_count' => 1350, 'sold_count' => 128, 'short_description' => 'Wall ball 5kg Decathlon với lớp vỏ mịn, nảy ít, phù hợp bài ném tường, squat kết hợp ném cho CrossFit.', 'features' => ['Vỏ vinyl chịu lực va đập cao, độ bền tốt khi ném liên tục.', 'Cát bên trong không bị vón cục, giữ trọng lượng đồng đều.', 'Đường kính 30cm, dễ bắt và ném chính xác.'], 'specs' => ['Trọng lượng' => '5kg', 'Đường kính' => '30cm', 'Mã' => 'DC-WB5'], 'variants' => $this->colorVariants('dc-wb5', ['Cam/Đen'], 'Vinyl + cát', 5000, 499000, 599000, 15, 3)],

            ['name' => 'Bộ đôi vợt bóng bàn Decathlon TTP100', 'brand' => 'decathlon', 'brand_label' => 'Decathlon', 'category' => 'dung-cu-the-thao', 'category_label' => 'Dụng cụ', 'asset_type' => 'table-tennis', 'image_caption' => 'Combo tiết kiệm cho cả gia đình.', 'palette' => ['#EF4444', '#3B82F6'], 'base_price' => 199000, 'is_featured' => false, 'rating_avg' => 4.4, 'rating_count' => 193, 'view_count' => 3200, 'sold_count' => 456, 'short_description' => 'Combo 2 vợt + 3 bóng bóng bàn Decathlon TTP100 cho gia đình, văn phòng và nhóm bạn giải trí.', 'features' => ['Cán nhựa nhẹ, lớp mút cơ bản phù hợp chơi giải trí.', 'Kèm 3 bóng trắng tiêu chuẩn 40mm.', 'Giá hợp lý, lý tưởng mua tặng quà hoặc văn phòng.'], 'specs' => ['Gồm' => '2 vợt + 3 bóng', 'Mã' => 'DC-TTSET'], 'variants' => $this->colorVariants('dc-ttset', ['Đỏ/Xanh'], 'Nhựa + Cao su', 360, 199000, 249000, 35, 5)],

            // ══ PHỤ KIỆN (8) ══
            ['name' => 'Bình nước thể thao 750ml Nike Hydration', 'brand' => 'nike', 'brand_label' => 'Nike', 'category' => 'phu-kien-the-thao', 'category_label' => 'Phụ kiện', 'asset_type' => 'water-bottle', 'image_caption' => 'Nắp bật một tay, không rò rỉ.', 'palette' => ['#2563EB', '#111827'], 'base_price' => 199000, 'is_featured' => true, 'rating_avg' => 4.7, 'rating_count' => 278, 'view_count' => 5600, 'sold_count' => 834, 'short_description' => 'Bình nước Nike 750ml nắp bật một tay, chất liệu Tritan không BPA, dễ vệ sinh.', 'features' => ['Nắp flip-top bật một tay thuận tiện khi tập.', 'Chất liệu Tritan không BPA, an toàn và bền hơn nhựa thường.', 'Miệng rộng dễ thêm đá hoặc vệ sinh bên trong.'], 'specs' => ['Dung tích' => '750ml', 'Chất liệu' => 'Tritan không BPA', 'Mã' => 'NK-BTL750'], 'variants' => $this->colorVariants('nk-btl750', ['Xanh dương', 'Đen', 'Đỏ', 'Xanh lá'], 'Tritan', 180, 199000, 249000)],

            ['name' => 'Bình nước giữ nhiệt thể thao Adidas 600ml', 'brand' => 'adidas', 'brand_label' => 'Adidas', 'category' => 'phu-kien-the-thao', 'category_label' => 'Phụ kiện', 'asset_type' => 'water-bottle', 'image_caption' => 'Giữ lạnh 24h, nóng 12h tiêu chuẩn.', 'palette' => ['#111827', '#F59E0B'], 'base_price' => 349000, 'is_featured' => false, 'rating_avg' => 4.7, 'rating_count' => 134, 'view_count' => 2800, 'sold_count' => 389, 'short_description' => 'Bình nước giữ nhiệt Adidas 600ml inox 18/8, giữ lạnh 24h, nóng 12h, nắp chống rò rỉ.', 'features' => ['Inox 18/8 không gây mùi và an toàn thực phẩm.', 'Cách nhiệt chân không giữ lạnh 24h, nóng 12h.', 'Nắp khóa chống rò rỉ, an toàn bỏ túi gym.'], 'specs' => ['Dung tích' => '600ml', 'Chất liệu' => 'Inox 18/8', 'Mã' => 'AD-BTL600'], 'variants' => $this->colorVariants('ad-btl600', ['Đen/Vàng', 'Trắng/Đen', 'Xanh navy'], 'Inox 18/8', 300, 349000, 429000)],

            ['name' => 'Băng bảo vệ cổ tay thể thao Ocean Sport', 'brand' => 'ocean-sport', 'brand_label' => 'Ocean Sport', 'category' => 'phu-kien-the-thao', 'category_label' => 'Phụ kiện', 'asset_type' => 'wrist-support', 'image_caption' => 'Hỗ trợ cổ tay, giảm đau khi tập nặng.', 'palette' => ['#6366F1', '#111827'], 'base_price' => 89000, 'is_featured' => false, 'rating_avg' => 4.6, 'rating_count' => 321, 'view_count' => 6200, 'sold_count' => 1120, 'short_description' => 'Băng cổ tay thể thao Ocean Sport neoprene chắc, co giãn tốt, giảm đau và bảo vệ khớp khi tập tạ.', 'features' => ['Neoprene ôm cổ tay chắc, giữ khớp ổn định.', 'Velcro điều chỉnh được độ chặt phù hợp mọi cỡ tay.', 'Bán theo cặp, dùng đồng bộ hai tay.'], 'specs' => ['Chất liệu' => 'Neoprene', 'Bán' => 'Theo cặp', 'Mã' => 'OS-WS'], 'variants' => $this->colorVariants('os-ws', ['Đen', 'Xanh/Đen', 'Đỏ/Đen'], 'Neoprene', 120, 89000, 119000)],

            ['name' => 'Găng tay tập gym Adidas Essential', 'brand' => 'adidas', 'brand_label' => 'Adidas', 'category' => 'phu-kien-the-thao', 'category_label' => 'Phụ kiện', 'asset_type' => 'wrist-support', 'image_caption' => 'Bảo vệ lòng bàn tay khi tập tạ.', 'palette' => ['#111827', '#F59E0B'], 'base_price' => 149000, 'is_featured' => false, 'rating_avg' => 4.5, 'rating_count' => 187, 'view_count' => 3400, 'sold_count' => 543, 'short_description' => 'Găng tay Adidas Essential bảo vệ lòng bàn tay khỏi chai và trầy xước khi tập tạ, xà và gym.', 'features' => ['Lòng bàn tay đệm da tổng hợp chống chai.', 'Mu bàn tay lưới thoáng mát, tránh tay bị ẩm.', 'Velcro cổ tay cơ bản hỗ trợ nhẹ khi tập.'], 'specs' => ['Chất liệu' => 'PU + lưới thoáng', 'Mã' => 'AD-GLV'], 'variants' => $this->sizeVariants('ad-glv', 'Đen/Vàng', ['S', 'M', 'L', 'XL'], 'PU + Lưới', 90, 149000, 199000)],

            ['name' => 'Túi gym đa năng Under Armour Undeniable', 'brand' => 'under-armour', 'brand_label' => 'Under Armour', 'category' => 'phu-kien-the-thao', 'category_label' => 'Phụ kiện', 'asset_type' => 'gym-bag', 'image_caption' => 'Ngăn chứa giày riêng, chống thấm tốt.', 'palette' => ['#1D4ED8', '#111827'], 'base_price' => 799000, 'is_featured' => true, 'rating_avg' => 4.8, 'rating_count' => 142, 'view_count' => 3100, 'sold_count' => 387, 'short_description' => 'Túi gym Under Armour Undeniable 30L ngăn chứa giày riêng chống thấm, dây đeo vai có đệm.', 'features' => ['Ngăn giày riêng phủ chất chống thấm giữ đồ khô ráo.', 'Dung tích 30L đủ đựng quần áo, bình nước và phụ kiện.', 'Dây đeo điều chỉnh được, lưng có đệm êm.'], 'specs' => ['Dung tích' => '30L', 'Chất liệu' => 'Polyester 600D', 'Mã' => 'UA-UNDEF'], 'variants' => $this->colorVariants('ua-undef', ['Xanh navy', 'Đen', 'Xám'], 'Polyester 600D', 720, 799000, 999000)],

            ['name' => 'Miếng bảo vệ đầu gối Ocean Sport', 'brand' => 'ocean-sport', 'brand_label' => 'Ocean Sport', 'category' => 'phu-kien-the-thao', 'category_label' => 'Phụ kiện', 'asset_type' => 'knee-guard', 'image_caption' => 'Bảo vệ khớp gối khi chơi bóng chuyền.', 'palette' => ['#EF4444', '#111827'], 'base_price' => 129000, 'is_featured' => false, 'rating_avg' => 4.6, 'rating_count' => 241, 'view_count' => 4600, 'sold_count' => 734, 'short_description' => 'Miếng bảo vệ đầu gối Ocean Sport EVA dày 1cm, ôm gối tốt cho bóng chuyền, cầu lông, bóng rổ.', 'features' => ['Đệm EVA dày 1cm hấp thụ va chạm khi tiếp đất.', 'Vải neoprene co giãn 4 chiều, ôm gối không trượt.', 'Bán theo cặp, dùng đồng bộ hai chân.'], 'specs' => ['Chất liệu' => 'Neoprene + EVA', 'Bán' => 'Theo cặp', 'Mã' => 'OS-KG'], 'variants' => $this->sizeVariants('os-kg', 'Đỏ/Đen', ['S', 'M', 'L', 'XL'], 'Neoprene + EVA', 250, 129000, 169000)],

            ['name' => 'Đai lưng tập tạ Decathlon 700', 'brand' => 'decathlon', 'brand_label' => 'Decathlon', 'category' => 'phu-kien-the-thao', 'category_label' => 'Phụ kiện', 'asset_type' => 'wrist-support', 'image_caption' => 'Hỗ trợ lưng, an toàn khi deadlift nặng.', 'palette' => ['#F59E0B', '#1E293B'], 'base_price' => 249000, 'is_featured' => false, 'rating_avg' => 4.7, 'rating_count' => 89, 'view_count' => 2100, 'sold_count' => 267, 'short_description' => 'Đai lưng tập tạ Decathlon da PU dày 6mm, hỗ trợ cột sống khi squat nặng và deadlift.', 'features' => ['Da PU dày 6mm, cứng vừa đủ để hỗ trợ cột sống thắt lưng.', 'Khóa khóa nhanh, tháo lắp dễ giữa các set.', 'Chiều rộng phía sau 10cm, bề mặt đai cong theo lưng tự nhiên.'], 'specs' => ['Chất liệu' => 'Da PU', 'Độ dày' => '6mm', 'Mã' => 'DC-WB700'], 'variants' => $this->sizeVariants('dc-wb700', 'Đen/Vàng', ['S', 'M', 'L', 'XL'], 'Da PU', 600, 249000, 319000)],

            ['name' => 'Túi đeo hông chạy bộ Ocean Sport', 'brand' => 'ocean-sport', 'brand_label' => 'Ocean Sport', 'category' => 'phu-kien-the-thao', 'category_label' => 'Phụ kiện', 'asset_type' => 'gym-bag', 'image_caption' => 'Gọn nhẹ, chứa điện thoại và đồ cần thiết.', 'palette' => ['#0EA5E9', '#111827'], 'base_price' => 119000, 'is_featured' => false, 'rating_avg' => 4.5, 'rating_count' => 167, 'view_count' => 3200, 'sold_count' => 489, 'short_description' => 'Túi đeo hông chạy bộ Ocean Sport chống nước, có ngăn điện thoại và dây đai điều chỉnh 360°.', 'features' => ['Vải nylon chống nước giữ đồ khô ngay cả khi đổ mồ hôi nhiều.', 'Dây đai điều chỉnh 360° ôm vừa hông mọi vóc dáng.', 'Cổng USB tiện sạc điện thoại khi chạy dài.'], 'specs' => ['Dung tích' => '1.5L', 'Chất liệu' => 'Nylon chống nước', 'Mã' => 'OS-RBP'], 'variants' => $this->colorVariants('os-rbp', ['Xanh biển', 'Đen', 'Vàng'], 'Nylon chống nước', 150, 119000, 159000)],
        ];
    }
}
