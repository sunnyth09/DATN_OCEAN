<?php

namespace App\Repositories;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;

class AdminOrderRepository
{
    /**
     * Lấy danh sách đơn hàng cho Admin (có filter, search, paginate)
     */
    public function getFilteredOrders(array $filters, int $perPage = 10)
    {
        $query = Order::with(['items', 'user', 'returnRequests'])->orderBy('created_at', 'desc');

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'processing') {
                $query->whereIn('fulfillment_status', ['processing', 'packing']);
            } else {
                $query->where('fulfillment_status', $filters['status']);
            }
        }

        if (!empty($filters['payment_status']) && $filters['payment_status'] !== 'all') {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (!empty($filters['search'])) {
            $searchTerm = $filters['search'];
            $query->where(function ($q) use ($searchTerm) {
                $q->where('order_code', 'like', "%{$searchTerm}%")
                  ->orWhere('recipient_name', 'like', "%{$searchTerm}%")
                  ->orWhere('recipient_phone', 'like', "%{$searchTerm}%");
            });
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Lấy chi tiết đơn hàng với relations
     */
    public function findWithRelations(int $id): ?Order
    {
        return Order::with(['items.product', 'items.variant', 'user', 'statusHistories', 'returnRequests'])
            ->where('order_id', $id)
            ->first();
    }

    /**
     * Tìm đơn hàng theo ID
     */
    public function find(int $id): ?Order
    {
        return Order::where('order_id', $id)->first();
    }

    /**
     * Lấy nhiều đơn hàng theo IDs
     */
    public function findByIds(array $ids)
    {
        return Order::whereIn('order_id', $ids)->get();
    }

    /**
     * Tạo status history record
     */
    public function createStatusHistory(array $data): OrderStatusHistory
    {
        return OrderStatusHistory::create($data);
    }

    /**
     * Hoàn lại tồn kho bằng SQL CASE (batch update)
     */
    public function restoreStock(array $items): void
    {
        $cases = [];
        $bindings = [];
        $variantIds = [];

        foreach ($items as $item) {
            $variantId = $item->variant_id ?? $item['variant_id'] ?? null;
            $quantity  = $item->quantity ?? $item['quantity'] ?? 0;

            if (!$variantId) continue;

            $cases[]     = "WHEN ? THEN stock + ?";
            $bindings[]  = $variantId;
            $bindings[]  = $quantity;
            $variantIds[] = $variantId;
        }

        if (!empty($variantIds)) {
            $ids = implode(',', array_fill(0, count($variantIds), '?'));
            $casesSql = implode(' ', $cases);
            $bindings = array_merge($bindings, $variantIds);
            DB::statement(
                "UPDATE product_variants SET stock = CASE variant_id {$casesSql} END, updated_at = NOW() WHERE variant_id IN ({$ids})",
                $bindings
            );
        }
    }
}
