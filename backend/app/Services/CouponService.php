<?php

namespace App\Services;

use App\Exceptions\OrderException;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use App\Models\UserCoupon;
use App\Repositories\CouponRepository;
use Illuminate\Support\Facades\Cache;

class CouponService
{
    public function __construct(
        protected CouponRepository $couponRepository
    ) {}

    // ─── ADMIN CRUD ────────────────────────────────────────────────────

    /**
     * Admin: danh sách coupon (kèm categories + thống kê lượt dùng), phân trang.
     */
    public function adminPaginate(int $perPage = 20)
    {
        $coupons = Coupon::with(['categories:category_id,name', 'userCoupons'])->paginate($perPage);

        collect($coupons->items())->each(function ($coupon) {
            $coupon->total_users_used = $coupon->userCoupons->where('used_count', '>', 0)->count();
            $coupon->category_ids = $coupon->categories->pluck('category_id');
        });

        return $coupons;
    }

    /**
     * Admin: tạo coupon mới + sync categories. Trả về coupon đã load categories.
     */
    public function adminCreate(array $data): Coupon
    {
        $coupon = Coupon::create(collect($data)->only([
            'code', 'type', 'value', 'max_discount_value', 'min_order_value',
            'usage_limit', 'user_usage_limit', 'start_date', 'end_date',
            'is_active', 'is_public', 'is_first_order',
        ])->all());

        if (isset($data['category_ids']) && is_array($data['category_ids'])) {
            $coupon->categories()->sync($data['category_ids']);
        }

        Cache::forget('coupons:public_active');

        return $coupon->load('categories:category_id,name');
    }

    /**
     * Admin: cập nhật coupon + sync lại categories.
     *
     * @return Coupon|null null nếu không tìm thấy.
     */
    public function adminUpdate($id, array $data): ?Coupon
    {
        $coupon = Coupon::find($id);
        if (! $coupon) {
            return null;
        }

        $coupon->update(collect($data)->except(['category_ids'])->all());

        if (array_key_exists('category_ids', $data)) {
            $coupon->categories()->sync($data['category_ids'] ?? []);
        }

        Cache::forget('coupons:public_active');

        return $coupon->load('categories:category_id,name');
    }

    /**
     * Admin: xóa mềm coupon.
     */
    public function adminDelete($id): bool
    {
        $coupon = Coupon::find($id);
        if (! $coupon) {
            return false;
        }

        $coupon->delete();
        Cache::forget('coupons:public_active');

        return true;
    }

