<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\FlashSale;
use App\Repositories\CartRepository;
use App\Services\ComboService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * ComboController — Public API cho Combo/Bundle Promotion.
 *
 * GET  /api/combos                → Danh sách flash sale combo + auto voucher đang active
 * POST /api/combos/check-cart     → Kiểm tra cart có eligible không + preview discount
 * POST /admin/combos/flash-sale   → Tạo Flash Sale Combo (Admin)
 * POST /admin/combos/voucher      → Tạo Combo Voucher auto-apply (Admin)
 */
class ComboController extends Controller
{
    public function __construct(
        protected ComboService $comboService,
        protected CartRepository $cartRepository,
    ) {}

    // ─── PUBLIC ─────────────────────────────────────────────────────────

    /**
     * GET /api/combos
     * Danh sách combo đang active (flash sale combo + combo vouchers).
     * Cache 3 phút.
     */
    public function index(): JsonResponse
    {
        $data = Cache::remember('combos:active', 180, function () {
            $flashCombos = FlashSale::active()->combo()
                ->with('items.product:product_id,name,thumbnail_url,min_price,slug')
                ->get()
                ->map(fn ($fs) => [
                    'type' => 'flash_sale_combo',
                    'id' => $fs->id,
                    'name' => $fs->name,
                    'combo_label' => $fs->combo_label,
                    'end_time' => $fs->end_time?->toISOString(),
                    'items' => $fs->items->map(fn ($i) => [
                        'product_id' => $i->product_id,
                        'product_name' => $i->product?->name,
                        'thumbnail_url' => $i->product?->thumbnail_url,
                        'campaign_price' => $i->campaign_price,
                        'min_qty' => $i->min_qty ?? 1,
                    ]),
                ]);

            $comboVouchers = Coupon::where('type', 'combo')
                ->where('auto_apply', true)
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', now());
                })
                ->with('products:product_id,name,thumbnail_url,min_price')
                ->get()
                ->map(fn ($c) => [
                    'type' => 'combo_voucher',
                    'id' => $c->id,
                    'code' => $c->code,
                    'value' => $c->value,
                    'max_discount_value' => $c->max_discount_value,
                    'end_date' => $c->end_date?->toISOString(),
                    'products' => $c->products->map(fn ($p) => [
                        'product_id' => $p->product_id,
                        'name' => $p->name,
                        'thumbnail_url' => $p->thumbnail_url,
                        'min_qty' => $p->pivot->min_qty ?? 1,
                    ]),
                ]);

            return [
                'flash_sale_combos' => $flashCombos,
                'combo_vouchers' => $comboVouchers,
            ];
        });

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /**
     * POST /api/combos/check-cart
     * Kiểm tra cart của user đang eligible với combo nào + preview discount.
     * Requires auth:api
     */
    public function checkCart(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $cart = $this->cartRepository->getActiveCart($user->user_id);

        if (! $cart) {
            return response()->json([
                'status' => 'success',
                'data' => ['eligible_combos' => [], 'total_discount' => 0],
            ]);
        }

        $cartItems = $this->cartRepository->getSelectedCartItems($cart->cart_id);

        if ($cartItems->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'data' => ['eligible_combos' => [], 'total_discount' => 0],
            ]);
        }

        $subtotal = $cartItems->sum(fn ($i) => ($i->variant->price ?? 0) * $i->quantity);

        $result = $this->comboService->applyAllCombos($user->user_id, $cartItems, $subtotal);

        return response()->json([
            'status' => 'success',
            'data' => [
                'eligible_combos' => $result['details'],
                'applied_flash_sale_combos' => collect($result['applied_flash_sale_combos'])->pluck('id'),
                'applied_combo_vouchers' => collect($result['applied_combo_vouchers'])->pluck('code'),
                'total_discount' => $result['discount_amount'],
            ],
        ]);
    }

    // ─── ADMIN ──────────────────────────────────────────────────────────

    /**
     * POST /admin/combos/flash-sale
     * Tạo Flash Sale campaign ở chế độ Combo Bundle.
     */
    public function storeFlashCombo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'combo_label' => 'nullable|string|max:200',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'items' => 'required|array|min:2',
            'items.*.product_id' => 'required|integer|exists:products,product_id',
            'items.*.campaign_price' => 'required|numeric|min:0',
            'items.*.campaign_stock' => 'required|integer|min:1',
            'items.*.min_qty' => 'nullable|integer|min:1',
        ]);

        $flashSale = FlashSale::create([
            'name' => $validated['name'],
            'combo_label' => $validated['combo_label'] ?? null,
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'status' => 'draft',
            'is_combo' => true,
        ]);

        foreach ($validated['items'] as $item) {
            $flashSale->items()->create([
                'product_id' => $item['product_id'],
                'campaign_price' => $item['campaign_price'],
                'campaign_stock' => $item['campaign_stock'],
                'sold' => 0,
                'min_qty' => $item['min_qty'] ?? 1,
            ]);
        }

        Cache::forget('combos:active');

        return response()->json([
            'status' => 'success',
            'message' => 'Tạo Flash Sale Combo thành công!',
            'data' => $flashSale->load('items.product'),
        ], 201);
    }

    /**
     * POST /admin/combos/voucher
     * Tạo Combo Voucher tự động áp dụng (type=combo, auto_apply=true).
     */
    public function storeComboVoucher(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:50|unique:coupons,code',
            'value' => 'required|numeric|min:0',
            'max_discount_value' => 'nullable|numeric|min:0',
            'min_order_value' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'user_usage_limit' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'min_product_qty' => 'nullable|integer|min:1',
            'products' => 'nullable|array',
            'products.*.product_id' => 'integer|exists:products,product_id',
            'products.*.min_qty' => 'nullable|integer|min:1',
        ]);

        // Tự sinh code nếu không truyền
        $code = $validated['code'] ?? 'COMBO-'.strtoupper(Str::random(6));

        $coupon = Coupon::create([
            'code' => $code,
            'type' => 'combo',
            'value' => $validated['value'],
            'max_discount_value' => $validated['max_discount_value'] ?? null,
            'min_order_value' => $validated['min_order_value'] ?? null,
            'usage_limit' => $validated['usage_limit'] ?? null,
            'used_count' => 0,
            'user_usage_limit' => $validated['user_usage_limit'] ?? 1,
            'is_public' => true,
            'is_first_order' => false,
            'is_active' => true,
            'auto_apply' => true,
            'min_product_qty' => $validated['min_product_qty'] ?? 1,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ]);

        // Gán sản phẩm bắt buộc
        if (! empty($validated['products'])) {
            $syncData = [];
            foreach ($validated['products'] as $prod) {
                $syncData[$prod['product_id']] = ['min_qty' => $prod['min_qty'] ?? 1];
            }
            $coupon->products()->sync($syncData);
        }

        Cache::forget('combos:active');

        return response()->json([
            'status' => 'success',
            'message' => 'Tạo Combo Voucher thành công!',
            'data' => $coupon->load('products'),
        ], 201);
    }
}
