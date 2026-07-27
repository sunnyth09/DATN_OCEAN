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

        return response()->json(['status' => 'success', 'data' => $flashSales]);
    }

    public function searchProducts(Request $request)
    {
        $query = $request->input('query', '');

        if (strlen($query) < 2) {
            return response()->json(['status' => 'success', 'data' => []]);
        }

        $products = Product::where('status', 'active')
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('slug', 'LIKE', "%{$query}%");
            })
            ->limit(20)
            ->get();

        $results = $products->map(function ($product) {
            // Load variants to sum stock
            $product->load('variants');

            return [
                'product_id' => $product->product_id,
                'name' => $product->name,
                'thumbnail' => $product->thumbnail_url,
                'base_price' => $product->min_price ?? 0,
                'stock' => $product->variants->sum('stock'),
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
                    'campaign_stock' => $item['campaign_stock'],
                    'sold' => 0,
                ]);
            }

            if ($flashSale->status === 'active') {
                $this->service->syncStockToRedis($flashSale);
            }

            Cache::forget('flash_sale_public_list');
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

            // Cập nhật record tổng
            $flashSale->update($request->only('name', 'start_time', 'end_time', 'status'));

            // Xoá và thay thế toàn bộ Items (cho đơn giản vì form inline, hoặc bạn có thể update firstOrCreate)
            $flashSale->items()->delete();
            foreach ($request->items as $item) {
                $flashSale->items()->create([
                    'product_id' => $item['product_id'],
                    'campaign_price' => $item['campaign_price'],
                    'campaign_stock' => $item['campaign_stock'],
                    'sold' => isset($item['sold']) ? $item['sold'] : 0,
                ]);
            }

            $flashSale->load('items'); // Load lại relationship

            // Xử lý Redis trạng thái state machine
            if ($oldStatus === 'draft' && $flashSale->status === 'active') {
                $this->service->syncStockToRedis($flashSale);
            } elseif ($oldStatus === 'active' && $flashSale->status === 'ended') {
                $this->service->revertStockFromRedis($flashSale);
            }

            Cache::forget('flash_sale_public_list');
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
