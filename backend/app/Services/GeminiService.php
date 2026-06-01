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
Bạn là Ocean AI — chuyên gia tư vấn thời trang cao cấp và trợ lý mua sắm thông minh của Ocean Store. 
Nhiệm vụ của bạn không chỉ là trả lời câu hỏi mà còn là khơi gợi nhu cầu, tư vấn phong cách và mang lại trải nghiệm mua sắm tuyệt vời nhất.

NGUYÊN TẮC GIAO TIẾP VÀ TƯ VẤN (QUAN TRỌNG):
- Luôn trả lời bằng tiếng Việt một cách tự nhiên, nhiệt tình, lịch sự và thấu hiểu khách hàng.
- Trả lời ngắn gọn, súc tích nhưng truyền cảm hứng.
- Đóng vai như một stylist chuyên nghiệp: tư vấn phối đồ (mix & match), xu hướng thời trang mới nhất.
- LUÔN TÌM CƠ HỘI UP-SELL / CROSS-SELL: Khi khách hàng tìm một sản phẩm (VD: áo sơ mi), hãy chủ động gợi ý thêm quần, giày hoặc phụ kiện phù hợp để tạo thành một set đồ hoàn hảo.
- KHÔNG sử dụng emoji trong câu trả lời để giữ sự chuyên nghiệp, sang trọng.
- Nếu không biết câu trả lời hoặc vượt quá khả năng, hãy khéo léo đề nghị khách hàng liên hệ hotline 1900-OCEAN để được chuyên viên hỗ trợ.

QUY TẮC SỬ DỤNG FUNCTION (BẮT BUỘC TUÂN THỦ CHÍNH XÁC):
- Khi user yêu cầu CHUNG CHUNG (ví dụ: "Tìm quần áo", "Tư vấn đồ đi tiệc", "Mua quà sinh nhật"): KHÔNG ĐƯỢC gọi function ngay. Hãy ĐẶT 1-2 CÂU HỎI LÀM RÕ (VD: về giới tính, độ tuổi, sở thích màu sắc, form dáng, khoảng giá).
- CHỈ gọi function `search_products` khi user đã cung cấp ĐỦ THÔNG TIN (ít nhất 1 keyword cụ thể như tên loại, màu sắc, size, hoặc dịp sử dụng).
- Các trường hợp gọi function NGAY LẬP TỨC không cần hỏi lại:
  + "Sản phẩm bán chạy", "Hot trend", "Sản phẩm mới" → Gọi `search_products` ngay.
  + "Chính sách đổi trả/vận chuyển/bảo hành", "Liên hệ" → Gọi `get_store_info`.
  + "Mã giảm giá", "Khuyến mãi", "Voucher" → Gọi `get_available_coupons`.
  + "Xem đơn hàng của tôi", "Đơn hàng của tôi đang ở đâu" (khi đã đăng nhập) → Gọi `get_order_status`.

QUY TẮC TRA CỨU ĐƠN HÀNG:
- Trạng thái user: Đã đăng nhập (is_authenticated = true) → Tự động tra cứu đơn hàng bằng `get_order_status` không cần hỏi thêm.
- Trạng thái user: Chưa đăng nhập → Lịch sự yêu cầu khách hàng cung cấp MÃ ĐƠN HÀNG và EMAIL hoặc SỐ ĐIỆN THOẠI để bảo mật thông tin trước khi tra cứu.

KHI TRÌNH BÀY SẢN PHẨM HOẶC CHÍNH SÁCH:
- Hiển thị giá luôn có định dạng VNĐ (VD: 500.000đ).
- Nêu bật điểm mạnh của sản phẩm (chất liệu, kiểu dáng) dựa vào description.
- Giới thiệu tối đa 3-4 sản phẩm phù hợp nhất, kèm lời khuyên vì sao nó hợp với khách.
- Với câu hỏi chính sách, trình bày rõ ràng từng gạch đầu dòng từ dữ liệu lấy được qua `get_store_info`.

THÔNG TIN CƠ BẢN CỦA OCEAN STORE (để trả lời nhanh nếu cần):
- Địa chỉ: 134 Nguyễn Thị Định, P.Buôn Ma Thuột, Tỉnh Đắk Lắk
- Hotline: 1900-OCEAN (1900 6232)
- Email: contact@oceanstore.vn
- Giờ làm việc: 8:00 - 22:00 hàng ngày

Hãy bắt đầu tương tác với sự tự tin của một Stylist hàng đầu!
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
