<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TryOnService
{
    /**
     * Số lần tối đa polling status
     */
    private const MAX_POLL_ATTEMPTS = 60;

    /**
     * Khoảng cách giữa mỗi lần poll (giây)
     */
    private const POLL_INTERVAL_SECONDS = 2;

    /**
     * Process virtual try on
     * 
     * @param string $userImagePath Đường dẫn absolute tới file ảnh của user trên server
     * @param string $productImageUrl URL hoặc path của ảnh sản phẩm
     * @return array
     */
    public function process(string $userImagePath, string $productImageUrl): array
    {
        $mode = config('tryon.mode', 'mock');
        
        if ($mode === 'live') {
            return $this->callFashnApi($userImagePath, $productImageUrl);
        }
        
        return $this->mockResponse($productImageUrl);
    }

    /**
     * Gọi Fashn.ai API theo đúng flow:
     * 1. POST /v1/run → nhận prediction ID
     * 2. GET /v1/status/{id} → polling cho đến khi completed/failed
     * 3. Lấy output URL từ kết quả
     */
    private function callFashnApi(string $userImagePath, string $productImageUrl): array
    {
        $apiKey = config('tryon.api_key');
        $apiUrl = config('tryon.api_url', 'https://api.fashn.ai/v1/run');
        $statusBaseUrl = config('tryon.status_url', 'https://api.fashn.ai/v1/status');
        
        if (empty($apiKey)) {
            Log::error('TryOnService: Missing Fashn API Key');
            return [
                'status' => 'error',
                'message' => 'Tính năng AI Try-On chưa được cấu hình (thiếu API key). Vui lòng liên hệ admin.'
            ];
        }

        try {
            // ═══ BƯỚC 1: Encode ảnh user thành base64 data URI ═══
            $imageContent = file_get_contents($userImagePath);
            if ($imageContent === false) {
                Log::error('TryOnService: Cannot read user image file', ['path' => $userImagePath]);
                return [
                    'status' => 'error',
                    'message' => 'Không thể đọc file ảnh đã upload.'
                ];
            }

            $mimeType = mime_content_type($userImagePath) ?: 'image/jpeg';
            $base64Image = 'data:' . $mimeType . ';base64,' . base64_encode($imageContent);

            // ═══ BƯỚC 2: POST /v1/run → gửi JSON, nhận prediction ID ═══
            Log::info('TryOnService: Submitting try-on request to Fashn.ai', [
                'product_image' => $productImageUrl,
                'model_image_size' => strlen($imageContent),
            ]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiKey,
                ])
                ->post($apiUrl, [
                    'model_name' => 'tryon-max',
                    'inputs' => [
                        'model_image' => $base64Image,
                        'product_image' => $productImageUrl,
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('TryOnService: Fashn API submission failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [
                    'status' => 'error',
                    'message' => 'Không thể gửi yêu cầu tới dịch vụ AI. Mã lỗi: ' . $response->status()
                ];
            }

            $submitData = $response->json();
            $predictionId = $submitData['id'] ?? null;

            if (empty($predictionId)) {
                Log::error('TryOnService: No prediction ID returned', ['response' => $submitData]);
                return [
                    'status' => 'error',
                    'message' => 'Dịch vụ AI không trả về ID xử lý.'
                ];
            }

            Log::info('TryOnService: Prediction submitted', ['prediction_id' => $predictionId]);

            // ═══ BƯỚC 3: Poll /v1/status/{id} cho đến khi hoàn thành ═══
            return $this->pollForResult($predictionId, $apiKey, $statusBaseUrl);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('TryOnService: Connection timeout', ['error' => $e->getMessage()]);
            return [
                'status' => 'error',
                'message' => 'Kết nối tới dịch vụ AI bị timeout. Vui lòng thử lại sau.'
            ];
        } catch (\Exception $e) {
            Log::error('TryOnService Exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'status' => 'error',
                'message' => 'Lỗi kết nối tới dịch vụ AI Try-On.'
            ];
        }
    }

    /**
     * Polling status endpoint cho đến khi prediction completed hoặc failed.
     * 
     * Fashn.ai status flow:
     * - starting → in_queue → processing → completed|failed
     * 
     * @param string $predictionId ID prediction từ /v1/run
     * @param string $apiKey API key
     * @param string $statusBaseUrl Base URL cho status endpoint
     * @return array
     */
    private function pollForResult(string $predictionId, string $apiKey, string $statusBaseUrl): array
    {
        $statusUrl = rtrim($statusBaseUrl, '/') . '/' . $predictionId;

        for ($attempt = 0; $attempt < self::MAX_POLL_ATTEMPTS; $attempt++) {
            // Đợi trước khi poll (trừ lần đầu, đợi ngắn hơn)
            sleep($attempt === 0 ? 1 : self::POLL_INTERVAL_SECONDS);

            try {
                $response = Http::timeout(15)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $apiKey,
                    ])
                    ->get($statusUrl);

                if (!$response->successful()) {
                    Log::warning('TryOnService: Status poll failed', [
                        'attempt' => $attempt + 1,
                        'status' => $response->status(),
                        'prediction_id' => $predictionId,
                    ]);
                    continue; // Retry
                }

                $statusData = $response->json();
                $status = $statusData['status'] ?? 'unknown';

                Log::info('TryOnService: Poll status', [
                    'prediction_id' => $predictionId,
                    'status' => $status,
                    'attempt' => $attempt + 1,
                ]);

                // ═══ COMPLETED: Lấy output URL ═══
                if ($status === 'completed') {
                    $output = $statusData['output'] ?? [];

                    if (is_array($output) && !empty($output)) {
                        $resultUrl = $output[0]; // Fashn trả về array URLs
                    } elseif (is_string($output)) {
                        $resultUrl = $output;
                    } else {
                        $resultUrl = null;
                    }

                    if ($resultUrl) {
                        Log::info('TryOnService: Try-on completed successfully', [
                            'prediction_id' => $predictionId,
                            'result_url' => $resultUrl,
                        ]);
                        return [
                            'status' => 'success',
                            'result_image_url' => $resultUrl,
                        ];
                    }

                    Log::error('TryOnService: Completed but no output URL', [
                        'prediction_id' => $predictionId,
                        'response' => $statusData,
                    ]);
                    return [
                        'status' => 'error',
                        'message' => 'AI đã xử lý xong nhưng không tạo được ảnh kết quả.',
                    ];
                }

                // ═══ FAILED: Trả về lỗi ═══
                if ($status === 'failed') {
                    $errorMsg = $statusData['error'] ?? 'Unknown error';
                    Log::error('TryOnService: Prediction failed', [
                        'prediction_id' => $predictionId,
                        'error' => $errorMsg,
                    ]);
                    return [
                        'status' => 'error',
                        'message' => 'AI không thể xử lý ảnh. Vui lòng thử với ảnh khác.',
                    ];
                }

                // ═══ ĐANG XỬ LÝ: starting, in_queue, processing → tiếp tục poll ═══

            } catch (\Exception $e) {
                Log::warning('TryOnService: Poll exception', [
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage(),
                ]);
                // Tiếp tục retry
            }
        }

        // Hết số lần poll → timeout
        Log::error('TryOnService: Polling timeout', [
            'prediction_id' => $predictionId,
            'max_attempts' => self::MAX_POLL_ATTEMPTS,
        ]);

        return [
            'status' => 'error',
            'message' => 'AI đang xử lý quá lâu. Vui lòng thử lại sau.',
        ];
    }

    /**
     * Mock response cho demo/development (không cần API key)
     */
    private function mockResponse(string $productImageUrl): array
    {
        // Delay giả lập AI processing (từ 2 đến 4 giây)
        sleep(rand(2, 4));

        // Mock trả về luôn ảnh sản phẩm để demo
        return [
            'status' => 'success',
            'result_image_url' => $productImageUrl,
            'is_mock' => true,
            'message' => 'DEMO MODE: Bạn cần cấu hình API key để xem kết quả thật.'
        ];
    }
}
