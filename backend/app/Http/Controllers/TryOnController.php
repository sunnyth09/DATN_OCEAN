<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\TryOnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TryOnController extends Controller
{
    protected TryOnService $tryOnService;

    public function __construct(TryOnService $tryOnService)
    {
        $this->tryOnService = $tryOnService;
    }

    public function process(Request $request)
    {
        // 1. Validate request
        $maxSizeKb = config('tryon.max_file_size_kb', 5120);
        $allowedMimes = implode(',', config('tryon.allowed_mimes', ['jpg', 'jpeg', 'png', 'webp']));

        $request->validate([
            'product_id' => 'required|integer|exists:products,product_id',
            'user_image' => "required|image|mimes:{$allowedMimes}|max:{$maxSizeKb}",
        ], [
            'user_image.max' => 'Ảnh upload không được vượt quá '.($maxSizeKb / 1024).'MB.',
            'user_image.mimes' => 'Chỉ hỗ trợ định dạng: '.$allowedMimes,
            'product_id.exists' => 'Sản phẩm không tồn tại.',
        ]);

        // 2. Lấy thông tin sản phẩm và ảnh sản phẩm
        $product = Product::with('mainImage')->find($request->product_id);

        // Cố gắng tìm ảnh tốt nhất để gửi cho AI
        $productImagePath = '';
        if ($product->thumbnail_url && $product->thumbnail_url !== '0') {
            $productImagePath = $product->thumbnail_url;
        } elseif ($product->mainImage) {
            $productImagePath = $product->mainImage->image_url;
        }

        if (empty($productImagePath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sản phẩm này chưa có ảnh để thử.',
            ], 400);
        }

        $productImageUrl = $productImagePath;
        if (! preg_match('/^https?:\/\//', $productImagePath)) {
            // Ảnh nằm trong storage local hoặc public → đọc file và encode base64
            $cleanPath = ltrim($productImagePath, '/');
            // Xoá tiền tố 'storage/' nếu có (đề phòng DB lưu thừa)
            if (str_starts_with($cleanPath, 'storage/')) {
                $cleanPath = substr($cleanPath, 8);
            }

            $storagePath = storage_path('app/public/'.$cleanPath);
            $publicPath = public_path('storage/'.$cleanPath);
            $directPublicPath = public_path($cleanPath);

            $filePathToUse = null;
            if (file_exists($storagePath)) {
                $filePathToUse = $storagePath;
            } elseif (file_exists($publicPath)) {
                $filePathToUse = $publicPath;
            } elseif (file_exists($directPublicPath)) {
                $filePathToUse = $directPublicPath;
            }

            if ($filePathToUse) {
                $mime = mime_content_type($filePathToUse) ?: 'image/jpeg';
                $productImageUrl = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($filePathToUse));
            } else {
                // Fallback: dùng URL tuyệt đối dựa vào request domain thay vì config APP_URL
                $productImageUrl = $request->getSchemeAndHttpHost().'/storage/'.$cleanPath;
            }
        }

        // 3. Xử lý lưu tạm ảnh user
        $file = $request->file('user_image');
        $userId = auth('api')->id() ?? ('guest_'.substr(md5($request->ip()), 0, 8));
        $fileName = 'tryon_'.$userId.'_'.time().'.'.$file->getClientOriginalExtension();

        // Lưu vào private disk, tự xóa sau khi xử lý
        $path = $file->storeAs('tryon-uploads', $fileName, 'local');
        $absolutePath = storage_path('app/private/'.$path);

        // 4. Gọi Service AI
        try {
            Log::info('TryOnController: Processing try-on request', [
                'user_id' => $userId,
                'file_path' => $absolutePath,
                'file_exists' => file_exists($absolutePath),
                'product_image_type' => str_starts_with($productImageUrl, 'data:') ? 'base64' : 'url',
            ]);

            $result = $this->tryOnService->process($absolutePath, $productImageUrl);

            // 5. Xóa ảnh tạm
            if (file_exists($absolutePath)) {
                unlink($absolutePath);
            }

            if ($result['status'] === 'success') {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'result_image_url' => $result['result_image_url'],
                        'is_mock' => $result['is_mock'] ?? false,
                        'message' => $result['message'] ?? 'Thành công',
                    ],
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => $result['message'],
            ], 500);

        } catch (\Exception $e) {
            Log::error('TryOnController: Exception during try-on processing', [
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);
            // Đảm bảo xóa ảnh tạm nếu có exception
            if (file_exists($absolutePath)) {
                unlink($absolutePath);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Đã có lỗi hệ thống xảy ra.',
            ], 500);
        }
    }
}
