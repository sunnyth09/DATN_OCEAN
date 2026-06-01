<?php

namespace App\Http\Controllers;

use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class FlashSaleController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC — Danh sách Flash Sale Items đang active
    // GET /api/flash-sale
    // ─────────────────────────────────────────────────────────────────────────
    public function index(): JsonResponse
    {
        // Phục vụ real-time: Giảm cache xuống 5-10s hoặc lấy trực tiếp
        // Vì FlashSaleBoard lấy data để đếm ngược
        $data = Cache::remember('flash_sale_public_list', 5, function () {
            // Lấy chiến dịch đang active hoặc sắp diễn ra
            $campaigns = FlashSale::whereIn('status', ['active', 'draft'])
                ->where('end_time', '>', now())
                ->with(['items.product'])
                ->orderBy('start_time', 'asc')
                ->get();

            $formatted = [];
            foreach ($campaigns as $fs) {
                foreach ($fs->items as $item) {
                    $originalPrice = $item->product ? ($item->product->min_price ?? 0) : 0;
                    $discountPct = $originalPrice > 0 ? round((($originalPrice - $item->campaign_price) / $originalPrice) * 100) : 0;
                    
                    // Gọi key redis từ FlashSaleService
                    $stockKey = "flash_sale_{$fs->id}_product_{$item->product_id}_stock";
                    $redisStock = Redis::get($stockKey);
                    $remaining = $redisStock !== null ? (int)$redisStock : ($item->campaign_stock - $item->sold);

                    $formatted[] = [
                        'id'               => $fs->id,       // Vẫn mang id campaign để query stock
                        'item_id'          => $item->id,     // ID của item
                        'product_id'       => $item->product_id,
                        'title'            => $fs->name,
                        'product_name'     => $item->product->name ?? 'Sản phẩm Flash Sale',
                        'product_thumbnail'=> $item->product->thumbnail_url ?? null,
                        'sale_price'       => (float)$item->campaign_price,
                        'original_price'   => (float)$originalPrice,
                        'discount_percent' => $discountPct,
                        'total_stock'      => $item->campaign_stock,
                        'sold_count'       => max(0, $item->campaign_stock - $remaining),
                        'max_per_user'     => 1, // Mặc định mỗi người 1 sp
                        'starts_at'        => $fs->start_time->toISOString(),
                        'ends_at'          => $fs->end_time->toISOString(),
                        'status'           => $fs->status,
                        'server_time'      => now()->toISOString(),
                    ];
                }
            }
            return $formatted;
        });

        // Chỉ ưu tiên những item đang active/bắt đầu
        $activeData = array_filter($data, function($i) {
            return $i['status'] === 'active' && strtotime($i['starts_at']) <= time() && strtotime($i['ends_at']) >= time();
        });

        if (empty($activeData)) {
            $activeData = $data; // Fallback lấy cả upcoming
        }

        return response()->json([
            'status' => 'success',
            'data'   => array_values($activeData),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC — Lấy tồn kho hiện tại từ Redis (cho Progress Bar)
    // GET /api/flash-sale/{id}/stock?product_id=xxx
    // ─────────────────────────────────────────────────────────────────────────
    public function stock(Request $request, int $id): JsonResponse
    {
        $productId = $request->query('product_id');

        $flashSale = Cache::remember("flash_sale_meta_{$id}", 30, fn () => FlashSale::find($id));
        if (!$flashSale) {
            return response()->json(['message' => 'Flash Sale không tồn tại.'], 404);
        }

        // Lấy Item
        $itemQuery = FlashSaleItem::where('flash_sale_id', $id);
        if ($productId) {
            $itemQuery->where('product_id', $productId);
        }
        $item = $itemQuery->first();

        if (!$item) {
            return response()->json(['message' => 'Sản phẩm không có trong Flash Sale.'], 404);
        }

        $stockKey = "flash_sale_{$id}_product_{$item->product_id}_stock";
        $remaining = Redis::get($stockKey);
        
        if ($remaining === null) {
            $remaining = max(0, $item->campaign_stock - $item->sold);
        } else {
            $remaining = (int)$remaining;
        }

        $soldCount = max(0, $item->campaign_stock - $remaining);

        return response()->json([
            'status'      => 'success',
            'flash_sale_id' => $id,
            'product_id'  => $item->product_id,
            'total_stock' => $item->campaign_stock,
            'remaining'   => $remaining,
            'sold_count'  => $soldCount,
            'is_sold_out' => $remaining <= 0,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CORE — Mua Flash Sale (High-Concurrency safe)
    // POST /api/flash-sale/buy  [auth required, throttle:10,1]
    // ─────────────────────────────────────────────────────────────────────────
    public function buy(Request $request): JsonResponse
    {
        $request->validate([
            'flash_sale_id'   => 'required|integer|exists:flash_sales,id',
            'product_id'      => 'required|integer|exists:products,product_id',
            'quantity'        => 'integer|min:1|max:5',
            'recipient_name'  => 'required|string|max:100',
            'recipient_phone' => 'required|string|max:20',
        ]);

        $flashSaleId = (int) $request->flash_sale_id;
        $productId   = (int) $request->product_id;
        $quantity    = (int) ($request->quantity ?? 1);
        $userId      = auth('api')->user()?->user_id ?? auth('admin')->user()?->getKey();

        $flashSale = Cache::remember("flash_sale_meta_{$flashSaleId}", 10, fn () => FlashSale::find($flashSaleId));

        if (!$flashSale || $flashSale->status !== 'active' || now()->lt($flashSale->start_time) || now()->gt($flashSale->end_time)) {
            return response()->json(['message' => 'Flash Sale không hoạt động.'], 400);
        }

        $itemQuery = FlashSaleItem::where('flash_sale_id', $flashSaleId)->where('product_id', $productId)->first();
        if (!$itemQuery) {
            return response()->json(['message' => 'Sản phẩm không có trong Flash Sale.'], 400);
        }

        $userPurchaseKey = "flash_sale_{$flashSaleId}_user_{$userId}_prod_{$productId}";
        $userBought = (int) (Redis::get($userPurchaseKey) ?? 0);

        if ($userBought + $quantity > 1) { // hardcode max 1 if not defined
            return response()->json(['message' => "Mỗi khách hàng chỉ được mua 1 sản phẩm này."], 400);
        }

        $stockKey  = "flash_sale_{$flashSaleId}_product_{$productId}_stock";
        $remaining = Redis::decrby($stockKey, $quantity);

        if ($remaining < 0) {
            Redis::incrby($stockKey, $quantity); 
            return response()->json([
                'message'  => 'Rất tiếc! Sản phẩm đã hết hàng.',
                'sold_out' => true,
            ], 400);
        }

        $ttl = max(60, now()->diffInSeconds($flashSale->end_time));
        Redis::incrby($userPurchaseKey, $quantity);
        Redis::expire($userPurchaseKey, $ttl);

        return response()->json([
            'status'     => 'success',
            'message'    => '🎉 Đặt hàng thành công!',
            'order_code' => 'FS-' . strtoupper(uniqid()),
            'remaining'  => (int) $remaining,
        ], 200);
    }
}
