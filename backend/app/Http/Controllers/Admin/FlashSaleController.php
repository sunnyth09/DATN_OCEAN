<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FlashSaleRequest;
use App\Models\FlashSale;
use App\Models\Product;
use App\Services\FlashSaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FlashSaleController extends Controller
{
    protected $service;

    public function __construct(FlashSaleService $service)
    {
        $this->service = $service;
    }

    public function adminIndex()
    {
        $flashSales = FlashSale::with('items.product')->latest()->get();
        $tz = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $now = now();

        $data = $flashSales->map(function ($fs) use ($tz, $now) {
            $calculatedStatus = $fs->status;
            if ($fs->status === 'active' || $fs->status === 'upcoming') {
                if ($fs->end_time && $fs->end_time->lt($now)) {
                    $calculatedStatus = 'ended';
                } elseif ($fs->start_time && $fs->start_time->gt($now)) {
                    $calculatedStatus = 'upcoming';
                } else {
                    $calculatedStatus = 'ongoing';
                }
            }

            return [
                'id' => $fs->id,
                'name' => $fs->name,
                'status' => $fs->status,
                'calculated_status' => $calculatedStatus,
                'start_time' => $fs->start_time ? $fs->start_time->toISOString() : null,
                'end_time' => $fs->end_time ? $fs->end_time->toISOString() : null,
                'start_time_formatted' => $fs->start_time ? $fs->start_time->timezone($tz)->format('d/m/Y H:i') : '',
                'end_time_formatted' => $fs->end_time ? $fs->end_time->timezone($tz)->format('d/m/Y H:i') : '',
                'start_time_local' => $fs->start_time ? $fs->start_time->timezone($tz)->format('Y-m-d\TH:i') : '',
                'end_time_local' => $fs->end_time ? $fs->end_time->timezone($tz)->format('Y-m-d\TH:i') : '',
                'items' => $fs->items->map(function ($item) {
                    $product = $item->product;
                    $basePrice = $product ? (float) ($product->min_price ?? 0) : 0;
                    $discountPct = $basePrice > 0 ? round((($basePrice - $item->campaign_price) / $basePrice) * 100) : 0;

                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'campaign_price' => (float) $item->campaign_price,
                        'campaign_stock' => (int) $item->campaign_stock,
                        'sold' => (int) $item->sold,
                        'discount_percent' => max(0, $discountPct),
                        'product' => $product ? [
                            'product_id' => $product->product_id,
                            'name' => $product->name,
                            'thumbnail' => $product->thumbnail_url ?: ($product->mainImage?->image_url ?: ($product->images?->first()?->image_url ?: null)),
                            'base_price' => $basePrice,
                        ] : null,
                    ];
                }),
                'created_at' => $fs->created_at ? $fs->created_at->toISOString() : null,
            ];
        });

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function searchProducts(Request $request)
    {
        $query = trim($request->input('query', ''));
        $categoryId = $request->input('category_id');
        $limit = (int) $request->input('limit', 60);
        $limit = max(1, min($limit, 100));

        $q = Product::where('status', 'active')
            ->with(['category', 'variants', 'mainImage', 'images']);

        if ($query !== '') {
            $q->where(function ($sub) use ($query) {
                $sub->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('slug', 'LIKE', "%{$query}%")
                    ->orWhere('sku', 'LIKE', "%{$query}%")
                    ->orWhere('product_id', $query);
            });
        }

        if (! empty($categoryId) && $categoryId !== 'all') {
            $q->where('category_id', $categoryId);
        }

        $products = $q->latest()->limit($limit)->get();

        $results = $products->map(function ($product) {
            $totalStock = (int) $product->variants->sum('stock');

            return [
                'product_id' => $product->product_id,
                'name' => $product->name,
                'sku' => $product->sku,
                'category_name' => $product->category?->name ?? '',
                'category_id' => $product->category_id,
                'thumbnail' => $product->thumbnail_url ?: ($product->mainImage?->image_url ?: ($product->images?->first()?->image_url ?: null)),
                'base_price' => (float) ($product->min_price ?? 0),
                'stock' => $totalStock,
            ];
        });

        return response()->json(['status' => 'success', 'data' => $results]);
    }

    public function store(FlashSaleRequest $request)
    {
        DB::beginTransaction();
        try {
            $flashSale = FlashSale::create($request->only('name', 'start_time', 'end_time', 'status'));

            foreach ($request->items as $item) {
                $flashSale->items()->create([
                    'product_id' => $item['product_id'],
                    'campaign_price' => $item['campaign_price'],
                    'campaign_stock' => (int) $item['campaign_stock'],
                    'sold' => 0,
                ]);
            }

            if ($flashSale->status === 'active' || $flashSale->status === 'upcoming') {
                $this->service->syncStockToRedis($flashSale);
            }

            Cache::forget('flash_sale_public_list');
            Cache::forget('flash_sale_public_list_all');
            Cache::forget('flash_sale_public_list_ongoing');
            Cache::forget('flash_sale_public_list_upcoming');
            Cache::forget('flash_sale_public_list_active');
            Cache::forget("flash_sale_meta_{$flashSale->id}");

            DB::commit();

            return response()->json(['status' => 'success', 'message' => 'Tạo Flash Sale thành công!']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Flash Sale store failed: '.$e->getMessage());

            return response()->json(['status' => 'error', 'message' => 'Không thể tạo Flash Sale, vui lòng thử lại.'], 500);
        }
    }

    public function update(FlashSaleRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $flashSale = FlashSale::with('items')->findOrFail($id);
            $oldStatus = $flashSale->status;

            // Lưu lại số lượng đã bán thực tế của các sản phẩm cũ
            $existingSoldMap = $flashSale->items->pluck('sold', 'product_id')->toArray();

            // Cập nhật record tổng
            $flashSale->update($request->only('name', 'start_time', 'end_time', 'status'));

            // Xoá và tạo lại Items nhưng bảo toàn số lượng sold thực tế
            $flashSale->items()->delete();
            foreach ($request->items as $item) {
                $prevSold = $existingSoldMap[$item['product_id']] ?? (isset($item['sold']) ? (int) $item['sold'] : 0);
                $campaignStock = (int) $item['campaign_stock'];
                $sold = min($prevSold, $campaignStock);

                $flashSale->items()->create([
                    'product_id' => $item['product_id'],
                    'campaign_price' => $item['campaign_price'],
                    'campaign_stock' => $campaignStock,
                    'sold' => $sold,
                ]);
            }

            $flashSale->load('items'); // Load lại relationship

            // Luôn đồng bộ Redis khi chiến dịch đang Active hoặc Upcoming
            if ($flashSale->status === 'active' || $flashSale->status === 'upcoming') {
                $this->service->syncStockToRedis($flashSale);
            } elseif ($flashSale->status === 'ended') {
                $this->service->revertStockFromRedis($flashSale);
            }

            Cache::forget('flash_sale_public_list');
            Cache::forget('flash_sale_public_list_all');
            Cache::forget('flash_sale_public_list_ongoing');
            Cache::forget('flash_sale_public_list_upcoming');
            Cache::forget('flash_sale_public_list_active');
            Cache::forget("flash_sale_meta_{$flashSale->id}");

            DB::commit();

            return response()->json(['status' => 'success', 'message' => 'Cập nhật thành công!']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Flash Sale update failed: '.$e->getMessage());

            return response()->json(['status' => 'error', 'message' => 'Không thể cập nhật Flash Sale, vui lòng thử lại.'], 500);
        }
    }

    public function destroy($id)
    {
        $flashSale = FlashSale::findOrFail($id);
        if ($flashSale->status === 'active') {
            $this->service->revertStockFromRedis($flashSale); // Thu hồi trên redis nếu lỡ xóa khi đang active
        }
        $flashSale->delete();

        Cache::forget('flash_sale_public_list');
        Cache::forget('flash_sale_public_list_all');
        Cache::forget('flash_sale_public_list_ongoing');
        Cache::forget('flash_sale_public_list_upcoming');
        Cache::forget('flash_sale_public_list_active');
        Cache::forget("flash_sale_meta_{$id}");

        return response()->json(['status' => 'success', 'message' => 'Đã xóa Flash Sale!']);
    }

    /**
     * Nạp thủ công (hoặc do job)
     */
    public function initialize($id)
    {
        $flashSale = FlashSale::with('items')->findOrFail($id);
        if ($flashSale->status === 'active') {
            $this->service->syncStockToRedis($flashSale);

            return response()->json(['status' => 'success', 'message' => 'Đã nạp lên Redis thành công!']);
        }

        return response()->json(['status' => 'error', 'message' => 'Flash Sale chưa active!'], 400);
    }
}
