<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * ReturnRequestPolicy — Kiểm tra quyền tạo/xem/xử lý yêu cầu hoàn hàng.
 *
 * Rules:
 *   - Customer: chỉ tạo return cho đơn CỦA MÌNH và trong trạng thái eligible.
 *   - Staff: được xem + xử lý (approve/reject/refund) mọi return.
 *   - Admin: được toàn quyền.
 */
class ReturnRequestPolicy
{
    use HandlesAuthorization;

    /**
     * Gate trước: Admin/Staff được phép làm tất cả.
     */
    public function before(mixed $user, string $ability): bool|null
    {
        if (auth('admin')->check()) {
            $adminUser = auth('admin')->user();
            return in_array($adminUser->role, ['admin', 'staff'], true);
        }

        return null;
    }

    /**
     * Customer tạo yêu cầu hoàn hàng.
     * Phải là đơn của chính mình, trạng thái eligible (delivered/completed).
     */
    public function create(User $user, Order $order): bool
    {
        return $user->user_id === $order->user_id
            && in_array($order->fulfillment_status, \App\Enums\OrderStatus::returnEligibleValues(), true);
    }

    /**
     * Customer xem return request của chính mình.
     */
    public function view(User $user, ReturnRequest $returnRequest): bool
    {
        return $user->user_id === $returnRequest->user_id;
    }

    /**
     * Chỉ Admin (không phải staff) được xử lý refund tiền.
     * Staff chỉ được approve/reject, không refund.
     */
    public function processRefund(): bool
    {
        if (!auth('admin')->check()) return false;
        return auth('admin')->user()->role === 'admin';
    }
}
