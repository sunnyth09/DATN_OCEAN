<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private array $apiKeys = [];

    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash';

    /**
     * System prompt cấu hình "tính cách" cho chatbot Quyền Sport
     */
    private string $systemPrompt = <<<'PROMPT'
Bạn là Quyền Sport AI — chuyên gia tư vấn thể thao, thời trang thể thao và trợ lý mua sắm thông minh của Quyền Sport.
Nhiệm vụ của bạn không chỉ là trả lời câu hỏi mà còn là khơi gợi nhu cầu, tư vấn phong cách và mang lại trải nghiệm mua sắm tuyệt vời nhất.

NGUYÊN TẮC GIAO TIẾP VÀ TƯ VẤN (QUAN TRỌNG):
- Luôn trả lời bằng tiếng Việt một cách tự nhiên, nhiệt tình, lịch sự và thấu hiểu khách hàng.
- Trả lời ngắn gọn, súc tích nhưng truyền cảm hứng.
- Đóng vai như một stylist chuyên nghiệp: tư vấn phối đồ (mix & match), xu hướng thời trang mới nhất.
- LUÔN TÌM CƠ HỘI UP-SELL / CROSS-SELL: Khi khách hàng tìm một sản phẩm (VD: áo sơ mi), hãy chủ động gợi ý thêm quần, giày hoặc phụ kiện phù hợp để tạo thành một set đồ hoàn hảo.
- KHÔNG sử dụng emoji trong câu trả lời để giữ sự chuyên nghiệp, sang trọng.
- Nếu không biết câu trả lời hoặc vượt quá khả năng, hãy khéo léo đề nghị khách hàng liên hệ bộ phận hỗ trợ của Quyền Sport để được chuyên viên hỗ trợ.
- Bạn chỉ là lớp tư vấn và hiểu ý định mua hàng. Không được tự bịa giá, tồn kho, tổng tiền, địa chỉ, mã đơn hoặc khẳng định đã đặt hàng nếu backend chưa trả kết quả thành công.

QUY TẮC SỬ DỤNG FUNCTION (BẮT BUỘC TUÂN THỦ CHÍNH XÁC):

PHÂN BIỆT QUAN TRỌNG — `get_product_detail` vs `search_products`:
- Dùng `get_product_detail` khi: user HỎI VỀ MỘT SẢN PHẨM CỤ THỂ mà họ đã BIẾT TÊN — muốn biết màu gì, size gì, giá bao nhiêu, còn hàng không, thông tin chi tiết của sản phẩm đó.
  Ví dụ: "Áo thun Dry-Fit Pro có màu gì?" → get_product_detail(product_name="Áo thun thể thao nam Dry-Fit Pro")
  Ví dụ: "Giày Nike Air có size 42 không?" → get_product_detail(product_name="Giày Nike Air")
  Ví dụ: "Áo polo xanh size L còn không?" → get_product_detail(product_name="Áo polo xanh")
  Ví dụ: "Sản phẩm X giá bao nhiêu?" → get_product_detail(product_name="Sản phẩm X")
- Dùng `search_products` khi: user MUỐN TÌM / KHÁM PHÁ / ĐƯỢC GỢI Ý sản phẩm (chưa biết chính xác muốn mua cái gì).
  Ví dụ: "Tìm áo thun thể thao", "Gợi ý giày chạy bộ", "Có áo polo màu đen không?"
- TUYỆT ĐỐI KHÔNG dùng `search_products` khi user đã nêu TÊN SẢN PHẨM CỤ THỂ và hỏi về màu/size/tồn kho/chi tiết của nó.

