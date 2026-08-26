<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Chatbot\ChatbotActionService;
use App\Services\Chatbot\ChatbotInfoService;
use App\Services\Chatbot\ChatbotSessionService;
use App\Services\Chatbot\IntentParserService;
use App\Services\GeminiService;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    private GeminiService $gemini;

    private ChatbotActionService $chatbotActions;

    private IntentParserService $intentParser;

    private ChatbotSessionService $sessionService;

    private ChatbotInfoService $chatbotInfo;

    public function __construct(GeminiService $gemini, ChatbotActionService $chatbotActions, IntentParserService $intentParser, ChatbotSessionService $sessionService, ChatbotInfoService $chatbotInfo)
    {
        $this->gemini = $gemini;
        $this->chatbotActions = $chatbotActions;
        $this->intentParser = $intentParser;
        $this->sessionService = $sessionService;
        $this->chatbotInfo = $chatbotInfo;
    }

    /**
     * Xử lý tin nhắn chatbot
     * POST /api/chatbot/message
     *
     * @param  Request  $request  { message: string, history: array }
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'history' => 'nullable|array',
            'history.*.role' => 'required_with:history|string|in:user,model',
            'history.*.parts' => 'required_with:history|array',
        ]);

        $userMessage = trim($request->input('message'));
        $history = $request->input('history', []);

        // Detect user auth — hỗ trợ cả user và admin
        $isAuthenticated = false;
        $authUser = null;
        $customerUser = null;
        try {
            // Thử user guard trước
            $authUser = auth('api')->user();
            $customerUser = $authUser;

            // Nếu không phải user, thử admin guard
            if (! $authUser) {
                $adminUser = auth('admin')->user();
                if ($adminUser) {
                    // Admin đăng nhập → tìm user tương ứng (cùng email) để tra đơn hàng
                    $matchedUser = User::where('email', $adminUser->email)
                        ->orWhere('phone', $adminUser->phone)
                        ->first();
                    if ($matchedUser) {
                        $authUser = $matchedUser;
                    }
                }
            }

            $isAuthenticated = $authUser !== null;
        } catch (\Exception $e) {
            // Không có token hoặc token không hợp lệ → guest
        }

        // Ưu tiên xử lý deterministic cho các intent phổ biến để tránh phụ thuộc Gemini lúc API lỗi/nhập ngắn
        $sessionId = $request->input('session_id');
        $sessionId = $this->sessionService->getSessionId($sessionId, $authUser);
        // Bỏ qua xử lý Rule-based để ném thẳng vào Gemini AI
        // $directResponse = $this->handleDirectIntent($userMessage, $authUser, $customerUser, $request, $sessionId);
        // if ($directResponse) {
        //     return response()->json($directResponse);
        // }

        // Build conversation history cho Gemini
        $conversationHistory = $this->buildConversationHistory($history, $userMessage);

        // Bước 1: Gửi tin nhắn đến Gemini
        $response = $this->gemini->sendMessage($conversationHistory, $isAuthenticated);

        // Check error
        if (isset($response['error'])) {
            return response()->json([
                'success' => false,
                'message' => $response['message'],
                'data' => null,
                'type' => 'text',
                'session_id' => $sessionId ?? '',
            ]);
        }

        // Bước 2: Nếu Gemini muốn gọi function → thực thi và gửi kết quả lại
        if ($response['type'] === 'function_call') {
            $functionName = $response['function_name'];
            $arguments = $response['arguments'];

            // Thực thi function qua allowlist an toàn
            $functionResult = $this->executeFunction($functionName, $arguments, $authUser, $customerUser, $request);

            // Lưu context (những sản phẩm AI gợi ý hoặc người dùng đang xem)
            if ($functionName === 'search_products' && ($functionResult['status'] ?? 'error') === 'success' && ! empty($functionResult['data'])) {
                $productIds = array_column($functionResult['data'], 'product_id');
                $this->sessionService->saveRecommendedProducts($sessionId, $productIds);
            } elseif ($functionName === 'get_product_detail' && ($functionResult['status'] ?? 'error') === 'success' && ! empty($functionResult['data']['product']['product_id'])) {
                $this->sessionService->saveRecommendedProducts($sessionId, [$functionResult['data']['product']['product_id']]);
            }

            // Gửi kết quả function về Gemini để tạo response text
            $finalResponse = $this->gemini->sendFunctionResult(
                $conversationHistory,
                $functionName,
                $functionResult,
                $isAuthenticated,
                $arguments
            );

            // Nếu Gemini API lỗi (rate limit, etc.), tự tạo fallback message từ data
            $message = $finalResponse['message'] ?? '';
            if (isset($finalResponse['error']) || empty($message)) {
                $message = $this->buildFallbackMessage($functionName, $functionResult);
            }

            // Ghi đè message từ backend cho các hàm xử lý nghiệp vụ (vì message của backend cực kỳ chính xác và đầy đủ, tránh Gemini tự chế lỗi)
            if (in_array($functionName, ['auto_order', 'quick_order', 'add_to_cart', 'prepare_order', 'confirm_order'])) {
                $message = $functionResult['message'] ?? $message;
            }

            // Map quick_order status → frontend type
            $responseType = $functionName;
            if ($functionName === 'quick_order') {
                $responseType = match ($functionResult['status'] ?? 'error') {
                    'choose_variant' => 'quick_order_choose_variant',
                    'no_address' => 'quick_order_no_address',
                    'over_limit' => 'quick_order_over_limit',
                    'success' => 'order_preview',
                    default => 'quick_order',
                };
            }

            if ($functionName === 'auto_order') {
                $responseType = match ($functionResult['status'] ?? 'error') {
                    'auto_order_success' => 'order_confirmation',
                    'need_variant_info' => 'need_variant_info',
                    'color_not_found' => 'color_not_found',
                    'size_not_found' => 'size_not_found',
                    'no_address',
                    'over_limit',
                    'not_found' => 'text',
                    default => 'text',
                };

                // Trả thêm status để frontend có thể handle
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => $functionResult['data'] ?? null,
                    'type' => $responseType,
                    'status' => $functionResult['status'] ?? 'error',
                    'session_id' => $sessionId ?? '',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $functionResult['data'] ?? null,
                'type' => $responseType,
                'session_id' => $sessionId ?? '',
            ]);
        }

        // Bước 3: Response text thông thường (không cần function call)
        return response()->json([
            'success' => true,
            'message' => $response['message'],
            'data' => null,
            'type' => 'text',
            'session_id' => $sessionId ?? '',
        ]);
    }

    public function addToCart(Request $request)
    {
        $request->validate([
            'product_id' => 'nullable|integer',
            'variant_id' => 'required|integer',
            'quantity' => 'nullable|integer|min:1|max:20',
        ]);

        $result = $this->chatbotActions->addToCart($request->only(['product_id', 'variant_id', 'quantity']), auth('api')->user());

        return response()->json([
            'success' => ($result['status'] ?? 'error') === 'success',
            'message' => $result['message'] ?? '',
            'data' => $result['data'] ?? null,
            'type' => ($result['status'] ?? '') === 'requires_login' ? 'requires_login' : 'cart_summary',
        ], ($result['status'] ?? '') === 'success' ? 200 : 422);
    }

    public function getAddresses(Request $request)
    {
        $result = $this->chatbotActions->getCheckoutAddresses(auth('api')->user());

        return response()->json([
            'success' => ($result['status'] ?? 'error') === 'success',
            'message' => $result['message'] ?? '',
            'data' => $result['data'] ?? null,
            'type' => 'get_my_addresses',
        ], ($result['status'] ?? '') === 'success' ? 200 : 422);
    }

    public function prepareOrder(Request $request)
    {
        $request->validate([
            'address_id' => 'required|integer',
            'payment_method' => 'nullable|in:cod,bank_transfer',
            'coupon_applied' => 'nullable|string|max:50',
            'note' => 'nullable|string|max:500',
        ]);

        $result = $this->chatbotActions->prepareOrder($request->only(['address_id', 'payment_method', 'coupon_applied', 'note']), auth('api')->user());

        return response()->json([
            'success' => ($result['status'] ?? 'error') === 'success',
            'message' => $result['message'] ?? '',
            'data' => $result['data'] ?? null,
            'type' => 'order_preview',
        ], ($result['status'] ?? '') === 'success' ? 200 : 422);
    }

    public function confirmOrder(Request $request)
    {
        $request->validate([
            'confirmation_token' => 'required|string|max:120',
        ]);

        $result = $this->chatbotActions->confirmOrder($request->only(['confirmation_token']), auth('api')->user(), $request);

        return response()->json([
            'success' => ($result['status'] ?? 'error') === 'success',
            'message' => $result['message'] ?? '',
            'data' => $result['data'] ?? null,
            'type' => 'order_confirmation',
        ], ($result['status'] ?? '') === 'success' ? 200 : 422);
    }

    /**
     * Quick Order với variant đã chọn
     * POST /api/chatbot/quick-order
     */
    public function quickOrder(Request $request)
    {
        $request->validate([
            'variant_id' => 'required|integer',
            'quantity' => 'nullable|integer|min:1|max:20',
            'coupon_code' => 'nullable|string|max:50',
        ]);

        $result = $this->chatbotActions->quickOrderWithVariant(
            $request->only(['variant_id', 'quantity', 'coupon_code']),
            auth('api')->user()
        );

        $status = $result['status'] ?? 'error';
        $responseType = match ($status) {
            'choose_variant' => 'quick_order_choose_variant',
            'no_address' => 'quick_order_no_address',
            'over_limit' => 'quick_order_over_limit',
            'success' => 'order_preview',
            default => 'quick_order',
        };

        return response()->json([
            'success' => $status === 'success',
            'message' => $result['message'] ?? '',
            'data' => $result['data'] ?? null,
            'type' => $responseType,
        ], $status === 'success' ? 200 : 422);
    }

    /**
     * Cập nhật preferences (default payment method)
     * POST /api/chatbot/preferences
     */
    public function updatePreferences(Request $request)
    {
        $request->validate([
            'default_payment_method' => 'required|in:cod,bank_transfer',
        ]);

        $user = auth('api')->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn cần đăng nhập để cập nhật tuỳ chọn.',
            ], 401);
        }

        $user->forceFill([
            'default_payment_method' => $request->input('default_payment_method'),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Đã lưu phương thức thanh toán mặc định.',
            'data' => ['default_payment_method' => $user->default_payment_method],
            'type' => 'text',
            'session_id' => $sessionId ?? '',
        ]);
    }

    /**
     * Build conversation history theo format Gemini API
     */
    private function buildConversationHistory(array $history, string $newMessage): array
    {
        $conversation = [];

        // Thêm history cũ (giới hạn 5 tin nhắn gần nhất để tiết kiệm tối đa token)
        $recentHistory = array_slice($history, -5);
        foreach ($recentHistory as $entry) {
            $text = $entry['parts'][0]['text'] ?? '';
            if (! is_string($text) || trim($text) === '') {
                continue;
            }
            $conversation[] = [
                'role' => $entry['role'],
                'parts' => [['text' => mb_substr(strip_tags($text), 0, 1000)]],
            ];
        }

        // Thêm tin nhắn mới của user
        $conversation[] = [
            'role' => 'user',
            'parts' => [['text' => mb_substr(strip_tags($newMessage), 0, 1000)]],
        ];

        return $conversation;
    }

    /**
     * Thực thi function call từ Gemini
     *
     * @param  mixed|null  $authUser
     * @return array Kết quả function
     */
    private function executeFunction(string $functionName, array $arguments, $authUser = null, $customerUser = null, ?Request $request = null): array
    {
        return match ($functionName) {
            'search_products',
            'get_product_detail',
            'add_to_cart',
            'get_my_addresses',
            'add_shipping_address',
            'prepare_order',
            'confirm_order',
            'auto_order',
            'quick_order' => $this->chatbotActions->execute($functionName, $arguments, $customerUser, $request),
            'get_order_status' => $this->chatbotInfo->getOrderStatus($arguments, $authUser),
            'get_available_coupons' => $this->chatbotInfo->getAvailableCoupons(),
            'get_categories' => $this->chatbotInfo->getCategories(),
            'get_store_info' => $this->chatbotInfo->getStoreInfo($arguments),
            default => ['status' => 'error', 'message' => 'Function không tồn tại'],
        };
    }

    // ================================================================
    //  FALLBACK MESSAGE — Khi Gemini API lỗi, tự tạo response text
    // ================================================================

    /**
     * Tạo message text từ function result khi Gemini API không khả dụng
     */
    private function buildFallbackMessage(string $functionName, array $result): string
    {
        $status = $result['status'] ?? 'error';
        $message = $result['message'] ?? '';
        $data = $result['data'] ?? [];

        // Nếu function trả về lỗi hoặc không có data
        if ($status !== 'success' || empty($data)) {
            return $message ?: 'Không tìm thấy kết quả phù hợp.';
        }

        return match ($functionName) {
            'search_products' => $this->buildProductFallback($data),
            'get_product_detail' => $this->buildProductDetailFallback($data),
            'get_order_status' => $this->buildOrderFallback($data),
            'get_available_coupons' => $this->buildCouponFallback($data),
            'get_categories' => $this->buildCategoryFallback($data),
            'get_store_info' => $data['title'] ?? 'Thông tin cửa hàng Ocean Sport.',
            default => $message ?: 'Đã xử lý xong.',
        };
    }

    private function buildProductFallback(array $data): string
    {
        $count = count($data);
        $lines = ["Tìm thấy {$count} sản phẩm:"];
        foreach (array_slice($data, 0, 5) as $p) {
            $lines[] = "- {$p['name']}: {$p['price']}";
        }

        return implode("\n", $lines);
    }

    private function buildProductDetailFallback(array $data): string
    {
        $name = $data['name'] ?? 'Sản phẩm';
        $price = $data['price_range'] ?? '';

        $colors = ! empty($data['available_colors']) ? implode(', ', $data['available_colors']) : null;
        $sizes = ! empty($data['available_sizes']) ? implode(', ', $data['available_sizes']) : null;

        $lines = ["Sản phẩm: {$name}"];
        if ($price) {
            $lines[] = "Giá: {$price}";
        }
        if ($colors) {
            $lines[] = "Màu sắc hiện có: {$colors}";
        }
        if ($sizes) {
            $lines[] = "Size hiện có: {$sizes}";
        }
        $lines[] = 'Bạn muốn chọn màu/size nào để đặt hàng?';

        return implode("\n", $lines);
    }

    private function buildOrderFallback($data): string
    {
        if (! is_array($data) || empty($data)) {
            return 'Không tìm thấy thông tin đơn hàng.';
        }

        // Nếu là 1 đơn hàng cụ thể
        if (isset($data['order_code'])) {
            $lines = [
                "📦 **Đơn hàng: {$data['order_code']}**",
                "🚚 **Trạng thái vận chuyển**: {$data['status']}",
                "💳 **Thanh toán**: {$data['payment_status']} ({$data['payment_method']})",
                "💰 **Tổng tiền**: {$data['grand_total']}",
            ];
            if (! empty($data['items'])) {
                $lines[] = "\n**Sản phẩm trong đơn**:";
                foreach ($data['items'] as $item) {
                    $lines[] = "• {$item['product_name']} ({$item['variant']}) x{$item['quantity']} - {$item['unit_price']}";
                }
            }
            if (! empty($data['shipping_address'])) {
                $lines[] = "📍 **Địa chỉ nhận hàng**: {$data['shipping_address']}";
            }
            return implode("\n", $lines);
        }

        // Nếu là danh sách nhiều đơn hàng
        $lines = ["Tìm thấy ".count($data)." đơn hàng gần đây của bạn:"];
        foreach ($data as $order) {
            $lines[] = "• **{$order['order_code']}** ({$order['created_at']}): {$order['status']} - {$order['grand_total']}";
        }
        return implode("\n", $lines);
    }

    private function buildCouponFallback(array $data): string
    {
        return 'Dạ đây là các mã giảm giá đang có hiệu lực tại shop:';
    }

    private function buildCategoryFallback(array $data): string
    {
        $lines = ['Danh mục sản phẩm:'];
        foreach ($data as $cat) {
            $lines[] = "- {$cat['name']} ({$cat['product_count']} sản phẩm)";
        }

        return implode("\n", $lines);
    }
}
