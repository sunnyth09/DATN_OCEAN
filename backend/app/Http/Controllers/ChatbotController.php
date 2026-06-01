<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\GeminiService;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Models\Coupon;
use App\Models\User;

class ChatbotController extends Controller
{
    private GeminiService $gemini;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    /**
     * Xử lý tin nhắn chatbot
     * POST /api/chatbot/message
     *
     * @param Request $request { message: string, history: array }
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'message'   => 'required|string|max:1000',
            'history'   => 'nullable|array',
            'history.*.role'  => 'required_with:history|string|in:user,model',
            'history.*.parts' => 'required_with:history|array',
        ]);

        $userMessage = trim($request->input('message'));
        $history = $request->input('history', []);

        // Detect user auth — hỗ trợ cả user và admin
        $isAuthenticated = false;
        $authUser = null;
        try {
            // Thử user guard trước
            $authUser = auth('api')->user();

            // Nếu không phải user, thử admin guard
            if (!$authUser) {
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

        // Build conversation history cho Gemini
        $conversationHistory = $this->buildConversationHistory($history, $userMessage);

        // Bước 1: Gửi tin nhắn đến Gemini
        $response = $this->gemini->sendMessage($conversationHistory, $isAuthenticated);

        // Check error
        if (isset($response['error'])) {
            return response()->json([
                'success' => false,
                'message' => $response['message'],
                'data'    => null,
                'type'    => 'text',
            ]);
        }

        // Bước 2: Nếu Gemini muốn gọi function → thực thi và gửi kết quả lại
        if ($response['type'] === 'function_call') {
            $functionName = $response['function_name'];
            $arguments = $response['arguments'];

            // Thực thi function
            $functionResult = $this->executeFunction($functionName, $arguments, $authUser);

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

            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => $functionResult['data'] ?? null,
                'type'    => $functionName,
            ]);
        }

        // Bước 3: Response text thông thường (không cần function call)
        return response()->json([
            'success' => true,
            'message' => $response['message'],
            'data'    => null,
            'type'    => 'text',
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
            $conversation[] = [
                'role'  => $entry['role'],
                'parts' => $entry['parts'],
            ];
        }

        // Thêm tin nhắn mới của user
        $conversation[] = [
            'role'  => 'user',
            'parts' => [['text' => $newMessage]],
        ];

        return $conversation;
    }

    /**
     * Thực thi function call từ Gemini
     *
     * @param string     $functionName
     * @param array      $arguments
     * @param mixed|null $authUser
     * @return array  Kết quả function
     */
    private function executeFunction(string $functionName, array $arguments, $authUser = null): array
    {
        return match ($functionName) {
            'search_products'      => $this->searchProducts($arguments),
            'get_product_detail'   => $this->getProductDetail($arguments),
            'get_order_status'     => $this->getOrderStatus($arguments, $authUser),
            'get_available_coupons'=> $this->getAvailableCoupons(),
            'get_categories'       => $this->getCategories(),
            'get_store_info'       => $this->getStoreInfo($arguments),
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
            'get_store_info' => $data['title'] ?? 'Thông tin cửa hàng Ocean Store.',
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
        $name = $data['name'] ?? '';
        $price = $data['price_range'] ?? '';
        return "Sản phẩm {$name} - Giá: {$price}. Bạn có muốn xem thêm chi tiết không?";
    }

    private function buildOrderFallback($data): string
    {
        if (!is_array($data)) return 'Không tìm thấy đơn hàng.';

        // Single order
        if (isset($data['order_code'])) {
            return "Đơn hàng {$data['order_code']} - Trạng thái: {$data['status']} - Tổng: {$data['grand_total']}";
        }

        // Multiple orders
        $count = count($data);
        $lines = ["Tìm thấy {$count} đơn hàng:"];
        foreach (array_slice($data, 0, 5) as $order) {
            $lines[] = "- {$order['order_code']}: {$order['status']} - {$order['grand_total']}";
        }
        return implode("\n", $lines);
    }

    private function buildCouponFallback(array $data): string
    {
        $count = count($data);
        $lines = ["Hiện có {$count} mã giảm giá:"];
        foreach ($data as $c) {
            $lines[] = "- {$c['code']}: {$c['description']} (Đơn tối thiểu: {$c['min_order']})";
        }
        return implode("\n", $lines);
    }

    private function buildCategoryFallback(array $data): string
    {
        $lines = ["Danh mục sản phẩm:"];
        foreach ($data as $cat) {
            $lines[] = "- {$cat['name']} ({$cat['product_count']} sản phẩm)";
        }
        return implode("\n", $lines);
    }

    // ================================================================
    //  FUNCTION IMPLEMENTATIONS — Truy vấn database thật
    // ================================================================

    /**
     * Tìm kiếm sản phẩm
     */
    private function searchProducts(array $args): array
    {
        $color = $args['color'] ?? null;
        $size = $args['size'] ?? null;

        $query = Product::query()
            ->where('status', 'active')
            ->with(['category', 'mainImage']);

        // Eager load variants — nếu có color/size thì filter variants theo đó
        $query->with(['variants' => function ($q) use ($color, $size) {
            $q->where('status', 'active');
            if ($color) {
                $q->where('color', 'LIKE', "%{$color}%");
            }
            if ($size) {
                $q->where('size', 'LIKE', "%{$size}%");
            }
            $q->orderBy('price', 'asc');
        }]);

        // Tìm theo keyword
        if (!empty($args['keyword'])) {
            $keyword = $args['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'LIKE', "%{$keyword}%")
                  ->orWhere('short_description', 'LIKE', "%{$keyword}%")
                  ->orWhere('description', 'LIKE', "%{$keyword}%");
            });
        }

        // Tìm theo danh mục
        if (!empty($args['category'])) {
            $categoryName = $args['category'];
            $categoryIds = Category::where('name', 'LIKE', "%{$categoryName}%")
                ->pluck('category_id');
            if ($categoryIds->isNotEmpty()) {
                $query->whereIn('category_id', $categoryIds);
            }
        }

        // Filter theo màu sắc — chỉ lấy product có variant với màu đó
        if ($color) {
            $query->whereHas('variants', function ($q) use ($color) {
                $q->where('status', 'active')
                  ->where('color', 'LIKE', "%{$color}%");
            });
        }

        // Filter theo size — chỉ lấy product có variant với size đó
        if ($size) {
            $query->whereHas('variants', function ($q) use ($size) {
                $q->where('status', 'active')
                  ->where('size', 'LIKE', "%{$size}%");
            });
        }

        // Filter theo giá — sử dụng variant price để chính xác hơn
        if (!empty($args['min_price']) || !empty($args['max_price'])) {
            $minPrice = $args['min_price'] ?? null;
            $maxPrice = $args['max_price'] ?? null;
            $query->whereHas('variants', function ($q) use ($minPrice, $maxPrice) {
                $q->where('status', 'active');
                if ($minPrice) {
                    $q->where('price', '>=', $minPrice);
                }
                if ($maxPrice) {
                    $q->where('price', '<=', $maxPrice);
                }
            });
        }

        $products = $query->orderByDesc('sold_count')->limit(6)->get();

        if ($products->isEmpty()) {
            return [
                'status' => 'no_results',
                'message' => 'Không tìm thấy sản phẩm nào phù hợp.',
                'data' => [],
            ];
        }

        $data = $products->map(function ($p) use ($color, $size) {
            // Nếu filter theo color/size, ưu tiên lấy variant phù hợp
            $matchedVariant = $p->variants->first();
            $thumbnail = $p->thumbnail_url;
            if ($p->mainImage) {
                $thumbnail = $p->mainImage->image_url;
            }

            $result = [
                'product_id' => $p->product_id,
                'name'       => $p->name,
                'slug'       => $p->slug,
                'price'      => $matchedVariant ? number_format($matchedVariant->price, 0, ',', '.') . 'đ' : number_format($p->min_price, 0, ',', '.') . 'đ',
                'price_raw'  => $matchedVariant ? $matchedVariant->price : $p->min_price,
                'thumbnail'  => $thumbnail,
                'category'   => $p->category ? $p->category->name : null,
                'sold_count' => $p->sold_count,
            ];

            // Thêm thông tin variant phù hợp (màu, size) cho AI mô tả
            if ($matchedVariant) {
                $result['available_colors'] = $p->variants->pluck('color')->unique()->filter()->values()->toArray();
                $result['available_sizes'] = $p->variants->pluck('size')->unique()->filter()->values()->toArray();
                $result['matched_variant'] = $matchedVariant->variant_name;
                $result['stock'] = $matchedVariant->stock;
            }

            return $result;
        })->toArray();

        return [
            'status'  => 'success',
            'count'   => count($data),
            'message' => 'Tìm thấy ' . count($data) . ' sản phẩm.',
            'data'    => $data,
        ];
    }

