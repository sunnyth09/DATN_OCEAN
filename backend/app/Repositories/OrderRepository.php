<?php

namespace App\Repositories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;

class OrderRepository
{
    public function getUserOrders(int $userId, string $status = 'all')
    {
        $query = Order::with(['items.product', 'items.variant', 'items.comment'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        if ($status !== 'all') {
            $query->where('fulfillment_status', $status);
        }

        return $query->paginate(10);
    }

    public function getUserOrderDetail(int $userId, int $orderId)
    {
        return Order::with(['items.product.images', 'items.variant', 'statusHistories'])
            ->where('user_id', $userId)
            ->where('order_id', $orderId)
            ->first();
    }

    public function findUserOrder(int $userId, int $orderId)
    {
        return Order::where('order_id', $orderId)
            ->where('user_id', $userId)
            ->first();
    }

    public function findByCodeAndUser(int $userId, string $orderCode)
    {
        return Order::where('order_code', $orderCode)
            ->where('user_id', $userId)
            ->first();
    }

    public function create(array $data)
    {
        return Order::create($data);
    }

    public function createItem(array $data)
    {
        return OrderItem::create($data);
    }

    public function createStatusHistory(array $data)
    {
        return OrderStatusHistory::create($data);
    }

    public function cancel(Order $order, string $reason): bool
    {
        return $order->update([
            'fulfillment_status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);
    }

    public function getOrderItems(int $orderId)
    {
        return OrderItem::where('order_id', $orderId)->get();
    }
}