<?php

namespace App\Services;

use App\Exports\ProductsTemplateExport;
use App\Imports\ProductsImport;
use App\Imports\ProductsRowImport;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Repositories\ProductRepository;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class ProductService
{
    public function __construct(
        protected ProductRepository $productRepository
    ) {}

    // ═══════════════════════════════════════════════════════════════════
    //  BARCODE & QR CODE
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Tạo mã barcode duy nhất cho biến thể
     */
    public function generateUniqueBarcode(): string
    {
        do {
            $barcode = 'OCN'.strtoupper(Str::random(10)).rand(10, 99);
        } while (ProductVariant::where('barcode', $barcode)->exists());

        return $barcode;
    }

    /**
     * Tạo QR code PNG từ barcode và lưu vào storage
     */
    public function generateQrCodeImage(string $barcode): string
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

    // ═══════════════════════════════════════════════════════════════════
    //  ADMIN: DANH SÁCH SẢN PHẨM
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Admin: danh sách sản phẩm (phân trang, tìm kiếm, lọc)
     */
    public function listAdminProducts(Request $request): array
    {
        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 12);
        $search = $request->query('search', '');
        $status = $request->query('status', '');

        // Search qua Meilisearch
        $matchedIds = null;
        $filters = [
            'status' => $status,
            'price_range' => $request->query('price_range'),
            'max_price' => $request->query('max_price'),
            'brand_ids' => $request->query('brand_ids'),
            'sort_by' => $request->query('sort_by'),
        ];

        if ($search) {
            // Bypass Meilisearch and use SQL LIKE directly to ensure all products are searchable
            $filters['search_like'] = $search;
        }

        // Category filter (bao gồm con)
        $categoryInput = $request->query('category_ids') ?? $request->query('category_id');
        if (! empty($categoryInput) && $categoryInput !== 'All') {
            $categoryIds = is_array($categoryInput) ? $categoryInput : explode(',', $categoryInput);

            $childIds = Category::whereIn('parent_id', $categoryIds)->pluck('category_id')->toArray();
            $filters['category_ids'] = array_merge($categoryIds, $childIds);
        }

        return $this->productRepository->getAdminProducts($matchedIds, $filters, $page, $limit);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  PUBLIC: DANH SÁCH, CHI TIẾT, FEATURED, RELATED
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Public: danh sách tất cả sản phẩm active (phân trang + cache)
     */
    public function listPublicProducts(Request $request): array
    {
        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 12);
        $offset = ($page - 1) * $limit;

        $cacheKey = "products:all:page:{$page}:limit:{$limit}";

        $products = Cache::remember($cacheKey, 1800, function () use ($offset, $limit) {
            return $this->productRepository->getPublicProducts($offset, $limit);
        });

        return [
            'status' => 'success',
            'data' => $products,
        ];
    }

    /**
     * Sản phẩm nổi bật (productFeatured — cached)
     */
    public function getProductFeatured(): array
    {
        $products = Cache::remember('products:productFeatured', 1800, function () {
            return $this->productRepository->getFeaturedProducts(4);
        });

        return ['data' => $products];
    }

    /**
     * Tất cả sản phẩm featured (featured — cached)
     */
    public function getAllFeatured(): array
    {
        $products = Cache::remember('products:featured', 1800, function () {
            return $this->productRepository->getAllFeaturedProducts();
        });

        return [
            'status' => 'success',
            'data' => $products,
        ];
    }

    public function clearBestSellingCache(): void
    {
        Cache::tags(['products:best-selling'])->flush();
    }

    /**
     * Sản phẩm bán chạy nhất (theo sold_count — cached)
     */
    public function getBestSelling(int $limit = 8): array
    {
        $products = Cache::tags(['products:best-selling'])->remember("products:best-selling:{$limit}", 1800, function () use ($limit) {
            return $this->productRepository->getBestSellingProducts($limit);
        });

        return [
            'status' => 'success',
            'data' => $products,
        ];
    }

    /**
     * Sản phẩm đang sale (sale_price active — cached)
     */
    public function getOnSale(int $limit = 8): array
    {
        $products = Cache::remember("products:on-sale:{$limit}", 900, function () use ($limit) {
            return $this->productRepository->getOnSaleProducts($limit);
        });

        return [
            'status' => 'success',
            'data' => $products,
        ];
    }

    /**
     * Chi tiết sản phẩm theo slug/ID (cached)
     */
    public function showProduct($identifier): array
    {
        $product = Cache::remember("product:identifier:{$identifier}", 1800, function () use ($identifier) {
            return $this->productRepository->findByIdentifier($identifier);
        });

        if (! $product) {
            return ['_status' => 404, 'status' => 'error', 'message' => 'Product not found'];
        }

        return ['_status' => 200, 'data' => $product];
    }

    /**
     * Sản phẩm liên quan (cached)
     */
    public function getRelatedProducts($slug): array
    {
        $product = Cache::remember("product:identifier:{$slug}", 1800, function () use ($slug) {
            return $this->productRepository->findByIdentifierBasic($slug);
        });

        if (! $product) {
            return ['_status' => 404, 'status' => 'error', 'message' => 'Product not found'];
        }

        $cacheKey = "products:related:{$product->product_id}";
        $related = Cache::remember($cacheKey, 900, function () use ($product) {
            return $this->productRepository->getRelatedProducts(
                $product->product_id,
                $product->category_id
            )->map(function ($p) {
                return [
                    'product_id' => $p->product_id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'min_price' => $p->min_price,
                    'thumbnail_url' => $p->mainImage?->image_url ?? $p->thumbnail_url,
                ];
            });
        });

        return [
            '_status' => 200,
            'status' => 'success',
            'data' => $related,
        ];
    }

    /**
     * Lấy danh sách biến thể active của sản phẩm
     */
    public function getProductVariants(int $id): array
    {
        $product = $this->productRepository->getActiveVariants($id);

        if (! $product) {
            return ['_status' => 404, 'status' => 'error', 'message' => 'Sản phẩm không tồn tại.'];
        }

        $variants = $product->variants->map(function ($v) {
            return [
                'variant_id' => $v->variant_id,
                'color' => $v->color,
                'size' => $v->size,
                'variant_name' => $v->variant_name,
                'price' => $v->price,
                'compare_at_price' => $v->compare_at_price,
                'stock' => $v->stock,
                'status' => $v->status,
                'image_url' => $v->image_url,
            ];
        });

        return [
            '_status' => 200,
            'status' => 'success',
            'data' => $variants,
        ];
    }

    /**
     * Admin: chi tiết sản phẩm để edit
     */
    public function getProductForEdit(int $id)
    {
        return $this->productRepository->findForEdit($id);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  STORE (TẠO MỚI)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Thêm sản phẩm mới (full flow: validate, upload, variants, QR)
     */
    public function storeProduct(Request $request): array
    {
        // Parse variants JSON
        $variantsData = [];
        if ($request->filled('variants')) {
            $variantsData = json_decode($request->variants, true);
            if (! is_array($variantsData)) {
                return ['_status' => 422, 'message' => 'Dữ liệu variants không hợp lệ.'];
            }
            // Validate giá biến thể >= 100.000đ
            foreach ($variantsData as $vIdx => $vItem) {
                foreach (($vItem['sizes'] ?? []) as $sIdx => $sItem) {
                    if (($sItem['price'] ?? 0) < 100000) {
                        return ['_status' => 422, 'message' => "Giá biến thể #{$vIdx}-size #{$sIdx} phải tối thiểu 100.000đ."];
                    }
                }
            }
        }

        DB::beginTransaction();

        try {
            $this->ensureStorageDirectories();

            // Upload thumbnail
            $thumbnailPath = $this->uploadThumbnail($request);

            // Tạo sản phẩm
            $slug = Str::slug($request->name).'-'.Str::random(5);
            $product = $this->productRepository->create([
                'category_id' => $request->category_id,
                'brand_id' => $request->brand_id ?: null,
                'seller_id' => $request->seller_id ?: null,
                'name' => $request->name,
                'slug' => $slug,
                'short_description' => $request->short_description,
                'description' => $request->description,
                'thumbnail_url' => $thumbnailPath,
                'product_type' => $request->product_type,
                'status' => $request->status,
                'is_featured' => $request->boolean('is_featured'),
                'min_price' => 0,
                'max_price' => 0,
            ]);

            // Main image → product_images
            if ($thumbnailPath) {
                $this->productRepository->createImage([
                    'product_id' => $product->product_id,
                    'image_url' => $thumbnailPath,
                    'is_main' => true,
                    'sort_order' => 0,
                ]);
            }

            // Gallery images
            $this->uploadGalleryImages($request, $product->product_id);

            // Tạo variants
            $allPrices = $this->createVariants($request, $product, $slug, $variantsData);

            // Update min/max price
            $this->productRepository->updateMinMaxPrice($product, $allPrices);

            DB::commit();
            Cache::flush();

            return [
                '_status' => 201,
                'success' => true,
                'message' => 'Thêm sản phẩm thành công.',
                'data' => $product->load('variants', 'images'),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            $isDbError = $e instanceof QueryException || $e instanceof \PDOException;
            $errorMsg = $isDbError ? 'Lỗi hệ thống.' : $e->getMessage();

            return [
                '_status' => 500,
                'success' => false,
                'message' => 'Thêm sản phẩm thất bại: '.$errorMsg,
            ];
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    //  UPDATE (CẬP NHẬT)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Cập nhật sản phẩm (full flow)
     */
    public function updateProduct(Request $request, int $id): array
    {
        $product = Product::findOrFail($id);

        // Parse variants JSON
        $variantsData = [];
        if ($request->filled('variants')) {
            $variantsData = json_decode($request->variants, true);
            if (! is_array($variantsData)) {
                return ['_status' => 422, 'message' => 'Dữ liệu variants không hợp lệ.'];
            }
            foreach ($variantsData as $vIdx => $vItem) {
                foreach (($vItem['sizes'] ?? []) as $sIdx => $sItem) {
                    if (($sItem['price'] ?? 0) < 100000) {
                        return ['_status' => 422, 'message' => "Giá biến thể #{$vIdx}-size #{$sIdx} phải tối thiểu 100.000đ."];
                    }
                }
            }
        }

        DB::beginTransaction();

        try {
            $this->ensureStorageDirectories();

            // Thumbnail
            $thumbnailPath = $this->handleThumbnailUpdate($request, $product);

            // Update product info
            $this->productRepository->update($product, [
                'category_id' => $request->category_id,
                'brand_id' => $request->brand_id ?: null,
                'seller_id' => $request->seller_id ?: null,
                'name' => $request->name,
                'slug' => $request->slug ?: Str::slug($request->name),
                'short_description' => $request->short_description,
                'description' => $request->description,
                'thumbnail_url' => $thumbnailPath,
                'product_type' => $request->product_type,
                'status' => $request->status,
                'is_featured' => $request->boolean('is_featured'),
            ]);

            // Xóa gallery cũ
            $this->deleteGalleryImages($request, $product->product_id);

            // Thêm gallery mới
            $this->addNewGalleryImages($request, $product->product_id);

            // Xử lý variants
            $allPrices = $this->updateVariants($request, $product, $variantsData);

            // Update min/max price
            $this->productRepository->updateMinMaxPrice($product, $allPrices);

            DB::commit();
            Cache::flush();

            return [
                '_status' => 200,
                'success' => true,
                'message' => 'Cập nhật sản phẩm thành công.',
                'data' => $product->load('variants', 'images'),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            $isDbError = $e instanceof QueryException || $e instanceof \PDOException;
            $errorMsg = $isDbError ? 'Lỗi hệ thống.' : $e->getMessage();

            return [
                '_status' => 500,
                'success' => false,
                'message' => 'Cập nhật thất bại: '.$errorMsg,
            ];
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    //  DELETE / RESTORE
    // ═══════════════════════════════════════════════════════════════════

    public function destroyProduct(int $id): array
    {
        try {
            $msg = $this->productRepository->softDelete($id);
            Cache::flush();

            return ['_status' => 200, 'status' => 'success', 'message' => $msg];
        } catch (\Exception $e) {
            $isDbError = $e instanceof QueryException || $e instanceof \PDOException;
            $errorMsg = $isDbError ? 'Lỗi hệ thống.' : $e->getMessage();

            return ['_status' => 500, 'status' => 'error', 'message' => 'Lỗi khi xóa: '.$errorMsg];
        }
    }

    public function restoreProduct(int $id): array
    {
        try {
            $this->productRepository->restore($id);
            Cache::flush();

            return ['_status' => 200, 'status' => 'success', 'message' => 'Khôi phục sản phẩm thành công.'];
        } catch (\Exception $e) {
            return ['_status' => 500, 'status' => 'error', 'message' => 'Không thể khôi phục sản phẩm.'];
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    //  IMPORT EXCEL
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Import sản phẩm từ Excel — Lưu file + trả session info
     */
    public function importExcel(Request $request): array
    {
        try {
            $rowsPerChunk = 50;

            $file = $request->file('excel_file');
            $sessionId = Str::random(30);
            $ext = $file->getClientOriginalExtension();
            $storagePath = 'imports/'.$sessionId.'.'.$ext;
            Storage::disk('local')->put($storagePath, file_get_contents($file->getRealPath()));
            $filePath = Storage::disk('local')->path($storagePath);

            // Đếm số dòng
            $totalDataRows = $this->countExcelRows($filePath);

            if ($totalDataRows === 0) {
                Storage::disk('local')->delete($storagePath);

                return ['_status' => 400, 'success' => false, 'message' => 'File không có dữ liệu hợp lệ.'];
            }

            $totalChunks = (int) ceil($totalDataRows / $rowsPerChunk);

            Cache::put('import_meta_'.$sessionId, [
                'storage_path' => $storagePath,
                'rows_per_chunk' => $rowsPerChunk,
                'total_chunks' => $totalChunks,
                'total_rows' => $totalDataRows,
            ], 7200);

            return [
                '_status' => 200,
                'success' => true,
                'session_id' => $sessionId,
                'total_chunks' => $totalChunks,
                'total_rows' => $totalDataRows,
            ];
        } catch (\Throwable $e) {
            Log::error('[ProductImportExcel] '.$e->getMessage()."\n".$e->getTraceAsString());
            if (isset($storagePath)) {
                Storage::disk('local')->delete($storagePath);
            }

            return [
                '_status' => 500,
                'success' => false,
                'message' => 'Lỗi khi đọc file: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Xử lý 1 chunk import
     */
    public function processImportChunk(Request $request): array
    {
        $sessionId = $request->session_id;
        $chunkIndex = (int) $request->chunk_index;

        $meta = Cache::get('import_meta_'.$sessionId);
        if (! $meta) {
            return ['_status' => 400, 'success' => false, 'error' => 'Session đã hết hạn. Vui lòng upload lại file.'];
        }

        $storagePath = $meta['storage_path'];
        $rowsPerChunk = $meta['rows_per_chunk'];
        $totalChunks = $meta['total_chunks'];
        $chunkStartRow = 2 + ($chunkIndex * $rowsPerChunk);
        $filePath = Storage::disk('local')->path($storagePath);

        if (! file_exists($filePath)) {
            return ['_status' => 400, 'success' => false, 'error' => 'File không tồn tại trên server.'];
        }

        try {
            ini_set('memory_limit', '512M');
            set_time_limit(120);

            $rowImport = new ProductsRowImport($chunkStartRow, $rowsPerChunk);
            Excel::import($rowImport, $filePath);
            $rawRows = $rowImport->getRows();
            unset($rowImport);

            if (empty($rawRows)) {
                return ['_status' => 200, 'success' => true, 'success_count' => 0, 'errors' => []];
            }

            // Gom nhóm theo tên sản phẩm
            $groups = [];
            $groupOrder = [];
            foreach ($rawRows as $idx => $row) {
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
                    'row' => array_values((array) $row),
                    'excelRow' => $chunkStartRow + $idx,
                ];
            }
            unset($rawRows);

            $import = new ProductsImport;
            foreach ($groupOrder as $key) {
                $import->processProductGroup($groups[$key]);
            }
            unset($groups);

            // Chunk cuối → dọn dẹp
            $isLastChunk = ($chunkIndex >= $totalChunks - 1);
            if ($isLastChunk) {
                Storage::disk('local')->delete($storagePath);
                Cache::forget('import_meta_'.$sessionId);
                Cache::flush();
            }

            return [
                '_status' => 200,
                'success' => true,
                'success_count' => $import->getSuccessCount(),
                'errors' => $import->getErrors(),
            ];
        } catch (\Throwable $e) {
            Log::error('[ProductImportChunk] chunk='.$chunkIndex.' '.$e->getMessage());

            return [
                '_status' => 500,
                'success' => false,
                'error' => 'Lỗi chunk '.$chunkIndex.': '.$e->getMessage(),
            ];
        }
    }

    /**
     * Tải file Excel mẫu
     */
    public function downloadTemplate()
    {
        return Excel::download(new ProductsTemplateExport, 'mau_import_san_pham.xlsx');
    }

    // ═══════════════════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════════════

    private function ensureStorageDirectories(): void
    {
        $storageDisk = Storage::disk('public');
        foreach (['products/thumbnails', 'products/gallery', 'products/variants'] as $dir) {
            if (! $storageDisk->exists($dir)) {
                $storageDisk->makeDirectory($dir);
            }
        }
    }

    private function uploadThumbnail(Request $request): ?string
    {
        if (! $request->hasFile('thumbnail')) {
            return null;
        }

        $file = $request->file('thumbnail');
        Log::info('[ProductStore] thumbnail file info', [
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        $thumbnailPath = $file->store('products/thumbnails', 'public');
        Log::info('[ProductStore] store() returned: '.var_export($thumbnailPath, true));

        if (! $thumbnailPath || $thumbnailPath === false) {
            $reason = ! $file->isValid() ? $file->getErrorMessage() : 'Kiểm tra quyền ghi thư mục storage.';
            throw new \Exception('Lỗi lưu thumbnail: '.$reason);
        }

        return $thumbnailPath;
    }

    private function handleThumbnailUpdate(Request $request, $product): ?string
    {
        $thumbnailPath = $product->thumbnail_url;

        if ($request->hasFile('thumbnail')) {
            if ($thumbnailPath && is_string($thumbnailPath) && $thumbnailPath !== '0') {
                $this->deletePhysicalImage($thumbnailPath);
            }
            $thumbnailPath = $request->file('thumbnail')->store('products/thumbnails', 'public');
            if (! $thumbnailPath || $thumbnailPath === false) {
                $file = $request->file('thumbnail');
                $reason = ! $file->isValid() ? $file->getErrorMessage() : 'Kiểm tra quyền ghi thư mục storage.';
                throw new \Exception('Lỗi lưu thumbnail: '.$reason);
            }

            // Update main image
            $oldMainImages = ProductImage::where('product_id', $product->product_id)->where('is_main', true)->get();
            foreach ($oldMainImages as $oldMain) {
                $this->deletePhysicalImage($oldMain->image_url);
            }
            $this->productRepository->deleteMainImage($product->product_id);
            $this->productRepository->createImage([
                'product_id' => $product->product_id,
                'image_url' => $thumbnailPath,
                'is_main' => true,
                'sort_order' => 0,
            ]);
        } else {
            if ($thumbnailPath === '0' || $thumbnailPath === 0) {
                $thumbnailPath = null;
            }
        }

        return $thumbnailPath;
    }

    private function uploadGalleryImages(Request $request, int $productId): void
    {
        if (! $request->hasFile('gallery')) {
            return;
        }

        foreach ($request->file('gallery') as $i => $file) {
            $path = $file->store('products/gallery', 'public');
            if (! $path || $path === false) {
                $reason = ! $file->isValid() ? $file->getErrorMessage() : 'Kiểm tra quyền ghi thư mục storage.';
                throw new \Exception('Lỗi lưu ảnh gallery: '.$reason);
            }
            $this->productRepository->createImage([
                'product_id' => $productId,
                'image_url' => $path,
                'is_main' => false,
                'sort_order' => $i + 1,
            ]);
        }
    }

    private function deleteGalleryImages(Request $request, int $productId): void
    {
        if (! $request->filled('deleted_gallery_ids')) {
            return;
        }

        $imagesToDelete = $this->productRepository->deleteImagesByIds(
            $request->deleted_gallery_ids,
            $productId
        );
        foreach ($imagesToDelete as $img) {
            $this->deletePhysicalImage($img->image_url);
            $img->delete();
        }
    }

    private function addNewGalleryImages(Request $request, int $productId): void
    {
        if (! $request->hasFile('gallery')) {
            return;
        }

        $maxSort = $this->productRepository->getMaxImageSortOrder($productId);
        foreach ($request->file('gallery') as $i => $file) {
            $path = $file->store('products/gallery', 'public');
            if (! $path || $path === false) {
                $reason = ! $file->isValid() ? $file->getErrorMessage() : 'Kiểm tra quyền ghi thư mục storage.';
                throw new \Exception('Lỗi lưu ảnh gallery: '.$reason);
            }
            $this->productRepository->createImage([
                'product_id' => $productId,
                'image_url' => $path,
                'is_main' => false,
                'sort_order' => $maxSort + $i + 1,
            ]);
        }
    }

    /**
     * Tạo variants cho sản phẩm mới (store)
     */
    private function createVariants(Request $request, $product, string $slug, array $variantsData): array
    {
        $allPrices = [];

        if ($request->product_type === 'simple') {
            $price = $request->price ?? 0;
            $allPrices[] = $price;
            $barcode = $this->generateUniqueBarcode();
            $this->productRepository->createVariant([
                'product_id' => $product->product_id,
                'sku' => substr($slug, 0, 50).'-default',
                'barcode' => $barcode,
                'price' => $price,
                'compare_at_price' => $request->compare_at_price,
                'stock' => $request->stock ?? 0,
                'sale_price' => $request->sale_price ?: null,
                'sale_starts_at' => $request->sale_starts_at ?: null,
                'sale_ends_at' => $request->sale_ends_at ?: null,
                'status' => 'active',
            ]);
            $this->generateQrCodeImage($barcode);
        } else {
            $allPrices = $this->createColorSizeVariants($request, $product, $slug, $variantsData);
        }

        return $allPrices;
    }

    /**
     * Tạo biến thể color-size cho variant product
     */
    private function createColorSizeVariants(Request $request, $product, string $slug, array $variantsData): array
    {
        $allPrices = [];
        $combinations = [];

        foreach ($variantsData as $vIndex => $vData) {
            $color = $vData['color'] ?? null;
            $sizes = $vData['sizes'] ?? [];

            // Upload variant images
            $variantImagePaths = $this->uploadVariantImages($request, $vIndex);

            foreach ($sizes as $sData) {
                $size = $sData['size'] ?? null;
                $combo = strtolower(trim($color ?? '')).'|'.strtolower(trim($size ?? ''));

                if (in_array($combo, $combinations)) {
                    throw new \Exception("Biến thể trùng lặp: Màu [{$color}] - Size [{$size}]");
                }
                $combinations[] = $combo;

                $vPrice = $sData['price'] ?? 0;
                $allPrices[] = $vPrice;

                $barcode = $this->generateUniqueBarcode();
                $variant = $this->productRepository->createVariant([
                    'product_id' => $product->product_id,
                    'sku' => substr($slug, 0, 50).'-'.Str::slug(substr($color ?? 'def', 0, 20)).'-'.Str::slug(substr($size ?? 'def', 0, 20)).'-'.Str::random(4),
                    'barcode' => $barcode,
                    'color' => $color,
                    'size' => $size,
                    'price' => $vPrice,
                    'stock' => $sData['stock'] ?? 0,
                    'sale_price' => ! empty($sData['sale_price']) ? $sData['sale_price'] : null,
                    'sale_starts_at' => ! empty($sData['sale_starts_at']) ? $sData['sale_starts_at'] : null,
                    'sale_ends_at' => ! empty($sData['sale_ends_at']) ? $sData['sale_ends_at'] : null,
                    'image_url' => $variantImagePaths[0] ?? null,
                    'status' => 'active',
                ]);
                $this->generateQrCodeImage($barcode);

                // Lưu ảnh biến thể vào product_images
                foreach ($variantImagePaths as $imgIndex => $imgPath) {
                    $this->productRepository->createImage([
                        'product_id' => $product->product_id,
                        'variant_id' => $variant->variant_id,
                        'image_url' => $imgPath,
                        'is_main' => false,
                        'sort_order' => $imgIndex + 1,
                    ]);
                }
            }
        }

        return $allPrices;
    }

    private function uploadVariantImages(Request $request, int $vIndex): array
    {
        $variantImagePaths = [];
        if ($request->hasFile("variant_images.{$vIndex}")) {
            foreach ($request->file("variant_images.{$vIndex}") as $imgFile) {
                $imgPath = $imgFile->store('products/variants', 'public');
                if (! $imgPath || $imgPath === false) {
                    $reason = ! $imgFile->isValid() ? $imgFile->getErrorMessage() : 'Kiểm tra quyền ghi thư mục storage.';
                    throw new \Exception('Lỗi lưu ảnh biến thể: '.$reason);
                }
                $variantImagePaths[] = $imgPath;
            }
        }

        return $variantImagePaths;
    }

    /**
     * Cập nhật variants khi update product
     */
    private function updateVariants(Request $request, $product, array $variantsData): array
    {
        $allPrices = [];

        if ($request->product_type === 'simple') {
            $price = $request->price ?? 0;
            $stock = $request->stock ?? 0;
            $allPrices[] = $price;

            $defaultVariant = $this->productRepository->getFirstVariant($product->product_id);
            if ($defaultVariant) {
                $updateData = [
                    'price' => $price,
                    'compare_at_price' => $request->compare_at_price,
                    'stock' => $stock,
                    'sale_price' => $request->sale_price ?: null,
                    'sale_starts_at' => $request->sale_starts_at ?: null,
                    'sale_ends_at' => $request->sale_ends_at ?: null,
                ];
                if (empty($defaultVariant->barcode)) {
                    $newBarcode = $this->generateUniqueBarcode();
                    $updateData['barcode'] = $newBarcode;
                    $this->generateQrCodeImage($newBarcode);
                }
                $defaultVariant->update($updateData);
            } else {
                $barcode = $this->generateUniqueBarcode();
                $this->productRepository->createVariant([
                    'product_id' => $product->product_id,
                    'sku' => substr(Str::slug($product->name), 0, 50).'-default',
                    'barcode' => $barcode,
                    'price' => $price,
                    'compare_at_price' => $request->compare_at_price,
                    'stock' => $stock,
                    'sale_price' => $request->sale_price ?: null,
                    'sale_starts_at' => $request->sale_starts_at ?: null,
                    'sale_ends_at' => $request->sale_ends_at ?: null,
                    'status' => 'active',
                ]);
                $this->generateQrCodeImage($barcode);
            }
        } else {
            $allPrices = $this->updateColorSizeVariants($request, $product, $variantsData);
        }

        return $allPrices;
    }

    /**
     * Cập nhật biến thể color-size khi update product
     */
    private function updateColorSizeVariants(Request $request, $product, array $variantsData): array
    {
        $allPrices = [];
        $existingVariantIds = $this->productRepository->getVariantIds($product->product_id);

        // Xóa cart items tham chiếu đến variants cũ
        $this->productRepository->deleteCartItemsByVariants($existingVariantIds);

        // Xóa ảnh biến thể mà user đã ấn nút xóa
        $deletedImageIds = $request->input('deleted_variant_image_ids', []);
        if (! empty($deletedImageIds)) {
            $imagesToDelete = $this->productRepository->deleteImagesByIds($deletedImageIds, $product->product_id);
            foreach ($imagesToDelete as $img) {
                $this->deletePhysicalImage($img->image_url);
                $img->delete();
            }
        }

        // Set variant_id = NULL cho ảnh TRƯỚC khi xóa variant
        $oldVariantImagesMap = [];
        foreach ($product->variants as $oldVariant) {
            $color = $oldVariant->color ?? 'default';
            if (! isset($oldVariantImagesMap[$color])) {
                $oldVariantImagesMap[$color] = [];
            }
            $variantOldImages = $this->productRepository->getVariantImages($product->product_id, $oldVariant->variant_id);
            foreach ($variantOldImages as $img) {
                $img->update(['variant_id' => null]);
                $oldVariantImagesMap[$color][] = $img;
            }
        }

        // Xóa tất cả variant cũ
        $this->productRepository->deleteProductVariants($product->product_id);

        // Tạo lại variant mới
        $combinations = [];
        foreach ($variantsData as $vIndex => $vData) {
            $color = $vData['color'] ?? null;
            $sizes = $vData['sizes'] ?? [];

            // Upload variant images MỚI
            $variantImagePaths = $this->uploadVariantImages($request, $vIndex);

            // Ảnh cũ còn tồn tại cho color này
            $colorKey = $color ?? 'default';
            $existingColorImages = $oldVariantImagesMap[$colorKey] ?? [];

            // image_url cho variant
            $mainImageUrl = null;
            if (! empty($existingColorImages)) {
                $mainImageUrl = $existingColorImages[0]->image_url;
            } elseif (! empty($variantImagePaths)) {
                $mainImageUrl = $variantImagePaths[0];
            }

            $firstVariantForColor = null;

            foreach ($sizes as $sData) {
                $size = $sData['size'] ?? null;
                $combo = strtolower(trim($color ?? '')).'|'.strtolower(trim($size ?? ''));

                if (in_array($combo, $combinations)) {
                    throw new \Exception("Biến thể trùng lặp: Màu [{$color}] - Size [{$size}]");
                }
                $combinations[] = $combo;

                $vPrice = $sData['price'] ?? 0;
                $allPrices[] = $vPrice;

                $barcode = $this->generateUniqueBarcode();
                $variant = $this->productRepository->createVariant([
                    'product_id' => $product->product_id,
                    'sku' => substr(Str::slug($product->name), 0, 50).'-'.Str::slug(substr($color ?? 'def', 0, 20)).'-'.Str::slug(substr($size ?? 'def', 0, 20)).'-'.Str::random(4),
                    'barcode' => $barcode,
                    'color' => $color,
                    'size' => $size,
                    'price' => $vPrice,
                    'stock' => $sData['stock'] ?? 0,
                    'sale_price' => ! empty($sData['sale_price']) ? $sData['sale_price'] : null,
                    'sale_starts_at' => ! empty($sData['sale_starts_at']) ? $sData['sale_starts_at'] : null,
                    'sale_ends_at' => ! empty($sData['sale_ends_at']) ? $sData['sale_ends_at'] : null,
                    'image_url' => $mainImageUrl,
                    'status' => 'active',
                ]);
                $this->generateQrCodeImage($barcode);

                if (! $firstVariantForColor) {
                    $firstVariantForColor = $variant;
                }
            }

            // Gán ảnh cũ + mới cho variant đầu tiên của color
            if ($firstVariantForColor) {
                foreach ($existingColorImages as $oldImg) {
                    $oldImg->update(['variant_id' => $firstVariantForColor->variant_id]);
                }
                foreach ($variantImagePaths as $imgIndex => $imgPath) {
                    $this->productRepository->createImage([
                        'product_id' => $product->product_id,
                        'variant_id' => $firstVariantForColor->variant_id,
                        'image_url' => $imgPath,
                        'is_main' => false,
                        'sort_order' => count($existingColorImages) + $imgIndex + 1,
                    ]);
                }
            }
        }

        return $allPrices;
    }

    /**
     * Đếm số dòng data trong file Excel (zero RAM overhead cho .xlsx)
     */
    private function countExcelRows(string $filePath): int
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($ext === 'xlsx') {
            $zip = new \ZipArchive;
            if ($zip->open($filePath) === true) {
                $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
                $zip->close();
                if ($sheetXml !== false) {
                    $rowCount = preg_match_all('/<row\s/', $sheetXml);
                    unset($sheetXml);

                    return max(0, $rowCount - 1);
                }
            }

            return 0;
        }

        // .xls: dùng PhpSpreadsheet chỉ đọc cột A
        $filter = new class implements IReadFilter
        {
            public function readCell($columnAddress, $row, $worksheetName = '')
            {
                return $columnAddress === 'A';
            }
        };
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $reader->setReadFilter($filter);
        $spreadsheet = $reader->load($filePath);
        $highestRow = $spreadsheet->getActiveSheet()->getHighestDataRow('A');
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $reader, $filter);

        return max(0, $highestRow - 1);
    }

    /**
     * Xóa file ảnh vật lý trong disk public, tự động chuẩn hóa đường dẫn để tránh lỗi do tiền tố /storage/ hoặc URL đầy đủ.
     */
    private function deletePhysicalImage(?string $imageUrl): void
    {
        if (empty($imageUrl) || $imageUrl === '0' || $imageUrl === 0) {
            return;
        }

        // Loại bỏ domain/host nếu có (vd: http://127.0.0.1:8000 hoặc https://domain.com)
        $path = preg_replace('/^https?:\/\/[^\/]+/i', '', (string) $imageUrl);
        // Loại bỏ tiền tố /storage/ hoặc storage/ bị thừa
        $path = preg_replace('/^\/?storage\//i', '', ltrim($path, '/'));

        if (! empty($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
