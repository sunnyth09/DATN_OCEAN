<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TryOnService
{
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
            return $this->callProvider($userImagePath, $productImageUrl);
        }
        
        return $this->mockResponse($productImageUrl);
    }

    private function callProvider(string $userImagePath, string $productImageUrl): array
    {
        $provider = config('tryon.provider');
        $apiKey = config('tryon.api_key');
        $apiUrl = config('tryon.api_url');
        $timeout = config('tryon.timeout') / 1000; // Convert ms to seconds
        
        if (empty($apiKey)) {
            Log::error('TryOnService: Missing API Key for provider ' . $provider);
            return [
                'status' => 'error',
                'message' => 'Tính năng AI Try-On chưa được cấu hình (thiếu API key). Vui lòng liên hệ admin.'
            ];
        }

        try {
            // Note: Cần customize đoạn logic call HTTP tùy vào spec của từng Provider
            // Đây là ví dụ chung cho multipart form-data.
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                ])
                ->attach(
                    'model_image', file_get_contents($userImagePath), basename($userImagePath)
                )
                ->post($apiUrl, [
                    'garment_image_url' => $productImageUrl,
                    // 'category' => 'tops' // tùy provider
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Assuming provider returns a direct URL in some field
                // Lấy URL tuỳ vào response format của FASHN.ai (VD: id job, sau đó phải fetch)
                // FASHN.ai thường trả về output_url hoặc cần polling.
                $resultUrl = $data['output_url'] ?? ($data['images'][0]['url'] ?? null);

                if ($resultUrl) {
                    return [
                        'status' => 'success',
                        'result_image_url' => $resultUrl
                    ];
                }
                
                Log::error('TryOnService: Missing result URL in response', ['response' => $data]);
                return [
                    'status' => 'error',
                    'message' => 'Lỗi xử lý kết quả từ nhà cung cấp AI.'
                ];
            }

            Log::error('TryOnService API Error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [
                'status' => 'error',
                'message' => 'Dịch vụ AI đang bận hoặc quá tải, vui lòng thử lại sau.'
            ];
            
        } catch (\Exception $e) {
            Log::error('TryOnService Exception: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Lỗi kết nối tới dịch vụ AI Try-On.'
            ];
        }
    }

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