    /**
     * Xem chi tiết sản phẩm
     */
    private function getProductDetail(array $args): array
    {
        $productName = $args['product_name'] ?? '';

        $product = Product::where('status', 'active')
            ->where('name', 'LIKE', "%{$productName}%")
            ->with(['category', 'variants' => function ($q) {
                $q->where('status', 'active');
            }, 'images'])
            ->first();

        if (!$product) {
            return [
                'status' => 'not_found',
                'message' => "Không tìm thấy sản phẩm \"{$productName}\".",
                'data' => null,
            ];
        }

        $variants = $product->variants->map(function ($v) {
            return [
                'variant_name' => $v->variant_name,
                'color'        => $v->color,
                'size'         => $v->size,
                'price'        => number_format($v->price, 0, ',', '.') . 'đ',
                'stock'        => $v->stock,
                'status'       => $v->stock > 0 ? 'Còn hàng' : 'Hết hàng',
            ];
        })->toArray();

        $data = [
            'product_id'       => $product->product_id,
            'name'             => $product->name,
            'slug'             => $product->slug,
            'short_description'=> $product->short_description,
            'category'         => $product->category ? $product->category->name : null,
            'price_range'      => number_format($product->min_price, 0, ',', '.') . 'đ - ' . number_format($product->max_price, 0, ',', '.') . 'đ',
            'thumbnail'        => $product->thumbnail_url,
            'variants'         => $variants,
            'rating'           => $product->rating_avg,
            'sold_count'       => $product->sold_count,
        ];

        return [
            'status'  => 'success',
            'message' => 'Đã tìm thấy chi tiết sản phẩm.',
            'data'    => $data,
        ];
    }

