<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;

    private string $baseUrl;

    private string $model;

    /**
     * System prompt cấu hình "tính cách" cho chatbot Ocean Sport
     */
    private string $systemPrompt = <<<'PROMPT'
Bạn là Ocean Sport AI — chuyên gia tư vấn thể thao, thời trang thể thao và trợ lý mua sắm thông minh của Ocean Sport.
Nhiệm vụ của bạn không chỉ là trả lời câu hỏi mà còn là khơi gợi nhu cầu, tư vấn phong cách và mang lại trải nghiệm mua sắm tuyệt vời nhất.

NGUYÊN TẮC GIAO TIẾP VÀ TƯ VẤN (QUAN TRỌNG):
- Luôn trả lời bằng tiếng Việt một cách tự nhiên, nhiệt tình, lịch sự và thấu hiểu khách hàng.
- Trả lời ngắn gọn, súc tích nhưng truyền cảm hứng.
- Đóng vai như một stylist chuyên nghiệp: tư vấn phối đồ (mix & match), xu hướng thời trang mới nhất.
- LUÔN TÌM CƠ HỘI UP-SELL / CROSS-SELL: Khi khách hàng tìm một sản phẩm (VD: áo sơ mi), hãy chủ động gợi ý thêm quần, giày hoặc phụ kiện phù hợp để tạo thành một set đồ hoàn hảo.
- KHÔNG sử dụng emoji trong câu trả lời để giữ sự chuyên nghiệp, sang trọng.
- Nếu không biết câu trả lời hoặc vượt quá khả năng, hãy khéo léo đề nghị khách hàng liên hệ bộ phận hỗ trợ của Ocean Sport để được chuyên viên hỗ trợ.
- Bạn chỉ là lớp tư vấn và hiểu ý định mua hàng. Không được tự bịa giá, tồn kho, tổng tiền, địa chỉ, mã đơn hoặc khẳng định đã đặt hàng nếu backend chưa trả kết quả thành công.

QUY TẮC SỬ DỤNG FUNCTION (BẮT BUỘC TUÂN THỦ CHÍNH XÁC):

PHÂN BIỆT QUAN TRỌNG — `get_product_detail` vs `search_products`:
- Dùng `get_product_detail` khi: user HỎI VỀ MỘT SẢN PHẨM CỤ THỂ mà họ đã BIẾT TÊN — muốn biết màu gì, size gì, giá bao nhiêu, còn hàng không, thông tin chi tiết của sản phẩm đó.
  Ví dụ: "Áo thun Dry-Fit Pro có màu gì?" → get_product_detail(product_name="Áo thun thể thao nam Dry-Fit Pro")
  Ví dụ: "Giày Nike Air có size 42 không?" → get_product_detail(product_name="Giày Nike Air")
  Ví dụ: "Áo polo xanh size L còn không?" → get_product_detail(product_name="Áo polo xanh")
  Ví dụ: "Sản phẩm X giá bao nhiêu?" → get_product_detail(product_name="Sản phẩm X")
  TUYỆT ĐỐI KHÔNG dùng get_product_detail khi khách hàng đang nhờ tư vấn, chọn giúp hoặc gợi ý (như "nên chọn loại nào", "gợi ý cho tôi").
- Dùng `search_products` khi: user MUỐN TÌM / KHÁM PHÁ / ĐƯỢC GỢI Ý sản phẩm (chưa biết chính xác muốn mua cái gì) hoặc nhờ tư vấn chọn lựa.
  Ví dụ: "Tìm áo thun thể thao", "Gợi ý giày chạy bộ", "Có áo polo màu đen không?", "Mới chơi thì nên chọn vợt nào?"
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

