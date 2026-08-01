<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\FlashSale;
use App\Models\UserCoupon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ComboService — Xử lý Combo/Bundle Promotion dựa trên:
 *   1. Flash Sale có is_combo=true  → bundle giá campaign_price
 *   2. Coupon có type='combo'       → auto-apply voucher khi đủ sản phẩm
 *
 * Được gọi từ OrderService sau CouponService, trước khi tính grand_total.
 *
 * Discount chain: subtotal → coupon_discount → combo_discount → shipping → grand_total
 */
class ComboService
{
    // ─── PUBLIC ─────────────────────────────────────────────────────────

    /**
     * Áp dụng tất cả combo đang eligible cho cart.
     *
     * @param  Collection  $cartItems  CartItem collection (với variant.product loaded)
     * @param  float  $subtotal  Tổng tiền hàng trước discount
     * @return array {
     *               discount_amount: float,
     *               applied_flash_sale_combos: FlashSale[],
     *               applied_combo_vouchers: Coupon[],
     *               details: array           // Chi tiết từng combo áp dụng (để hiển thị)
     *               }
     */
    public function applyAllCombos(int $userId, Collection $cartItems, float $subtotal): array
    {
        $totalDiscount = 0.0;
        $flashCombos = [];
        $voucherCombos = [];
        $details = [];

        // 1. Flash Sale Combo (is_combo=true)
        $flashResult = $this->applyFlashSaleCombos($cartItems);
        $totalDiscount += $flashResult['discount_amount'];
        $flashCombos = $flashResult['applied'];
        $details = array_merge($details, $flashResult['details']);

        // 2. Auto-apply Combo Vouchers (type=combo, auto_apply=true)
        $voucherResult = $this->applyComboVouchers($userId, $cartItems, $subtotal - $totalDiscount);
        $totalDiscount += $voucherResult['discount_amount'];
        $voucherCombos = $voucherResult['applied'];
        $details = array_merge($details, $voucherResult['details']);

        return [
            'discount_amount' => round($totalDiscount, 2),
            'applied_flash_sale_combos' => $flashCombos,
            'applied_combo_vouchers' => $voucherCombos,
            'details' => $details,
        ];
    }

    /**
     * Lấy danh sách combo đang eligible với cart (dùng cho preview ở frontend).
     *
     * @return array { flash_sale_combos: [], combo_vouchers: [] }
     */
    public function getEligibleCombos(Collection $cartItems): array
    {
        $flashCombos = $this->getEligibleFlashSaleCombos($cartItems);
        $voucherCombos = $this->getEligibleComboVouchers($cartItems);

        return [
            'flash_sale_combos' => $flashCombos->map(fn ($fs) => $this->formatFlashCombo($fs, $cartItems))->values(),
            'combo_vouchers' => $voucherCombos->map(fn ($c) => $this->formatVoucherCombo($c, $cartItems))->values(),
        ];
    }

    /**
     * Đánh dấu đã sử dụng combo vouchers sau khi order tạo xong.
     *
     * @param  Coupon[]  $vouchers
     */
    public function markVouchersAsUsed(array $vouchers, int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        // PHẢI gọi bên trong DB::transaction() của đơn hàng (OrderService) để đảm bảo
        // atomic cùng với việc tạo đơn. Dùng conditional UPDATE trên used_count để
        // chống over-redemption khi có nhiều request đồng thời.
        foreach ($vouchers as $voucher) {
            // Tiêu thụ 1 lượt toàn cục: chỉ tăng khi còn lượt.
            $globalOk = Coupon::where('id', $voucher->id)
                ->where(function ($q) {
                    $q->whereNull('usage_limit')
                        ->orWhereColumn('used_count', '<', 'usage_limit');
                })
                ->update(['used_count' => DB::raw('used_count + 1')]);

            if (! $globalOk) {
                // Hết lượt toàn cục → bỏ qua voucher này (không chặn cả đơn vì đây là auto-apply).
                continue;
            }

            // Tiêu thụ lượt theo user trên bảng user_coupons (cột thật là used_count).
            $perUserLimit = $voucher->user_usage_limit;
            $query = UserCoupon::where('user_id', $userId)->where('coupon_id', $voucher->id);
            if ($perUserLimit !== null) {
                $query->where('used_count', '<', $perUserLimit);
            }
            $affected = $query->update(['used_count' => DB::raw('used_count + 1')]);

            if ($affected === 0) {
                $exists = UserCoupon::where('user_id', $userId)
                    ->where('coupon_id', $voucher->id)
                    ->lockForUpdate()
                    ->exists();

                if (! $exists) {
                    UserCoupon::create([
                        'user_id' => $userId,
                        'coupon_id' => $voucher->id,
                        'used_count' => 1,
                        'is_saved' => false,
                    ]);
                }
            }
        }
    }

    // ─── FLASH SALE COMBO ───────────────────────────────────────────────

