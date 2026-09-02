<?php

namespace App\Jobs;

use App\Events\OrderCreatedAdmin;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redis;

class OrderProcessingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Số lần retry nếu job thất bại.
     */
    public int $tries = 3;

    /**
     * Thời gian chờ giữa các lần retry (giây).
     */
    public int $backoff = 5;

    /**
     * Timeout tối đa cho job (giây).
     */
    public int $timeout = 60;

    public function __construct(
        public readonly int $flashSaleId,
        public readonly int $productId,
        public readonly int $userId,
        public readonly int $quantity,
        public readonly ?int $addressId,
        public readonly string $recipientName,
        public readonly string $recipientPhone,
        public readonly string $shippingAddress,
        public readonly string $paymentMethod,
        public readonly string $orderCode,
        public readonly ?int $variantId = null,
    ) {}

    /**
     * Xử lý tạo đơn hàng trong MySQL.
     * Chạy bất đồng bộ qua Queue Worker — không block HTTP response.
     */
    public function handle(): void
    {
        $flashSaleItem = FlashSaleItem::with('product')
            ->where('flash_sale_id', $this->flashSaleId)
            ->where('product_id', $this->productId)
            ->first();

        if (! $flashSaleItem) {
            Log::error("[OrderProcessingJob] FlashSaleItem (FlashSale #{$this->flashSaleId}, Product #{$this->productId}) không tồn tại.");
            $this->rollbackRedisStock();

            return;
        }

        try {
            DB::transaction(function () use ($flashSaleItem) {
                // Khóa + chọn variant còn đủ tồn kho THẬT (product_variants.stock) — tồn kho
                // campaign trên Redis độc lập với tồn kho thật nên phải kiểm tra lại ở đây,
                // tránh trừ mù khiến stock âm và oversell ở cửa hàng thường.
                $variant = null;
                if ($this->variantId) {
                    $variant = ProductVariant::where('variant_id', $this->variantId)
                        ->where('product_id', $this->productId)
                        ->where('stock', '>=', $this->quantity)
                        ->lockForUpdate()
                        ->first();
                }

                if (! $variant) {
                    $variant = ProductVariant::where('product_id', $this->productId)
                        ->where('stock', '>=', $this->quantity)
                        ->lockForUpdate()
                        ->first();
                }

                if (! $variant) {
                    throw new \RuntimeException("Không đủ tồn kho thực cho sản phẩm #{$this->productId} (cần {$this->quantity}).");
                }

                $unitPrice = $flashSaleItem->campaign_price;
                $subtotal = $unitPrice * $this->quantity;
                $shippingFee = 0; // Flash sale: freeship
                $grandTotal = $subtotal;

                // 1. Tạo đơn hàng
                $order = Order::create([
                    'order_code' => $this->orderCode,
                    'user_id' => $this->userId,
                    'address_id' => $this->addressId,
                    'recipient_name' => $this->recipientName,
                    'recipient_phone' => $this->recipientPhone,
                    'shipping_address' => $this->shippingAddress,
                    'note' => "Flash Sale #{$this->flashSaleId}",
                    'payment_method' => $this->paymentMethod,
                    'payment_status' => 'unpaid',
                    'fulfillment_status' => 'pending',
                    'subtotal' => $subtotal,
                    'discount_amount' => 0,
                    'shipping_fee' => $shippingFee,
                    'grand_total' => $grandTotal,
                    'email_sent' => false,
                ]);

                // 2. Tạo order item từ flash sale
                OrderItem::create([
                    'order_id' => $order->order_id,
                    'product_id' => $this->productId,
                    'variant_id' => $variant->variant_id,
                    'product_name' => $flashSaleItem->product->name,
                    'variant_name' => $variant->variant_name,
                    'sku' => $variant->sku,
                    'color' => $variant->color,
                    'size' => $variant->size,
                    'quantity' => $this->quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $subtotal,
                ]);

                // 3. Cập nhật sold_count trong flash_sale_items
                $flashSaleItem->increment('sold', $this->quantity);

                // 4. Trừ tồn kho trong bảng product_variants (đã khóa + validate ở trên)
                $variant->decrement('stock', $this->quantity);
                if ($variant->product_id) {
                    Product::where('product_id', $variant->product_id)
                        ->increment('sold_count', $this->quantity);
                }

                Cache::tags(['products:best-selling'])->flush();

                // 5. Tạo lịch sử trạng thái đơn hàng
                OrderStatusHistory::create([
                    'order_id' => $order->order_id,
                    'new_status' => 'pending',
                    'note' => 'Khách hàng đặt đơn hàng Flash Sale mới',
                ]);

                // 6. Phát sự kiện và gửi thông báo
                try {
                    event(new OrderCreatedAdmin($order));

                    // Nạp relation user nếu cần
                    $order->loadMissing('user');

                    // Notify Customer
                    if ($order->user) {
                        Notification::sendNow($order->user, new SystemNotification(
                            'Đặt hàng thành công',
                            'Đơn hàng '.$order->order_code.' của bạn đã được đặt thành công.',
                            '/profile/orders/'.$order->order_id,
                            'order',
                            ['is_flash_sale' => true]
                        ));
                    }

                    // Notify Admins
                    $admins = User::whereIn('role', ['admin', 'seller'])->get();
                    if ($admins->count() > 0) {
                        Notification::sendNow($admins, new SystemNotification(
                            'Đơn hàng mới',
                            'Khách hàng vừa đặt đơn hàng '.$order->order_code,
                            '/admin/order/'.$order->order_id,
                            'order',
                            [
                                'payment_status' => $order->payment_status,
                                'fulfillment_status' => $order->fulfillment_status,
                                'is_flash_sale' => true,
                            ]
                        ));
                    }
                } catch (\Exception $ex) {
                    Log::error('[OrderProcessingJob] Lỗi gửi thông báo: '.$ex->getMessage());
                }

                Log::info("[OrderProcessingJob] Tạo đơn #{$this->orderCode} thành công cho user #{$this->userId}, flash_sale #{$this->flashSaleId}.");
            });
        } catch (\Throwable $e) {
            // Không đủ tồn kho thực / lỗi khác → hoàn stock Redis và ném lại để job retry/failed().
            Log::error("[OrderProcessingJob] Tạo đơn thất bại: {$e->getMessage()}");
            throw $e;
        }

        Cache::flush();
    }

    /**
     * Xử lý khi job thất bại sau tất cả lần retry.
     * QUAN TRỌNG: Hoàn trả stock về Redis để tránh thất thoát hàng.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("[OrderProcessingJob] Job thất bại vĩnh viễn. Order: {$this->orderCode}. Error: {$exception->getMessage()}");
        $this->rollbackRedisStock();
    }

    /**
     * Hoàn stock về Redis khi không thể tạo đơn hàng.
     */
    private function rollbackRedisStock(): void
    {
        try {
            $key = "flash_sale_{$this->flashSaleId}_product_{$this->productId}_stock";
            Redis::incrby($key, $this->quantity);
            Log::info("[OrderProcessingJob] Đã hoàn {$this->quantity} stock về Redis key: {$key}");
        } catch (\Exception $e) {
            Log::critical("[OrderProcessingJob] Không thể hoàn stock Redis! Key: {$key}. Error: {$e->getMessage()}");
        }
    }
}