QUY TẮC TRA CỨU ĐƠN HÀNG / VẬN ĐƠN:
- Khi khách hàng hỏi về đơn hàng, tra cứu vận đơn, hoặc CHỈ CẦN NHẬP MÃ ĐƠN HÀNG (VD: "ORD-123456", "tra cứu ORD-XXXXXX"):
  + Lập tức trích xuất mã đơn hàng và gọi function `get_order_status` với `order_code`.
  + KHÔNG BẮT BUỘC khách phải nhập Email hay Số điện thoại nếu đã có mã đơn hàng.
  + Khi nhận được kết quả, hãy trả lời thật NGẮN GỌN, SÚC TÍCH, GỌN GÀNG (tuyệt đối không viết văn dài dòng, không lặp lại mô tả dài):
    Dạ, thông tin đơn hàng **[order_code]** của anh/chị:
    • 🚚 **Trạng thái**: [status]
    • 💳 **Thanh toán**: [payment_status]
    • 💰 **Tổng tiền**: [grand_total]
    • 📍 **Địa chỉ**: [shipping_address]
- Nếu khách đã đăng nhập mà chỉ hỏi chung chung "xem đơn của tôi" hoặc "đơn hàng của tôi" → Gọi `get_order_status` không cần tham số để liệt kê danh sách đơn gần nhất.

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

THÔNG TIN CƠ BẢN CỦA OCEAN SPORT (để trả lời nhanh nếu cần):
- Địa chỉ: 134 Nguyễn Thị Định, P.Buôn Ma Thuột, Tỉnh Đắk Lắk
- Hotline: 1900-SPORT
- Email: contact@oceansport.vn
- Giờ làm việc: 8:00 - 22:00 hàng ngày

