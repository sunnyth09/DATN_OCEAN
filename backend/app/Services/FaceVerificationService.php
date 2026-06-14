<?php

namespace App\Services;

use App\Models\FaceEncoding;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service xác thực khuôn mặt — PHP thuần (không cần Python).
 *
 * Encoding/Descriptor được tạo bởi face-api.js trên browser (128-dim vector).
 * Backend chỉ lưu trữ và so sánh bằng euclidean distance.
 *
 * Flow:
 * 1. Register: Browser chụp ảnh → face-api.js tạo 128-dim descriptor → gửi lên Laravel lưu DB
 * 2. Verify:   Browser chụp selfie → face-api.js tạo descriptor → gửi lên Laravel so khớp
 */
class FaceVerificationService
{
    /**
     * Ngưỡng euclidean distance cho phép (tolerance).
     * Distance ≤ tolerance → cùng 1 người (MATCH).
     * Distance > tolerance → khác người (REJECT).
     *
     * 0.45 là ngưỡng nghiêm ngặt (strict). Có thể tăng lên 0.5 nếu cần linh hoạt hơn.
     */
    private const TOLERANCE = 0.45;

    // ================================================================
    //  REGISTER — Đăng ký khuôn mặt (nhiều ảnh)
    // ================================================================

    /**
     * Đăng ký khuôn mặt cho một nhân viên.
     * Nhận descriptor (128-dim) từ frontend, lưu vào DB.
     *
     * @param  int    $userId
     * @param  string $userType
     * @param  array  $images    [['image' => base64, 'descriptor' => [128 floats], 'label' => 'front'], ...]
     * @return array
     */
    public function registerFaces(int $userId, string $userType, array $images): array
    {
        $results = [];
        $successCount = 0;

        foreach ($images as $index => $item) {
            $base64Image = $item['image'] ?? '';
            $descriptor = $item['descriptor'] ?? null;
            $label = $item['label'] ?? ('photo_' . ($index + 1));

            // Validate descriptor
            if (!$descriptor || !is_array($descriptor) || count($descriptor) !== 128) {
                $results[] = [
                    'index'   => $index,
                    'success' => false,
                    'message' => 'Descriptor không hợp lệ (cần 128-dim vector).',
                ];
                continue;
            }

            // Validate mỗi phần tử là số
            foreach ($descriptor as $val) {
                if (!is_numeric($val)) {
                    $results[] = [
                        'index'   => $index,
                        'success' => false,
                        'message' => 'Descriptor chứa giá trị không hợp lệ.',
                    ];
                    continue 2;
                }
            }

            // Lưu ảnh vào storage
            $imagePath = $this->saveRegistrationImage($base64Image, $userId, $label);

            // Lưu descriptor vào DB
            FaceEncoding::create([
                'user_id'    => $userId,
                'user_type'  => $userType,
                'encoding'   => $descriptor, // 128-dim vector (cast to JSON by model)
                'image_path' => $imagePath ?? '',
                'label'      => $label,
                'is_active'  => true,
            ]);

            $successCount++;
            $results[] = [
                'index'   => $index,
                'success' => true,
                'message' => 'Đăng ký thành công.',
            ];
        }

        return [
            'success'       => $successCount > 0,
            'total'         => count($images),
            'success_count' => $successCount,
            'results'       => $results,
            'message'       => $successCount > 0
                ? "Đăng ký thành công {$successCount}/" . count($images) . " ảnh."
                : 'Không thể đăng ký khuôn mặt. Vui lòng thử lại.',
        ];
    }

    // ================================================================
    //  VERIFY FOR ATTENDANCE — Xác thực khi chấm công
    // ================================================================

    /**
     * Xác thực khuôn mặt cho chấm công.
     * Nhận descriptor từ frontend, so khớp với descriptors đã lưu trong DB.
     *
     * @param  int        $userId
     * @param  string     $userType
     * @param  array|null $descriptor 128-dim descriptor vector từ browser
     * @return array  ['match' => bool, 'confidence' => float, 'distance' => float, 'registered' => bool]
     */
    public function verifyForAttendance(int $userId, string $userType, ?array $descriptor): array
    {
        // Lấy tất cả descriptors active của user
        $faceEncodings = FaceEncoding::ofUser($userId, $userType)->get();

        if ($faceEncodings->isEmpty()) {
            return [
                'registered' => false,
                'match'      => false,
                'confidence' => 0.0,
                'distance'   => 1.0,
                'message'    => 'Bạn chưa đăng ký khuôn mặt. Vui lòng đăng ký trước khi chấm công.',
            ];
        }

        // Nếu không có descriptor từ frontend (model chưa load, fallback)
        if (!$descriptor || count($descriptor) !== 128) {
            return [
                'registered' => true,
                'match'      => false,
                'confidence' => 0.0,
                'distance'   => 1.0,
                'message'    => 'Không nhận được dữ liệu khuôn mặt. Vui lòng thử lại.',
            ];
        }

        // So khớp với tất cả encodings đã đăng ký
        $registeredDescriptors = $faceEncodings->pluck('encoding')->toArray();

        return $this->verifyDescriptor($descriptor, $registeredDescriptors);
    }

