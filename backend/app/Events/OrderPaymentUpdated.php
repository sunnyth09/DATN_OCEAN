<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast khi payment_status của đơn hàng được cập nhật thành 'paid' (ví dụ: qua SePay webhook).
 * Khác với OrderCreatedAdmin (đơn hàng vừa tạo) — event này cập nhật đơn đã tồn tại.
 */
class OrderPaymentUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Broadcast trên channel admin-notifications (cùng channel với OrderCreatedAdmin).
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin-notifications'),
        ];
    }

    /**
     * Tên event phía frontend sẽ listen.
     */
    public function broadcastAs(): string
    {
        return 'OrderPaymentUpdated';
    }

    /**
     * Dữ liệu gửi kèm — chỉ cần các trường cần thiết để frontend update đúng row.
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->order_id,
            'order_code' => $this->order->order_code,
            'payment_status' => $this->order->payment_status,
            'payment_method' => $this->order->payment_method,
            'grand_total' => $this->order->grand_total,
        ];
    }
}
