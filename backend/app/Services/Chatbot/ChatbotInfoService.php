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
     *  - Khách: chỉ cần order_code là tra cứu được ngay (tự động mask thông tin nhạy cảm).
     */
    public function getOrderStatus(array $args, $authUser = null): array
    {
        // Chế độ 1: User đã đăng nhập → tra tất cả đơn hoặc đơn cụ thể
        if ($authUser) {
            $query = Order::where('user_id', $authUser->user_id ?? $authUser->id)
                ->with(['items'])
                ->orderByDesc('created_at');

            if (! empty($args['order_code'])) {
                $query->where('order_code', trim($args['order_code']));
            }

            $orders = $query->limit(5)->get();

            if ($orders->isEmpty()) {
                // Nếu tìm theo mã đơn cụ thể trong tài khoản không thấy, thử tìm đơn đó trong toàn hệ thống
                if (! empty($args['order_code'])) {
                    $anyOrder = Order::where('order_code', trim($args['order_code']))->with(['items'])->first();
                    if ($anyOrder) {
                        return [
                            'status' => 'success',
                            'message' => 'Đã tìm thấy thông tin đơn hàng.',
                            'data' => $this->formatOrderData($anyOrder, false),
                        ];
                    }
                }

                return [
                    'status' => 'no_orders',
                    'message' => 'Bạn chưa có đơn hàng nào hoặc không tìm thấy mã đơn hàng này trong tài khoản.',
                    'data' => [],
                ];
            }

            $data = $orders->map(fn ($order) => $this->formatOrderData($order, true))->toArray();

            return [
                'status' => 'success',
                'count' => count($data),
                'message' => 'Tìm thấy '.count($data).' đơn hàng.',
                'data' => count($data) === 1 ? $data[0] : $data,
            ];
        }

        // Chế độ 2: Khách chưa đăng nhập → tra cứu bằng order_code
        $orderCode = isset($args['order_code']) ? trim($args['order_code']) : null;
        $email = isset($args['email']) ? trim($args['email']) : null;
        $phone = isset($args['phone']) ? trim($args['phone']) : null;

        if (! $orderCode) {
            return [
                'status' => 'need_info',
                'message' => 'Vui lòng cung cấp mã đơn hàng (VD: ORD-XXXXXX) để tra cứu trạng thái vận chuyển.',
                'data' => null,
            ];
        }

        $query = Order::where('order_code', $orderCode);

        // Nếu có email hoặc phone thì kiểm tra khớp nếu có
        if ($email || $phone) {
            $query->where(function ($q) use ($email, $phone) {
                if ($email) {
                    $q->whereHas('user', function ($uq) use ($email) {
                        $uq->where('email', $email);
                    });
                }
                if ($phone) {
                    $q->orWhere('recipient_phone', $phone);
                }
            });
        }

        $order = $query->with(['items'])->first();

        if (! $order) {
            return [
                'status' => 'not_found',
                'message' => "Không tìm thấy đơn hàng với mã \"{$orderCode}\". Vui lòng kiểm tra lại chính xác mã đơn hàng đã nhập.",
                'data' => null,
            ];
        }

        // Khách vãng lai tra cứu không có xác thực email/sđt -> che bớt thông tin nhạy cảm
        $isFullyVerified = ! empty($email) || ! empty($phone);

        return [
            'status' => 'success',
            'message' => 'Đã tìm thấy thông tin đơn hàng.',
            'data' => $this->formatOrderData($order, $isFullyVerified),
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
                    'Email: contact@oceansport.vn',
                    'Giờ làm việc: 8:00 - 22:00 hàng ngày',
                    'Fanpage Facebook: Ocean Sport',
                ],
            ],
            default => [
                'title' => 'Về Ocean Sport',
                'content' => [
                    'Ocean Sport — Cửa hàng thời trang và phụ kiện trực tuyến',
                    'Sản phẩm chính hãng, đa dạng thương hiệu',
                    'Giao hàng toàn quốc',
                    'Hotline: 1900-SPORT',
                    'Email: contact@oceansport.vn',
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
    private function formatOrderData(Order $order, bool $isFullyVerified = true): array
    {
        $statusLabels = [
            'pending' => 'Chờ xác nhận',
            'confirmed' => 'Đã xác nhận',
            'processing' => 'Đang xử lý / Chuẩn bị hàng',
            'awaiting_pickup' => 'Chờ lấy hàng / Đang đóng gói',
            'shipping' => 'Đang giao hàng',
            'shipped' => 'Đã gửi hàng cho đơn vị vận chuyển',
            'delivered' => 'Đã giao hàng thành công',
            'completed' => 'Đã hoàn thành',
            'cancelled' => 'Đã hủy đơn hàng',
            'return_requested' => 'Yêu cầu đổi trả',
            'returned' => 'Đã hoàn trả hàng',
        ];

        $paymentStatusLabels = [
            'pending' => 'Chưa thanh toán (COD)',
            'unpaid' => 'Chưa thanh toán',
            'paid' => 'Đã thanh toán',
            'refunded' => 'Đã hoàn tiền',
            'failed' => 'Thanh toán thất bại',
        ];

        $items = $order->items->map(fn ($item) => [
            'product_name' => $item->product_name,
            'variant' => $item->variant_name,
            'quantity' => $item->quantity,
            'unit_price' => number_format($item->unit_price, 0, ',', '.').'đ',
            'line_total' => number_format($item->line_total, 0, ',', '.').'đ',
        ])->toArray();

        // Che bớt thông tin nếu khách vãng lai tra cứu
        $recipientName = $order->recipient_name;
        $recipientPhone = $order->recipient_phone;
        $shippingAddress = $order->shipping_address;

        if (! $isFullyVerified) {
            if ($recipientPhone && strlen($recipientPhone) >= 7) {
                $recipientPhone = substr($recipientPhone, 0, 3) . '****' . substr($recipientPhone, -3);
            }
            if ($recipientName) {
                $words = explode(' ', trim($recipientName));
                if (count($words) > 1) {
                    $recipientName = $words[0] . ' *** ' . end($words);
                }
            }
            if ($shippingAddress) {
                $parts = explode(',', $shippingAddress);
                if (count($parts) > 1) {
                    $shippingAddress = '***, ' . trim(implode(', ', array_slice($parts, 1)));
                } else {
                    $shippingAddress = '*** (Đã bảo mật)';
                }
            }
        }

        return [
            'order_code' => $order->order_code,
            'status' => $statusLabels[$order->fulfillment_status] ?? $order->fulfillment_status,
            'status_raw' => $order->fulfillment_status,
            'payment_method' => $order->payment_method === 'cod' ? 'Thanh toán khi nhận hàng (COD)' : 'Chuyển khoản ngân hàng',
            'payment_status' => $paymentStatusLabels[$order->payment_status] ?? $order->payment_status,
            'subtotal' => number_format($order->subtotal, 0, ',', '.').'đ',
            'discount' => number_format($order->discount_amount, 0, ',', '.').'đ',
            'shipping_fee' => number_format($order->shipping_fee, 0, ',', '.').'đ',
            'grand_total' => number_format($order->grand_total, 0, ',', '.').'đ',
            'recipient_name' => $recipientName,
            'recipient_phone' => $recipientPhone,
            'shipping_address' => $shippingAddress,
            'items' => $items,
            'created_at' => $order->created_at ? $order->created_at->format('d/m/Y H:i') : '',
        ];
    }
}