    /**
     * Tra cứu đơn hàng — 2 chế độ
     */
    private function getOrderStatus(array $args, $authUser = null): array
    {
        // Chế độ 1: User đã đăng nhập → tra tất cả đơn hoặc đơn cụ thể
        if ($authUser) {
            $query = Order::where('user_id', $authUser->user_id ?? $authUser->id)
                ->with(['items'])
                ->orderByDesc('created_at');

            // Nếu có order_code cụ thể → lọc theo order_code
            if (!empty($args['order_code'])) {
                $query->where('order_code', $args['order_code']);
            }

            $orders = $query->limit(5)->get();

            if ($orders->isEmpty()) {
                return [
                    'status' => 'no_orders',
                    'message' => 'Bạn chưa có đơn hàng nào.',
                    'data' => [],
                ];
            }

            $data = $orders->map(function ($order) {
                return $this->formatOrderData($order);
            })->toArray();

            return [
                'status'  => 'success',
                'count'   => count($data),
                'message' => 'Tìm thấy ' . count($data) . ' đơn hàng.',
                'data'    => $data,
            ];
        }

        // Chế độ 2: Khách chưa đăng nhập → cần order_code + email/phone
        $orderCode = $args['order_code'] ?? null;
        $email = $args['email'] ?? null;
        $phone = $args['phone'] ?? null;

        if (!$orderCode) {
            return [
                'status'  => 'need_info',
                'message' => 'Vui lòng cung cấp mã đơn hàng để tra cứu.',
                'data'    => null,
            ];
        }

        if (!$email && !$phone) {
            return [
                'status'  => 'need_verification',
                'message' => 'Vui lòng cung cấp thêm email hoặc số điện thoại để xác minh đơn hàng.',
                'data'    => null,
            ];
        }

        // Tìm đơn hàng khớp order_code AND (email hoặc phone)
        $order = Order::where('order_code', $orderCode)
            ->where(function ($q) use ($email, $phone) {
                if ($email) {
                    $q->whereHas('user', function ($uq) use ($email) {
                        $uq->where('email', $email);
                    });
                }
                if ($phone) {
                    $q->orWhere('recipient_phone', $phone);
                }
            })
            ->with(['items'])
            ->first();

        if (!$order) {
            return [
                'status'  => 'not_found',
                'message' => 'Không tìm thấy đơn hàng với thông tin đã cung cấp. Vui lòng kiểm tra lại mã đơn và email/SĐT.',
                'data'    => null,
            ];
        }

        return [
            'status'  => 'success',
            'message' => 'Đã tìm thấy đơn hàng.',
            'data'    => $this->formatOrderData($order),
        ];
    }

