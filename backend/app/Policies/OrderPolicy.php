<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\Enums\OrderStatus;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * OrderPolicy — Kiểm tra quyền truy cập đơn hàng theo từng actor.
 *
 * Nguyên tắc:
 *   - Customer chỉ xem/hủy đơn của CHÍNH MÌNH.
 *   - Admin/Staff được xem và cập nhật MỌI đơn hàng (qua route middleware 'role:admin,staff').
 *   - Chưa login → tất cả đều bị từ chối (before() trả null → tiếp tục xét rule).
 *
 * Sử dụng trong controller:
 *   $this->authorize('view', $order);       // customer xem đơn của mình
 *   $this->authorize('cancel', $order);     // customer hủy đơn của mình
 */
class OrderPolicy
{
    use HandlesAuthorization;

    /**
     * Gate trước: Admin/Staff được phép làm mọi thứ trên Order.
     * Trả null để tiếp tục xét các rules bên dưới cho customer.
     */
    public function before(mixed $user, string $ability): bool|null
    {
        // Admin model dùng 'admin' guard — check role thuộc admin/staff
        if (auth('admin')->check()) {
            $adminUser = auth('admin')->user();
            return in_array($adminUser->role, ['admin', 'staff'], true);
        }

        return null; // Để policy tiếp tục xét
    }

    /**
     * Customer xem chi tiết đơn hàng.
     * Chỉ được xem đơn của chính mình.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->user_id === $order->user_id;
    }

    /**
     * Customer hủy đơn hàng.
     * Phải là đơn của mình VÀ đang ở trạng thái pending.
     */
    public function cancel(User $user, Order $order): bool
    {
        return $user->user_id === $order->user_id
            && $order->fulfillment_status === OrderStatus::PENDING->value;
    }

    /**
     * Customer tạo đơn hàng (chỉ khách hàng, không phải admin).
     * Note: method này không nhận $order vì chưa tồn tại (create gate).
     */
    public function create(User $user): bool
    {
        // Đã qua auth('api') middleware, chỉ cần confirm không phải admin guard
        return auth('api')->check() && $user->status === 'active';
    }
}
