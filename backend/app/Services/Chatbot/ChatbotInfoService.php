<?php

namespace App\Services\Chatbot;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;

/**
 * ChatbotInfoService — các truy vấn tra cứu (read-only) cho chatbot:
 * trạng thái đơn hàng, mã giảm giá, danh mục, thông tin cửa hàng.
 *
 * Tách khỏi ChatbotActionService (thiên về hành động cart/order) để controller
 * không còn query Model trực tiếp. Giữ nguyên shape trả về (status/message/data)
 * mà buildFallbackMessage của controller đang dựa vào.
 */
class ChatbotInfoService
{
    /**
     * Tra cứu đơn hàng — 2 chế độ:
     *  - User đã đăng nhập: liệt kê đơn (hoặc lọc theo order_code).
     *  - Khách: cần order_code + email/phone để xác minh.
     */
    public function getOrderStatus(array $args, $authUser = null): array
    {
        // Chế độ 1: User đã đăng nhập → tra tất cả đơn hoặc đơn cụ thể
        if ($authUser) {
            $query = Order::where('user_id', $authUser->user_id ?? $authUser->id)
                ->with(['items'])
                ->orderByDesc('created_at');

            if (! empty($args['order_code'])) {
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

            $data = $orders->map(fn ($order) => $this->formatOrderData($order))->toArray();

            return [
                'status' => 'success',
                'count' => count($data),
                'message' => 'Tìm thấy '.count($data).' đơn hàng.',
                'data' => $data,
            ];
        }

        // Chế độ 2: Khách chưa đăng nhập → cần order_code + email/phone
        $orderCode = $args['order_code'] ?? null;
        $email = $args['email'] ?? null;
        $phone = $args['phone'] ?? null;

        if (! $orderCode) {
            return [
                'status' => 'need_info',
                'message' => 'Vui lòng cung cấp mã đơn hàng để tra cứu.',
                'data' => null,
            ];
        }

        if (! $email && ! $phone) {
            return [
                'status' => 'need_verification',
                'message' => 'Vui lòng cung cấp thêm email hoặc số điện thoại để xác minh đơn hàng.',
                'data' => null,
            ];
        }

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

        if (! $order) {
            return [
                'status' => 'not_found',
                'message' => 'Không tìm thấy đơn hàng với thông tin đã cung cấp. Vui lòng kiểm tra lại mã đơn và email/SĐT.',
                'data' => null,
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Đã tìm thấy đơn hàng.',
            'data' => $this->formatOrderData($order),
        ];
    }

    /**
     * Mã giảm giá công khai còn hiệu lực (tối đa 10).
     */
    public function getAvailableCoupons(): array
    {
        $coupons = Coupon::where('is_active', 1)
            ->where('is_public', 1)
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->where(function ($q) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->limit(10)
            ->get();

        if ($coupons->isEmpty()) {
            return [
                'status' => 'no_coupons',
                'message' => 'Hiện tại không có mã giảm giá nào.',
                'data' => [],
            ];
        }

        $data = $coupons->map(function ($c) {
            $description = $c->type === 'percent'
                ? "Giảm {$c->value}%".($c->max_discount_value ? ' (tối đa '.number_format($c->max_discount_value, 0, ',', '.').'đ)' : '')
                : ($c->type === 'free_ship'
                    ? 'Miễn phí vận chuyển'
                    : 'Giảm '.number_format($c->value, 0, ',', '.').'đ');

            return [
                'code' => $c->code,
                'description' => $description,
                'type' => $c->type,
                'min_order' => $c->min_order_value ? number_format($c->min_order_value, 0, ',', '.').'đ' : 'Không giới hạn',
                'end_date' => $c->end_date ? Carbon::parse($c->end_date)->format('d/m/Y') : 'Không thời hạn',
            ];
        })->toArray();

        return [
            'status' => 'success',
            'count' => count($data),
            'message' => 'Tìm thấy '.count($data).' mã giảm giá.',
            'data' => $data,
        ];
    }

    /**
     * Danh mục sản phẩm gốc + con, kèm số sản phẩm active.
     */
    public function getCategories(): array
    {
        $categories = Category::where('is_active', 1)
            ->whereNull('parent_id')
            ->with(['children' => function ($q) {
                $q->where('is_active', 1);
            }])
            ->orderBy('sort_order')
            ->get();

        $data = $categories->map(function ($cat) {
            $productCount = Product::where('category_id', $cat->category_id)
                ->where('status', 'active')
                ->count();

            $children = [];
            if ($cat->children) {
                $children = $cat->children->map(fn ($child) => [
                    'name' => $child->name,
                    'product_count' => Product::where('category_id', $child->category_id)
                        ->where('status', 'active')
                        ->count(),
                ])->toArray();
            }

            return [
                'name' => $cat->name,
                'product_count' => $productCount,
                'children' => $children,
            ];
        })->toArray();

        return [
            'status' => 'success',
            'message' => 'Danh sách danh mục sản phẩm.',
            'data' => $data,
        ];
    }

    /**
     * Thông tin cửa hàng theo chủ đề (tĩnh).
     */
    public function getStoreInfo(array $args): array
    {
        $topic = $args['topic'] ?? 'general';

        $info = match ($topic) {
            'shipping' => [
                'title' => 'Chính sách vận chuyển',
                'content' => [
                    'Miễn phí vận chuyển cho đơn từ 500.000đ',
                    'Giao hàng toàn quốc qua Giao Hàng Nhanh (GHN)',
                    'Thời gian giao hàng: 2-5 ngày tùy khu vực',
                    'Nội thành Buôn Ma Thuột: Giao trong 1-2 ngày',
                    'Phí vận chuyển tính theo khu vực, hiển thị khi thanh toán',
                ],
            ],
            'return_policy' => [
                'title' => 'Chính sách đổi trả',
                'content' => [
                    'Đổi trả trong vòng 30 ngày kể từ ngày nhận hàng',
                    'Sản phẩm phải còn nguyên tem mác, chưa qua sử dụng',
                    'Hoàn tiền trong 3-5 ngày làm việc sau khi nhận hàng đổi trả',
                    'Miễn phí đổi trả nếu lỗi từ nhà sản xuất hoặc giao sai hàng',
                    'Liên hệ hotline 1900-SPORT để được hỗ trợ',
                ],
            ],
            'payment' => [
                'title' => 'Phương thức thanh toán',
                'content' => [
                    'COD — Thanh toán khi nhận hàng',
                    'Chuyển khoản ngân hàng',
                    'Hỗ trợ mã giảm giá khi thanh toán',
                ],
            ],
            'contact' => [
                'title' => 'Thông tin liên hệ',
                'content' => [
                    'Địa chỉ: 134 Nguyễn Thị Định, P.Buôn Ma Thuột, Tỉnh Đắk Lắk',
                    'Hotline: 1900-SPORT',
                    'Email: contact@quyensport.vn',
                    'Giờ làm việc: 8:00 - 22:00 hàng ngày',
                    'Fanpage Facebook: Quyền Sport',
                ],
            ],
            default => [
                'title' => 'Về Quyền Sport',
                'content' => [
                    'Quyền Sport — Cửa hàng thời trang và phụ kiện trực tuyến',
                    'Sản phẩm chính hãng, đa dạng thương hiệu',
                    'Giao hàng toàn quốc',
                    'Hotline: 1900-SPORT',
                    'Email: contact@quyensport.vn',
                ],
            ],
        };

        return [
            'status' => 'success',
            'message' => $info['title'],
            'data' => $info,
        ];
    }

    /**
     * Format dữ liệu đơn hàng để trả về cho chatbot.
     */
    private function formatOrderData(Order $order): array
    {
        $statusLabels = [
            'pending' => 'Chờ xác nhận',
            'confirmed' => 'Đã xác nhận',
            'shipping' => 'Đang giao hàng',
            'delivered' => 'Đã giao hàng',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy',
        ];

        $items = $order->items->map(fn ($item) => [
            'product_name' => $item->product_name,
            'variant' => $item->variant_name,
            'quantity' => $item->quantity,
            'unit_price' => number_format($item->unit_price, 0, ',', '.').'đ',
            'line_total' => number_format($item->line_total, 0, ',', '.').'đ',
        ])->toArray();

        return [
            'order_code' => $order->order_code,
            'status' => $statusLabels[$order->fulfillment_status] ?? $order->fulfillment_status,
            'status_raw' => $order->fulfillment_status,
            'payment_method' => $order->payment_method === 'cod' ? 'Thanh toán khi nhận hàng' : 'Chuyển khoản',
            'payment_status' => $order->payment_status,
            'subtotal' => number_format($order->subtotal, 0, ',', '.').'đ',
            'discount' => number_format($order->discount_amount, 0, ',', '.').'đ',
            'shipping_fee' => number_format($order->shipping_fee, 0, ',', '.').'đ',
            'grand_total' => number_format($order->grand_total, 0, ',', '.').'đ',
            'recipient_name' => $order->recipient_name,
            'shipping_address' => $order->shipping_address,
            'items' => $items,
            'created_at' => $order->created_at->format('d/m/Y H:i'),
        ];
    }
}
