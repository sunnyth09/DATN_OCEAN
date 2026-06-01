<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private array $apiKeys = [];
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash';

    /**
     * System prompt cấu hình "tính cách" cho chatbot Ocean Store
     */
    private string $systemPrompt = <<<'PROMPT'
Bạn là Ocean AI — trợ lý mua sắm thông minh của Ocean Store, một cửa hàng thời trang và phụ kiện trực tuyến.

NGUYÊN TẮC GIAO TIẾP:
- Luôn trả lời bằng tiếng Việt, thân thiện, chuyên nghiệp
- Trả lời ngắn gọn, rõ ràng, đi thẳng vào vấn đề
- KHÔNG sử dụng emoji trong câu trả lời
- Nếu không biết câu trả lời, hãy thành thật và đề nghị liên hệ hotline: 1900-OCEAN

QUY TẮC QUAN TRỌNG - BẮT BUỘC TUÂN THỦ:
- Khi user gửi yêu cầu CHUNG CHUNG, MƠ HỒ (ví dụ: "Tìm sản phẩm", "Tôi muốn mua đồ", "Tra cứu đơn hàng", "Liên hệ hỗ trợ"), KHÔNG ĐƯỢC gọi function ngay. Thay vào đó, hãy HỎI LẠI để làm rõ nhu cầu cụ thể.
- CHỈ gọi function khi user đã cung cấp ĐỦ THÔNG TIN CỤ THỂ (ví dụ: "Tìm áo khoác đen", "Đơn hàng ORD-123456", "Có mã giảm giá nào không?").
- NGOẠI LỆ — Các yêu cầu sau ĐÃ ĐỦ RÕ RÀNG, gọi function ngay KHÔNG cần hỏi lại:
  + "Gợi ý sản phẩm bán chạy", "Sản phẩm nổi bật", "Sản phẩm hot" → Gọi search_products (sẽ tự sắp xếp theo sold_count)
  + "Chính sách đổi trả", "Chính sách vận chuyển", "Liên hệ", "Thanh toán" → Gọi get_store_info với topic tương ứng
  + "Có mã giảm giá nào không?", "Voucher", "Khuyến mãi" → Gọi get_available_coupons
  + "Xem đơn hàng của tôi" (khi đã đăng nhập) → Gọi get_order_status
- Ví dụ cách hỏi lại (CHỈ khi yêu cầu thực sự mơ hồ):
  + "Tìm sản phẩm" → Hỏi: "Bạn muốn tìm loại sản phẩm nào? (áo, quần, giày...) Có yêu cầu về màu sắc, size hay khoảng giá không?"
  + "Tra cứu đơn hàng" (chưa đăng nhập) → Hỏi: "Bạn vui lòng cho tôi biết mã đơn hàng để tra cứu nhé."

QUY TẮC VỀ CHÍNH SÁCH CỬA HÀNG:
- Khi user hỏi về chính sách (đổi trả, vận chuyển, thanh toán, liên hệ), BẮT BUỘC gọi function get_store_info để lấy thông tin đầy đủ.
- KHÔNG ĐƯỢC tự trả lời sơ sài từ kiến thức có sẵn. Luôn gọi function để đảm bảo thông tin chính xác và chi tiết.
- Khi trả lời, liệt kê đầy đủ các điểm chính sách, không bỏ sót.

THÔNG TIN CỬA HÀNG:
- Tên: Ocean Store
- Địa chỉ: 101 Y Ngông, P. Tân Lập, TP.Buôn Ma Thuột, Đắk Lắk
- Hotline: 1900-OCEAN (1900 6232)
- Email: contact@oceanstore.vn
- Giờ làm việc: 8:00 - 22:00 hàng ngày

KHẢ NĂNG CỦA BẠN:
1. Tìm kiếm và gợi ý sản phẩm phù hợp
2. Tra cứu đơn hàng (user đã đăng nhập hoặc dùng mã đơn + email/SĐT)
3. Cung cấp thông tin mã giảm giá đang có
4. Trả lời câu hỏi về chính sách, vận chuyển, đổi trả (luôn dùng get_store_info)
5. Hướng dẫn mua hàng

