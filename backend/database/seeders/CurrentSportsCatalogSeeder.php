<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CurrentSportsCatalogSeeder extends Seeder
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

        echo "✅ CurrentSportsCatalogSeeder hoàn tất: 20 sản phẩm cầu lông, bóng chuyền, pickleball.\n";
    }

    private function seedBrands(): array
    {
        $brands = [
            [
                'name' => 'Kuikma',
                'slug' => 'kuikma',
                'description' => 'Thương hiệu thể thao dùng vợt của Decathlon, nổi bật ở cầu lông và pickleball.',
            ],
            [
                'name' => 'Kipsta',
                'slug' => 'kipsta',
                'description' => 'Thương hiệu thể thao đồng đội của Decathlon, phù hợp bóng chuyền và phụ kiện sân bãi.',
            ],
            [
                'name' => 'Artengo',
                'slug' => 'artengo',
                'description' => 'Thương hiệu dụng cụ và giày sân vợt của Decathlon.',
            ],
            [
                'name' => 'Decathlon',
                'slug' => 'decathlon',
                'description' => 'Dòng sản phẩm phổ thông do Decathlon phát triển cho người chơi mới bắt đầu.',
            ],
            [
                'name' => 'Facolos',
                'slug' => 'facolos',
                'description' => 'Thương hiệu pickleball thiên về hiệu năng cao, phù hợp người chơi nâng cao.',
            ],
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

                continue;
            }

            $payload['created_at'] = $this->now;
            $map[$brand['slug']] = DB::table('brands')->insertGetId($payload);
        }

        return $map;
    }

    private function seedCategories(): array
    {
        $parentSlug = 'the-thao-vot-va-bong';
        $parentId = $this->upsertCategory(
            null,
            'Thể thao vợt & bóng',
            $parentSlug,
            'Danh mục chuyên cho cầu lông, bóng chuyền và pickleball.',
            'products/sports/categories/the-thao-vot-va-bong.svg',
            60
        );

        Storage::disk('public')->put(
            'products/sports/categories/the-thao-vot-va-bong.svg',
            $this->buildCategorySvg('Thể thao vợt & bóng', ['#0F766E', '#F97316'])
        );

        $children = [
            [
                'name' => 'Cầu lông',
                'slug' => 'cau-long',
                'description' => 'Vợt, giày và phụ kiện cầu lông cho người mới đến người chơi phong trào.',
                'sort_order' => 61,
                'palette' => ['#0EA5E9', '#0F766E'],
            ],
            [
                'name' => 'Bóng chuyền',
                'slug' => 'bong-chuyen',
                'description' => 'Bóng, lưới và phụ kiện bóng chuyền trong nhà, bãi biển.',
                'sort_order' => 62,
                'palette' => ['#F97316', '#EA580C'],
            ],
            [
                'name' => 'Pickleball',
                'slug' => 'pickleball',
                'description' => 'Vợt, giày và set pickleball cho nhu cầu giải trí đến nâng cao.',
                'sort_order' => 63,
                'palette' => ['#22C55E', '#0891B2'],
            ],
        ];

        $map = [];

        foreach ($children as $child) {
            $imagePath = 'products/sports/categories/'.$child['slug'].'.svg';
            Storage::disk('public')->put($imagePath, $this->buildCategorySvg($child['name'], $child['palette']));

            $map[$child['slug']] = $this->upsertCategory(
                $parentId,
                $child['name'],
                $child['slug'],
                $child['description'],
                $imagePath,
                $child['sort_order']
            );
        }

        return $map;
    }

    private function upsertCategory(
        ?int $parentId,
        string $name,
        string $slug,
        string $description,
        ?string $imagePath,
        int $sortOrder
    ): int {
        $existing = DB::table('categories')->where('slug', $slug)->first();

        $payload = [
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $slug,
            'image' => $imagePath,
            'description' => $description,
            'sort_order' => $sortOrder,
            'is_active' => 1,
            'updated_at' => $this->now,
        ];

        if ($existing) {
            DB::table('categories')->where('category_id', $existing->category_id)->update($payload);

            return $existing->category_id;
        }

        $payload['created_at'] = $this->now;

        return DB::table('categories')->insertGetId($payload);
    }

    private function upsertProduct(array $product, array $brandMap, array $categoryMap): void
    {
        $slug = $product['slug'] ?? Str::slug($product['name']);
        $categoryId = $categoryMap[$product['sport']];
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
            $sku = $this->ensureUniqueSku($variant['sku']);

            DB::table('product_variants')->insert([
                'product_id' => $productId,
                'sku' => $sku,
                'barcode' => $this->buildBarcode($product['sport'], $slug, $index),
                'variant_name' => $variant['variant_name'],
                'color' => $variant['color'],
                'size' => $variant['size'],
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

        $imageRows = [
            [
                'product_id' => $productId,
                'variant_id' => null,
                'image_url' => $assets['main'],
                'alt_text' => $product['name'].' - ảnh chính',
                'is_main' => 1,
                'sort_order' => 0,
                'created_at' => $this->now,
            ],
            [
                'product_id' => $productId,
                'variant_id' => null,
                'image_url' => $assets['angle'],
                'alt_text' => $product['name'].' - góc nghiêng',
                'is_main' => 0,
                'sort_order' => 1,
                'created_at' => $this->now,
            ],
            [
                'product_id' => $productId,
                'variant_id' => null,
                'image_url' => $assets['detail'],
                'alt_text' => $product['name'].' - chi tiết',
                'is_main' => 0,
                'sort_order' => 2,
                'created_at' => $this->now,
            ],
        ];

        DB::table('product_images')->insert($imageRows);

        DB::table('products')->where('product_id', $productId)->update([
            'min_price' => min($prices),
            'max_price' => max($prices),
            'thumbnail_url' => $assets['main'],
            'updated_at' => $this->now,
        ]);
    }

    private function generateProductAssets(array $product, string $slug): array
    {
        $dir = 'products/sports/'.$product['sport'].'/'.$slug;
        $main = $dir.'/main.svg';
        $angle = $dir.'/angle.svg';
        $detail = $dir.'/detail.svg';
        $variant = $dir.'/variant.svg';

        Storage::disk('public')->put($main, $this->buildPackshotSvg($product, 'main'));
        Storage::disk('public')->put($angle, $this->buildPackshotSvg($product, 'angle'));
        Storage::disk('public')->put($detail, $this->buildPackshotSvg($product, 'detail'));
        Storage::disk('public')->put($variant, $this->buildPackshotSvg($product, 'variant'));

        return [
            'main' => $main,
            'angle' => $angle,
            'detail' => $detail,
            'variant' => $variant,
        ];
    }

    private function buildPackshotSvg(array $product, string $mode): string
    {
        $palette = $product['palette'];
        $brand = $this->svgEscape(Str::upper($product['brand_label']));
        $sport = $this->svgEscape(Str::upper($product['sport_label']));
        $name = $this->svgEscape($product['name']);
        $subtitle = $this->svgEscape($product['image_caption']);
        $price = number_format($product['base_price'], 0, ',', '.').' đ';
        $accentA = $palette[0];
        $accentB = $palette[1];

        $shape = $this->renderShape($product['asset_type'], $palette, $mode);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1400" height="1400" viewBox="0 0 1400 1400" role="img" aria-label="{$name}">
  <defs>
    <linearGradient id="hero" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$accentA}" />
      <stop offset="100%" stop-color="{$accentB}" />
    </linearGradient>
    <filter id="shadow" x="-20%" y="-20%" width="140%" height="140%">
      <feDropShadow dx="0" dy="24" stdDeviation="26" flood-color="#0f172a" flood-opacity="0.12" />
    </filter>
  </defs>
  <rect width="1400" height="1400" rx="40" fill="#ffffff" />
  <rect x="68" y="68" width="196" height="48" rx="24" fill="#F8FAFC" stroke="#E2E8F0" />
  <text x="166" y="99" text-anchor="middle" font-family="Arial, sans-serif" font-size="22" font-weight="700" fill="#0F172A">{$brand}</text>
  <rect x="1120" y="68" width="212" height="48" rx="24" fill="url(#hero)" />
  <text x="1226" y="99" text-anchor="middle" font-family="Arial, sans-serif" font-size="22" font-weight="700" fill="#ffffff">{$sport}</text>
  <ellipse cx="700" cy="860" rx="360" ry="68" fill="#E2E8F0" opacity="0.9" />
  <g filter="url(#shadow)">
    {$shape}
  </g>
  <text x="92" y="1142" font-family="Arial, sans-serif" font-size="54" font-weight="700" fill="#0F172A">{$name}</text>
  <text x="92" y="1192" font-family="Arial, sans-serif" font-size="28" fill="#475569">{$subtitle}</text>
  <rect x="92" y="1236" width="228" height="68" rx="34" fill="#0F172A" />
  <text x="206" y="1280" text-anchor="middle" font-family="Arial, sans-serif" font-size="30" font-weight="700" fill="#ffffff">{$price}</text>
  <text x="1308" y="1290" text-anchor="end" font-family="Arial, sans-serif" font-size="22" fill="#94A3B8">Ocean Sport Seed</text>
</svg>
SVG;
    }

    private function renderShape(string $assetType, array $palette, string $mode): string
    {
        $accentA = $palette[0];
        $accentB = $palette[1];
        $metal = '#CBD5E1';
        $dark = '#0F172A';
        $light = '#F8FAFC';

        return match ($assetType) {
            'badminton-racket' => $this->badmintonRacketSvg($accentA, $accentB, $metal, $dark, $mode),
            'badminton-shoes' => $this->shoeSvg($accentA, $accentB, $dark, $mode, true),
            'volleyball-ball' => $this->volleyballSvg($accentA, $accentB, $dark, $light, $mode),
            'volleyball-net' => $this->volleyballNetSvg($accentA, $accentB, $dark, $mode),
            'volleyball-guard' => $this->kneePadSvg($accentA, $accentB, $dark, $mode),
            'pickleball-paddle' => $this->pickleballPaddleSvg($accentA, $accentB, $dark, $mode),
            'pickleball-set' => $this->pickleballSetSvg($accentA, $accentB, $dark, $mode),
            'pickleball-shoes' => $this->shoeSvg($accentA, $accentB, $dark, $mode, false),
            default => '<circle cx="700" cy="640" r="220" fill="url(#hero)" />',
        };
    }

    private function badmintonRacketSvg(string $accentA, string $accentB, string $metal, string $dark, string $mode): string
    {
        $rotate = $mode === 'angle' ? '-18 700 640' : ($mode === 'detail' ? '-8 700 620' : '-12 700 650');
        $scale = $mode === 'detail' ? 'scale(1.06)' : 'scale(1)';

        return <<<SVG
<g transform="rotate({$rotate}) {$scale}">
  <ellipse cx="705" cy="470" rx="170" ry="220" fill="none" stroke="{$dark}" stroke-width="22" />
  <ellipse cx="705" cy="470" rx="142" ry="190" fill="#ffffff" stroke="{$metal}" stroke-width="4" stroke-dasharray="18 14" />
  <line x1="555" y1="340" x2="855" y2="600" stroke="{$metal}" stroke-width="4" />
  <line x1="555" y1="600" x2="855" y2="340" stroke="{$metal}" stroke-width="4" />
  <line x1="610" y1="286" x2="610" y2="654" stroke="{$metal}" stroke-width="3" />
  <line x1="700" y1="252" x2="700" y2="688" stroke="{$metal}" stroke-width="3" />
  <line x1="790" y1="286" x2="790" y2="654" stroke="{$metal}" stroke-width="3" />
  <rect x="678" y="664" width="54" height="270" rx="24" fill="{$accentA}" />
  <rect x="664" y="924" width="82" height="166" rx="28" fill="{$accentB}" />
  <rect x="655" y="1010" width="100" height="78" rx="28" fill="#111827" />
  <circle cx="705" cy="612" r="28" fill="{$accentB}" />
</g>
SVG;
    }

    private function shoeSvg(string $accentA, string $accentB, string $dark, string $mode, bool $slim): string
    {
        $translateY = $mode === 'detail' ? 12 : 0;
        $width = $slim ? 520 : 560;

        return <<<SVG
<g transform="translate(430 {$translateY})">
  <path d="M120 690 C200 620, 360 570, {$width} 620 L700 676 C748 686, 788 724, 788 770 C788 816, 744 840, 700 840 H126 C74 840, 40 810, 40 770 C40 742, 58 716, 88 702 Z" fill="{$accentA}" />
  <path d="M170 674 C254 620, 360 602, 520 644 L622 676 C652 684, 676 702, 690 726 H160 C152 708, 154 694, 170 674 Z" fill="{$accentB}" opacity="0.95" />
  <path d="M84 770 H790 C786 818, 750 852, 706 852 H130 C82 852, 48 822, 40 770 Z" fill="#111827" />
  <path d="M148 782 H708" stroke="#F8FAFC" stroke-width="12" stroke-linecap="round" opacity="0.9" />
  <path d="M248 666 L334 712" stroke="#E2E8F0" stroke-width="12" stroke-linecap="round" />
  <path d="M298 646 L390 704" stroke="#E2E8F0" stroke-width="12" stroke-linecap="round" />
  <path d="M360 632 L448 694" stroke="#E2E8F0" stroke-width="12" stroke-linecap="round" />
  <path d="M426 628 L512 690" stroke="#E2E8F0" stroke-width="12" stroke-linecap="round" />
  <circle cx="640" cy="726" r="18" fill="{$dark}" />
</g>
SVG;
    }

    private function volleyballSvg(string $accentA, string $accentB, string $dark, string $light, string $mode): string
    {
        $radius = $mode === 'detail' ? 250 : 228;

        return <<<SVG
<g>
  <circle cx="700" cy="640" r="{$radius}" fill="{$light}" stroke="{$dark}" stroke-width="18" />
  <path d="M560 432 C650 500, 666 770, 602 858" fill="none" stroke="{$accentA}" stroke-width="36" stroke-linecap="round" />
  <path d="M842 438 C760 512, 742 772, 794 850" fill="none" stroke="{$accentB}" stroke-width="36" stroke-linecap="round" />
  <path d="M496 618 C620 570, 792 574, 904 626" fill="none" stroke="{$dark}" stroke-width="26" stroke-linecap="round" opacity="0.85" />
  <path d="M518 716 C642 668, 780 670, 876 714" fill="none" stroke="{$dark}" stroke-width="18" stroke-linecap="round" opacity="0.3" />
  <circle cx="700" cy="640" r="56" fill="{$accentB}" opacity="0.14" />
</g>
SVG;
    }

    private function volleyballNetSvg(string $accentA, string $accentB, string $dark, string $mode): string
    {
        $extra = $mode === 'detail' ? 40 : 0;

        return <<<SVG
<g transform="translate(0 {$extra})">
  <rect x="408" y="488" width="28" height="430" rx="12" fill="{$dark}" />
  <rect x="964" y="488" width="28" height="430" rx="12" fill="{$dark}" />
  <rect x="436" y="512" width="528" height="248" rx="18" fill="#ffffff" stroke="{$dark}" stroke-width="16" />
  <path d="M436 560 H964 M436 608 H964 M436 656 H964 M436 704 H964" stroke="#CBD5E1" stroke-width="8" />
  <path d="M510 512 V760 M584 512 V760 M658 512 V760 M732 512 V760 M806 512 V760 M880 512 V760" stroke="#CBD5E1" stroke-width="8" />
  <rect x="420" y="494" width="560" height="22" rx="11" fill="url(#hero)" />
  <circle cx="484" cy="846" r="92" fill="{$accentB}" />
  <path d="M438 806 C476 790, 526 790, 560 814" fill="none" stroke="#ffffff" stroke-width="12" stroke-linecap="round" />
  <path d="M438 864 C474 850, 522 850, 556 874" fill="none" stroke="#ffffff" stroke-width="12" stroke-linecap="round" />
  <path d="M484 756 C524 788, 530 906, 492 936" fill="none" stroke="#ffffff" stroke-width="12" stroke-linecap="round" />
</g>
SVG;
    }

    private function kneePadSvg(string $accentA, string $accentB, string $dark, string $mode): string
    {
        $shift = $mode === 'angle' ? 16 : 0;

        return <<<SVG
<g transform="translate(0 {$shift})">
  <rect x="474" y="454" width="168" height="330" rx="82" fill="{$accentA}" />
  <rect x="760" y="454" width="168" height="330" rx="82" fill="{$accentB}" />
  <ellipse cx="558" cy="620" rx="58" ry="86" fill="#ffffff" opacity="0.22" />
  <ellipse cx="844" cy="620" rx="58" ry="86" fill="#ffffff" opacity="0.22" />
  <path d="M500 522 C546 506, 594 506, 632 530" fill="none" stroke="{$dark}" stroke-width="8" opacity="0.4" />
  <path d="M786 522 C832 506, 880 506, 918 530" fill="none" stroke="{$dark}" stroke-width="8" opacity="0.4" />
</g>
SVG;
    }

    private function pickleballPaddleSvg(string $accentA, string $accentB, string $dark, string $mode): string
    {
        $rotate = $mode === 'angle' ? '-14 700 660' : '-8 700 660';

        return <<<SVG
<g transform="rotate({$rotate})">
  <rect x="528" y="316" width="344" height="430" rx="150" fill="#ffffff" stroke="{$dark}" stroke-width="20" />
  <rect x="556" y="344" width="288" height="374" rx="126" fill="url(#hero)" />
  <circle cx="700" cy="530" r="92" fill="#ffffff" opacity="0.14" />
  <rect x="650" y="730" width="100" height="234" rx="42" fill="{$dark}" />
  <rect x="662" y="780" width="76" height="168" rx="32" fill="{$accentB}" />
  <circle cx="700" cy="1030" r="42" fill="{$accentA}" />
</g>
SVG;
    }

    private function pickleballSetSvg(string $accentA, string $accentB, string $dark, string $mode): string
    {
        return <<<SVG
<g>
  <g transform="translate(-90 -18) rotate(-10 610 628)">
    <rect x="460" y="320" width="248" height="350" rx="112" fill="#ffffff" stroke="{$dark}" stroke-width="16" />
    <rect x="480" y="340" width="208" height="310" rx="94" fill="{$accentA}" />
    <rect x="548" y="650" width="72" height="210" rx="28" fill="{$dark}" />
  </g>
  <g transform="translate(124 8) rotate(12 792 644)">
    <rect x="666" y="336" width="248" height="350" rx="112" fill="#ffffff" stroke="{$dark}" stroke-width="16" />
    <rect x="686" y="356" width="208" height="310" rx="94" fill="{$accentB}" />
    <rect x="754" y="666" width="72" height="210" rx="28" fill="{$dark}" />
  </g>
  <circle cx="610" cy="860" r="36" fill="#ffffff" stroke="{$dark}" stroke-width="12" />
  <circle cx="792" cy="894" r="36" fill="#ffffff" stroke="{$dark}" stroke-width="12" />
  <path d="M590 860 H630 M610 840 V880" stroke="{$dark}" stroke-width="6" />
  <path d="M772 894 H812 M792 874 V914" stroke="{$dark}" stroke-width="6" />
</g>
SVG;
    }

    private function buildCategorySvg(string $title, array $palette): string
    {
        $safeTitle = $this->svgEscape($title);
        $accentA = $palette[0];
        $accentB = $palette[1];

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="900" viewBox="0 0 1200 900" role="img" aria-label="{$safeTitle}">
  <defs>
    <linearGradient id="cat" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$accentA}" />
      <stop offset="100%" stop-color="{$accentB}" />
    </linearGradient>
  </defs>
  <rect width="1200" height="900" rx="40" fill="#ffffff" />
  <rect x="90" y="90" width="1020" height="720" rx="36" fill="url(#cat)" opacity="0.12" />
  <circle cx="362" cy="452" r="160" fill="url(#cat)" opacity="0.9" />
  <circle cx="844" cy="332" r="86" fill="#ffffff" opacity="0.7" />
  <circle cx="782" cy="566" r="112" fill="#ffffff" opacity="0.5" />
  <text x="90" y="760" font-family="Arial, sans-serif" font-size="72" font-weight="700" fill="#0F172A">{$safeTitle}</text>
  <text x="90" y="814" font-family="Arial, sans-serif" font-size="28" fill="#475569">Ocean Sport collection</text>
</svg>
SVG;
    }

    private function buildProductDescription(array $product): string
    {
        $features = '';

        foreach ($product['features'] as $feature) {
            $features .= '<li>'.e($feature).'</li>';
        }

        $specs = '';
        foreach ($product['specs'] as $label => $value) {
            $specs .= '<li><strong>'.e($label).':</strong> '.e($value).'</li>';
        }

        return '<div class="product-description">'
            .'<h3>Mô tả sản phẩm</h3>'
            .'<p>'.e($product['short_description']).'</p>'
            .'<h3>Điểm nổi bật</h3><ul>'.$features.'</ul>'
            .'<h3>Thông số nhanh</h3><ul>'.$specs.'</ul>'
            .'<p><em>Giá seed tham chiếu theo dữ liệu công khai được tổng hợp ngày 05/06/2026.</em></p>'
            .'</div>';
    }

    private function ensureUniqueSku(string $sku): string
    {
        $candidate = Str::upper($sku);
        $suffix = 1;

        while (DB::table('product_variants')->where('sku', $candidate)->exists()) {
            $candidate = Str::upper($sku).'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function buildBarcode(string $sport, string $slug, int $index): string
    {
        $prefix = match ($sport) {
            'cau-long' => 'BDM',
            'bong-chuyen' => 'VLB',
            default => 'PKB',
        };

        return $prefix.strtoupper(substr(md5($slug.$index), 0, 10));
    }

    private function svgEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function singleVariant(
        string $sku,
        string $variantName,
        ?string $color,
        ?string $size,
        string $material,
        int $weightGram,
        int $price,
        int $compareAt,
        int $stock = 18,
        int $safetyStock = 3
    ): array {
        return [[
            'sku' => $sku,
            'variant_name' => $variantName,
            'color' => $color,
            'size' => $size,
            'material' => $material,
            'weight_gram' => $weightGram,
            'price' => $price,
            'compare_at_price' => $compareAt,
            'stock' => $stock,
            'safety_stock' => $safetyStock,
        ]];
    }

    private function sizeVariants(
        string $skuBase,
        string $color,
        array $sizes,
        string $material,
        int $weightGram,
        int $price,
        int $compareAt,
        int $stock = 14,
        int $safetyStock = 3
    ): array {
        $variants = [];

        foreach ($sizes as $size) {
            $variants[] = [
                'sku' => $skuBase.'-'.$size,
                'variant_name' => 'Size '.$size,
                'color' => $color,
                'size' => (string) $size,
                'material' => $material,
                'weight_gram' => $weightGram,
                'price' => $price,
                'compare_at_price' => $compareAt,
                'stock' => $stock,
                'safety_stock' => $safetyStock,
            ];
        }

        return $variants;
    }

    private function gripVariants(
        string $skuBase,
        string $color,
        array $grips,
        string $material,
        int $weightGram,
        int $price,
        int $compareAt
    ): array {
        $variants = [];

        foreach ($grips as $grip) {
            $variants[] = [
                'sku' => $skuBase.'-'.strtolower($grip),
                'variant_name' => 'Cán '.$grip,
                'color' => $color,
                'size' => $grip,
                'material' => $material,
                'weight_gram' => $weightGram,
                'price' => $price,
                'compare_at_price' => $compareAt,
                'stock' => 12,
                'safety_stock' => 2,
            ];
        }

        return $variants;
    }

    private function products(): array
    {
        return [
            [
                'name' => 'Vợt cầu lông BR160',
                'brand' => 'kuikma',
                'brand_label' => 'Kuikma',
                'sport' => 'cau-long',
                'sport_label' => 'Cầu lông',
                'asset_type' => 'badminton-racket',
                'image_caption' => 'Khung thép bền bỉ cho người mới bắt đầu.',
                'palette' => ['#14B8A6', '#0EA5E9'],
                'base_price' => 299000,
                'is_featured' => true,
                'rating_avg' => 4.6,
                'rating_count' => 86,
                'view_count' => 1860,
                'sold_count' => 312,
                'short_description' => 'Vợt cầu lông người lớn BR160 phù hợp cho các buổi tập đầu tiên, cân bằng dễ kiểm soát và khung chắc tay.',
                'features' => [
                    'Khung nhôm kết hợp thân thép cho độ bền cao ở tần suất chơi cơ bản.',
                    'Thiết kế isometric giúp vùng điểm ngọt rộng, dễ sửa lỗi khi đánh lệch tâm.',
                    'Độ cân bằng trung tính giúp người mới làm quen kỹ thuật nhanh hơn.',
                ],
                'specs' => [
                    'Nguồn giá' => 'Decathlon Việt Nam',
                    'Cập nhật giá' => '05/06/2026',
                    'Mã tham chiếu' => '8545288',
                ],
                'variants' => $this->gripVariants('bdm-br160', 'Đen/Xanh lá', ['G4', 'G5'], 'Nhôm + thép', 95, 299000, 349000),
            ],
            [
                'name' => 'Vợt cầu lông BR Sensation 190 Blue',
                'brand' => 'kuikma',
                'brand_label' => 'Kuikma',
                'sport' => 'cau-long',
                'sport_label' => 'Cầu lông',
                'asset_type' => 'badminton-racket',
                'image_caption' => 'Mẫu 87g dễ thuần tay cho người mới chơi.',
                'palette' => ['#2563EB', '#38BDF8'],
                'base_price' => 299000,
                'is_featured' => true,
                'rating_avg' => 4.7,
                'rating_count' => 124,
                'view_count' => 2410,
                'sold_count' => 406,
                'short_description' => 'BR Sensation 190 Blue nổi bật ở trọng lượng nhẹ 87g, hỗ trợ người chơi mới tạo lực dễ hơn và bớt mỏi tay.',
                'features' => [
                    'Thân vợt mềm giúp người mới dễ tạo lực khi cổ tay chưa khỏe.',
                    'Điểm ngọt rộng để tăng độ ổn định trong các pha chạm cầu đầu tiên.',
                    'Phối màu xanh hiện đại, dễ nhận diện khi lên sân.',
                ],
                'specs' => [
                    'Nguồn giá' => 'Decathlon Việt Nam',
                    'Cập nhật giá' => '05/06/2026',
                    'Mã tham chiếu' => '8981537',
                ],
                'variants' => $this->gripVariants('bdm-s190-blue', 'Xanh dương', ['G4', 'G5'], 'Graphite + composite', 87, 299000, 359000),
            ],
            [
                'name' => 'Vợt cầu lông BR Sensation 530 Green Black',
                'brand' => 'kuikma',
                'brand_label' => 'Kuikma',
                'sport' => 'cau-long',
                'sport_label' => 'Cầu lông',
                'asset_type' => 'badminton-racket',
                'image_caption' => 'Dòng control racket nhẹ tay cho người chơi thường xuyên.',
                'palette' => ['#16A34A', '#111827'],
                'base_price' => 769000,
                'is_featured' => true,
                'rating_avg' => 4.8,
                'rating_count' => 74,
                'view_count' => 1988,
                'sold_count' => 184,
                'short_description' => 'BR Sensation 530 Green Black là vợt graphite 100% cân bằng đều, hợp người chơi phong trào cần kiểm soát tốt.',
                'features' => [
                    'Khung isometric tăng diện tích tiếp xúc cầu trong các pha phòng thủ nhanh.',
                    'Trọng lượng 87g và cân bằng đều tạo cảm giác linh hoạt trong điều cầu.',
                    'Cấu trúc graphite 100% cho độ ổn định tốt hơn ở nhịp tập thường xuyên.',
                ],
                'specs' => [
                    'Nguồn giá' => 'Decathlon Việt Nam',
                    'Cập nhật giá' => '05/06/2026',
                    'Mã tham chiếu' => '8736758',
                ],
                'variants' => $this->gripVariants('bdm-s530', 'Xanh lá/Đen', ['G4', 'G5'], 'Graphite 100%', 87, 769000, 899000),
            ],
            [
                'name' => 'Vợt cầu lông BR Perform 590 Purple',
                'brand' => 'kuikma',
                'brand_label' => 'Kuikma',
                'sport' => 'cau-long',
                'sport_label' => 'Cầu lông',
                'asset_type' => 'badminton-racket',
                'image_caption' => 'Điểm cân bằng hơi nặng đầu cho người chơi trung cấp.',
                'palette' => ['#7C3AED', '#EC4899'],
                'base_price' => 709000,
                'is_featured' => true,
                'rating_avg' => 4.9,
                'rating_count' => 66,
                'view_count' => 2160,
                'sold_count' => 142,
                'short_description' => 'BR Perform 590 Purple thiên về lực đánh và cảm giác cầu rõ ràng hơn, phù hợp người chơi trung cấp muốn nâng nhịp tấn công.',
                'features' => [
                    'Thân vợt 6.6 mm siêu mỏng cải thiện phản hồi và khả năng truyền lực.',
                    'Điểm cân bằng nặng đầu hỗ trợ các pha đập cầu có độ xuyên tốt hơn.',
                    'Khung chịu lực căng cao phù hợp người chơi đã có nền kỹ thuật.',
                ],
                'specs' => [
                    'Nguồn giá' => 'Decathlon Việt Nam',
                    'Cập nhật giá' => '05/06/2026',
                    'Mã tham chiếu' => '8862675',
                ],
                'variants' => $this->gripVariants('bdm-p590', 'Tím', ['G4', 'G5'], 'Graphite + resin', 84, 709000, 1199000),
            ],
            [
                'name' => 'Vợt cầu lông BR 500 White',
                'brand' => 'kuikma',
                'brand_label' => 'Kuikma',
                'sport' => 'cau-long',
                'sport_label' => 'Cầu lông',
                'asset_type' => 'badminton-racket',
                'image_caption' => 'Thiết kế trắng tối giản cho người chơi phong trào muốn lên trình.',
                'palette' => ['#CBD5E1', '#94A3B8'],
                'base_price' => 399000,
                'is_featured' => false,
                'rating_avg' => 4.6,
                'rating_count' => 58,
                'view_count' => 1540,
                'sold_count' => 133,
                'short_description' => 'BR 500 White cân bằng giữa độ linh hoạt và kiểm soát, là lựa chọn hợp lý cho người chơi đã quen kỹ thuật cơ bản.',
                'features' => [
                    'Khung và thân graphite 100% cho cảm giác đánh thoát và chính xác.',
                    'Màu trắng thanh lịch, dễ lên concept hình ảnh bán hàng.',
                    'Phù hợp người chơi phong trào muốn chuyển từ vợt entry lên dòng dễ đánh hơn.',
                ],
                'specs' => [
                    'Nguồn giá' => 'Decathlon blog / bảng tham chiếu',
                    'Cập nhật giá' => '05/06/2026',
                    'Giá tham chiếu' => '399.000 đ',
                ],
                'variants' => $this->gripVariants('bdm-br500-white', 'Trắng', ['G4', 'G5'], 'Graphite 100%', 90, 399000, 499000),
            ],
            [
                'name' => 'Vợt cầu lông BR Discover',
                'brand' => 'decathlon',
                'brand_label' => 'Decathlon',
                'sport' => 'cau-long',
                'sport_label' => 'Cầu lông',
                'asset_type' => 'badminton-racket',
                'image_caption' => 'Mức giá mềm để làm bộ vợt nhập môn.',
                'palette' => ['#FACC15', '#22C55E'],
                'base_price' => 245000,
                'is_featured' => false,
                'rating_avg' => 4.5,
                'rating_count' => 91,
                'view_count' => 1782,
                'sold_count' => 360,
                'short_description' => 'BR Discover là lựa chọn dễ tiếp cận cho học sinh, sinh viên và người chơi gia đình cần một cây vợt bền, dễ làm quen.',
                'features' => [
                    'Khung tiêu chuẩn giúp ổn định khi tiếp cầu lệch tâm.',
                    'Thiết kế gọn, dễ bán theo combo vợt + cầu + quấn cán.',
                    'Giá tham chiếu thấp, phù hợp làm sản phẩm entry trong catalog.',
                ],
                'specs' => [
                    'Nguồn giá' => 'Decathlon blog / bảng tham chiếu',
                    'Cập nhật giá' => '05/06/2026',
                    'Giá tham chiếu' => '245.000 đ',
                ],
                'variants' => $this->gripVariants('bdm-discover', 'Vàng/Xanh', ['G4', 'G5'], 'Thép', 104, 245000, 299000),
            ],
            [
                'name' => 'Giày cầu lông BS Sensation 500 White Blue',
                'brand' => 'kuikma',
                'brand_label' => 'Kuikma',
                'sport' => 'cau-long',
                'sport_label' => 'Cầu lông',
                'asset_type' => 'badminton-shoes',
                'image_caption' => 'Giày cầu lông trẻ em ổn định cho bước di chuyển đầu tiên.',
                'palette' => ['#2563EB', '#E2E8F0'],
                'base_price' => 599000,
                'is_featured' => false,
                'rating_avg' => 4.7,
                'rating_count' => 39,
                'view_count' => 870,
                'sold_count' => 61,
                'short_description' => 'BS Sensation 500 trắng/xanh dương là đôi giày trẻ em dễ đi, bám sàn và giữ chân khá ổn cho các buổi tập cầu lông đầu tiên.',
                'features' => [
                    'Form ôm gọn, phù hợp bước di chuyển ngang cơ bản trên sân trong nhà.',
                    'Phối trắng xanh sạch mắt, thuận lợi cho trưng bày cùng vợt và phụ kiện.',
                    'Mức giá trung cấp dễ ghép combo cho người chơi nhỏ tuổi.',
                ],
                'specs' => [
                    'Nguồn giá' => 'Decathlon Việt Nam',
                    'Cập nhật giá' => '05/06/2026',
                    'Mã tham chiếu' => '8867425',
                ],
                'variants' => $this->sizeVariants('bdm-bss500', 'Trắng/Xanh dương', ['33', '34', '35', '36'], 'Mesh + PU', 520, 599000, 699000),
            ],
            [
                'name' => 'Giày cầu lông BS Lite 560 White Sea Blue',
                'brand' => 'kuikma',
                'brand_label' => 'Kuikma',
                'sport' => 'cau-long',
                'sport_label' => 'Cầu lông',
                'asset_type' => 'badminton-shoes',
                'image_caption' => 'Giảm chấn tốt hơn cho tay vợt nhí trình độ tầm trung.',
                'palette' => ['#0EA5E9', '#38BDF8'],
                'base_price' => 1299000,
                'is_featured' => true,
                'rating_avg' => 4.8,
                'rating_count' => 24,
                'view_count' => 620,
                'sold_count' => 42,
                'short_description' => 'BS Lite 560 trắng/xanh biển là mẫu giày trẻ em cao hơn một nấc về giảm chấn, độ thoáng và độ ổn định khi di chuyển nhanh.',
                'features' => [
                    'Đệm gót DHN kết hợp EVA giúp hấp thụ lực tốt hơn ở bước chạm đất.',
                    'Cấu trúc M hỗ trợ ổn định khi đổi hướng ngang trên sân.',
                    'Upper thoáng khí và trọng lượng nhẹ giúp mang lâu vẫn dễ chịu.',
                ],
                'specs' => [
                    'Nguồn giá' => 'Decathlon Việt Nam',
                    'Cập nhật giá' => '05/06/2026',
                    'Mã tham chiếu' => '8804495',
                ],
                'variants' => $this->sizeVariants('bdm-bsl560', 'Trắng/Xanh biển', ['35', '36', '37', '38'], 'TPU + EVA + mesh', 540, 1299000, 1499000),
            ],
            [
                'name' => 'Bóng chuyền đa dụng BV Crystal Orange',
                'brand' => 'kipsta',
                'brand_label' => 'Kipsta',
                'sport' => 'bong-chuyen',
                'sport_label' => 'Bóng chuyền',
                'asset_type' => 'volleyball-ball',
                'image_caption' => 'Bóng nhẹ cho vui chơi gia đình trong nhà và ngoài bãi biển.',
                'palette' => ['#F97316', '#FB923C'],
                'base_price' => 99000,
                'is_featured' => false,
                'rating_avg' => 4.4,
                'rating_count' => 68,
                'view_count' => 1280,
                'sold_count' => 284,
                'short_description' => 'BV Crystal Orange là mẫu bóng nhẹ size 4, phù hợp trẻ em và người mới tập làm quen các pha chuyền cơ bản.',
                'features' => [
                    'Khối lượng nhẹ giúp rally dễ hơn với người chơi mới.',
                    'Chất liệu mềm tạo cảm giác chạm bóng dễ chịu, ít sợ tay.',
                    'Dùng linh hoạt trong nhà hoặc ngoài bãi cát.',
                ],
                'specs' => [
                    'Nguồn giá' => 'Decathlon Việt Nam',
                    'Cập nhật giá' => '05/06/2026',
                    'Mã tham chiếu' => '8973757',
                ],
                'variants' => $this->singleVariant('vlb-bvcrystal', 'Size 4', 'Cam', '4', 'PVC mềm', 200, 99000, 129000, 26, 5),
            ],
            [
                'name' => 'Bóng chuyền bãi biển BV100 Classic Turquoise',
                'brand' => 'kipsta',
                'brand_label' => 'Kipsta',
                'sport' => 'bong-chuyen',
                'sport_label' => 'Bóng chuyền',
                'asset_type' => 'volleyball-ball',
                'image_caption' => 'Mềm tay và vui nhộn cho các buổi chơi bãi biển.',
                'palette' => ['#14B8A6', '#06B6D4'],
                'base_price' => 259000,
                'is_featured' => false,
                'rating_avg' => 4.6,
                'rating_count' => 44,
                'view_count' => 968,
                'sold_count' => 112,
                'short_description' => 'BV100 Classic Turquoise là bóng chuyền bãi biển size 5 cho nhu cầu giải trí, ưu tiên độ mềm và cảm giác chạm bóng thân thiện.',
                'features' => [
                    'Bề mặt mềm giúp người mới tự tin khi chuyền và đỡ bóng.',
                    'Kích thước size 5 phù hợp nhóm bạn chơi bãi biển hoặc sân cát.',
                    'Thiết kế màu xanh biển dễ nhận diện trong concept summer sport.',
                ],
                'specs' => [
                    'Nguồn giá' => 'Decathlon Việt Nam',
                    'Cập nhật giá' => '05/06/2026',
                    'Mã tham chiếu' => '8816711',
                ],
                'variants' => $this->singleVariant('vlb-bv100', 'Size 5', 'Xanh turquoise', '5', 'PVC + butyl', 260, 259000, 319000, 20, 4),
            ],
            [
                'name' => 'Bóng chuyền VB500 Classic White Blue',
                'brand' => 'kipsta',
                'brand_label' => 'Kipsta',
                'sport' => 'bong-chuyen',
                'sport_label' => 'Bóng chuyền',
                'asset_type' => 'volleyball-ball',
                'image_caption' => 'Bóng laminate bền chắc cho sân trong nhà phong trào.',
                'palette' => ['#1D4ED8', '#60A5FA'],
                'base_price' => 479000,
                'is_featured' => true,
                'rating_avg' => 4.8,
                'rating_count' => 52,
                'view_count' => 1365,
                'sold_count' => 167,
                'short_description' => 'VB500 Classic là mẫu bóng dành cho người chơi trung cấp cần cảm giác bóng ổn định hơn ở sân trong nhà và các trận giao hữu.',
                'features' => [
                    'Lớp phủ laminate cho cảm giác bóng đều và bền hơn khi tập thường xuyên.',
                    'Trọng lượng theo chuẩn chính thức giúp làm quen cảm giác thi đấu tốt hơn.',
                    'Tông trắng xanh rất hợp danh mục bóng chuyền indoor.',
                ],
                'specs' => [
                    'Nguồn giá' => 'Decathlon Việt Nam',
                    'Cập nhật giá' => '05/06/2026',
                    'Mã tham chiếu' => '8927919',
                ],
                'variants' => $this->singleVariant('vlb-vb500', 'Size 5 thi đấu', 'Trắng/Xanh dương', '5', 'Laminate tổng hợp', 270, 479000, 569000, 18, 4),
            ],
            [
                'name' => 'Băng bảo vệ gối bóng chuyền VKP100 Black',
                'brand' => 'kipsta',
                'brand_label' => 'Kipsta',
                'sport' => 'bong-chuyen',
                'sport_label' => 'Bóng chuyền',
                'asset_type' => 'volleyball-guard',
                'image_caption' => 'Đệm gối cơ bản cho người mới tập kỹ thuật đỡ bóng.',
                'palette' => ['#111827', '#475569'],
                'base_price' => 199000,
                'is_featured' => false,
                'rating_avg' => 4.5,
                'rating_count' => 37,
                'view_count' => 842,
                'sold_count' => 95,
                'short_description' => 'VKP100 là băng bảo vệ gối cơ bản dành cho người mới tập bóng chuyền, ưu tiên sự thoải mái và tự tin khi lao người cứu bóng.',
                'features' => [
                    'Đệm foam 20 mm giảm khó chịu khi chạm sàn ở mức cơ bản.',
                    'Chất liệu co giãn dễ ôm chân, phù hợp nhiều thể trạng.',
                    'Sản phẩm tiện để bán kèm bóng và lưới tập gia đình.',
                ],
                'specs' => [
                    'Nguồn giá' => 'Decathlon Việt Nam',
                    'Cập nhật giá' => '05/06/2026',
                    'Mã tham chiếu' => '8670049',
                ],
                'variants' => $this->sizeVariants('vlb-vkp100', 'Đen', ['S', 'M', 'L'], 'Polyester + foam PU', 180, 199000, 249000, 16, 3),
            ],
            [
                'name' => 'Bộ lưới bóng chuyền bãi biển BV500 Yellow',
                'brand' => 'kipsta',
                'brand_label' => 'Kipsta',
                'sport' => 'bong-chuyen',
                'sport_label' => 'Bóng chuyền',
                'asset_type' => 'volleyball-net',
                'image_caption' => 'Set lưới beach volley linh hoạt cho sân cát phong trào.',
                'palette' => ['#EAB308', '#F97316'],
                'base_price' => 2499000,
                'is_featured' => true,
                'rating_avg' => 4.8,
                'rating_count' => 18,
                'view_count' => 510,
                'sold_count' => 28,
                'short_description' => 'BV500 Yellow là bộ lưới bóng chuyền bãi biển phù hợp cho nhóm bạn, resort hoặc sân cộng đồng cần set-up nhanh và gọn.',
                'features' => [
                    'Tổng thể gọn trong một bộ, dễ vận chuyển và dựng sân ngoài trời.',
                    'Phù hợp nhu cầu beach volley giải trí và tập luyện bán chuyên.',
                    'Màu vàng nổi bật giúp bộ hình ảnh sản phẩm bắt mắt trên nền trắng.',
                ],
                'specs' => [
                    'Nguồn giá' => 'Decathlon Việt Nam',
                    'Cập nhật giá' => '05/06/2026',
                    'Mã tham chiếu' => '8480571',
                ],
                'variants' => $this->singleVariant('vlb-bv500-set', 'Bộ chuẩn bãi biển', 'Vàng', 'One Size', 'Nhôm + lưới polyester', 6200, 2499000, 2799000, 7, 1),
            ],
            [
                'name' => 'Bộ sân bóng chuyền bãi biển BV900 Official',
                'brand' => 'kipsta',
                'brand_label' => 'Kipsta',
                'sport' => 'bong-chuyen',
                'sport_label' => 'Bóng chuyền',
                'asset_type' => 'volleyball-net',
                'image_caption' => 'Set sân beach volley hoàn chỉnh với 3 mức chiều cao chính thức.',
                'palette' => ['#F97316', '#EA580C'],
                'base_price' => 2999000,
                'is_featured' => true,
                'rating_avg' => 4.9,
                'rating_count' => 12,
                'view_count' => 402,
                'sold_count' => 17,
                'short_description' => 'BV900 Official là bộ sân beach volley hoàn chỉnh cho nhu cầu setup nghiêm túc hơn, có kèm đường biên và balo đựng.',
                'features' => [
                    'Có 3 mức chiều cao chính thức 2.24 m, 2.35 m và 2.43 m.',
                    'Dựng và tháo tương đối nhanh khi có 2 người hỗ trợ.',
                    'Phù hợp sân cát cộng đồng, bãi biển sự kiện và CLB phong trào.',
                ],
                'specs' => [
                    'Nguồn giá' => 'Decathlon Việt Nam',
                    'Cập nhật giá' => '05/06/2026',
                    'Mã tham chiếu' => '8408762',
                ],
                'variants' => $this->singleVariant('vlb-bv900-set', 'Bộ sân official', 'Cam/Đen', 'One Size', 'Nhôm + polyester', 7800, 2999000, 3399000, 5, 1),
            ],
            [
                'name' => 'Pickleball Paddle 100 Black',
                'brand' => 'artengo',
                'brand_label' => 'Artengo',
                'sport' => 'pickleball',
                'sport_label' => 'Pickleball',
                'asset_type' => 'pickleball-paddle',
                'image_caption' => 'Vợt entry-level dễ điều khiển cho người mới tập đều.',
                'palette' => ['#111827', '#334155'],
                'base_price' => 399000,
                'is_featured' => false,
                'rating_avg' => 4.6,
                'rating_count' => 47,
                'view_count' => 1488,
                'sold_count' => 173,
                'short_description' => 'Pickleball Paddle 100 Black hướng tới người mới tập thường xuyên, dễ cầm và cho cảm giác chạm bóng êm tay.',
                'features' => [
                    'Trọng lượng 230g giúp thao tác dễ và ít mỏi khi tập thời gian dài.',
                    'Mặt vợt sợi thủy tinh kết hợp carbon, lõi polypropylene cho độ êm tốt.',
                    'Màu đen tối giản phù hợp trưng bày cùng set bóng hoặc túi.',
                ],
                'specs' => [
                    'Nguồn giá' => 'Decathlon Việt Nam',
                    'Cập nhật giá' => '05/06/2026',
                    'Mã tham chiếu' => '8767131',
                ],
                'variants' => $this->singleVariant('pkb-p100', 'One Size', 'Đen', 'One Size', 'Fiberglass + carbon + polypropylene', 230, 399000, 499000, 16, 3),
            ],
            [
                'name' => 'Vợt Pickleball Kuikma Open Blue',
                'brand' => 'kuikma',
                'brand_label' => 'Kuikma',
                'sport' => 'pickleball',
                'sport_label' => 'Pickleball',
                'asset_type' => 'pickleball-paddle',
                'image_caption' => 'Lõi tổ ong 16 mm cho cảm giác đánh chắc và kiểm soát.',
                'palette' => ['#2563EB', '#06B6D4'],
                'base_price' => 699000,
                'is_featured' => true,
                'rating_avg' => 4.8,
                'rating_count' => 58,
                'view_count' => 1825,
                'sold_count' => 144,
                'short_description' => 'Kuikma Open Blue là mẫu vợt pickleball bán chạy cho người mới chơi thường xuyên, cân bằng tốt giữa lực và độ dễ điều khiển.',
                'features' => [
                    'Độ dày 16 mm cho lực đánh vững và cảm giác bóng chắc hơn dòng mỏng.',
                    'Trọng lượng 225g giữ được sự linh hoạt ở các pha phản xạ gần lưới.',
                    'Thiết kế xanh dương dễ lên ảnh packshot phông trắng.',
                ],
                'specs' => [
                    'Nguồn giá' => 'Decathlon Việt Nam',
                    'Cập nhật giá' => '05/06/2026',
                    'Mã tham chiếu' => '8941064',
                ],
                'variants' => $this->singleVariant('pkb-open', 'One Size', 'Xanh dương', 'One Size', 'Fiberglass + polypropylene honeycomb', 225, 699000, 799000, 14, 3),
            ],
            [
                'name' => 'Bộ 2 vợt pickleball + 2 bóng + túi Play',
                'brand' => 'decathlon',
                'brand_label' => 'Decathlon',
                'sport' => 'pickleball',
                'sport_label' => 'Pickleball',
                'asset_type' => 'pickleball-set',
                'image_caption' => 'Bộ starter set đầy đủ cho hai người chơi.',
                'palette' => ['#F97316', '#22C55E'],
                'base_price' => 1099000,
                'is_featured' => true,
                'rating_avg' => 4.9,
                'rating_count' => 33,
                'view_count' => 1056,
                'sold_count' => 87,
                'short_description' => 'Set Play gồm 2 vợt, 2 bóng và 1 túi đựng, cực hợp để người mới bước vào pickleball mà không cần mua lẻ nhiều món.',
                'features' => [
                    'Combo đủ đồ để hai người vào sân ngay sau khi mở hộp.',
                    'Vợt 220g khá thân thiện với người lớn lẫn thiếu niên.',
                    'Rất phù hợp làm sản phẩm featured ở landing page pickleball.',
                ],
                'specs' => [
                    'Nguồn giá' => 'Decathlon Việt Nam',
                    'Cập nhật giá' => '05/06/2026',
                    'Mã tham chiếu' => '8969343',
                ],
                'variants' => $this->singleVariant('pkb-play-set', 'Set 2 vợt', 'Cam/Xanh', 'One Size', 'Composite + lưới túi', 650, 1099000, 1299000, 11, 2),
            ],
            [
                'name' => 'Vợt Pickleball EliteX 16MM Blue',
                'brand' => 'facolos',
                'brand_label' => 'Facolos',
                'sport' => 'pickleball',
                'sport_label' => 'Pickleball',
                'asset_type' => 'pickleball-paddle',
                'image_caption' => 'Hiệu năng cao hơn cho người chơi đã có nhịp đánh ổn định.',
                'palette' => ['#2563EB', '#1D4ED8'],
                'base_price' => 4500000,
                'is_featured' => true,
                'rating_avg' => 4.9,
                'rating_count' => 21,
                'view_count' => 740,
                'sold_count' => 29,
                'short_description' => 'EliteX 16MM Blue nằm ở phân khúc cao hơn, hướng đến người chơi pickleball muốn cảm giác kiểm soát, độ ổn định và độ bám mặt vợt tốt.',
                'features' => [
                    'Lõi tổ ong ElasticPP dày 16 mm cho cảm giác bóng đầm và chắc.',
                    'Bề mặt carbon phủ gốm tăng độ bám bóng khi tạo xoáy.',
                    'Hợp người chơi đã có nền tảng và muốn nâng cấp cảm giác thi đấu.',
                ],
                'specs' => [
                    'Nguồn giá' => 'Decathlon Việt Nam',
                    'Cập nhật giá' => '05/06/2026',
                    'Mã tham chiếu' => '9010633',
                ],
                'variants' => $this->singleVariant('pkb-elitex16', 'One Size', 'Xanh dương', 'One Size', 'Carbon + ElasticPP', 235, 4500000, 4900000, 8, 1),
            ],
            [
                'name' => 'Giày tennis/pickleball nam All Court Light Grey Blue',
                'brand' => 'artengo',
                'brand_label' => 'Artengo',
                'sport' => 'pickleball',
                'sport_label' => 'Pickleball',
                'asset_type' => 'pickleball-shoes',
                'image_caption' => 'Đế all-court bám tốt cho pickleball và tennis phong trào.',
                'palette' => ['#94A3B8', '#0EA5E9'],
                'base_price' => 1399000,
                'is_featured' => true,
                'rating_avg' => 4.8,
                'rating_count' => 41,
                'view_count' => 1322,
                'sold_count' => 76,
                'short_description' => 'Mẫu giày all-court xám nhạt/xanh dương của Artengo phù hợp người chơi pickleball nam cần độ bám và cảm giác đổi hướng nhanh.',
                'features' => [
                    'Đế đa mặt sân cho cảm giác bám và chuyển hướng linh hoạt.',
                    'Form thể thao gọn, dễ phối cùng quần short hoặc set pickleball cơ bản.',
                    'Tông xám nhạt/xanh dương rất hợp chụp packshot nền trắng.',
                ],
                'specs' => [
                    'Nguồn giá' => 'Decathlon Việt Nam',
                    'Cập nhật giá' => '05/06/2026',
                    'Mã tham chiếu' => '8750854',
                ],
                'variants' => $this->sizeVariants('pkb-allcourt-m', 'Xám nhạt/Xanh dương', ['39', '40', '41', '42', '43'], 'Mesh + rubber all-court', 710, 1399000, 1699000, 12, 2),
            ],
            [
                'name' => 'Giày pickleball nam Essential White',
                'brand' => 'artengo',
                'brand_label' => 'Artengo',
                'sport' => 'pickleball',
                'sport_label' => 'Pickleball',
                'asset_type' => 'pickleball-shoes',
                'image_caption' => 'Đôi giày entry-level trắng sạch cho người mới chơi pickleball.',
                'palette' => ['#E5E7EB', '#9CA3AF'],
                'base_price' => 539000,
                'is_featured' => false,
                'rating_avg' => 4.5,
                'rating_count' => 28,
                'view_count' => 996,
                'sold_count' => 63,
                'short_description' => 'Giày Essential White là lựa chọn mở đầu dễ tiếp cận cho người chơi pickleball cần một đôi giày sáng màu, dễ phối và đủ ổn định.',
                'features' => [
                    'Giá tham chiếu dễ chịu cho người mới bước vào môn pickleball.',
                    'Màu trắng gọn gàng, hợp concept storefront thể thao hiện đại.',
                    'Có thể bán kèm vợt Paddle 100 hoặc set Play để lên combo nhập môn.',
                ],
                'specs' => [
                    'Nguồn giá' => 'Decathlon blog / bảng tham chiếu',
                    'Cập nhật giá' => '05/06/2026',
                    'Giá tham chiếu' => '539.000 đ',
                ],
                'variants' => $this->sizeVariants('pkb-essential-white', 'Trắng', ['39', '40', '41', '42', '43'], 'Textile + rubber', 690, 539000, 639000, 13, 2),
            ],
        ];
    }
}