    /**
     * Format dữ liệu đơn hàng để trả về
     */
    private function formatOrderData(Order $order): array
    {
        $statusLabels = [
            'pending'    => 'Chờ xác nhận',
            'confirmed'  => 'Đã xác nhận',
            'shipping'   => 'Đang giao hàng',
            'delivered'  => 'Đã giao hàng',
            'completed'  => 'Hoàn thành',
            'cancelled'  => 'Đã hủy',
        ];

        $items = $order->items->map(function ($item) {
            return [
                'product_name' => $item->product_name,
                'variant'      => $item->variant_name,
                'quantity'     => $item->quantity,
                'unit_price'   => number_format($item->unit_price, 0, ',', '.') . 'đ',
                'line_total'   => number_format($item->line_total, 0, ',', '.') . 'đ',
            ];
        })->toArray();

        return [
            'order_code'        => $order->order_code,
            'status'            => $statusLabels[$order->fulfillment_status] ?? $order->fulfillment_status,
            'status_raw'        => $order->fulfillment_status,
            'payment_method'    => $order->payment_method === 'cod' ? 'Thanh toán khi nhận hàng' : 'Chuyển khoản',
            'payment_status'    => $order->payment_status,
            'subtotal'          => number_format($order->subtotal, 0, ',', '.') . 'đ',
            'discount'          => number_format($order->discount_amount, 0, ',', '.') . 'đ',
            'shipping_fee'      => number_format($order->shipping_fee, 0, ',', '.') . 'đ',
            'grand_total'       => number_format($order->grand_total, 0, ',', '.') . 'đ',
            'recipient_name'    => $order->recipient_name,
            'shipping_address'  => $order->shipping_address,
            'items'             => $items,
            'created_at'        => $order->created_at->format('d/m/Y H:i'),
        ];
    }

