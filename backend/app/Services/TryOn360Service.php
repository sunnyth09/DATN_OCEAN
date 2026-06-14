<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TryOn360Service
{
    /**
     * Số lần tối đa polling status
     */
    private const MAX_POLL_ATTEMPTS = 60;

    /**
     * Khoảng cách giữa mỗi lần poll (giây)
     */
    private const POLL_INTERVAL_SECONDS = 3;

    /**
     * Generate 360° views từ ảnh kết quả try-on
     *
     * Flow:
     * 1. Nhận ảnh kết quả try-on (URL hoặc base64)
     * 2. Gửi lên Replicate API (model Zero123++)
     * 3. AI tạo ảnh người từ 6 góc nhìn khác nhau
     * 4. Trả về mảng URLs ảnh các góc
     *
     * @param string $imageUrl URL ảnh kết quả try-on
     * @return array
     */
    public function generate(string $imageUrl): array
    {
        $mode = config('tryon.rotate_360.mode', 'mock');

        if ($mode === 'live') {
            return $this->callReplicateApi($imageUrl);
        }

        return $this->mockResponse($imageUrl);
    }

    /**
     * Gọi Replicate API để generate multi-view images
     * 
     * Model: Zero123++ v1.2 (SUDO-AI-3D)
     * - Input: 1 ảnh người mặc đồ
     * - Output: 1 ảnh grid 3x2 chứa 6 góc nhìn
     * - Các góc: front, right-front, right, back, left, left-front
     * 
     * Flow:
     * 1. POST /v1/predictions → submit prediction
     * 2. GET /v1/predictions/{id} → poll until completed
     * 3. Trả về URL ảnh grid
     */
    private function callReplicateApi(string $imageUrl): array
    {
        $apiKey = config('tryon.rotate_360.api_key');

        if (empty($apiKey)) {
            Log::error('TryOn360Service: Missing Replicate API Key');
            return [
                'status' => 'error',
                'message' => 'Tính năng 360° chưa được cấu hình. Vui lòng thêm TRYON_360_API_KEY vào .env'
            ];
        }

        try {
            // ═══ BƯỚC 1: Chuẩn bị ảnh đầu vào ═══
            $inputImageUrl = $this->prepareImageInput($imageUrl);

            // ═══ BƯỚC 2: Submit prediction ═══
            Log::info('TryOn360Service: Submitting 360 generation', [
                'image_url_preview' => substr($inputImageUrl, 0, 100),
            ]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Prefer' => 'wait', // Replicate webhook-less polling
                ])
                ->post('https://api.replicate.com/v1/predictions', [
                    'version' => config('tryon.rotate_360.replicate.model_version'),
                    'input' => [
                        'image' => $inputImageUrl,
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('TryOn360Service: Replicate submit failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [
                    'status' => 'error',
                    'message' => 'Không thể gửi yêu cầu tới AI 360°. Mã lỗi: ' . $response->status()
                ];
            }

            $data = $response->json();
            $status = $data['status'] ?? '';

            // Nếu Replicate trả về ngay kết quả (dùng header Prefer: wait)
            if ($status === 'succeeded' && !empty($data['output'])) {
                return $this->parseReplicateOutput($data['output']);
            }

            // Nếu chưa xong, poll status
            $statusUrl = $data['urls']['get'] ?? null;
            if (empty($statusUrl)) {
                return [
                    'status' => 'error',
                    'message' => 'AI 360° không trả về URL xử lý.'
                ];
            }

            return $this->pollReplicateResult($statusUrl, $apiKey);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('TryOn360Service: Timeout', ['error' => $e->getMessage()]);
            return [
                'status' => 'error',
                'message' => 'Kết nối tới AI 360° bị timeout. Vui lòng thử lại.'
            ];
        } catch (\Exception $e) {
            Log::error('TryOn360Service: Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'status' => 'error',
                'message' => 'Lỗi hệ thống khi tạo ảnh 360°.'
            ];
        }
    }

    /**
     * Chuẩn bị input image cho Replicate API
     * - URL public: giữ nguyên
     * - Local file: encode sang base64 data URI
     */
    private function prepareImageInput(string $imageUrl): string
    {
        if (preg_match('/^https?:\/\//', $imageUrl)) {
            return $imageUrl; // URL public, dùng trực tiếp
        }

        // Local file → base64
        $path = storage_path('app/public/' . ltrim($imageUrl, '/'));
        if (file_exists($path)) {
            $mime = mime_content_type($path) ?: 'image/jpeg';
            return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
        }

        return $imageUrl;
    }

    /**
     * Poll Replicate prediction status cho đến khi hoàn thành
     */
    private function pollReplicateResult(string $statusUrl, string $apiKey): array
    {
        for ($attempt = 0; $attempt < self::MAX_POLL_ATTEMPTS; $attempt++) {
            sleep($attempt === 0 ? 2 : self::POLL_INTERVAL_SECONDS);

            try {
                $response = Http::timeout(15)
                    ->withHeaders(['Authorization' => 'Bearer ' . $apiKey])
                    ->get($statusUrl);

                if (!$response->successful()) continue;

                $data = $response->json();
                $status = $data['status'] ?? 'unknown';

                Log::info('TryOn360Service: Poll', [
                    'status' => $status,
                    'attempt' => $attempt + 1,
                ]);

                if ($status === 'succeeded') {
                    $output = $data['output'] ?? null;
                    if (!empty($output)) {
                        return $this->parseReplicateOutput($output);
                    }
                    return [
                        'status' => 'error',
                        'message' => 'AI xử lý xong nhưng không có kết quả.'
                    ];
                }

                if ($status === 'failed' || $status === 'canceled') {
                    Log::error('TryOn360Service: Failed', ['error' => $data['error'] ?? '']);
                    return [
                        'status' => 'error',
                        'message' => 'AI không thể tạo ảnh 360°. Thử ảnh khác nhé.'
                    ];
                }

                // starting, processing → tiếp tục poll

            } catch (\Exception $e) {
                Log::warning('TryOn360Service: Poll error', [
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'status' => 'error',
            'message' => 'AI 360° xử lý quá lâu. Vui lòng thử lại sau.'
        ];
    }

    /**
     * Parse output từ Replicate
     * 
     * Zero123++ trả về 1 hoặc nhiều URLs:
     * - Nếu output là string → 1 ảnh grid (chứa 6 views)
     * - Nếu output là array → nhiều ảnh riêng lẻ
     */
    private function parseReplicateOutput($output): array
    {
        $views = [];

        if (is_string($output)) {
            // Output là 1 URL duy nhất (ảnh grid 3x2)
            $views = [$output];
        } elseif (is_array($output)) {
            $views = $output;
        }

        if (empty($views)) {
            return [
                'status' => 'error',
                'message' => 'Không parse được kết quả từ AI.'
            ];
        }

        Log::info('TryOn360Service: Success', ['num_views' => count($views)]);

        return [
            'status' => 'success',
            'views' => $views,
            'is_mock' => false,
        ];
    }

    /**
     * Mock response cho development
     * Trả về cùng 1 ảnh + flag is_mock để frontend hiển thị thông báo
     */
    private function mockResponse(string $imageUrl): array
    {
        // Delay ngắn giả lập
        sleep(1);

        return [
            'status' => 'success',
            'views' => [$imageUrl],
            'is_mock' => true,
        ];
    }
}
