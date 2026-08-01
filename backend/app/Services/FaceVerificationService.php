<?php

namespace App\Services;

use App\Models\FaceEncoding;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service gọi đến Python Face Verification Microservice.
 *
 * Flow:
 * 1. Register: Nhân viên chụp 3-5 ảnh → encode → lưu vectors vào DB
 * 2. Verify:   Khi chấm công → gửi ảnh + encodings đã lưu → so khớp
 */
class FaceVerificationService
{
    /**
     * URL của Python Face Service (internal Docker network).
     */
    private string $serviceUrl;

    /**
     * Timeout cho HTTP request đến face service (giây).
     */
    private int $timeout;

    public function __construct()
    {
        $this->serviceUrl = rtrim(config('services.face.url', 'http://face-service:8001'), '/');
        $this->timeout = (int) config('services.face.timeout', 15);
    }

    // ================================================================
    //  ENCODE — Gửi ảnh, nhận 128-dim encoding vector
    // ================================================================

    /**
     * Gửi ảnh base64 đến face service để encode.
     *
     * @param  string  $base64Image  Ảnh base64 (có hoặc không có header)
     * @return array ['success' => bool, 'encoding' => array|null, 'message' => string]
     */
    public function encodeFace(string $base64Image): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->serviceUrl}/encode", [
                    'image' => $base64Image,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => $data['success'] ?? false,
                    'encoding' => $data['encoding'] ?? null,
                    'message' => $data['message'] ?? '',
                ];
            }

            Log::warning('Face encode failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'encoding' => null,
                'message' => 'Face service trả về lỗi: '.($response->json('detail') ?? 'Unknown error'),
            ];
        } catch (\Exception $e) {
            Log::error('Face service connection error (encode)', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'encoding' => null,
                'message' => 'Không thể kết nối đến Face Service. Vui lòng thử lại sau.',
            ];
        }
    }

    // ================================================================
    //  VERIFY — Gửi ảnh + encodings đã đăng ký, nhận match result
    // ================================================================

    /**
     * Verify ảnh mới với danh sách encodings đã đăng ký.
     *
     * @param  string  $base64Image  Ảnh mới (base64)
     * @param  array  $registeredEncodings  Danh sách encoding vectors (128-dim mỗi cái)
     * @return array ['match' => bool, 'distance' => float, 'confidence' => float, 'message' => string]
     */
    public function verify(string $base64Image, array $registeredEncodings): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->serviceUrl}/verify", [
                    'image' => $base64Image,
                    'registered_encodings' => $registeredEncodings,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => $data['success'] ?? false,
                    'match' => $data['match'] ?? false,
                    'distance' => $data['distance'] ?? 1.0,
                    'confidence' => $data['confidence'] ?? 0.0,
                    'message' => $data['message'] ?? '',
                ];
            }

            Log::warning('Face verify failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'match' => false,
                'distance' => 1.0,
                'confidence' => 0.0,
                'message' => 'Face service trả về lỗi.',
            ];
        } catch (\Exception $e) {
            Log::error('Face service connection error (verify)', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'match' => false,
                'distance' => 1.0,
                'confidence' => 0.0,
                'message' => 'Không thể kết nối đến Face Service.',
            ];
        }
    }

    // ================================================================
    //  REGISTER — Đăng ký khuôn mặt (nhiều ảnh)
    // ================================================================

    /**
     * Đăng ký khuôn mặt cho một nhân viên.
     * Nhận nhiều ảnh base64, encode từng ảnh và lưu vào DB.
     *
     * @param  array  $images  [['image' => base64, 'label' => 'front'], ...]
     */
    public function registerFaces(int $userId, string $userType, array $images): array
    {
        $results = [];
        $successCount = 0;

        foreach ($images as $index => $item) {
            $base64Image = $item['image'] ?? '';
            $label = $item['label'] ?? ('photo_'.($index + 1));

            if (empty($base64Image)) {
                $results[] = ['index' => $index, 'success' => false, 'message' => 'Ảnh trống.'];

                continue;
            }

            // Encode qua Python service
            $encodeResult = $this->encodeFace($base64Image);

            if (! $encodeResult['success'] || ! $encodeResult['encoding']) {
                $results[] = [
                    'index' => $index,
                    'success' => false,
                    'message' => $encodeResult['message'] ?: 'Không tìm thấy khuôn mặt.',
                ];

                continue;
            }

            // Lưu ảnh vào storage
            $imagePath = $this->saveRegistrationImage($base64Image, $userId, $label);

            // Lưu encoding vào DB
            FaceEncoding::create([
                'user_id' => $userId,
                'user_type' => $userType,
                'encoding' => $encodeResult['encoding'],
                'image_path' => $imagePath ?? '',
                'label' => $label,
                'is_active' => true,
            ]);

            $successCount++;
            $results[] = [
                'index' => $index,
                'success' => true,
                'message' => 'Đăng ký thành công.',
            ];
        }

        return [
            'success' => $successCount > 0,
            'total' => count($images),
            'success_count' => $successCount,
            'results' => $results,
            'message' => $successCount > 0
                ? "Đăng ký thành công {$successCount}/".count($images).' ảnh.'
                : 'Không thể đăng ký khuôn mặt. Vui lòng thử lại.',
        ];
    }

    // ================================================================
    //  VERIFY FOR ATTENDANCE — Xác thực khi chấm công
    // ================================================================

    /**
     * Xác thực khuôn mặt cho chấm công.
     * Lấy encodings đã đăng ký từ DB, gửi lên Python service so khớp.
     *
     * @param  string  $base64Image  Ảnh selfie khi check-in/out
     * @return array ['match' => bool, 'confidence' => float, 'distance' => float, 'registered' => bool]
     */
    public function verifyForAttendance(int $userId, string $userType, string $base64Image): array
    {
        // Lấy tất cả encodings active của user
        $faceEncodings = FaceEncoding::ofUser($userId, $userType)->get();

        if ($faceEncodings->isEmpty()) {
            return [
                'registered' => false,
                'match' => false,
                'confidence' => 0.0,
                'distance' => 1.0,
                'message' => 'Bạn chưa đăng ký khuôn mặt. Vui lòng đăng ký trước khi chấm công.',
            ];
        }

        $encodings = $faceEncodings->pluck('encoding')->toArray();

        // Gọi Python service verify
        $result = $this->verify($base64Image, $encodings);

        return [
            'registered' => true,
            'match' => $result['match'],
            'confidence' => $result['confidence'],
            'distance' => $result['distance'],
            'message' => $result['message'],
        ];
    }

    // ================================================================
    //  STATUS — Kiểm tra trạng thái đăng ký
    // ================================================================

    /**
     * Kiểm tra trạng thái đăng ký khuôn mặt của nhân viên.
     */
    public function getRegistrationStatus(int $userId, string $userType): array
    {
        $encodings = FaceEncoding::ofUser($userId, $userType)->get();

        return [
            'registered' => $encodings->isNotEmpty(),
            'encoding_count' => $encodings->count(),
            'encodings' => $encodings->map(fn ($e) => [
                'id' => $e->id,
                'label' => $e->label,
                'image_path' => $e->image_path,
                'created_at' => $e->created_at?->toDateTimeString(),
            ])->toArray(),
        ];
    }

    // ================================================================
    //  PRIVATE HELPERS
    // ================================================================

    /**
     * Lưu ảnh đăng ký khuôn mặt vào storage.
     */
    private function saveRegistrationImage(?string $base64Image, int $userId, string $label): ?string
    {
        if (! $base64Image) {
            return null;
        }

        $imageParts = explode(';base64,', $base64Image);
        if (count($imageParts) !== 2) {
            return null;
        }

        $imageData = base64_decode($imageParts[1]);
        if ($imageData === false) {
            return null;
        }

        // Validate file size (max 2MB)
        if (strlen($imageData) > 2 * 1024 * 1024) {
            return null;
        }

        // Validate magic bytes (JPEG or PNG only)
        $isJpeg = str_starts_with($imageData, "\xFF\xD8");
        $isPng = str_starts_with($imageData, "\x89PNG");
        if (! $isJpeg && ! $isPng) {
            return null;
        }

        $ext = $isPng ? 'png' : 'jpg';
        $fileName = "face_{$userId}_{$label}_".time().'_'.bin2hex(random_bytes(4)).".{$ext}";
        $path = 'face_registrations/'.$fileName;

        Storage::disk('public')->put($path, $imageData, 'public');

        return '/storage/'.$path;
    }
}