    /**
     * Lấy mã giảm giá đang có hiệu lực
     */
    private function getAvailableCoupons(): array
    {
        $coupons = Coupon::where('is_active', 1)
            ->where('is_public', 1)
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            })
            ->where(function ($q) {
                $q->whereNull('start_date')
                  ->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                  ->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->limit(10)
            ->get();

        if ($coupons->isEmpty()) {
            return [
                'status'  => 'no_coupons',
                'message' => 'Hiện tại không có mã giảm giá nào.',
                'data'    => [],
            ];
        }

        $data = $coupons->map(function ($c) {
            $description = $c->type === 'percent'
                ? "Giảm {$c->value}%" . ($c->max_discount_value ? " (tối đa " . number_format($c->max_discount_value, 0, ',', '.') . "đ)" : "")
                : ($c->type === 'free_ship'
                    ? "Miễn phí vận chuyển"
                    : "Giảm " . number_format($c->value, 0, ',', '.') . "đ");

            return [
                'code'        => $c->code,
                'description' => $description,
                'type'        => $c->type,
                'min_order'   => $c->min_order_value ? number_format($c->min_order_value, 0, ',', '.') . 'đ' : 'Không giới hạn',
                'end_date'    => $c->end_date ? \Carbon\Carbon::parse($c->end_date)->format('d/m/Y') : 'Không thời hạn',
            ];
        })->toArray();

        return [
            'status'  => 'success',
            'count'   => count($data),
            'message' => 'Tìm thấy ' . count($data) . ' mã giảm giá.',
            'data'    => $data,
        ];
    }

    /**
     * Lấy danh mục sản phẩm
     */
    private function getCategories(): array
    {
        $categories = Category::where('is_active', 1)
            ->whereNull('parent_id')
            ->with(['children' => function ($q) {
                $q->where('is_active', 1);
            }])
            ->orderBy('sort_order')
            ->get();

        // Đếm sản phẩm mỗi danh mục
        $data = $categories->map(function ($cat) {
            $productCount = Product::where('category_id', $cat->category_id)
                ->where('status', 'active')
                ->count();

            $children = [];
            if ($cat->children) {
                $children = $cat->children->map(function ($child) {
                    return [
                        'name' => $child->name,
                        'product_count' => Product::where('category_id', $child->category_id)
                            ->where('status', 'active')
                            ->count(),
                    ];
                })->toArray();
            }

            return [
                'name'          => $cat->name,
                'product_count' => $productCount,
                'children'      => $children,
            ];
        })->toArray();

        return [
            'status'  => 'success',
            'message' => 'Danh sách danh mục sản phẩm.',
            'data'    => $data,
        ];
    }

    /**
     * Lấy thông tin cửa hàng
     */
    private function getStoreInfo(array $args): array
    {
        $topic = $args['topic'] ?? 'general';

        $info = match ($topic) {
            'shipping' => [
                'title'   => 'Chính sách vận chuyển',
                'content' => [
                    'Miễn phí vận chuyển cho đơn từ 500.000đ',
                    'Giao hàng toàn quốc qua Giao Hàng Nhanh (GHN)',
                    'Thời gian giao hàng: 2-5 ngày tùy khu vực',
                    'Nội thành Buôn Ma Thuột: Giao trong 1-2 ngày',
                    'Phí vận chuyển tính theo khu vực, hiển thị khi thanh toán',
                ],
            ],
            'return_policy' => [
                'title'   => 'Chính sách đổi trả',
                'content' => [
                    'Đổi trả trong vòng 30 ngày kể từ ngày nhận hàng',
                    'Sản phẩm phải còn nguyên tem mác, chưa qua sử dụng',
                    'Hoàn tiền trong 3-5 ngày làm việc sau khi nhận hàng đổi trả',
                    'Miễn phí đổi trả nếu lỗi từ nhà sản xuất hoặc giao sai hàng',
                    'Liên hệ hotline 1900-OCEAN để được hỗ trợ',
                ],
            ],
            'payment' => [
                'title'   => 'Phương thức thanh toán',
                'content' => [
                    'COD — Thanh toán khi nhận hàng',
                    'Chuyển khoản ngân hàng',
                    'Hỗ trợ mã giảm giá khi thanh toán',
                ],
            ],
            'contact' => [
                'title'   => 'Thông tin liên hệ',
                'content' => [
                    'Địa chỉ: 101 Y Ngông, P. Tân Lập, TP.Buôn Ma Thuột, Đắk Lắk',
                    'Hotline: 1900-OCEAN (1900 6232)',
                    'Email: contact@oceanstore.vn',
                    'Giờ làm việc: 8:00 - 22:00 hàng ngày',
                    'Fanpage Facebook: Ocean Store',
                ],
            ],
            default => [
                'title'   => 'Về Ocean Store',
                'content' => [
                    'Ocean Store — Cửa hàng thời trang và phụ kiện trực tuyến',
                    'Sản phẩm chính hãng, đa dạng thương hiệu',
                    'Giao hàng toàn quốc',
                    'Hotline: 1900-OCEAN (1900 6232)',
                    'Email: contact@oceanstore.vn',
                ],
            ],
        };

        return [
            'status'  => 'success',
            'message' => $info['title'],
            'data'    => $info,
        ];
    }
}