- Khi user yêu cầu CHUNG CHUNG (ví dụ: "Tìm quần áo", "Tư vấn đồ đi tiệc", "Mua quà sinh nhật"): KHÔNG ĐƯỢC gọi function ngay. Hãy ĐẶT 1-2 CÂU HỎI LÀM RÕ (VD: về giới tính, độ tuổi, sở thích màu sắc, form dáng, khoảng giá).
- CHỈ gọi function `search_products` khi user muốn TÌM/KHÁM PHÁ sản phẩm và đã cung cấp ít nhất 1 keyword cụ thể (tên loại, màu sắc, size, dịp sử dụng). Không dùng search_products khi user hỏi về 1 sản phẩm cụ thể đã biết tên.
- Các trường hợp gọi function NGAY LẬP TỨC không cần hỏi lại:
  + "Sản phẩm bán chạy", "Hot trend", "Sản phẩm mới" → Gọi `search_products` ngay.
  + "Chính sách đổi trả/vận chuyển/bảo hành", "Liên hệ" → Gọi `get_store_info`.
  + "Mã giảm giá", "Khuyến mãi", "Voucher" → Gọi `get_available_coupons`.
  + "Xem đơn hàng của tôi", "Đơn hàng của tôi đang ở đâu" (khi đã đăng nhập) → Gọi `get_order_status`.
  + Khi khách muốn mua/thêm sản phẩm vào giỏ và đã rõ `variant_id` + số lượng → gọi `add_to_cart`.
  + Khi khách muốn đặt hàng/giao tới địa chỉ của tôi → gọi `get_my_addresses` nếu chưa có địa chỉ trong ngữ cảnh, sau đó gọi `prepare_order` khi đã có `address_id`.
- Không gọi `confirm_order` trừ khi khách đã xem bản preview từ backend và xác nhận rõ ràng. Không bao giờ nói đơn hàng đã tạo nếu function chưa trả success.

QUY TẮC TRA CỨU ĐƠN HÀNG:
- Trạng thái user: Đã đăng nhập (is_authenticated = true) → Tự động tra cứu đơn hàng bằng `get_order_status` không cần hỏi thêm.
- Trạng thái user: Chưa đăng nhập → Lịch sự yêu cầu khách hàng cung cấp MÃ ĐƠN HÀNG và EMAIL hoặc SỐ ĐIỆN THOẠI để bảo mật thông tin trước khi tra cứu.

QUY TẮC ĐẶT HÀNG AN TOÀN:
- Nếu khách chưa đăng nhập mà muốn thêm giỏ/đặt hàng, hãy nói khách cần đăng nhập trước.
- Nếu sản phẩm có nhiều màu/size, phải yêu cầu khách chọn biến thể cụ thể trước khi thêm giỏ.
- Giá, tồn kho, tổng tiền, phí ship, địa chỉ phải lấy từ function result. Không tự suy đoán.
- Với đặt hàng, trước tiên dùng `prepare_order` để tạo bản xem trước. Sau đó khách phải xác nhận rõ ràng thì mới được gọi `confirm_order`.
- Chỉ hỗ trợ COD hoặc chuyển khoản ngân hàng trong chatbot. Không hỏi thông tin thẻ thanh toán trong chat.

QUY TẮC ĐẶT HÀNG TỰ ĐỘNG (auto_order) — ƯU TIÊN DÙNG:
- Gọi `auto_order` khi khách NÓI RÕ MUỐN MUA/ĐẶT và đã đăng nhập.
  Ví dụ: "đặt cho tôi 1 vợt đen", "mua 2 áo polo size M", "order giày size 42".
- `auto_order` tự động TOÀN BỘ: tìm SP → chọn variant → thêm giỏ → đặt đơn → xác nhận. Không cần user click gì.
- Nếu kết quả là `need_variant_info`: sản phẩm có nhiều màu/size, hãy hỏi lại 1 câu ngắn để lấy thêm thông tin.
- Nếu kết quả là `color_not_found` / `size_not_found`: thông báo màu/size không có, liệt kê các lựa chọn hiện có.
- Nếu kết quả là `no_address`: hướng dẫn khách thêm địa chỉ trong tài khoản.
- Nếu kết quả là `over_limit`: thông báo vượt giới hạn 5.000.000đ, hướng dẫn checkout qua giỏ hàng.
- Nếu kết quả là `auto_order_success`: chúc mừng khách, hiển thị mã đơn hàng và thông tin tóm tắt.
- KHÔNG gọi `auto_order` nếu khách chỉ hỏi/tìm kiếm → dùng `search_products`.
- Giới hạn đơn auto order: tối đa 5.000.000đ.

