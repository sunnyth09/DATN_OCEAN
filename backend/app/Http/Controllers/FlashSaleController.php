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
        $user        = auth('api')->user() ?? auth('admin')->user();
        $userId      = $user ? ($user->user_id ?? $user->getKey()) : null;

        if (!$userId || !$user) {
            return response()->json(['message' => 'Vui lòng đăng nhập để tiếp tục.'], 401);
        }

        $flashSale = Cache::remember("flash_sale_meta_{$flashSaleId}", 10, fn () => FlashSale::find($flashSaleId));

        if (!$flashSale || $flashSale->status !== 'active' || now()->lt($flashSale->start_time) || now()->gt($flashSale->end_time)) {
            return response()->json(['message' => 'Flash Sale không hoạt động.'], 400);
        }

        $itemQuery = FlashSaleItem::where('flash_sale_id', $flashSaleId)->where('product_id', $productId)->first();
        if (!$itemQuery) {
            return response()->json(['message' => 'Sản phẩm không có trong Flash Sale.'], 400);
        }

        $maxPerUser = 1; // Giới hạn mỗi khách 1 sản phẩm
        $ttl = max(60, now()->diffInSeconds($flashSale->end_time));

        // Reserve suất mua theo user một cách ATOMIC: incrby trước rồi mới kiểm tra
        // giá trị trả về. Tránh TOCTOU khi nhiều request đồng thời cùng đọc giá trị cũ.
        $userPurchaseKey = "flash_sale_{$flashSaleId}_user_{$userId}_prod_{$productId}";
        $userBought = Redis::incrby($userPurchaseKey, $quantity);
        Redis::expire($userPurchaseKey, $ttl);

        if ($userBought > $maxPerUser) {
            // Vượt giới hạn → trả lại suất vừa reserve.
            Redis::decrby($userPurchaseKey, $quantity);
            return response()->json(['message' => "Mỗi khách hàng chỉ được mua {$maxPerUser} sản phẩm này."], 400);
        }

        $stockKey  = "flash_sale_{$flashSaleId}_product_{$productId}_stock";
        $remaining = Redis::decrby($stockKey, $quantity);

        if ($remaining < 0) {
            Redis::incrby($stockKey, $quantity);
            // Hết hàng → nhả luôn suất mua đã reserve cho user.
            Redis::decrby($userPurchaseKey, $quantity);
            return response()->json([
                'message'  => 'Rất tiếc! Sản phẩm đã hết hàng.',
                'sold_out' => true,
            ], 400);
        }

        // Chuẩn bị thông tin đơn hàng
        $orderCode = 'FS-' . strtoupper(uniqid());
        $defaultAddress = $user->addresses()->where('is_default', true)->first() ?? $user->addresses()->first();
        $addressId = $defaultAddress ? $defaultAddress->address_id : null;
        
        $shippingAddress = $request->shipping_address ?? 'Địa chỉ mặc định';
        if ($defaultAddress && $shippingAddress === 'Địa chỉ mặc định') {
            $shippingAddress = implode(', ', array_filter([
                $defaultAddress->address_line,
                $defaultAddress->ward,
                $defaultAddress->district,
                $defaultAddress->province
            ]));
        }

        // Đưa vào Queue để tạo đơn hàng trong Database
        \App\Jobs\OrderProcessingJob::dispatch(
            $flashSaleId,
            $productId,
            $userId,
            $quantity,
            $addressId,
            $request->recipient_name,
            $request->recipient_phone,
            $shippingAddress,
            $request->payment_method ?? 'cod',
            $orderCode
        );

        return response()->json([
            'status'     => 'success',
            'message'    => '🎉 Đặt hàng thành công!',
            'order_code' => $orderCode,
            'remaining'  => (int) $remaining,
        ], 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ADMIN METHODS
    // ─────────────────────────────────────────────────────────────────────────

    public function adminIndex()
    {
        $flashSales = FlashSale::with('items.product')->orderBy('created_at', 'desc')->get();
        return response()->json(['status' => 'success', 'data' => $flashSales]);
    }

    public function searchProducts(Request $request)
    {
        $query = $request->query('query');
        $products = \App\Models\Product::where('name', 'like', "%{$query}%")
            ->limit(10)
            ->get(['product_id', 'name', 'thumbnail_url as thumbnail', 'base_price', 'stock']);
            
        return response()->json(['status' => 'success', 'data' => $products]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'status' => 'required|in:draft,active,ended',
            'items' => 'array',
            'items.*.product_id' => 'required|exists:products,product_id',
            'items.*.campaign_price' => 'required|numeric|min:0',
            'items.*.campaign_stock' => 'required|integer|min:1',
        ]);

        $flashSale = FlashSale::create($request->only(['name', 'start_time', 'end_time', 'status']));

        if ($request->has('items')) {
            foreach ($request->items as $item) {
                FlashSaleItem::create([
                    'flash_sale_id' => $flashSale->id,
                    'product_id' => $item['product_id'],
                    'campaign_price' => $item['campaign_price'],
                    'campaign_stock' => $item['campaign_stock'],
                    'sold' => 0
                ]);
            }
        }

        return response()->json(['status' => 'success', 'message' => 'Created successfully']);
    }

    public function update(Request $request, $id)
    {
        $flashSale = FlashSale::findOrFail($id);
        
        $request->validate([
            'name' => 'required|string',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'status' => 'required|in:draft,active,ended',
            'items' => 'array',
        ]);

        $flashSale->update($request->only(['name', 'start_time', 'end_time', 'status']));

        FlashSaleItem::where('flash_sale_id', $id)->delete();
        if ($request->has('items')) {
            foreach ($request->items as $item) {
                FlashSaleItem::create([
                    'flash_sale_id' => $flashSale->id,
                    'product_id' => $item['product_id'],
                    'campaign_price' => $item['campaign_price'],
                    'campaign_stock' => $item['campaign_stock'],
                    'sold' => $item['sold'] ?? 0
                ]);
            }
        }

        return response()->json(['status' => 'success', 'message' => 'Updated successfully']);
    }

    public function destroy($id)
    {
        $flashSale = FlashSale::findOrFail($id);
        FlashSaleItem::where('flash_sale_id', $id)->delete();
        $flashSale->delete();
        
        return response()->json(['status' => 'success', 'message' => 'Deleted successfully']);
    }
}