    // ================================================================
    //  VERIFY DESCRIPTOR — So khớp bằng PHP
    // ================================================================

    /**
     * So khớp descriptor mới với danh sách descriptors đã đăng ký.
     * Tính euclidean distance và quyết định match/không match.
     *
     * @param  array  $newDescriptor          128-dim vector
     * @param  array  $registeredDescriptors  Mảng các 128-dim vectors
     * @param  float  $tolerance              Ngưỡng distance cho phép
     * @return array  ['match' => bool, 'confidence' => float, 'distance' => float]
     */
    public function verifyDescriptor(array $newDescriptor, array $registeredDescriptors, float $tolerance = self::TOLERANCE): array
    {
        if (empty($registeredDescriptors)) {
            return [
                'registered' => true,
                'match'      => false,
                'confidence' => 0.0,
                'distance'   => 1.0,
                'message'    => 'Không có dữ liệu khuôn mặt đã đăng ký.',
            ];
        }

        $minDistance = PHP_FLOAT_MAX;

        foreach ($registeredDescriptors as $registered) {
            $distance = $this->euclideanDistance($newDescriptor, $registered);
            if ($distance < $minDistance) {
                $minDistance = $distance;
            }
        }

        // Confidence: chuyển distance → confidence (0-1)
        // distance = 0 → confidence = 1.0 (perfect match)
        // distance = tolerance → confidence ≈ 0.5
        // distance > tolerance → confidence < 0.5
        $confidence = max(0.0, min(1.0, 1.0 - ($minDistance / ($tolerance * 2))));
        $isMatch = $minDistance <= $tolerance;

        Log::info('Face verification (PHP)', [
            'match'      => $isMatch,
            'distance'   => round($minDistance, 4),
            'confidence' => round($confidence, 4),
            'tolerance'  => $tolerance,
        ]);

        return [
            'registered' => true,
            'match'      => $isMatch,
            'distance'   => round($minDistance, 4),
            'confidence' => round($confidence, 4),
            'message'    => $isMatch ? 'Xác thực thành công!' : 'Khuôn mặt không khớp.',
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
            'registered'     => $encodings->isNotEmpty(),
            'encoding_count' => $encodings->count(),
            'encodings'      => $encodings->map(fn ($e) => [
                'id'         => $e->id,
                'label'      => $e->label,
                'image_path' => $e->image_path,
                'created_at' => $e->created_at?->toDateTimeString(),
            ])->toArray(),
        ];
    }

    // ================================================================
    //  PRIVATE HELPERS
    // ================================================================

    /**
     * Tính Euclidean Distance giữa 2 vectors.
     *
     * Công thức: distance = √[(a₁-b₁)² + (a₂-b₂)² + ... + (a₁₂₈-b₁₂₈)²]
     *
     * @param  array $a Vector 1 (128-dim)
     * @param  array $b Vector 2 (128-dim)
     * @return float    Khoảng cách (0 = giống hoàn toàn)
     */
    private function euclideanDistance(array $a, array $b): float
    {
        $sum = 0.0;
        $length = min(count($a), count($b));

        for ($i = 0; $i < $length; $i++) {
            $diff = (float) $a[$i] - (float) $b[$i];
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }

    /**
     * Lưu ảnh đăng ký khuôn mặt vào storage.
     */
    private function saveRegistrationImage(?string $base64Image, int $userId, string $label): ?string
    {
        if (!$base64Image) {
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
        $isPng  = str_starts_with($imageData, "\x89PNG");
        if (!$isJpeg && !$isPng) {
            return null;
        }

        $ext = $isPng ? 'png' : 'jpg';
        $fileName = "face_{$userId}_{$label}_" . time() . '_' . bin2hex(random_bytes(4)) . ".{$ext}";
        $path = 'face_registrations/' . $fileName;

        Storage::disk('public')->put($path, $imageData, 'public');

        return '/storage/' . $path;
    }
}