Hãy bắt đầu tương tác với sự tự tin của một Stylist hàng đầu!
PROMPT;

    public function __construct()
    {
        $this->apiKey = config('services.deepseek.api_key', '');
        $this->baseUrl = rtrim(config('services.deepseek.base_url', 'https://api.deepseek.com'), '/');
        $this->model = config('services.deepseek.model', 'deepseek-v4-pro');
    }

    /**
     * Khai báo các function (tools) theo chuẩn OpenAI
     * DeepSeek sẽ phân tích intent của user và tự quyết định gọi function nào
     */
    private function getToolDeclarations(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_products',
                    'description' => 'Tìm kiếm sản phẩm theo tên, từ khoá, danh mục, hoặc khoảng giá. Dùng khi khách hàng muốn tìm, hỏi về sản phẩm, hoặc cần gợi ý sản phẩm.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'keyword' => [
                                'type' => 'string',
                                'description' => 'Từ khoá tìm kiếm sản phẩm. TUYỆT ĐỐI CHỈ dùng 1-2 từ RẤT NGẮN GỌN (VD: "giày", "áo", "vợt", "tạ", "nike"). KHÔNG ĐƯỢC đưa giới tính (nam, nữ) hay mục đích (chạy bộ, tập gym, cho người mới) vào keyword vì hệ thống sẽ trả về 0 kết quả. Nếu khách hỏi chung chung, hãy bỏ trống keyword và dùng category thay thế.',
                            ],
                            'category' => [
                                'type' => 'string',
                                'description' => 'Tên danh mục sản phẩm. CHỈ lấy danh từ ngắn gọn (VD: "quần áo", "giày", "dụng cụ", "cầu lông", "pickleball"). TUYỆT ĐỐI không đưa mục đích sử dụng, giới tính hay mô tả dài dòng vào đây.',
                            ],
                            'color' => [
                                'type' => 'string',
                                'description' => 'Màu sắc sản phẩm. CHỈ DÙNG khi khách chủ động yêu cầu màu cụ thể (VD: "áo màu đen"). TUYỆT ĐỐI KHÔNG tự động suy luận màu (VD: khách hỏi mệnh phong thuỷ) để điền vào đây, vì database có thể không lưu màu của một số dụng cụ (vợt, bóng) dẫn đến 0 kết quả.',
                            ],
                            'size' => [
                                'type' => 'string',
                                'description' => 'Kích thước sản phẩm (S, M, L, XL, XXL, 38, 39, 40...). Nếu nhiều size, phân cách bằng phẩy.',
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
            ],
            [
                'type' => 'function',
                'function' => [
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
            ],
            [
                'type' => 'function',
                'function' => [
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
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_my_addresses',
                    'description' => 'Lấy danh sách địa chỉ giao hàng của khách hàng đã đăng nhập để chọn khi chuẩn bị đặt hàng.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass,
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
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
            ],
            [
                'type' => 'function',
                'function' => [
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
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_order_status',
                    'description' => 'Tra cứu trạng thái và tiến độ vận đơn của đơn hàng. Chỉ cần truyền order_code (mã đơn hàng) là có thể tra cứu được ngay lập tức.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'order_code' => [
                                'type' => 'string',
                                'description' => 'Mã đơn hàng cần tra cứu (VD: ORD-XXXXXX hoặc chuỗi mã đơn)',
                            ],
                            'email' => [
                                'type' => 'string',
                                'description' => 'Email đặt hàng (tuỳ chọn, dùng để xác minh bổ sung nếu có)',
                            ],
                            'phone' => [
                                'type' => 'string',
                                'description' => 'Số điện thoại nhận hàng (tuỳ chọn, dùng để xác minh bổ sung nếu có)',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_available_coupons',
                    'description' => 'Lấy danh sách mã giảm giá đang có hiệu lực. Dùng khi khách hỏi về voucher, mã giảm giá, khuyến mãi.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass,
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_categories',
                    'description' => 'Lấy danh sách tất cả danh mục sản phẩm của shop',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass,
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
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
            ],
            [
                'type' => 'function',
                'function' => [
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
            ],
            [
                'type' => 'function',
                'function' => [
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
            ],
        ];
    }

    /**
     * Trích xuất filter tìm kiếm sản phẩm từ tin nhắn user
     * Sử dụng DeepSeek API thay thế Gemini
     */
    public function extractProductSearchFilters(string $message): array
    {
        $url = "{$this->baseUrl}/chat/completions";

        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Bạn chỉ trích xuất filter tìm kiếm sản phẩm cho Ocean Sport. Chỉ trả JSON hợp lệ, không markdown, không giải thích. Schema: {"is_product_search": boolean, "keyword": string|null, "category": string|null, "categories": string[], "color": string|null, "size": string|null, "min_price": number|null, "max_price": number|null, "quantity": number|null}. Giá VNĐ: 500k=500000, 1tr=1000000, 2tr=2000000, 10tr=10000000. keyword phải là TÊN SẢN PHẨM SẠCH (chỉ tên loại hàng, không gồm các từ như "đặt cho tôi", "mua", "order", "1 cái"). Ví dụ: "đặt cho tôi 1 vợt cầu lông đen" → keyword="vợt cầu lông", color="đen", quantity=1. Không tự tạo product_id/variant_id. Không trả limit. Nếu câu không phải tìm/mua sản phẩm thì is_product_search=false.',
                ],
                [
                    'role' => 'user',
                    'content' => mb_substr($message, 0, 500),
                ],
            ],
            'temperature' => 0,
            'top_p' => 0.1,
            'max_tokens' => 256,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(20)->post($url, $payload);

            if (! $response->successful()) {
                Log::warning('DeepSeek product filter extraction failed', [
                    'status' => $response->status(),
                ]);

                return ['error' => true];
            }

            $data = $response->json();
            $text = $data['choices'][0]['message']['content'] ?? '';
            $text = trim(preg_replace('/^```json|```$/m', '', $text));
            $filters = json_decode($text, true);

            if (! is_array($filters)) {
                return ['error' => true];
            }

            return $filters;
        } catch (\Throwable $e) {
            Log::warning('DeepSeek product filter extraction exception', ['error' => $e->getMessage()]);

            return ['error' => true];
        }
    }

    /**
     * Gửi tin nhắn đến DeepSeek API với function calling (tool_choice: auto)
     *
     * @param  array  $conversationHistory  Lịch sử hội thoại [{role, parts}] (format Gemini từ frontend)
     * @param  bool  $isAuthenticated  User đã đăng nhập chưa
     * @return array Response chuẩn hoá (giữ nguyên interface cho Controller)
     */
    public function sendMessage(array $conversationHistory, bool $isAuthenticated = false): array
    {
        $cacheKey = 'deepseek_resp_'.md5(json_encode($conversationHistory).'_'.($isAuthenticated ? '1' : '0'));

        return Cache::remember($cacheKey, 3600, function () use ($conversationHistory, $isAuthenticated) {
            $url = "{$this->baseUrl}/chat/completions";

            // Thêm thông tin auth vào system prompt
            $authContext = $isAuthenticated
                ? "\n\nUSER STATUS: Đã đăng nhập (is_authenticated = true). Có thể tra cứu đơn hàng trực tiếp."
                : "\n\nUSER STATUS: Chưa đăng nhập (is_authenticated = false). Nếu muốn tra đơn hàng, cần yêu cầu mã đơn + email/SĐT.";

            // Chuyển đổi conversation history từ format Gemini sang format OpenAI
            $messages = $this->convertHistoryToOpenAI($conversationHistory, $authContext);

            $payload = [
                'model' => $this->model,
                'messages' => $messages,
                'tools' => $this->getToolDeclarations(),
                'tool_choice' => 'auto',
                'temperature' => 0.7,
                'top_p' => 0.95,
                'max_tokens' => 1024,
            ];

            try {
                $maxRetries = 3;
                $response = null;

                for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer '.$this->apiKey,
                        'Content-Type' => 'application/json',
                    ])->timeout(60)->post($url, $payload);

                    if ($response->status() === 429 && $attempt < $maxRetries) {
                        // Rate limit — exponential backoff
                        $wait = 2 + $attempt * 2; // 2s, 4s, 6s
                        Log::warning('DeepSeek rate limit hit, retry attempt '.($attempt + 1)." after {$wait}s");
                        sleep($wait);

                        continue;
                    }
                    break;
                }

                if (! $response->successful()) {
                    Log::error('DeepSeek API error', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    if ($response->status() === 429) {
                        return [
                            'error' => true,
                            'message' => 'Ocean Sport AI đang bận, vui lòng thử lại sau vài giây!',
                        ];
                    }

                    return [
                        'error' => true,
                        'message' => 'Ocean Sport AI đang gặp lỗi kết nối AI. Bạn có thể dùng các nút gợi ý nhanh hoặc nhập rõ hơn như: sản phẩm bán chạy, mã giảm giá, chính sách đổi trả, xem đơn hàng.',
                    ];
                }

                $data = $response->json();
                $choice = $data['choices'][0] ?? null;

                if (! $choice) {
                    return [
                        'error' => true,
                        'message' => 'Không nhận được phản hồi từ AI.',
                    ];
                }

                $message = $choice['message'] ?? [];

                // Check nếu DeepSeek muốn gọi function (tool_calls)
                if (! empty($message['tool_calls'])) {
                    $toolCall = $message['tool_calls'][0]; // Lấy tool call đầu tiên
                    $functionName = $toolCall['function']['name'] ?? '';
                    $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?? [];

                    return [
                        'type' => 'function_call',
                        'function_name' => $functionName,
                        'arguments' => $arguments,
                        '_tool_call_id' => $toolCall['id'] ?? null, // Lưu lại để dùng khi gửi function result
                    ];
                }

                // Trả về text response thông thường
                return [
                    'type' => 'text',
                    'message' => $message['content'] ?? '',
                ];

            } catch (\Exception $e) {
                Log::error('DeepSeek API exception', ['error' => $e->getMessage()]);

                return [
                    'error' => true,
                    'message' => 'Kết nối đến AI bị gián đoạn. Vui lòng thử lại!',
                ];
            }
        });
    }

    /**
     * Gửi kết quả function call về DeepSeek để nhận response cuối cùng
     *
     * @param  array  $conversationHistory  Lịch sử hội thoại (format Gemini từ frontend)
     * @param  string  $functionName  Tên function đã gọi
     * @param  array  $functionResult  Kết quả trả về từ function
     * @param  bool  $isAuthenticated  User đã đăng nhập chưa
     * @param  array  $functionArgs  Arguments gốc đã gọi function
     */
    public function sendFunctionResult(
        array $conversationHistory,
        string $functionName,
        array $functionResult,
        bool $isAuthenticated = false,
        array $functionArgs = []
    ): array {
        $url = "{$this->baseUrl}/chat/completions";

        $authContext = $isAuthenticated
            ? "\n\nUSER STATUS: Đã đăng nhập (is_authenticated = true)."
            : "\n\nUSER STATUS: Chưa đăng nhập (is_authenticated = false).";

        // Chuyển đổi history sang format OpenAI
        $messages = $this->convertHistoryToOpenAI($conversationHistory, $authContext);

        // Tạo unique tool_call_id cho lần gọi này
        $toolCallId = 'call_'.md5($functionName.json_encode($functionArgs).microtime(true));

        // Thêm assistant message với tool_calls (model muốn gọi function)
        $messages[] = [
            'role' => 'assistant',
            'content' => '',
            'reasoning_content' => '',
            'tool_calls' => [
                [
                    'id' => $toolCallId,
                    'type' => 'function',
                    'function' => [
                        'name' => $functionName,
                        'arguments' => json_encode($functionArgs ?: new \stdClass),
                    ],
                ],
            ],
        ];

        // Thêm tool response (kết quả thực thi function)
        $messages[] = [
            'role' => 'tool',
            'tool_call_id' => $toolCallId,
            'content' => json_encode($functionResult, JSON_UNESCAPED_UNICODE),
        ];

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'tools' => $this->getToolDeclarations(),
            'temperature' => 0.7,
            'top_p' => 0.95,
            'max_tokens' => 1024,
        ];

        try {
            $maxRetries = 2;
            $response = null;

            for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ])->timeout(60)->post($url, $payload);

                if ($response->status() === 429 && $attempt < $maxRetries) {
                    Log::warning('DeepSeek function result rate limit, retry attempt '.($attempt + 1));
                    sleep(2 + $attempt * 2); // 2s, 4s backoff

                    continue;
                }
                break;
            }

            if (! $response->successful()) {
                Log::error('DeepSeek function result error', [
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
            $choice = $data['choices'][0] ?? null;
            $text = $choice['message']['content'] ?? '';

            return [
                'type' => 'text',
                'message' => $text,
            ];

        } catch (\Exception $e) {
            Log::error('DeepSeek function result exception', ['error' => $e->getMessage()]);

            return [
                'error' => true,
                'type' => 'text',
                'message' => '',
            ];
        }
    }

    /**
     * Chuyển đổi conversation history từ format Gemini (parts/contents)
     * sang format OpenAI/DeepSeek (messages)
     *
     * Gemini format:
     *   [{ role: "user", parts: [{ text: "..." }] }, { role: "model", parts: [{ text: "..." }] }]
     *
     * OpenAI format:
     *   [{ role: "system", content: "..." }, { role: "user", content: "..." }, { role: "assistant", content: "..." }]
     */
    private function convertHistoryToOpenAI(array $geminiHistory, string $authContext): array
    {
        $messages = [];

        // System message luôn đặt ở đầu
        $messages[] = [
            'role' => 'system',
            'content' => $this->systemPrompt.$authContext,
        ];

        foreach ($geminiHistory as $entry) {
            $role = $entry['role'] ?? 'user';
            $text = '';

            // Extract text từ Gemini parts format
            if (isset($entry['parts']) && is_array($entry['parts'])) {
                foreach ($entry['parts'] as $part) {
                    if (isset($part['text'])) {
                        $text .= $part['text'];
                    }
                }
            }

            // Skip empty messages
            if (trim($text) === '') {
                continue;
            }

            // Map Gemini roles sang OpenAI roles
            // Gemini: "user", "model"
            // OpenAI: "user", "assistant"
            $openAIRole = ($role === 'model') ? 'assistant' : 'user';

            $msg = [
                'role' => $openAIRole,
                'content' => $text,
            ];

            if ($openAIRole === 'assistant') {
                $msg['reasoning_content'] = '';
            }

            $messages[] = $msg;
        }

        return $messages;
    }
}
