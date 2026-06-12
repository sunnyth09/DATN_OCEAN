<?php

namespace App\Services;

use App\Models\FlashSale;
use Illuminate\Support\Facades\Redis;

class FlashSaleService
{
    /**
     * Đồng bộ tồn kho lên Redis khi status: draft/ended -> active
     */
    public function syncStockToRedis(FlashSale $flashSale): void
    {
        foreach ($flashSale->items as $item) {
            $key = "flash_sale_{$flashSale->id}_product_{$item->product_id}_stock";
            $remainingStock = max(0, $item->campaign_stock - $item->sold);
            
            // Set số lượng trên Redis
            Redis::set($key, $remainingStock);
            
            // Tính TTL: set thời gian tồn tại của key bằng thời gian kết thúc campaign + 1h dự phòng
            $ttl = now()->diffInSeconds($flashSale->end_time) + 3600;
            Redis::expire($key, (int) max($ttl, 0));
        }
    }

    /**
     * Thu hồi tồn kho còn ế về MySQL khi status: active -> ended
     */
    public function revertStockFromRedis(FlashSale $flashSale): void
    {
        foreach ($flashSale->items as $item) {
            $key = "flash_sale_{$flashSale->id}_product_{$item->product_id}_stock";
            
            if (Redis::exists($key)) {
                $remainingStockOnRedis = (int) Redis::get($key);
                
                // Update số lượng thực sự đã bán được tại bảng master-detail
                $actualSold = $item->campaign_stock - $remainingStockOnRedis;
                if ($actualSold > $item->sold) {
                    $item->update(['sold' => $actualSold]);
                }
                
                // Xoá Redis Key
                Redis::del($key);
            }
        }
    }
}