    /**
     * Admin: danh sách user đã lưu/dùng 1 coupon + thống kê.
     *
     * @return array|null null nếu không tìm thấy coupon.
     */
    public function adminUsages($id): ?array
    {
        $coupon = Coupon::find($id);
        if (! $coupon) {
            return null;
        }

        $usages = UserCoupon::with('user:user_id,full_name,email,phone,avatar_url')
            ->where('coupon_id', $id)
            ->orderByDesc('used_count')
            ->get()
            ->map(fn ($uc) => [
                'user_id' => $uc->user_id,
                'full_name' => $uc->user->full_name ?? 'N/A',
                'email' => $uc->user->email ?? '',
                'phone' => $uc->user->phone ?? '',
                'avatar_url' => $uc->user->avatar_url ?? null,
                'used_count' => $uc->used_count,
                'is_saved' => $uc->is_saved,
                'saved_at' => $uc->created_at,
            ]);

        return [
            'coupon' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'used_count' => $coupon->used_count,
                'usage_limit' => $coupon->usage_limit,
            ],
            'usages' => $usages,
            'total_saved' => $usages->count(),
            'total_used' => $usages->filter(fn ($u) => $u['used_count'] > 0)->count(),
        ];
    }

    // ─── PUBLIC / CUSTOMER ─────────────────────────────────────────────

    /**
     * Danh sách coupon công khai còn hiệu lực (cache 30 phút).
     */
    public function getPublicCoupons()
    {
        return Cache::remember('coupons:public_active', 1800, function () {
            $now = now();

            return Coupon::where('is_public', true)
                ->where('is_active', true)
                ->where(function ($query) use ($now) {
                    $query->whereNull('start_date')->orWhere('start_date', '<=', $now);
                })
                ->where(function ($query) use ($now) {
                    $query->whereNull('end_date')->orWhere('end_date', '>=', $now);
                })
                ->with('categories:category_id,name')
                ->get();
        });
    }

    /**
     * Khách lưu coupon. Trả mã kết quả để controller map response shape.
     *
     * @return array{state: string, message: string}
     *                                               state: saved | already_saved | not_found
     */
    public function saveForUser(int $userId, $couponId): array
    {
        $coupon = $this->findSaveableCoupon((int) $couponId);
        if (! $coupon) {
            return ['state' => 'not_found', 'message' => 'Mã giảm giá không tồn tại hoặc đã hết hạn!'];
        }

        if ($coupon->is_first_order) {
            $hasOrders = Order::where('user_id', $userId)
                ->where('fulfillment_status', '!=', 'cancelled')
                ->exists();
            if ($hasOrders) {
                return ['state' => 'not_eligible', 'message' => 'Mã giảm giá này chỉ dành cho khách hàng chưa từng đặt đơn hàng nào!'];
            }
        }

        $record = UserCoupon::where('user_id', $userId)->where('coupon_id', $coupon->id)->first();
        if ($record && $record->is_saved) {
            return ['state' => 'already_saved', 'message' => 'Bạn đã lưu mã giảm giá này rồi!'];
        }

        if ($record) {
            $record->update(['is_saved' => true]);
        } else {
            UserCoupon::create([
                'user_id' => $userId,
                'coupon_id' => $coupon->id,
                'is_saved' => true,
                'used_count' => 0,
            ]);
        }

        return ['state' => 'saved', 'message' => 'Đã lưu mã giảm giá thành công!'];
    }

    /**
     * Danh sách coupon đã lưu của user (ẩn mã không còn khả dụng, đã dùng hết lượt, hoặc không đủ điều kiện đơn đầu).
     */
    public function getSavedForUser(int $userId)
    {
        $user = User::find($userId);
        $userEmail = $user?->email;
        $userPhone = $user?->phone;

        $hasOrders = Order::where(function ($q) use ($userId, $userEmail, $userPhone) {
            $q->where('user_id', $userId);
            if (! empty($userEmail)) {
                $q->orWhere('email', $userEmail);
            }
            if (! empty($userPhone)) {
                $q->orWhere('recipient_phone', $userPhone);
            }
        })
            ->where('fulfillment_status', '!=', 'cancelled')
            ->exists();

        return UserCoupon::with('coupon')
            ->where('user_id', $userId)
            ->where('is_saved', true)
            ->get()
            ->filter(function ($userCoupon) use ($hasOrders) {
                $coupon = $userCoupon->coupon;
                if (! $coupon || ! $this->isCouponSaveable($coupon)) {
                    return false;
                }
                if ($coupon->user_usage_limit && $userCoupon->used_count >= $coupon->user_usage_limit) {
                    return false;
                }
                if ($coupon->is_first_order && $hasOrders) {
                    return false;
                }

                return true;
            })
            ->values()
            ->pluck('coupon');
    }

    private function findSaveableCoupon(int $couponId): ?Coupon
    {
        $coupon = Coupon::find($couponId);

        return ($coupon && $this->isCouponSaveable($coupon)) ? $coupon : null;
    }

    private function isCouponSaveable(Coupon $coupon): bool
    {
        $now = now();

        if (! $coupon->is_active || ! $coupon->is_public) {
            return false;
        }

        if ($coupon->start_date && $now->lt($coupon->start_date)) {
            return false;
        }

        if ($coupon->end_date && $now->gt($coupon->end_date)) {
            return false;
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            return false;
        }

        return true;
    }

    public function checkCoupon(int $userId, string $couponCode, float $subtotal, $cartItems = null): array
    {
        $coupon = $this->couponRepository->findActiveByCode($couponCode);

        if (! $coupon) {
            return [
                'success' => false,
                'message' => 'Mã giảm giá không tồn tại hoặc đã hết hạn sử dụng. Bạn vui lòng kiểm tra lại nhé!',
            ];
        }

        $validateResult = $this->validateCoupon($userId, $coupon, $subtotal, $cartItems);

        if (! $validateResult['success']) {
            return $validateResult;
        }

        return [
            'success' => true,
            'coupon' => $coupon->loadMissing('categories:category_id,name'),
        ];
    }

    public function applyCoupon(int $userId, ?string $couponCode, float $subtotal, $cartItems = null): array
    {
        if (! $couponCode) {
            return [
                'success' => true,
                'coupon' => null,
                'discount_amount' => 0,
            ];
        }

        $coupon = $this->couponRepository->findActiveByCode($couponCode);

        if (! $coupon) {
            return $this->invalid('Mã giảm giá không tồn tại hoặc đã hết hạn sử dụng. Bạn vui lòng kiểm tra lại nhé!');
        }

        $validateResult = $this->validateCoupon($userId, $coupon, $subtotal, $cartItems);

        if (! $validateResult['success']) {
            return $validateResult;
        }

        $discountAmount = $this->calculateDiscount($coupon, $subtotal, $cartItems);

        return [
            'success' => true,
            'coupon' => $coupon,
            'discount_amount' => min($discountAmount, $subtotal),
        ];
    }

    private function validateCoupon(int $userId, $coupon, float $subtotal, $cartItems = null): array
    {
        $now = now();

        if (! $coupon->is_active) {
            return $this->invalid('Mã giảm giá hiện đang tạm ngưng áp dụng.');
        }

        if ($coupon->start_date && $now->lt($coupon->start_date)) {
            return $this->invalid('Mã giảm giá chưa đến thời gian áp dụng.');
        }

        if ($coupon->end_date && $now->gt($coupon->end_date)) {
            return $this->invalid('Mã giảm giá đã hết hạn sử dụng mất rồi.');
        }

        if ($coupon->min_order_value && $subtotal < $coupon->min_order_value) {
            return $this->invalid('Đơn hàng chưa đạt giá trị tối thiểu để áp dụng mã giảm giá này.');
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            return $this->invalid('Mã giảm giá đã hết lượt sử dụng mất rồi.');
        }

        if ($coupon->user_usage_limit !== null && $userId > 0) {
            $userUsedCount = $this->couponRepository->getUserCouponUsedCount(
                $userId,
                $coupon->id
            );

            if ($userUsedCount >= $coupon->user_usage_limit) {
                return $this->invalid('Bạn đã sử dụng hết số lượt cho phép của mã giảm giá này.');
            }
        }

        // Kiểm tra mã chỉ dành cho đơn hàng đầu tiên
        if ($coupon->is_first_order) {
            if ($userId <= 0) {
                return $this->invalid('Vui lòng đăng nhập để sử dụng mã ưu đãi cho đơn hàng đầu tiên.');
            }

            $user = User::find($userId);
            $userEmail = $user?->email;
            $userPhone = $user?->phone;

            $existingOrderCount = Order::where(function ($q) use ($userId, $userEmail, $userPhone) {
                $q->where('user_id', $userId);
                if (! empty($userEmail)) {
                    $q->orWhere('email', $userEmail);
                }
                if (! empty($userPhone)) {
                    $q->orWhere('recipient_phone', $userPhone);
                }
            })
                ->where('fulfillment_status', '!=', 'cancelled')
                ->count();

            if ($existingOrderCount > 0) {
                return $this->invalid('Mã ưu đãi này chỉ dành riêng cho khách hàng đặt đơn đầu tiên.');
            }
        }

        // Kiểm tra ràng buộc danh mục áp dụng (nếu coupon chỉ áp dụng cho một số danh mục nhất định)
        $coupon->loadMissing('categories:category_id,name');
        $allowedCategoryIds = $coupon->categories ? $coupon->categories->pluck('category_id')->filter()->all() : [];

        if (! empty($allowedCategoryIds) && ! empty($cartItems)) {
            $eligibleSubtotal = 0;
            $hasMatchingItem = false;

            foreach ($cartItems as $item) {
                $product = $item->variant?->product ?? $item->product ?? null;
                $catId = $product?->category_id ?? null;
                if ($catId && in_array($catId, $allowedCategoryIds)) {
                    $hasMatchingItem = true;
                    $itemPrice = (float) ($item->variant?->price ?? $item->price ?? 0);
                    $itemQty = (int) ($item->quantity ?? 1);
                    $eligibleSubtotal += $itemPrice * $itemQty;
                }
            }

            if (! $hasMatchingItem) {
                return $this->invalid('Mã giảm giá này chỉ áp dụng cho một số danh mục sản phẩm nhất định trong hệ thống.');
            }

            if ($coupon->min_order_value && $eligibleSubtotal < $coupon->min_order_value) {
                return $this->invalid('Tổng giá trị các sản phẩm thuộc danh mục áp dụng chưa đạt mức tối thiểu để dùng mã này.');
            }
        }

        return ['success' => true];
    }

    private function calculateDiscount($coupon, float $subtotal, $cartItems = null): float
    {
        $calcBase = $subtotal;
        $coupon->loadMissing('categories:category_id,name');
        $allowedCategoryIds = $coupon->categories ? $coupon->categories->pluck('category_id')->filter()->all() : [];

        if (! empty($allowedCategoryIds) && ! empty($cartItems)) {
            $eligibleSubtotal = 0;
            foreach ($cartItems as $item) {
                $product = $item->variant?->product ?? $item->product ?? null;
                $catId = $product?->category_id ?? null;
                if ($catId && in_array($catId, $allowedCategoryIds)) {
                    $itemPrice = (float) ($item->variant?->price ?? $item->price ?? 0);
                    $itemQty = (int) ($item->quantity ?? 1);
                    $eligibleSubtotal += $itemPrice * $itemQty;
                }
            }
            if ($eligibleSubtotal > 0) {
                $calcBase = $eligibleSubtotal;
            }
        }

        if ($coupon->type === 'percent') {
            $discount = ($calcBase * $coupon->value) / 100;

            if ($coupon->max_discount_value) {
                $discount = min($discount, $coupon->max_discount_value);
            }

            return $discount;
        }

        if ($coupon->type === 'fixed') {
            return min($coupon->value, $calcBase);
        }

        return 0;
    }

    public function markCouponAsUsed(int $userId, $coupon): void
    {
        $this->couponRepository->incrementUsage($coupon);

        if ($userId > 0) {
            $this->couponRepository->increaseUserCouponUsage(
                $userId,
                $coupon->id
            );
        }
    }

    /**
     * Tiêu thụ coupon một cách ATOMIC — phải gọi bên trong DB::transaction() của đơn hàng.
     *
     * Dùng conditional UPDATE để chống race (over-redemption / vượt giới hạn per-user).
     * Ném \App\Exceptions\OrderException nếu coupon đã hết lượt để rollback toàn bộ đơn.
     */
    public function consumeCoupon(int $userId, $coupon): void
    {
        if (! $this->couponRepository->tryConsumeGlobal($coupon->id)) {
            throw new OrderException('Mã giảm giá đã hết lượt sử dụng!');
        }

        if (! $this->couponRepository->tryConsumePerUser($userId, $coupon->id, $coupon->user_usage_limit)) {
            throw new OrderException('Bạn đã hết lượt sử dụng mã này!');
        }
    }

    private function invalid(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'coupon' => null,
            'discount_amount' => 0,
        ];
    }
}
