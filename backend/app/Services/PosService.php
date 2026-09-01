<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PosService
{
    public function __construct(
        protected CouponService $couponService
    ) {}

    /**
     * Quét barcode → 1 variant active.
     *
     * @return array|null null nếu không tìm thấy.
     */
    public function scanByBarcode(string $barcode): ?array
    {
        $variant = ProductVariant::with(['product.images', 'product.category'])
            ->where('barcode', $barcode)
            ->where('status', 'active')
            ->first();

        if (! $variant) {
            return null;
        }

        return $this->formatVariantResponse($variant);
    }

    /**
     * Tìm sản phẩm theo tên/slug/sku/barcode (chỉ variant còn hàng).
     */
    public function searchProducts(string $query)
    {
        $products = Product::with(['variants' => function ($q) {
            $q->where('status', 'active')->where('stock', '>', 0);
        }, 'images', 'category'])
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('slug', 'LIKE', "%{$query}%")
                    ->orWhereHas('variants', function ($vq) use ($query) {
                        $vq->where('sku', 'LIKE', "%{$query}%")
                            ->orWhere('barcode', 'LIKE', "%{$query}%");
                    });
            })
            ->where('status', 'active')
            ->limit(20)
            ->get();

        return $products->map(function ($product) {
            $mainImage = $product->images->where('is_main', 1)->first();
            $thumbnail = $mainImage
                ? $mainImage->image_url
                : ($product->images->first()->image_url ?? $product->thumbnail_url);

            return [
                'product_id' => $product->product_id,
                'name' => $product->name,
                'slug' => $product->slug,
                'thumbnail' => $thumbnail,
                'category_name' => $product->category->name ?? '',
                'variants' => $product->variants->map(fn ($v) => [
                    'variant_id' => $v->variant_id,
                    'variant_name' => $v->variant_name,
                    'sku' => $v->sku,
                    'barcode' => $v->barcode,
                    'color' => $v->color,
                    'size' => $v->size,
                    'price' => $v->price,
                    'compare_at_price' => $v->compare_at_price,
                    'stock' => $v->stock,
                    'image_url' => $v->image_url,
                ]),
            ];
        });
    }

    /**
     * Thanh toán POS (bán trực tiếp). Khóa từng variant để trừ kho an toàn.
     * Ném \Exception nếu sản phẩm không khả dụng / thiếu kho (controller map 422).
     *
     * @param  array  $data  items[], customer_name/phone, payment_method, note, discount_amount
     * @param  int|null  $staffId  admin_id/user_id của người bán
     * @return Order đơn đã tạo (kèm items).
     */
    public function checkout(array $data, ?int $staffId): Order
    {
        // Không tin user_id từ client: tra khách theo customer_phone để gắn đúng.
        $customerId = null;
        $customerName = $data['customer_name'] ?? null;
        if (! empty($data['customer_phone'])) {
            $customer = User::where('phone', $data['customer_phone'])->first();
            if ($customer) {
                $customerId = $customer->user_id;
                if (empty($customerName)) {
                    $customerName = $customer->full_name;
                }
            }
        }

        // Tra cứu coupon nếu có truyền
        $coupon = null;
        if (! empty($data['coupon_id'])) {
            $coupon = \App\Models\Coupon::find($data['coupon_id']);
        } elseif (! empty($data['coupon_code'])) {
            $coupon = \App\Models\Coupon::where('code', $data['coupon_code'])->first();
        }

        return DB::transaction(function () use ($data, $staffId, $customerId, $customerName, $coupon) {
            $subtotal = 0;
            $itemsData = [];

            foreach ($data['items'] as $item) {
                /** @var ProductVariant $variant */
                $variant = ProductVariant::with('product')
                    ->where('variant_id', $item['variant_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $variant || $variant->status !== 'active') {
                    $productName = $variant && $variant->product ? $variant->product->name : 'N/A';
                    throw new \Exception('Sản phẩm "'.$productName.'" không khả dụng.');
                }

                if ($variant->stock < $item['quantity']) {
                    throw new \Exception('Sản phẩm "'.$variant->product->name.'" ('.$variant->variant_name.') chỉ còn '.$variant->stock.' trong kho.');
                }

                $lineTotal = $variant->price * $item['quantity'];
                $subtotal += $lineTotal;

                $itemsData[] = [
                    'variant' => $variant,
                    'quantity' => $item['quantity'],
                    'line_total' => $lineTotal,
                ];
            }

            $discountAmount = min($data['discount_amount'] ?? 0, $subtotal);
            $grandTotal = $subtotal - $discountAmount;

            $order = Order::create([
                'order_code' => 'POS'.strtoupper(uniqid()).rand(10, 99),
                'order_type' => 'pos',
                'user_id' => $customerId,
                'seller_id' => $staffId,
                'promotion_id' => $coupon ? $coupon->id : null,
                'recipient_name' => ! empty($customerName) ? $customerName : 'Khách lẻ',
                'recipient_phone' => ! empty($data['customer_phone']) ? $data['customer_phone'] : '',
                'shipping_address' => 'Mua tại cửa hàng',
                'note' => ! empty($data['note']) ? $data['note'] : '',
                'payment_method' => ! empty($data['payment_method']) ? $data['payment_method'] : 'pos_cash',
                'payment_status' => 'paid',
                'fulfillment_status' => 'completed',
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'shipping_fee' => 0,
                'grand_total' => $grandTotal,
                'completed_at' => now(),
            ]);

            foreach ($itemsData as $row) {
                /** @var ProductVariant $v */
                $v = $row['variant'];
                OrderItem::create([
                    'order_id' => $order->order_id,
                    'product_id' => $v->product_id,
                    'variant_id' => $v->variant_id,
                    'product_name' => $v->product->name,
                    'variant_name' => $v->variant_name,
                    'sku' => $v->sku,
                    'color' => $v->color,
                    'size' => $v->size,
                    'quantity' => $row['quantity'],
                    'unit_price' => $v->price,
                    'line_total' => $row['line_total'],
                ]);

                $v->decrement('stock', $row['quantity']);
                if ($v->product_id) {
                    Product::where('product_id', $v->product_id)
                        ->increment('sold_count', $row['quantity']);
                }
            }

            // Ghi nhận lượt sử dụng cho mã giảm giá (voucher)
            if ($coupon) {
                $this->couponService->markCouponAsUsed($customerId ?? 0, $coupon);
            }

            OrderStatusHistory::create([
                'order_id' => $order->order_id,
                'new_status' => 'completed',
                'note' => 'Bán hàng trực tiếp tại cửa hàng (POS)',
            ]);

            Cache::flush();

            return $order->load('items');
        });
    }

    /**
     * Lấy order (kèm items) cho in hóa đơn PDF.
     */
    public function findOrderForReceipt($id): ?Order
    {
        return Order::with('items')->find($id);
    }

    private function formatVariantResponse($variant): array
    {
        $product = $variant->product;
        $mainImage = $product->images->where('is_main', 1)->first();

        return [
            'variant_id' => $variant->variant_id,
            'variant_name' => $variant->variant_name,
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'color' => $variant->color,
            'size' => $variant->size,
            'price' => $variant->price,
            'stock' => $variant->stock,
            'image_url' => $variant->image_url,
            'product' => [
                'product_id' => $product->product_id,
                'name' => $product->name,
                'thumbnail' => $mainImage->image_url ?? $product->thumbnail_url,
            ],
        ];
    }
}