    /**
     * Áp dụng tất cả Flash Sale combo đang active cho cart.
     * Logic: nếu cart có đủ tất cả items (với min_qty) của một combo flash sale
     *        → discount = Σ(regular_price - campaign_price) * qty cho các sản phẩm đó
     */
    private function applyFlashSaleCombos(Collection $cartItems): array
    {
        $eligibleCombos = $this->getEligibleFlashSaleCombos($cartItems);

        $totalDiscount = 0.0;
        $applied = [];
        $details = [];

        foreach ($eligibleCombos as $combo) {
            $discount = $this->calcFlashComboDiscount($combo, $cartItems);

            if ($discount <= 0) {
                continue;
            }

            $totalDiscount += $discount;
            $applied[] = $combo;
            $details[] = [
                'type' => 'flash_sale_combo',
                'id' => $combo->id,
                'name' => $combo->name,
                'combo_label' => $combo->combo_label,
                'discount' => $discount,
            ];
        }

        return [
            'discount_amount' => $totalDiscount,
            'applied' => $applied,
            'details' => $details,
        ];
    }

    /**
     * Lấy Flash Sale combo đang active và eligible với cart.
     */
    private function getEligibleFlashSaleCombos(Collection $cartItems): Collection
    {
        $activeCombos = FlashSale::active()->combo()->with('items.product')->get();

        return $activeCombos->filter(function (FlashSale $combo) use ($cartItems) {
            return $this->cartHasAllComboItems($combo, $cartItems);
        })->values();
    }

