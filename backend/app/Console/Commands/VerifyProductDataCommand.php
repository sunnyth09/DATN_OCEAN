<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyProductDataCommand extends Command
{
    protected $signature = 'products:verify';

    protected $description = 'Kiểm tra tính toàn vẹn của dữ liệu sản phẩm sau khi refactor';

    public function handle(): int
    {
        $this->info('══════════════════════════════════════════');
        $this->info('  🔍 KIỂM TRA DỮ LIỆU SẢN PHẨM');
        $this->info('══════════════════════════════════════════');

        $errors = 0;
        $warnings = 0;

        // 1. Tổng số sản phẩm
        $productCount = DB::table('products')->whereNull('deleted_at')->count();
        $this->checkResult('Tổng sản phẩm', $productCount, 100, $errors);

        // 2. Brands
        $brandCount = DB::table('brands')->count();
        $this->checkResult('Tổng brands', $brandCount, 7, $errors);

        // 3. Categories
        $parentCats = DB::table('categories')->whereNull('parent_id')->count();
        $childCats = DB::table('categories')->whereNotNull('parent_id')->count();
        $this->checkResult('Parent categories', $parentCats, 5, $errors);
        $this->checkResult('Child categories', $childCats, 20, $errors);

        // 4. Mỗi SP có ít nhất 1 variant
        $noVariants = DB::table('products')
            ->whereNull('deleted_at')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('product_variants')
                    ->whereColumn('product_variants.product_id', 'products.product_id');
            })->count();
        if ($noVariants > 0) {
            $this->error("  ❌ {$noVariants} sản phẩm KHÔNG có variant");
            $errors++;
        } else {
            $this->info('  ✅ Mọi sản phẩm đều có variant');
        }

        // 5. Mỗi SP có ít nhất 1 ảnh (is_main)
        $noMainImage = DB::table('products')
            ->whereNull('deleted_at')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('product_images')
                    ->whereColumn('product_images.product_id', 'products.product_id')
                    ->where('product_images.is_main', 1);
            })->count();
        if ($noMainImage > 0) {
            $this->error("  ❌ {$noMainImage} sản phẩm KHÔNG có ảnh chính (is_main)");
            $errors++;
        } else {
            $this->info('  ✅ Mọi sản phẩm đều có ảnh chính');
        }

        // 6. Mỗi category con có ảnh
        $catNoImage = DB::table('categories')
            ->whereNotNull('parent_id')
            ->whereNull('image')
            ->count();
        if ($catNoImage > 0) {
            $this->warn("  ⚠️ {$catNoImage} child categories thiếu ảnh");
            $warnings++;
        } else {
            $this->info('  ✅ Mọi child category đều có ảnh đại diện');
        }

        // 7. Không duplicate slug
        $dupSlugs = DB::table('products')
            ->select('slug', DB::raw('COUNT(*) as cnt'))
            ->whereNull('deleted_at')
            ->groupBy('slug')
            ->having('cnt', '>', 1)
            ->count();
        if ($dupSlugs > 0) {
            $this->error("  ❌ {$dupSlugs} slug bị trùng lặp");
            $errors++;
        } else {
            $this->info('  ✅ Không có slug trùng lặp');
        }

        // 8. Giá hợp lệ
        $invalidPrice = DB::table('products')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('min_price', '<=', 0)->orWhere('max_price', '<=', 0);
            })->count();
        if ($invalidPrice > 0) {
            $this->error("  ❌ {$invalidPrice} sản phẩm có giá <= 0");
            $errors++;
        } else {
            $this->info('  ✅ Tất cả sản phẩm có giá hợp lệ');
        }

        // 9. Orphan images
        $orphanImages = DB::table('product_images')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('products')
                    ->whereColumn('products.product_id', 'product_images.product_id');
            })->count();
        if ($orphanImages > 0) {
            $this->error("  ❌ {$orphanImages} ảnh mồ côi (orphan)");
            $errors++;
        } else {
            $this->info('  ✅ Không có orphan images');
        }

        // 10. Orphan variants
        $orphanVariants = DB::table('product_variants')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('products')
                    ->whereColumn('products.product_id', 'product_variants.product_id');
            })->count();
        if ($orphanVariants > 0) {
            $this->error("  ❌ {$orphanVariants} variants mồ côi (orphan)");
            $errors++;
        } else {
            $this->info('  ✅ Không có orphan variants');
        }

        // 11. Tổng variant count
        $totalVariants = DB::table('product_variants')->count();
        $this->info("  📊 Tổng biến thể: {$totalVariants}");

        // 12. Tổng images
        $totalImages = DB::table('product_images')->count();
        $this->info("  📷 Tổng ảnh: {$totalImages}");

        // 13. Duplicate SKU
        $dupSkus = DB::table('product_variants')
            ->select('sku', DB::raw('COUNT(*) as cnt'))
            ->groupBy('sku')
            ->having('cnt', '>', 1)
            ->count();
        if ($dupSkus > 0) {
            $this->error("  ❌ {$dupSkus} SKU bị trùng");
            $errors++;
        } else {
            $this->info('  ✅ Không có SKU trùng');
        }

        // 14. Status: chỉ active
        $activeCount = DB::table('products')->where('status', 'active')->whereNull('deleted_at')->count();
        $this->info("  📊 Sản phẩm active: {$activeCount}/{$productCount}");

        // 15. Thống kê phân bổ theo category
        $this->newLine();
        $this->info('── Phân bổ sản phẩm theo danh mục ──');
        $catStats = DB::table('products')
            ->join('categories', 'products.category_id', '=', 'categories.category_id')
            ->select('categories.name', DB::raw('COUNT(*) as cnt'))
            ->whereNull('products.deleted_at')
            ->groupBy('categories.name')
            ->orderBy('cnt', 'desc')
            ->get();

        foreach ($catStats as $stat) {
            $this->line("  {$stat->name}: {$stat->cnt} SP");
        }

        // Summary
        $this->newLine();
        $this->info('══════════════════════════════════════════');
        if ($errors === 0 && $warnings === 0) {
            $this->info('  ✅ DỮ LIỆU HOÀN TOÀN SẠCH!');
        } elseif ($errors === 0) {
            $this->warn("  ⚠️ {$warnings} cảnh báo, không có lỗi nghiêm trọng");
        } else {
            $this->error("  ❌ {$errors} lỗi, {$warnings} cảnh báo");
        }
        $this->info('══════════════════════════════════════════');

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function checkResult(string $label, int $actual, int $expected, int &$errors): void
    {
        if ($actual === $expected) {
            $this->info("  ✅ {$label}: {$actual} (đúng {$expected})");
        } else {
            $this->error("  ❌ {$label}: {$actual} (kỳ vọng {$expected})");
            $errors++;
        }
    }
}