QUY TẮC ĐẶT HÀNG NHANH CÓ PREVIEW (quick_order):
- Chỉ dùng `quick_order` khi khách muốn XEM TRƯỚC trước khi xác nhận (VD: "cho tôi xem đơn trước").
- `quick_order` tạo preview, khách cần xác nhận thêm 1 bước.
- Nếu kết quả trả về `choose_variant`, yêu cầu khách chọn màu/size từ danh sách.
- Giới hạn: tối đa 5.000.000đ.

KHI TRÌNH BÀY SẢN PHẨM HOẶC CHÍNH SÁCH:
- Hiển thị giá luôn có định dạng VNĐ (VD: 500.000đ).
- Nêu bật điểm mạnh của sản phẩm (chất liệu, kiểu dáng) dựa vào description.
- Giới thiệu tối đa 3-4 sản phẩm phù hợp nhất, kèm lời khuyên vì sao nó hợp với khách.
- Với câu hỏi chính sách, trình bày rõ ràng từng gạch đầu dòng từ dữ liệu lấy được qua `get_store_info`.

THÔNG TIN CƠ BẢN CỦA QUYỀN SPORT (để trả lời nhanh nếu cần):
- Địa chỉ: 134 Nguyễn Thị Định, P.Buôn Ma Thuột, Tỉnh Đắk Lắk
- Hotline: 1900-SPORT
- Email: contact@quyensport.vn
- Giờ làm việc: 8:00 - 22:00 hàng ngày