QUY TẮC TRA ĐƠN HÀNG:
- Nếu user đã đăng nhập (is_authenticated = true): Tự động tra cứu đơn hàng theo tài khoản
- Nếu user chưa đăng nhập: Yêu cầu cung cấp MÃ ĐƠN HÀNG và EMAIL hoặc SỐ ĐIỆN THOẠI để xác minh

KHI TRẢ LỜI VỀ SẢN PHẨM:
- Luôn hiển thị giá bằng VNĐ
- Nếu có nhiều sản phẩm, giới thiệu tối đa 4-5 sản phẩm phù hợp nhất
- Gợi ý xem chi tiết sản phẩm nếu khách quan tâm
PROMPT;

    public function __construct()
    {
        // Load nhiều API keys để rotate khi bị rate limit
        $keys = array_filter([
            env('GEMINI_API_KEY', ''),
            env('GEMINI_API_KEY_2', ''),
            env('GEMINI_API_KEY_3', ''),
            env('GEMINI_API_KEY_4', ''),
        ]);
        $this->apiKeys = !empty($keys) ? $keys : [''];
    }

    /**
     * Lấy API key theo round-robin + random
     */
    private function getApiKey(int $attempt = 0): string
    {
        $index = ($attempt + crc32(date('YmdHi'))) % count($this->apiKeys);
        return $this->apiKeys[$index];
    }

    /**
     * Khai báo các function mà Gemini có thể gọi
     * Gemini sẽ phân tích intent của user và tự quyết định gọi function nào
     */
    private function getFunctionDeclarations(): array
    {
        return [
            [
                'name' => 'search_products',
                'description' => 'Tìm kiếm sản phẩm theo tên, từ khoá, danh mục, hoặc khoảng giá. Dùng khi khách hàng muốn tìm, hỏi về sản phẩm, hoặc cần gợi ý sản phẩm.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => [
                            'type' => 'string',
                            'description' => 'Từ khoá tìm kiếm sản phẩm (tên, loại, thương hiệu...)',
                        ],
                        'category' => [
                            'type' => 'string',
                            'description' => 'Tên danh mục sản phẩm (áo, quần, giày, phụ kiện...)',
                        ],
                        'color' => [
                            'type' => 'string',
                            'description' => 'Màu sắc sản phẩm (đen, trắng, đỏ, xanh, nâu, hồng, xám...)',
                        ],
                        'size' => [
                            'type' => 'string',
                            'description' => 'Kích thước sản phẩm (S, M, L, XL, XXL, 38, 39, 40...)',
                        ],
                        'min_price' => [
                            'type' => 'number',
                            'description' => 'Giá tối thiểu (VNĐ)',
                        ],
                        'max_price' => [
                            'type' => 'number',
                            'description' => 'Giá tối đa (VNĐ)',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'get_product_detail',
                'description' => 'Lấy thông tin chi tiết của một sản phẩm cụ thể theo tên hoặc slug',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'product_name' => [
                            'type' => 'string',
                            'description' => 'Tên sản phẩm cần xem chi tiết',
                        ],
                    ],
                    'required' => ['product_name'],
                ],
            ],
            [
                'name' => 'get_order_status',
                'description' => 'Tra cứu trạng thái đơn hàng. Nếu user đã đăng nhập thì tra theo tài khoản. Nếu chưa đăng nhập, cần mã đơn hàng kèm email hoặc số điện thoại để xác minh.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'order_code' => [
                            'type' => 'string',
                            'description' => 'Mã đơn hàng (VD: ORD-XXXXXX)',
                        ],
                        'email' => [
                            'type' => 'string',
                            'description' => 'Email đã đặt hàng (dùng để xác minh khi chưa đăng nhập)',
                        ],
                        'phone' => [
                            'type' => 'string',
                            'description' => 'Số điện thoại nhận hàng (dùng để xác minh khi chưa đăng nhập)',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'get_available_coupons',
                'description' => 'Lấy danh sách mã giảm giá đang có hiệu lực. Dùng khi khách hỏi về voucher, mã giảm giá, khuyến mãi.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
            ],
            [
                'name' => 'get_categories',
                'description' => 'Lấy danh sách tất cả danh mục sản phẩm của shop',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
            ],
            [
                'name' => 'get_store_info',
                'description' => 'Lấy thông tin cửa hàng, chính sách đổi trả, vận chuyển, thanh toán, liên hệ. Dùng khi khách hỏi về shop, chính sách, hoặc cần hỗ trợ.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'topic' => [
                            'type' => 'string',
                            'enum' => ['shipping', 'return_policy', 'payment', 'contact', 'general'],
                            'description' => 'Chủ đề cần tìm thông tin',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Gửi tin nhắn đến Gemini API với function calling
     *
     * @param array $conversationHistory  Lịch sử hội thoại [{role, parts}]
     * @param bool  $isAuthenticated      User đã đăng nhập chưa
     * @return array  Response từ Gemini
     */
    public function sendMessage(array $conversationHistory, bool $isAuthenticated = false): array
    {
        $apiKey = $this->getApiKey();
        $url = "{$this->baseUrl}:generateContent?key={$apiKey}";

        // Thêm thông tin auth vào system prompt
        $authContext = $isAuthenticated
            ? "\n\nUSER STATUS: Đã đăng nhập (is_authenticated = true). Có thể tra cứu đơn hàng trực tiếp."
            : "\n\nUSER STATUS: Chưa đăng nhập (is_authenticated = false). Nếu muốn tra đơn hàng, cần yêu cầu mã đơn + email/SĐT.";

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $this->systemPrompt . $authContext]],
            ],
            'contents' => $conversationHistory,
            'tools' => [
                [
                    'function_declarations' => $this->getFunctionDeclarations(),
                ],
            ],
            'tool_config' => [
                'function_calling_config' => [
                    'mode' => 'AUTO',
                ],
            ],
            'generation_config' => [
                'temperature' => 0.7,
                'top_p' => 0.95,
                'max_output_tokens' => 1024,
                'thinking_config' => [
                    'thinking_budget' => 0,
                ],
            ],
        ];

        try {
            $maxRetries = 3;
            $response = null;

            for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
                // Rotate key khi retry
                if ($attempt > 0) {
                    $apiKey = $this->getApiKey($attempt);
                    $url = "{$this->baseUrl}:generateContent?key={$apiKey}";
                }
                $response = Http::timeout(60)->post($url, $payload);

                if ($response->status() === 429 && $attempt < $maxRetries) {
                    // Rate limit — exponential backoff
                    $wait = 2 + $attempt * 2; // 2s, 4s, 6s
                    Log::warning("Gemini rate limit hit, retry attempt " . ($attempt + 1) . " after {$wait}s");
                    sleep($wait);
                    continue;
                }
                break;
            }

            if (!$response->successful()) {
                Log::error('Gemini API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                if ($response->status() === 429) {
                    return [
                        'error' => true,
                        'message' => 'Ocean AI đang bận, vui lòng thử lại sau vài giây!',
                    ];
                }

                return [
                    'error' => true,
                    'message' => 'Xin lỗi, tôi đang gặp sự cố kỹ thuật. Vui lòng thử lại sau!',
                ];
            }

            $data = $response->json();
            $candidate = $data['candidates'][0] ?? null;

            if (!$candidate) {
                return [
                    'error' => true,
                    'message' => 'Không nhận được phản hồi từ AI.',
                ];
            }

            $content = $candidate['content'] ?? [];
            $parts = $content['parts'] ?? [];

            // Check nếu Gemini muốn gọi function
            foreach ($parts as $part) {
                if (isset($part['functionCall'])) {
                    return [
                        'type' => 'function_call',
                        'function_name' => $part['functionCall']['name'],
                        'arguments' => $part['functionCall']['args'] ?? [],
                    ];
                }
            }

            // Trả về text response thông thường
            $text = '';
            foreach ($parts as $part) {
                if (isset($part['text'])) {
                    $text .= $part['text'];
                }
            }

            return [
                'type' => 'text',
                'message' => $text,
            ];

        } catch (\Exception $e) {
            Log::error('Gemini API exception', ['error' => $e->getMessage()]);
            return [
                'error' => true,
                'message' => 'Kết nối đến AI bị gián đoạn. Vui lòng thử lại!',
            ];
        }
    }

    /**
     * Gửi kết quả function call về Gemini để nhận response cuối cùng
     *
     * @param array  $conversationHistory  Lịch sử hội thoại
     * @param string $functionName         Tên function đã gọi
     * @param array  $functionResult       Kết quả trả về từ function
     * @param bool   $isAuthenticated      User đã đăng nhập chưa
     * @return array
     */
    public function sendFunctionResult(
        array $conversationHistory,
        string $functionName,
        array $functionResult,
        bool $isAuthenticated = false,
        array $functionArgs = []
    ): array {
        $apiKey = $this->getApiKey();
        $url = "{$this->baseUrl}:generateContent?key={$apiKey}";

        $authContext = $isAuthenticated
            ? "\n\nUSER STATUS: Đã đăng nhập (is_authenticated = true)."
            : "\n\nUSER STATUS: Chưa đăng nhập (is_authenticated = false).";

        // Thêm function call của model vào conversation (với args thực tế)
        $conversationHistory[] = [
            'role' => 'model',
            'parts' => [
                [
                    'functionCall' => [
                        'name' => $functionName,
                        'args' => !empty($functionArgs) ? $functionArgs : new \stdClass(),
                    ],
                ],
            ],
        ];

        // Thêm function response — Gemini API v1beta dùng role 'user' với functionResponse
        $conversationHistory[] = [
            'role' => 'user',
            'parts' => [
                [
                    'functionResponse' => [
                        'name' => $functionName,
                        'response' => $functionResult,
                    ],
                ],
            ],
        ];

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $this->systemPrompt . $authContext]],
            ],
            'contents' => $conversationHistory,
            'tools' => [
                [
                    'function_declarations' => $this->getFunctionDeclarations(),
                ],
            ],
            'generation_config' => [
                'temperature' => 0.7,
                'top_p' => 0.95,
                'max_output_tokens' => 1024,
                'thinking_config' => [
                    'thinking_budget' => 0,
                ],
            ],
        ];

        try {
            $maxRetries = 2;
            $response = null;

            for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
                if ($attempt > 0) {
                    $apiKey = $this->getApiKey($attempt);
                    $url = "{$this->baseUrl}:generateContent?key={$apiKey}";
                }
                $response = Http::timeout(60)->post($url, $payload);

                if ($response->status() === 429 && $attempt < $maxRetries) {
                    Log::warning("Gemini function result rate limit, retry attempt " . ($attempt + 1));
                    sleep(2 + $attempt * 2); // 2s, 4s backoff
                    continue;
                }
                break;
            }

            if (!$response->successful()) {
                Log::error('Gemini function result error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [
                    'error' => true,
                    'type' => 'text',
                    'message' => '',
                ];
            }

            $data = $response->json();
            $candidate = $data['candidates'][0] ?? null;
            $parts = $candidate['content']['parts'] ?? [];

            $text = '';
            foreach ($parts as $part) {
                if (isset($part['text'])) {
                    $text .= $part['text'];
                }
            }

            return [
                'type' => 'text',
                'message' => $text,
            ];

        } catch (\Exception $e) {
            Log::error('Gemini function result exception', ['error' => $e->getMessage()]);
            return [
                'error' => true,
                'type' => 'text',
                'message' => '',
            ];
        }
    }
}
