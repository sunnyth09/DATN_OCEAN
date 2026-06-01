<?php

namespace App\Repositories;

use App\Models\ReturnRequest;

class ReturnRequestRepository
{
    public function create(array $data): ReturnRequest
    {
        return ReturnRequest::create($data);
    }

    public function getUserRequests(int $userId, array $filters = [], int $perPage = 10)
    {
        $query = ReturnRequest::with(['order', 'order.items', 'order.items.variant'])
            ->where('user_id', $userId)
            ->latest('requested_at')
            ->latest();

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage);
    }

    public function findUserRequest(int $userId, int $id): ?ReturnRequest
    {
        return ReturnRequest::with([
            'order',
            'order.items',
            'order.items.variant',
            'order.items.product',
            'user',
        ])
            ->where('user_id', $userId)
            ->find($id);
    }

    public function getAdminRequests(array $filters = [], int $perPage = 15)
    {
        $query = ReturnRequest::with(['order', 'user'])
            ->latest('requested_at')
            ->latest();

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['refund_status']) && $filters['refund_status'] !== 'all') {
            $query->where('refund_status', $filters['refund_status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($builder) use ($search) {
                $builder->where('reason', 'like', "%{$search}%")
                    ->orWhereHas('order', function ($orderQuery) use ($search) {
                        $orderQuery->where('order_code', 'like', "%{$search}%")
                            ->orWhere('recipient_name', 'like', "%{$search}%")
                            ->orWhere('recipient_phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('full_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        return $query->paginate($perPage);
    }

    public function findForAdmin(int $id): ?ReturnRequest
    {
        return ReturnRequest::with([
            'order',
            'order.items',
            'order.items.variant',
            'order.items.product',
            'order.statusHistories',
            'user',
        ])->find($id);
    }

    public function findActiveByOrderId(int $orderId): ?ReturnRequest
    {
        return ReturnRequest::where('order_id', $orderId)
            ->whereIn('status', \App\Enums\ReturnRequestStatus::activeValues())
            ->latest('requested_at')
            ->first();
    }

    public function findLatestByOrderId(int $orderId): ?ReturnRequest
    {
        return ReturnRequest::where('order_id', $orderId)
            ->latest('requested_at')
            ->latest()
            ->first();
    }
}