    /**
     * Kiểm tra cart có đủ tất cả items của Flash Sale combo không.
     */
    private function cartHasAllComboItems(FlashSale $combo, Collection $cartItems): bool
    {
        if ($combo->items->isEmpty()) {
            return false;
        }

        $cartProductIds = $this->getCartProductIds($cartItems);

        foreach ($combo->items as $item) {
            if (! in_array($item->product_id, $cartProductIds)) {
                return false;
            }

            // Kiểm tra số lượng tối thiểu
            $minQty = $item->min_qty ?? 1;
            $cartQty = $this->getCartQtyForProduct($cartItems, $item->product_id);

            if ($cartQty < $minQty) {
                return false;
            }

            // Kiểm tra còn hàng Flash Sale (kiểm tra Redis hoặc DB)
            $remaining = $item->campaign_stock - $item->sold;
            if ($remaining <= 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Tính discount của Flash Sale combo:
     * Discount = Σ (variant.price - campaign_price) * qty  cho các item trong combo
     */
    private function calcFlashComboDiscount(FlashSale $combo, Collection $cartItems): float
    {
        $discount = 0.0;

        foreach ($combo->items as $comboItem) {
            // Tìm cart item tương ứng
            foreach ($cartItems as $cartItem) {
                $productId = $cartItem->variant?->product_id ?? null;

                if ($productId !== $comboItem->product_id) {
                    continue;
                }

                $regularPrice = (float) ($cartItem->variant->price ?? 0);
                $campaignPrice = (float) $comboItem->campaign_price;
                $qty = min($cartItem->quantity, $comboItem->min_qty ?? 1);

                if ($regularPrice > $campaignPrice) {
                    $discount += ($regularPrice - $campaignPrice) * $qty;
                }
            }
        }

        return $discount;
    }

    // ─── COMBO VOUCHERS (AUTO-APPLY) ────────────────────────────────────

    /**
     * Tìm và áp dụng tất cả combo vouchers (auto_apply=true, type=combo)
     * đang eligible với cart của user.
     */
    private function applyComboVouchers(int $userId, Collection $cartItems, float $currentSubtotal): array
    {
        $eligibleVouchers = $this->getEligibleComboVouchers($cartItems);

        $totalDiscount = 0.0;
        $applied = [];
        $details = [];

        foreach ($eligibleVouchers as $voucher) {
            // Kiểm tra user còn lượt dùng không
            if (! $this->userCanUseVoucher($userId, $voucher)) {
                continue;
            }

            $discount = $this->calcVoucherDiscount($voucher, $currentSubtotal - $totalDiscount);

            if ($discount <= 0) {
                continue;
            }

            $totalDiscount += $discount;
            $applied[] = $voucher;
            $details[] = [
                'type' => 'combo_voucher',
                'id' => $voucher->id,
                'code' => $voucher->code,
                'discount' => $discount,
            ];
        }

        return [
            'discount_amount' => $totalDiscount,
            'applied' => $applied,
            'details' => $details,
        ];
    }

    /**
     * Lấy tất cả combo vouchers đang active, auto_apply=true,
     * và cart đủ điều kiện (có đủ sản phẩm chỉ định).
     */
    private function getEligibleComboVouchers(Collection $cartItems): Collection
    {
        $now = now();

        $vouchers = Coupon::where('type', 'combo')
            ->where('auto_apply', true)
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $now);
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->with('products') // eager load required products
            ->get();

        return $vouchers->filter(function (Coupon $voucher) use ($cartItems) {
            return $this->cartHasComboVoucherProducts($voucher, $cartItems);
        })->values();
    }

    /**
     * Kiểm tra cart có đủ sản phẩm bắt buộc của combo voucher không.
     */
    private function cartHasComboVoucherProducts(Coupon $voucher, Collection $cartItems): bool
    {
        $requiredProducts = $voucher->products;

        // Nếu không chỉ định sản phẩm → áp dụng dựa vào min_product_qty
        if ($requiredProducts->isEmpty()) {
            $totalQty = $cartItems->sum('quantity');

            return $totalQty >= ($voucher->min_product_qty ?? 1);
        }

        $cartProductIds = $this->getCartProductIds($cartItems);

        foreach ($requiredProducts as $product) {
            if (! in_array($product->product_id, $cartProductIds)) {
                return false;
            }

            $minQty = $product->pivot->min_qty ?? 1;
            $cartQty = $this->getCartQtyForProduct($cartItems, $product->product_id);

            if ($cartQty < $minQty) {
                return false;
            }
        }

        // Kiểm tra min_order_value nếu có
        if ($voucher->min_order_value) {
            $subtotal = $cartItems->sum(fn ($i) => ($i->variant->price ?? 0) * $i->quantity);
            if ($subtotal < $voucher->min_order_value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Kiểm tra user còn lượt dùng voucher này không.
     */
    private function userCanUseVoucher(int $userId, Coupon $voucher): bool
    {
        if ($userId <= 0) {
            return true;
        }
        if (! $voucher->user_usage_limit) {
            return true;
        }

        // Cột thật trên user_coupons là used_count (không có cột status).
        $used = (int) (UserCoupon::where('user_id', $userId)
            ->where('coupon_id', $voucher->id)
            ->value('used_count') ?? 0);

        return $used < $voucher->user_usage_limit;
    }

    /**
     * Tính discount của combo voucher.
     */
    private function calcVoucherDiscount(Coupon $voucher, float $subtotal): float
    {
        $discount = match ($voucher->type) {
            'percent' => $subtotal * ($voucher->value / 100),
            'fixed' => min($voucher->value, $subtotal),
            'free_ship' => 0, // xử lý ở ShippingService
            'combo' => $this->calcComboTypeDiscount($voucher, $subtotal),
            default => 0,
        };

        if ($voucher->max_discount_value) {
            $discount = min($discount, $voucher->max_discount_value);
        }

        return max(0, $discount);
    }

    /**
     * Tính discount cho voucher type='combo' (dùng value như percent hoặc fixed
     * tuỳ theo giá trị — nếu value <= 100 → hiểu là %, ngược lại là fixed).
     * Admin tự cấu hình theo từng campaign.
     */
    private function calcComboTypeDiscount(Coupon $voucher, float $subtotal): float
    {
        // Combo voucher: discount_type được xác định bởi max_discount_value
        // Nếu max_discount_value đặt → giảm % (value = phần trăm)
        // Nếu không → giảm cố định (value = số tiền)
        if ($voucher->max_discount_value) {
            // Percent mode
            return min($subtotal * ($voucher->value / 100), $voucher->max_discount_value);
        }

        // Fixed mode
        return min($voucher->value, $subtotal);
    }

    // ─── FORMAT (cho public API) ─────────────────────────────────────────

    private function formatFlashCombo(FlashSale $combo, Collection $cartItems): array
    {
        return [
            'type' => 'flash_sale_combo',
            'id' => $combo->id,
            'name' => $combo->name,
            'combo_label' => $combo->combo_label,
            'end_time' => $combo->end_time?->toISOString(),
            'discount' => $this->calcFlashComboDiscount($combo, $cartItems),
            'items' => $combo->items->map(fn ($i) => [
                'product_id' => $i->product_id,
                'product_name' => $i->product?->name,
                'campaign_price' => $i->campaign_price,
                'min_qty' => $i->min_qty ?? 1,
            ]),
        ];
    }

    private function formatVoucherCombo(Coupon $voucher, Collection $cartItems): array
    {
        $subtotal = $cartItems->sum(fn ($i) => ($i->variant->price ?? 0) * $i->quantity);

        return [
            'type' => 'combo_voucher',
            'id' => $voucher->id,
            'code' => $voucher->code,
            'discount' => $this->calcVoucherDiscount($voucher, $subtotal),
            'products' => $voucher->products->map(fn ($p) => [
                'product_id' => $p->product_id,
                'name' => $p->name,
                'min_qty' => $p->pivot->min_qty ?? 1,
            ]),
        ];
    }

    // ─── HELPERS ────────────────────────────────────────────────────────

    private function getCartProductIds(Collection $cartItems): array
    {
        return $cartItems
            ->map(fn ($item) => $item->variant?->product_id ?? null)
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    private function getCartQtyForProduct(Collection $cartItems, int $productId): int
    {
        return $cartItems
            ->filter(fn ($item) => ($item->variant?->product_id ?? null) === $productId)
            ->sum('quantity');
    }
}
