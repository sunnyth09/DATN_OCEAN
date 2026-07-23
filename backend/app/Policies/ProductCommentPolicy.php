<?php

namespace App\Policies;

use App\Models\OrderItem;
use App\Models\ProductComment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * ProductCommentPolicy — Kiểm tra quyền review và moderate đánh giá.
 *
 * Rules:
 *   - Customer: chỉ tạo review cho sản phẩm thuộc đơn hàng ĐÃ HOÀN THÀNH của mình.
 *   - Customer: chỉ xóa/cập nhật review CỦA CHÍNH MÌNH (nếu có).
 *   - Admin/Staff: được approve/reject/delete mọi comment.
 */
class ProductCommentPolicy
{
    use HandlesAuthorization;

    /**
     * Gate trước: Admin/Staff có quyền moderate.
     */
    public function before(mixed $user, string $ability): bool|null
    {
        // Chỉ shortcircuit với admin abilities (moderate)
        if (in_array($ability, ['moderate', 'delete'], true) && auth('admin')->check()) {
            return in_array(auth('admin')->user()->role, ['admin', 'staff'], true);
        }

        return null;
    }

    /**
     * Customer tạo review.
     * Kiểm tra:
     *   1. OrderItem thuộc đơn hàng của user.
     *   2. Đơn hàng đã completed/delivered.
     *   3. Chưa review item này.
     */
    public function create(User $user, OrderItem $orderItem): bool
    {
        // Phải là item trong đơn của user
        if ($orderItem->order->user_id !== $user->user_id) {
            return false;
        }

        // Đơn phải hoàn thành
        if (!in_array($orderItem->order->fulfillment_status, ['completed', 'delivered'], true)) {
            return false;
        }

        // Chưa review item này
        $alreadyReviewed = ProductComment::where('order_item_id', $orderItem->order_item_id)->exists();
        return !$alreadyReviewed;
    }

    /**
     * Customer cập nhật review của chính mình.
     */
    public function update(User $user, ProductComment $comment): bool
    {
        return $user->user_id === $comment->user_id;
    }

    /**
     * Admin/Staff moderate (approve/reject/delete).
     * Đã xử lý trong before() — method này chỉ là fallback.
     */
    public function moderate(User $user): bool
    {
        return false; // Customer không được moderate
    }

    /**
     * Xóa comment — Customer chỉ xóa của mình; Admin qua before().
     */
    public function delete(User $user, ProductComment $comment): bool
    {
        return $user->user_id === $comment->user_id;
    }
}
