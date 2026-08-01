<?php

namespace App\Imports;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithStartRow;

class ProductsImport implements ToCollection, WithChunkReading, WithStartRow
{
    protected int $successCount = 0;

    protected array $errors = [];

    /**
     * Dòng 1 là header, dữ liệu bắt đầu từ dòng 2
     */
    public function startRow(): int
    {
        return 2;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * Xử lý toàn bộ collection từ Excel
     */
    public function collection(Collection $rows)
    {
        // === BƯỚC 0: Lọc dòng trống ===
        $rows = $rows->filter(function ($row) {
            $name = trim((string) ($row[0] ?? ''));

            return ! empty($name);
        });

        if ($rows->isEmpty()) {
            $this->errors[] = 'File Excel không có dữ liệu.';

            return;
        }

        // === BƯỚC 1: Gom nhóm theo Tên Sản Phẩm (cột A) ===
        $groups = [];
        $groupOrder = []; // Giữ thứ tự xuất hiện

        foreach ($rows as $index => $row) {
            $name = trim((string) ($row[0] ?? ''));
            if (empty($name)) {
                continue;
            }

            $key = mb_strtolower($name);
            if (! isset($groups[$key])) {
                $groups[$key] = [];
                $groupOrder[] = $key;
            }
            $groups[$key][] = [
                'row' => $row,
                'excelRow' => $index + 2, // +2 vì startRow=2
            ];
        }

        // === BƯỚC 2: Xử lý từng nhóm sản phẩm ===
        foreach ($groupOrder as $key) {
            $group = $groups[$key];
            $this->processProductGroup($group);
        }
    }

    /**
     * Xử lý 1 nhóm sản phẩm (1 product + N variants)
     */
    public function processProductGroup(array $group): void
    {
        $firstRow = $group[0]['row'];
        $firstExcelRow = $group[0]['excelRow'];
        $lastExcelRow = end($group)['excelRow'];
        $rowLabel = count($group) > 1
            ? "Dòng {$firstExcelRow}-{$lastExcelRow}"
            : "Dòng {$firstExcelRow}";

        try {
            // --- ĐỌC THÔNG TIN SẢN PHẨM TỪ DÒNG ĐẦU ---
            $name = trim((string) ($firstRow[0] ?? ''));
            $type = strtolower(trim((string) ($firstRow[1] ?? 'simple')));
            $categoryId = trim((string) ($firstRow[2] ?? ''));
            $brandId = trim((string) ($firstRow[3] ?? ''));
            $shortDesc = trim((string) ($firstRow[4] ?? ''));
            $description = trim((string) ($firstRow[5] ?? ''));
            $status = strtolower(trim((string) ($firstRow[6] ?? 'draft')));
            $isFeatured = trim((string) ($firstRow[7] ?? '0'));
            $mainImgUrl = trim((string) ($firstRow[8] ?? ''));
            $galleryUrls = trim((string) ($firstRow[9] ?? ''));

            // --- VALIDATE SẢN PHẨM ---
            if (empty($name)) {
                $this->errors[] = "{$rowLabel}: Thiếu tên sản phẩm.";

                return;
            }

            if (! in_array($type, ['simple', 'variant'])) {
                $this->errors[] = "{$rowLabel}: Loại SP phải là 'simple' hoặc 'variant'. Nhận được: [{$type}].";

                return;
            }

            if ($categoryId === '' || ! is_numeric($categoryId)) {
                $this->errors[] = "{$rowLabel}: Mã danh mục không hợp lệ.";

                return;
            }

            if (! Category::where('category_id', $categoryId)->exists()) {
                $this->errors[] = "{$rowLabel}: Mã danh mục [{$categoryId}] không tồn tại.";

                return;
            }

            if (! in_array($status, ['draft', 'active', 'inactive', 'out_of_stock'])) {
                $status = 'draft';
            }

            if ($brandId !== '' && $brandId !== null) {
                if (! is_numeric($brandId) || ! Brand::where('brand_id', $brandId)->exists()) {
                    $this->errors[] = "{$rowLabel}: Mã thương hiệu [{$brandId}] không tồn tại. Đã bỏ qua.";
                    $brandId = null;
                }
            } else {
                $brandId = null;
            }

            // --- VALIDATE TẤT CẢ BIẾN THỂ TRƯỚC KHI TẠO ---
            $variantsData = [];
            foreach ($group as $item) {
                $r = $item['row'];
                $exRow = $item['excelRow'];

                $color = trim((string) ($r[10] ?? ''));
                $size = trim((string) ($r[11] ?? ''));
                $price = trim((string) ($r[12] ?? ''));
                $compPrice = trim((string) ($r[13] ?? ''));
                $stock = trim((string) ($r[14] ?? '0'));
                $varImgUrls = trim((string) ($r[15] ?? ''));

                // Validate giá bán
                if ($price === '' || ! is_numeric($price) || (float) $price < 0) {
                    $this->errors[] = "Dòng {$exRow}: Giá bán không hợp lệ.";

                    return;
                }

                // Validate stock
                if (! is_numeric($stock) || (int) $stock < 0) {
                    $this->errors[] = "Dòng {$exRow}: Số lượng kho không hợp lệ.";

                    return;
                }

                // Variant phải có color hoặc size
                if ($type === 'variant' && empty($color) && empty($size)) {
                    $this->errors[] = "Dòng {$exRow}: Biến thể phải có ít nhất Màu sắc hoặc Kích cỡ.";

                    return;
                }

                $variantsData[] = [
                    'excelRow' => $exRow,
                    'color' => $color ?: null,
                    'size' => $size ?: null,
                    'price' => (float) $price,
                    'compare_at' => ($compPrice !== '' && is_numeric($compPrice)) ? (float) $compPrice : null,
                    'stock' => (int) $stock,
                    'varImgUrls' => $varImgUrls,
                ];
            }

            // Kiểm tra trùng lặp biến thể
            if ($type === 'variant') {
                $combos = [];
                foreach ($variantsData as $vd) {
                    $combo = mb_strtolower(($vd['color'] ?? '').'|'.($vd['size'] ?? ''));
                    if (in_array($combo, $combos)) {
                        $this->errors[] = "{$rowLabel}: Biến thể trùng lặp: Màu [{$vd['color']}] - Size [{$vd['size']}].";

                        return;
                    }
                    $combos[] = $combo;
                }
            }

            // === BẮT ĐẦU TRANSACTION ===
            DB::beginTransaction();

            // Đảm bảo thư mục tồn tại
            $disk = Storage::disk('public');
            foreach (['products/thumbnails', 'products/gallery', 'products/variants', 'products/qrcodes'] as $dir) {
                if (! $disk->exists($dir)) {
                    $disk->makeDirectory($dir);
                }
            }

            // --- TẢI ẢNH CHÍNH ---
            $thumbnailPath = null;
            if (! empty($mainImgUrl)) {
                $thumbnailPath = $this->downloadImage($mainImgUrl, 'products/thumbnails');
                if ($thumbnailPath === null) {
                    DB::rollBack();
                    $this->errors[] = "{$rowLabel}: Không thể tải ảnh chính từ URL: {$mainImgUrl}";

                    return;
                }
            }

            // --- LẤY HOẶC TẠO PRODUCT ---
            $slug = Str::slug($name);
            $product = Product::where('name', $name)->orWhere('slug', $slug)->first();
            $isNewProduct = false;

            if (! $product) {
                $isNewProduct = true;
                $slug = $slug.'-'.Str::random(5);
                $allPrices = array_column($variantsData, 'price');

                $product = Product::create([
                    'category_id' => (int) $categoryId,
                    'brand_id' => $brandId ? (int) $brandId : null,
                    'name' => $name,
                    'slug' => $slug,
                    'short_description' => $shortDesc ?: null,
                    'description' => $description ?: null,
                    'thumbnail_url' => $thumbnailPath,
                    'product_type' => $type,
                    'status' => $status,
                    'is_featured' => (bool) (int) $isFeatured,
                    'min_price' => count($allPrices) > 0 ? min($allPrices) : 0,
                    'max_price' => count($allPrices) > 0 ? max($allPrices) : 0,
                ]);

                // --- LƯU ẢNH CHÍNH VÀO product_images ---
                if ($thumbnailPath) {
                    ProductImage::create([
                        'product_id' => $product->product_id,
                        'image_url' => $thumbnailPath,
                        'is_main' => true,
                        'sort_order' => 0,
                    ]);
                }

                // --- TẢI VÀ LƯU ẢNH PHỤ ---
                if (! empty($galleryUrls)) {
                    $urls = array_filter(array_map('trim', explode(',', $galleryUrls)));
                    foreach ($urls as $i => $url) {
                        $galleryPath = $this->downloadImage($url, 'products/gallery');
                        if ($galleryPath === null) {
                            // Cảnh báo nhưng không rollback toàn bộ SP
                            $this->errors[] = "{$rowLabel}: Không thể tải ảnh phụ từ URL: {$url}";

                            continue;
                        }
                        ProductImage::create([
                            'product_id' => $product->product_id,
                            'image_url' => $galleryPath,
                            'is_main' => false,
                            'sort_order' => $i + 1,
                        ]);
                    }
                }
            } else {
                $slug = $product->slug;
                // Nếu sản phẩm đã tồn tại, cập nhật lại min/max price
                $allPrices = array_merge(
                    $product->variants->pluck('price')->toArray(),
                    array_column($variantsData, 'price')
                );
                if (count($allPrices) > 0) {
                    $product->update([
                        'min_price' => min($allPrices),
                        'max_price' => max($allPrices),
                    ]);
                }
            }

            // --- TẠO VARIANTS ---
            foreach ($variantsData as $vIndex => $vd) {
                $barcode = $this->generateUniqueBarcode();

                $skuSuffix = $type === 'simple'
                    ? 'default'
                    : Str::slug($vd['color'] ?? 'def').'-'.Str::slug($vd['size'] ?? 'def').'-'.Str::random(4);

                // Tải ảnh biến thể
                $variantMainImgPath = null;
                $variantImagePaths = [];

                if (! empty($vd['varImgUrls'])) {
                    $vUrls = array_filter(array_map('trim', explode(',', $vd['varImgUrls'])));
                    foreach ($vUrls as $vUrl) {
                        $imgPath = $this->downloadImage($vUrl, 'products/variants');
                        if ($imgPath === null) {
                            DB::rollBack();
                            $this->errors[] = "Dòng {$vd['excelRow']}: Không thể tải ảnh biến thể từ URL: {$vUrl}";

                            return;
                        }
                        $variantImagePaths[] = $imgPath;
                    }
                    $variantMainImgPath = $variantImagePaths[0] ?? null;
                }

                $variant = ProductVariant::create([
                    'product_id' => $product->product_id,
                    'sku' => $slug.'-'.$skuSuffix,
                    'barcode' => $barcode,
                    'color' => $vd['color'],
                    'size' => $vd['size'],
                    'price' => $vd['price'],
                    'compare_at_price' => $vd['compare_at'],
                    'stock' => $vd['stock'],
                    'image_url' => $variantMainImgPath,
                    'status' => 'active',
                ]);

                // Sinh QR Code
                $this->generateQrCodeImage($barcode);

                // Lưu ảnh biến thể vào product_images
                foreach ($variantImagePaths as $imgIdx => $imgPath) {
                    ProductImage::create([
                        'product_id' => $product->product_id,
                        'variant_id' => $variant->variant_id,
                        'image_url' => $imgPath,
                        'is_main' => false,
                        'sort_order' => $imgIdx + 1,
                    ]);
                }
            }

            DB::commit();
            $this->successCount++;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("[ProductsImport] {$rowLabel} lỗi: ".$e->getMessage());
            $this->errors[] = "{$rowLabel}: ".$e->getMessage();
        }
    }

    /**
     * Tải ảnh từ URL và lưu vào storage
     *
     * @return string|null Đường dẫn tương đối trong public disk, null nếu thất bại
     */
    private function downloadImage(string $url, string $directory): ?string
    {
        try {
            // Validate URL
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                Log::warning("[ProductsImport] URL ảnh không hợp lệ: {$url}");

                return null;
            }

            $response = Http::timeout(15)->get($url);

            if (! $response->successful()) {
                Log::warning("[ProductsImport] Không tải được ảnh (HTTP {$response->status()}): {$url}");

                return null;
            }

            $content = $response->body();
            if (empty($content)) {
                return null;
            }

            // Detect extension từ Content-Type
            $contentType = $response->header('Content-Type') ?? '';
            $ext = match (true) {
                str_contains($contentType, 'png') => 'png',
                str_contains($contentType, 'gif') => 'gif',
                str_contains($contentType, 'webp') => 'webp',
                str_contains($contentType, 'svg') => 'svg',
                default => 'jpg',
            };

            $filename = Str::random(20).'.'.$ext;
            $filePath = $directory.'/'.$filename;

            Storage::disk('public')->put($filePath, $content);

            return $filePath;

        } catch (\Throwable $e) {
            Log::warning("[ProductsImport] Lỗi tải ảnh [{$url}]: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Tạo mã barcode duy nhất
     */
    private function generateUniqueBarcode(): string
    {
        do {
            $barcode = 'OCN'.strtoupper(Str::random(10)).rand(10, 99);
        } while (ProductVariant::where('barcode', $barcode)->exists());

        return $barcode;
    }

    /**
     * Tạo QR code PNG từ barcode
     */
    private function generateQrCodeImage(string $barcode): string
    {
        $storageDisk = Storage::disk('public');
        if (! $storageDisk->exists('products/qrcodes')) {
            $storageDisk->makeDirectory('products/qrcodes');
        }

        $builder = new Builder(writer: new PngWriter);
        $result = $builder->build(data: $barcode, size: 400, margin: 15);

        $filePath = 'products/qrcodes/'.$barcode.'.png';
        $storageDisk->put($filePath, $result->getString());

        return $filePath;
    }

    // === GETTERS ===

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
