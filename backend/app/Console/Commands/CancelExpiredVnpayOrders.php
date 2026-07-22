<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CancelExpiredVnpayOrders extends Command
{
    /**
     * Tên command — chạy bằng: php artisan orders:cancel-expired-vnpay
     */
    protected $signature = 'orders:cancel-expired-vnpay 
                            {--minutes=30 : Số phút timeout trước khi hủy}';

    protected $description = 'Tự động hủy các đơn hàng VNPay/MoMo chưa thanh toán quá thời hạn và hoàn trả tồn kho.';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');

        $expiredOrders = Order::whereIn('payment_method', ['vnpay', 'momo', 'bank_transfer'])
            ->where('payment_status', PaymentStatus::UNPAID->value)
            ->where('fulfillment_status', OrderStatus::PENDING->value)
            ->where('created_at', '<', now()->subMinutes($minutes))
            ->get();

        if ($expiredOrders->isEmpty()) {
            $this->info('Không có đơn hàng nào cần hủy.');
            return self::SUCCESS;
        }

        $this->info("Tìm thấy {$expiredOrders->count()} đơn hàng hết hạn thanh toán.");

        $cancelled = 0;
        $errors = 0;

        foreach ($expiredOrders as $order) {
            try {
                DB::beginTransaction();

                // Guard atomic: chỉ hủy khi đơn VẪN ở trạng thái pending+unpaid.
                // where(...)->update trả về số dòng bị ảnh hưởng — nếu 0 nghĩa là
                // user (hoặc tiến trình khác) đã cancel/thanh toán trước → bỏ qua để
                // KHÔNG hoàn trả tồn kho/coupon hai lần.
                $affected = Order::where('order_id', $order->order_id)
                    ->where('fulfillment_status', OrderStatus::PENDING->value)
                    ->where('payment_status', PaymentStatus::UNPAID->value)
                    ->update([
                        'fulfillment_status' => OrderStatus::CANCELLED->value,
                        'payment_status' => PaymentStatus::FAILED->value,
                        'cancelled_at' => now(),
                        'cancel_reason' => 'Hệ thống tự động hủy: quá thời hạn thanh toán (' . $minutes . ' phút)',
                    ]);

                if ($affected === 0) {
                    DB::rollBack();
                    continue;
                }

                // Ghi lịch sử
                OrderStatusHistory::create([
                    'order_id' => $order->order_id,
                    'old_status' => OrderStatus::PENDING->value,
                    'new_status' => OrderStatus::CANCELLED->value,
                    'note' => 'Hệ thống tự động hủy: chưa thanh toán sau ' . $minutes . ' phút.',
                ]);

                // Hoàn trả tồn kho
                $orderItems = OrderItem::where('order_id', $order->order_id)->get();
                foreach ($orderItems as $item) {
                    ProductVariant::where('variant_id', $item->variant_id)
                        ->increment('stock', $item->quantity);
                }

                // Hoàn coupon nếu có
                if ($order->promotion_id) {
                    \App\Models\Coupon::where('id', $order->promotion_id)->decrement('used_count');
                    \App\Models\UserCoupon::where('user_id', $order->user_id)
                        ->where('coupon_id', $order->promotion_id)
                        ->where('used_count', '>', 0)
                        ->decrement('used_count');
                }

                DB::commit();

                $cancelled++;
                $this->line("  ✓ Hủy đơn {$order->order_code} ({$order->payment_method})");

                Log::info('Auto-cancelled expired payment order', [
                    'order_code' => $order->order_code,
                    'payment_method' => $order->payment_method,
                    'created_at' => $order->created_at->toDateTimeString(),
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                $errors++;
                $this->error("  ✗ Lỗi hủy đơn {$order->order_code}: {$e->getMessage()}");
                Log::error('Failed to auto-cancel expired order', [
                    'order_code' => $order->order_code,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Hoàn tất: {$cancelled} đơn đã hủy, {$errors} lỗi.");
        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