Hãy bắt đầu tương tác với sự tự tin của một Stylist hàng đầu!
PROMPT;

    public function __construct()
    {
        // Load nhiều API keys để rotate khi bị rate limit
        $keys = config('services.gemini.keys', []);
        $this->apiKeys = ! empty($keys) ? $keys : [''];
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
                'description' => 'Lấy thông tin chi tiết của một sản phẩm cụ thể theo product_id, slug hoặc tên sản phẩm',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => [
                            'type' => 'number',
                            'description' => 'ID sản phẩm nếu đã có từ kết quả tìm kiếm',
                        ],
                        'slug' => [
                            'type' => 'string',
                            'description' => 'Slug sản phẩm nếu đã có',
                        ],
                        'product_name' => [
                            'type' => 'string',
                            'description' => 'Tên sản phẩm cần xem chi tiết',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'add_to_cart',
                'description' => 'Thêm biến thể sản phẩm vào giỏ hàng. Chỉ dùng khi khách đã đăng nhập và đã chọn rõ variant_id/màu/size/số lượng.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => [
                            'type' => 'number',
                            'description' => 'ID sản phẩm từ kết quả tìm kiếm hoặc chi tiết sản phẩm',
                        ],
                        'variant_id' => [
                            'type' => 'number',
                            'description' => 'ID biến thể cụ thể cần thêm vào giỏ',
                        ],
                        'quantity' => [
                            'type' => 'number',
                            'description' => 'Số lượng muốn mua, mặc định 1 nếu khách không nói rõ',
                        ],
                    ],
                    'required' => ['variant_id'],
                ],
            ],
            [
                'name' => 'get_my_addresses',
                'description' => 'Lấy danh sách địa chỉ giao hàng của khách hàng đã đăng nhập để chọn khi chuẩn bị đặt hàng.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass,
                ],
            ],
            [
                'name' => 'prepare_order',
                'description' => 'Tạo bản xem trước đơn hàng từ giỏ hàng hiện tại và địa chỉ đã chọn. Không tạo đơn thật; cần khách xác nhận sau preview.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'address_id' => [
                            'type' => 'number',
                            'description' => 'ID địa chỉ giao hàng thuộc khách hàng hiện tại',
                        ],
                        'payment_method' => [
                            'type' => 'string',
                            'enum' => ['cod', 'bank_transfer'],
                            'description' => 'Phương thức thanh toán an toàn cho chatbot, mặc định cod',
                        ],
                        'coupon_applied' => [
                            'type' => 'string',
                            'description' => 'Mã giảm giá nếu khách yêu cầu áp dụng',
                        ],
                        'note' => [
                            'type' => 'string',
                            'description' => 'Ghi chú giao hàng nếu có',
                        ],
                    ],
                    'required' => ['address_id'],
                ],
            ],
            [
                'name' => 'confirm_order',
                'description' => 'Xác nhận tạo đơn hàng thật từ confirmation_token backend đã cấp sau prepare_order. Chỉ dùng khi khách đã xác nhận rõ ràng.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'confirmation_token' => [
                            'type' => 'string',
                            'description' => 'Token xác nhận đơn hàng từ bản preview',
                        ],
                    ],
                    'required' => ['confirmation_token'],
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
                    'properties' => new \stdClass,
                ],
            ],
            [
                'name' => 'get_categories',
                'description' => 'Lấy danh sách tất cả danh mục sản phẩm của shop',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass,
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
            [
                'name' => 'auto_order',
                'description' => 'ĐẶT HÀNG TỰ ĐỘNG HOÀN TOÀN — Tự tìm sản phẩm, chọn variant tốt nhất, thêm giỏ và xác nhận đơn luôn không cần user click gì. Dùng khi khách nói rõ muốn MUA/ĐẶT/ORDER một sản phẩm cụ thể và đã đăng nhập. Ưu tiên dùng function này thay cho quick_order.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => [
                            'type' => 'string',
                            'description' => 'Tên hoặc loại sản phẩm muốn mua (VD: vợt pickleball, áo polo, giày chạy bộ)',
                        ],
                        'color' => [
                            'type' => 'string',
                            'description' => 'Màu sắc nếu khách nói rõ (đen, trắng, đỏ, xanh...)',
                        ],
                        'size' => [
                            'type' => 'string',
                            'description' => 'Size nếu khách nói rõ (S, M, L, XL, 38, 39, 40...)',
                        ],
                        'quantity' => [
                            'type' => 'number',
                            'description' => 'Số lượng muốn mua, mặc định 1 nếu khách không nói rõ',
                        ],
                        'coupon_code' => [
                            'type' => 'string',
                            'description' => 'Mã giảm giá nếu khách yêu cầu áp dụng',
                        ],
                    ],
                    'required' => ['keyword'],
                ],
            ],
            [
                'name' => 'quick_order',
                'description' => 'Đặt hàng nhanh có preview: tìm sản phẩm, tạo bản xem trước để khách xác nhận. Chỉ dùng khi khách muốn xem trước đơn hàng trước khi đặt.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => [
                            'type' => 'string',
                            'description' => 'Tên hoặc loại sản phẩm muốn mua',
                        ],
                        'color' => [
                            'type' => 'string',
                            'description' => 'Màu sắc nếu khách nói rõ',
                        ],
                        'size' => [
                            'type' => 'string',
                            'description' => 'Size nếu khách nói rõ',
                        ],
                        'quantity' => [
                            'type' => 'number',
                            'description' => 'Số lượng, mặc định 1',
                        ],
                        'coupon_code' => [
                            'type' => 'string',
                            'description' => 'Mã giảm giá nếu có',
                        ],
                    ],
                    'required' => ['keyword'],
                ],
            ],
        ];
    }

    public function extractProductSearchFilters(string $message): array
    {
        $apiKey = $this->getApiKey();
        $url = "{$this->baseUrl}:generateContent?key={$apiKey}";

        $payload = [
            'system_instruction' => [
                'parts' => [[
                    'text' => 'Bạn chỉ trích xuất filter tìm kiếm sản phẩm cho Quyền Sport. Chỉ trả JSON hợp lệ, không markdown, không giải thích. Schema: {"is_product_search": boolean, "keyword": string|null, "category": string|null, "categories": string[], "color": string|null, "size": string|null, "min_price": number|null, "max_price": number|null, "quantity": number|null}. Giá VNĐ: 500k=500000, 1tr=1000000, 2tr=2000000, 10tr=10000000. keyword phải là TÊN SẢN PHẨM SẠCH (chỉ tên loại hàng, không gồm các từ như "đặt cho tôi", "mua", "order", "1 cái"). Ví dụ: "đặt cho tôi 1 vợt cầu lông đen" → keyword="vợt cầu lông", color="đen", quantity=1. Không tự tạo product_id/variant_id. Không trả limit. Nếu câu không phải tìm/mua sản phẩm thì is_product_search=false.',
                ]],
            ],
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => mb_substr($message, 0, 500)]],
            ]],
            'generation_config' => [
                'temperature' => 0,
                'top_p' => 0.1,
                'max_output_tokens' => 256,
                'thinking_config' => ['thinking_budget' => 0],
            ],
        ];

        try {
            $response = Http::timeout(20)->post($url, $payload);
            if (! $response->successful()) {
                Log::warning('Gemini product filter extraction failed', [
                    'status' => $response->status(),
                ]);

                return ['error' => true];
            }

            $parts = $response->json('candidates.0.content.parts') ?? [];
            $text = '';
            foreach ($parts as $part) {
                $text .= $part['text'] ?? '';
            }
            $text = trim(preg_replace('/^```json|```$/m', '', $text));
            $filters = json_decode($text, true);

            if (! is_array($filters)) {
                return ['error' => true];
            }

            return $filters;
        } catch (\Throwable $e) {
            Log::warning('Gemini product filter extraction exception', ['error' => $e->getMessage()]);

            return ['error' => true];
        }
    }

    /**
     * Gửi tin nhắn đến Gemini API với function calling
     *
     * @param  array  $conversationHistory  Lịch sử hội thoại [{role, parts}]
     * @param  bool  $isAuthenticated  User đã đăng nhập chưa
     * @return array Response từ Gemini
     */
    public function sendMessage(array $conversationHistory, bool $isAuthenticated = false): array
    {
        $cacheKey = 'gemini_resp_'.md5(json_encode($conversationHistory).'_'.($isAuthenticated ? '1' : '0'));

        return Cache::remember($cacheKey, 3600, function () use ($conversationHistory, $isAuthenticated) {
            $apiKey = $this->getApiKey();
            $url = "{$this->baseUrl}:generateContent?key={$apiKey}";

            // Thêm thông tin auth vào system prompt
            $authContext = $isAuthenticated
                ? "\n\nUSER STATUS: Đã đăng nhập (is_authenticated = true). Có thể tra cứu đơn hàng trực tiếp."
                : "\n\nUSER STATUS: Chưa đăng nhập (is_authenticated = false). Nếu muốn tra đơn hàng, cần yêu cầu mã đơn + email/SĐT.";

            $payload = [
                'system_instruction' => [
                    'parts' => [['text' => $this->systemPrompt.$authContext]],
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
                        Log::warning('Gemini rate limit hit, retry attempt '.($attempt + 1)." after {$wait}s");
                        sleep($wait);

                        continue;
                    }
                    break;
                }

                if (! $response->successful()) {
                    Log::error('Gemini API error', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    if ($response->status() === 429) {
                        return [
                            'error' => true,
                            'message' => 'Quyền Sport AI đang bận, vui lòng thử lại sau vài giây!',
                        ];
                    }

                    return [
                        'error' => true,
                        'message' => 'Quyền Sport AI đang gặp lỗi kết nối AI. Bạn có thể dùng các nút gợi ý nhanh hoặc nhập rõ hơn như: sản phẩm bán chạy, mã giảm giá, chính sách đổi trả, xem đơn hàng.',
                    ];
                }

                $data = $response->json();
                $candidate = $data['candidates'][0] ?? null;

                if (! $candidate) {
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
        });
    }

    /**
     * Gửi kết quả function call về Gemini để nhận response cuối cùng
     *
     * @param  array  $conversationHistory  Lịch sử hội thoại
     * @param  string  $functionName  Tên function đã gọi
     * @param  array  $functionResult  Kết quả trả về từ function
     * @param  bool  $isAuthenticated  User đã đăng nhập chưa
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
                        'args' => ! empty($functionArgs) ? $functionArgs : new \stdClass,
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
                'parts' => [['text' => $this->systemPrompt.$authContext]],
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
                    Log::warning('Gemini function result rate limit, retry attempt '.($attempt + 1));
                    sleep(2 + $attempt * 2); // 2s, 4s backoff

                    continue;
                }
                break;
            }

            if (! $response->successful()) {
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
