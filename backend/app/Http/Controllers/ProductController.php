<?php

namespace App\Http\Controllers;

use App\Models\SearchHistory;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService
    ) {}

    /**
     * Danh sách sản phẩm (phân trang, tìm kiếm, lọc)
     * Tự động điều hướng:
     *  - Admin/Staff/Seller: xem toàn bộ sản phẩm với filter status tùy chọn
     *  - Public/Khách hàng: chỉ xem sản phẩm active, whereNull(deleted_at)
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $user = auth('admin')->user() ?? auth('api')->user();
        $isAdmin = $user && in_array($user->role, ['admin', 'staff', 'seller']);

        $result = $isAdmin
            ? $this->productService->listAdminProducts($request)
            : $this->productService->listClientProducts($request);

        // Log search history if search term exists
        if ($request->filled('search')) {
            $userId = auth('api')->id();
            $sessionId = $request->header('X-Session-ID');
            if (! $sessionId) {
                $sessionId = $request->query('session_id');
            }

            if ($userId || $sessionId) {
                $query = SearchHistory::where('keyword', $request->search);
                if ($userId) {
                    $query->where('user_id', $userId);
                } else {
                    $query->where('session_id', $sessionId);
                }

                $record = $query->first();
                if ($record) {
                    $record->update([
                        'updated_at' => now(),
                        'results_count' => $result['total'] ?? 0,
                    ]);
                } else {
                    SearchHistory::create([
                        'user_id' => $userId,
                        'session_id' => $userId ? null : $sessionId,
                        'keyword' => $request->search,
                        'results_count' => $result['total'] ?? 0,
                    ]);
                }
            }
        }

        return response()->json($result);
    }

    /**
     * Client (public): danh sách sản phẩm active (phân trang, tìm kiếm, lọc).
     * Đây là endpoint công khai dùng cho trang sản phẩm và tìm kiếm của người dùng.
     */
    public function clientList(Request $request)
    {
        $result = $this->productService->listClientProducts($request);

        // Ghi lịch sử tìm kiếm nếu có từ khoá
        if ($request->filled('search')) {
            $userId = auth('api')->id();
            $sessionId = $request->header('X-Session-ID') ?? $request->query('session_id');

            if ($userId || $sessionId) {
                $query = SearchHistory::where('keyword', $request->search);
                $userId
                    ? $query->where('user_id', $userId)
                    : $query->where('session_id', $sessionId);

                $record = $query->first();
                if ($record) {
                    $record->update([
                        'updated_at' => now(),
                        'results_count' => $result['total'] ?? 0,
                    ]);
                } else {
                    SearchHistory::create([
                        'user_id' => $userId,
                        'session_id' => $userId ? null : $sessionId,
                        'keyword' => $request->search,
                        'results_count' => $result['total'] ?? 0,
                    ]);
                }
            }
        }

        return response()->json($result);
    }

    /**
     * Lấy danh sách sản phẩm nổi bật (dành cho client, có giới hạn số lượng).
     *
     * @return JsonResponse
     */
    public function productFeatured(Request $request)
    {
        return response()->json(
            $this->productService->getProductFeatured()
        );
    }

    /**
     * Lấy thông tin chi tiết của một sản phẩm dựa vào slug hoặc ID.
     * Dành cho phía Client (người mua hàng).
     *
     * @param  string|int  $identifier  Slug hoặc ID của sản phẩm
     * @return JsonResponse
     */
    public function show($identifier)
    {
        $result = $this->productService->showProduct($identifier);
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }

    /**
     * Lấy danh sách các sản phẩm có liên quan dựa vào slug của sản phẩm hiện tại.
     * Gợi ý dựa trên cùng danh mục (category) hoặc thương hiệu.
     *
     * @param  string  $slug  Slug của sản phẩm
     * @return JsonResponse
     */
    public function related($slug)
    {
        $result = $this->productService->getRelatedProducts($slug);
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }

    /**
     * Sản phẩm phối đồ theo slug
     */
    public function matching($slug)
    {
        $result = $this->productService->getMatchingProducts($slug);
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }

    /**
     * Lấy danh sách tất cả sản phẩm nổi bật mà không bị giới hạn số lượng nhỏ.
     *
     * @return JsonResponse
     */
    public function featured()
    {
        return response()->json(
            $this->productService->getAllFeatured()
        );
    }

    /**
     * Lấy danh sách sản phẩm bán chạy nhất để hiển thị trên trang chủ.
     *
     * @return JsonResponse
     */
    public function bestSelling(Request $request)
    {
        $limit = (int) $request->query('limit', 8);
        $limit = max(1, min($limit, 20)); // Giới hạn an toàn: 1-20

        return response()->json(
            $this->productService->getBestSelling($limit)
        );
    }

    /**
     * Lấy danh sách các sản phẩm đang được giảm giá (Sale) để hiển thị trên trang chủ.
     *
     * @return JsonResponse
     */
    public function onSale(Request $request)
    {
        $limit = (int) $request->query('limit', 8);
        $limit = max(1, min($limit, 20));

        return response()->json(
            $this->productService->getOnSale($limit)
        );
    }

    /**
     * Lấy danh sách các phiên bản (variants) của một sản phẩm cụ thể.
     *
     * @param  int  $id  ID của sản phẩm
     * @return JsonResponse
     */
    public function getVariants($id)
    {
        $result = $this->productService->getProductVariants($id);
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }

    /**
     * Lấy toàn bộ danh sách sản phẩm (public).
     * Dành cho trang cửa hàng (Shop/Catalog) với phân trang và bộ lọc.
     *
     * @return JsonResponse
     */
    public function all(Request $request)
    {
        return response()->json(
            $this->productService->listPublicProducts($request)
        );
    }

    /**
     * Lấy chi tiết sản phẩm theo ID (admin edit)
     */
    public function edit($id)
    {
        return response()->json(
            $this->productService->getProductForEdit($id)
        );
    }

    /**
     * Thêm sản phẩm mới
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:200',
            'category_id' => 'required|exists:categories,category_id',
            'brand_id' => 'nullable|exists:brands,brand_id',
            'seller_id' => 'nullable|exists:users,user_id',
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'product_type' => 'required|in:simple,variant',
            'status' => 'required|in:draft,active,inactive,out_of_stock',
            'is_featured' => 'boolean',
            'sku' => 'nullable|string|max:100',
            'weight' => 'nullable|integer|min:0',
            'material' => 'nullable|string|max:150',
            'origin' => 'nullable|string|max:150',
            'style' => 'nullable|string|max:150',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'gallery' => 'nullable|array|max:10',
            'gallery.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'variant_images' => 'nullable|array|max:20',
            'variant_images.*' => 'nullable|array',
            'variant_images.*.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'price' => 'nullable|numeric|min:100000',
            'compare_at_price' => 'nullable|numeric|min:100000',
            'stock' => 'nullable|integer|min:0',
            'sale_price' => 'nullable|numeric|min:1000|lte:price',
            'sale_starts_at' => 'nullable|date',
            'sale_ends_at' => 'nullable|date|after_or_equal:sale_starts_at',
            'variants' => 'nullable|string',
        ]);

        $result = $this->productService->storeProduct($request);
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }

    /**
     * Cập nhật sản phẩm
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:200',
            'category_id' => 'required|exists:categories,category_id',
            'brand_id' => 'nullable|exists:brands,brand_id',
            'seller_id' => 'nullable|exists:users,user_id',
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'product_type' => 'required|in:simple,variant',
            'status' => 'required|in:draft,active,inactive,out_of_stock',
            'is_featured' => 'boolean',
            'sku' => 'nullable|string|max:100',
            'weight' => 'nullable|integer|min:0',
            'material' => 'nullable|string|max:150',
            'origin' => 'nullable|string|max:150',
            'style' => 'nullable|string|max:150',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'price' => 'nullable|numeric|min:100000',
            'compare_at_price' => 'nullable|numeric|min:100000',
            'stock' => 'nullable|integer|min:0',
            'sale_price' => 'nullable|numeric|min:1000|lte:price',
            'sale_starts_at' => 'nullable|date',
            'sale_ends_at' => 'nullable|date|after_or_equal:sale_starts_at',
            'gallery' => 'nullable|array|max:10',
            'gallery.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'variant_images' => 'nullable|array|max:20',
            'variant_images.*' => 'nullable|array',
            'variant_images.*.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'deleted_gallery_ids' => 'nullable|array',
            'deleted_gallery_ids.*' => 'integer',
            'variants' => 'nullable|string',
        ]);

        $result = $this->productService->updateProduct($request, $id);
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }

    /**
     * Xóa sản phẩm (soft delete)
     */
    public function destroy($id)
    {
        $result = $this->productService->destroyProduct($id);
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }

    /**
     * Khôi phục sản phẩm (restore)
     */
    public function restore($id)
    {
        $result = $this->productService->restoreProduct($id);
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }

    /**
     * Import sản phẩm từ Excel
     */
    public function importExcel(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls|max:20480',
        ], [
            'excel_file.required' => 'Vui lòng chọn file Excel.',
            'excel_file.mimes' => 'File phải có định dạng .xlsx hoặc .xls.',
            'excel_file.max' => 'File không được vượt quá 20MB.',
        ]);

        $result = $this->productService->importExcel($request);
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }

    /**
     * Xử lý 1 chunk import
     */
    public function processImportChunk(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
            'chunk_index' => 'required|integer|min:0',
        ]);

        $result = $this->productService->processImportChunk($request);
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }

    /**
     * Tải file Excel mẫu
     */
    public function downloadTemplate()
    {
        return $this->productService->downloadTemplate();
    }

    /**
     * Xuất danh sách sản phẩm ra file Excel
     */
    public function exportExcel(Request $request)
    {
        return $this->productService->exportProducts($request);
    }

    /**
     * Upload ảnh cho editor mô tả sản phẩm (Quill)
     */
    public function uploadEditorImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
        ], [
            'image.required' => 'Vui lòng chọn ảnh.',
            'image.image' => 'File phải là ảnh.',
            'image.mimes' => 'Chỉ hỗ trợ định dạng: JPEG, PNG, JPG, GIF, WEBP.',
            'image.max' => 'Ảnh không được vượt quá 4MB.',
        ]);

        $path = $request->file('image')->store('product_descriptions', 'public');

        return response()->json([
            'url' => '/storage/'.$path,
        ]);
    }
}
