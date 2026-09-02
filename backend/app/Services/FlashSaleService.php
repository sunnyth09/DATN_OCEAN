<?php

namespace App\Services;

use App\Jobs\OrderProcessingJob;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class FlashSaleService
{
    private const MAX_PER_USER = 1;

    /**
     * Danh sách item Flash Sale phục vụ trang public (cache 5s cho đếm ngược).
     * Chỉ lấy các chiến dịch status = 'active', phân tách rõ ràng ongoing và upcoming.
     */
    public function getPublicList(?string $filter = null): array
    {
        $now = now();
        $cacheKey = 'flash_sale_public_list_'.($filter ?: 'all');

        return Cache::remember($cacheKey, 5, function () use ($now, $filter) {
            $campaigns = FlashSale::where('status', 'active')
                ->where('end_time', '>', $now)
                ->with(['items.product.category', 'items.product.mainImage', 'items.product.images'])
                ->orderBy('start_time', 'asc')
                ->get();

            $formatted = [];
            foreach ($campaigns as $fs) {
                $isOngoing = $fs->start_time->lte($now) && $fs->end_time->gte($now);
                $isUpcoming = $fs->start_time->gt($now);

                foreach ($fs->items as $item) {
                    $product = $item->product;
                    if (! $product || $product->status !== 'active') {
                        continue;
                    }

                    $originalPrice = (float) ($product->min_price ?? 0);
                    $discountPct = $originalPrice > 0 ? round((($originalPrice - $item->campaign_price) / $originalPrice) * 100) : 0;

                    $dbSold = (int) ($item->sold ?? 0);
                    $totalStock = (int) $item->campaign_stock;
                    $maxRemaining = max(0, $totalStock - $dbSold);

                    $stockKey = "flash_sale_{$fs->id}_product_{$item->product_id}_stock";
                    $redisStock = Redis::get($stockKey);

                    // Tự động phục hồi Redis nếu chưa có key hoặc bị lệch giá trị
                    if ($redisStock === null || (int) $redisStock > $maxRemaining || ((int) $redisStock === 0 && $dbSold < $totalStock && $fs->status === 'active')) {
                        $remaining = $maxRemaining;
                        if ($fs->status === 'active') {
                            $ttl = max(60, now()->diffInSeconds($fs->end_time) + 3600);
                            Redis::set($stockKey, $remaining);
                            Redis::expire($stockKey, (int) $ttl);
                        }
                    } else {
                        $remaining = max(0, min((int) $redisStock, $maxRemaining));
                    }

                    $soldCount = max($dbSold, $totalStock - $remaining);
                    $thumbnailUrl = $product->thumbnail_url ?: ($product->mainImage?->image_url ?: ($product->images?->first()?->image_url ?: null));

                    $formatted[] = [
                        'id' => $fs->id,
                        'flash_sale_id' => $fs->id,
                        'item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'title' => $fs->name,
                        'name' => $product->name ?? 'Sản phẩm Flash Sale',
                        'product_name' => $product->name ?? 'Sản phẩm Flash Sale',
                        'slug' => $product->slug ?? null,
                        'product_thumbnail' => $thumbnailUrl,
                        'thumbnail_url' => $thumbnailUrl,
                        'image_url' => $thumbnailUrl,
                        'image' => $thumbnailUrl,
                        'sale_price' => (float) $item->campaign_price,
                        'flash_price' => (float) $item->campaign_price,
                        'min_price' => (float) $item->campaign_price,
                        'original_price' => $originalPrice,
                        'discount_percent' => max(0, $discountPct),
                        'total_stock' => $totalStock,
                        'total_quantity' => $totalStock,
                        'sold' => $soldCount,
                        'sold_count' => $soldCount,
                        'remaining' => max(0, $remaining),
                        'is_sold_out' => $remaining <= 0,
                        'max_per_user' => self::MAX_PER_USER,
                        'starts_at' => $fs->start_time->toISOString(),
                        'ends_at' => $fs->end_time->toISOString(),
                        'start_time' => $fs->start_time->toISOString(),
                        'end_time' => $fs->end_time->toISOString(),
                        'status' => $fs->status,
                        'is_ongoing' => $isOngoing,
                        'is_upcoming' => $isUpcoming,
                        'category_name' => $product->category->name ?? '',
                        'server_time' => $now->toISOString(),
                    ];
                }
            }

            if ($filter === 'active' || $filter === 'ongoing') {
                $formatted = array_filter($formatted, fn ($i) => $i['is_ongoing']);
            } elseif ($filter === 'upcoming') {
                $formatted = array_filter($formatted, fn ($i) => $i['is_upcoming']);
            }

            return array_values($formatted);
        });
    }

    /**
     * Lấy thông tin Flash Sale đang active của 1 sản phẩm cụ thể
     */
    public function getActiveForProduct(int $productId): ?array
    {
        $now = now();
        $item = FlashSaleItem::where('product_id', $productId)
            ->whereHas('flashSale', function ($q) use ($now) {
                $q->where('status', 'active')
                    ->where('start_time', '<=', $now)
                    ->where('end_time', '>=', $now);
            })
            ->with('flashSale')
            ->first();

        if (! $item || ! $item->flashSale) {
            return null;
        }

        $fs = $item->flashSale;
        $dbSold = (int) ($item->sold ?? 0);
        $totalStock = (int) $item->campaign_stock;
        $maxRemaining = max(0, $totalStock - $dbSold);

        $stockKey = "flash_sale_{$fs->id}_product_{$item->product_id}_stock";
        $redisStock = Redis::get($stockKey);

        if ($redisStock === null || (int) $redisStock > $maxRemaining || ((int) $redisStock === 0 && $dbSold < $totalStock && $fs->status === 'active')) {
            $remaining = $maxRemaining;
            if ($fs->status === 'active') {
                $ttl = max(60, now()->diffInSeconds($fs->end_time) + 3600);
                Redis::set($stockKey, $remaining);
                Redis::expire($stockKey, (int) $ttl);
            }
        } else {
            $remaining = max(0, min((int) $redisStock, $maxRemaining));
        }

        $soldCount = max($dbSold, $totalStock - $remaining);

        return [
            'flash_sale_id' => $fs->id,
            'item_id' => $item->id,
            'title' => $fs->name,
            'campaign_price' => (float) $item->campaign_price,
            'flash_price' => (float) $item->campaign_price,
            'total_stock' => $totalStock,
            'sold' => $soldCount,
            'sold_count' => $soldCount,
            'remaining' => max(0, $remaining),
            'is_sold_out' => $remaining <= 0,
            'max_per_user' => self::MAX_PER_USER,
            'starts_at' => $fs->start_time->toISOString(),
            'ends_at' => $fs->end_time->toISOString(),
            'start_time' => $fs->start_time->toISOString(),
            'end_time' => $fs->end_time->toISOString(),
            'server_time' => $now->toISOString(),
        ];
    }

    /**
     * Tồn kho hiện tại của 1 item (đọc Redis, fallback MySQL).
     *
     * @return array{state: string, data?: array}
     *                                            state: ok | sale_not_found | item_not_found
     */
    public function getStock(int $flashSaleId, $productId): array
    {
        $flashSale = Cache::remember("flash_sale_meta_{$flashSaleId}", 30, fn () => FlashSale::find($flashSaleId));
        if (! $flashSale) {
            return ['state' => 'sale_not_found'];
        }

        $itemQuery = FlashSaleItem::where('flash_sale_id', $flashSaleId);
        if ($productId) {
            $itemQuery->where('product_id', $productId);
        }
        $item = $itemQuery->first();

        if (! $item) {
            return ['state' => 'item_not_found'];
        }

        $dbSold = (int) ($item->sold ?? 0);
        $totalStock = (int) $item->campaign_stock;
        $maxRemaining = max(0, $totalStock - $dbSold);

        $stockKey = "flash_sale_{$flashSaleId}_product_{$item->product_id}_stock";
        $redisStock = Redis::get($stockKey);

        if ($redisStock === null || (int) $redisStock > $maxRemaining || ((int) $redisStock === 0 && $dbSold < $totalStock && $flashSale->status === 'active')) {
            $remaining = $maxRemaining;
            if ($flashSale->status === 'active') {
                $ttl = max(60, now()->diffInSeconds($flashSale->end_time) + 3600);
                Redis::set($stockKey, $remaining);
                Redis::expire($stockKey, (int) $ttl);
            }
        } else {
            $remaining = max(0, min((int) $redisStock, $maxRemaining));
        }

        $soldCount = max($dbSold, $totalStock - $remaining);

        return [
            'state' => 'ok',
            'data' => [
                'flash_sale_id' => $flashSaleId,
                'product_id' => $item->product_id,
                'total_stock' => $totalStock,
                'remaining' => $remaining,
                'sold_count' => $soldCount,
                'is_sold_out' => $remaining <= 0,
            ],
        ];
    }

    /**
     * Mua Flash Sale (high-concurrency): reserve suất theo user + trừ tồn kho
     * ATOMIC trên Redis (incrby/decrby rồi mới kiểm tra), sau đó đẩy tạo đơn qua Queue.
     *
     * @return array{state: string, message?: string, order_code?: string, remaining?: int}
     *                                                                                      state: ok | inactive | item_not_found | over_limit | sold_out
     */
    public function buy($user, int $userId, int $flashSaleId, int $productId, int $quantity, array $orderInfo): array
    {
        $flashSale = Cache::remember("flash_sale_meta_{$flashSaleId}", 10, fn () => FlashSale::find($flashSaleId));

        if (! $flashSale || $flashSale->status !== 'active' || now()->lt($flashSale->start_time) || now()->gt($flashSale->end_time)) {
            return ['state' => 'inactive', 'message' => 'Flash Sale không hoạt động.'];
        }

        $item = FlashSaleItem::where('flash_sale_id', $flashSaleId)->where('product_id', $productId)->first();
        if (! $item) {
            return ['state' => 'item_not_found', 'message' => 'Sản phẩm không có trong Flash Sale.'];
        }

        $ttl = max(60, now()->diffInSeconds($flashSale->end_time));

        // Reserve suất mua theo user ATOMIC: incrby trước rồi kiểm tra (tránh TOCTOU).
        $userPurchaseKey = "flash_sale_{$flashSaleId}_user_{$userId}_prod_{$productId}";
        $userBought = Redis::incrby($userPurchaseKey, $quantity);
        Redis::expire($userPurchaseKey, $ttl);

        if ($userBought > self::MAX_PER_USER) {
            Redis::decrby($userPurchaseKey, $quantity);

            return ['state' => 'over_limit', 'message' => 'Mỗi khách hàng chỉ được mua '.self::MAX_PER_USER.' sản phẩm này.'];
        }

        $stockKey = "flash_sale_{$flashSaleId}_product_{$productId}_stock";

        // Initialize stock in Redis if it doesn't exist (e.g., Redis restart, expiration, or missing sync)
        if (! Redis::exists($stockKey)) {
            $remainingStock = max(0, $item->campaign_stock - $item->sold);
            Redis::set($stockKey, $remainingStock);
            Redis::expire($stockKey, $ttl);
        }

        $remaining = Redis::decrby($stockKey, $quantity);

        if ($remaining < 0) {
            Redis::incrby($stockKey, $quantity);
            Redis::decrby($userPurchaseKey, $quantity);

            return ['state' => 'sold_out', 'message' => 'Rất tiếc! Sản phẩm đã hết hàng.'];
        }

        $orderCode = 'FS-'.strtoupper(uniqid());
        $defaultAddress = $user->addresses()->where('is_default', true)->first() ?? $user->addresses()->first();
        $addressId = $defaultAddress ? $defaultAddress->address_id : null;

        $shippingAddress = $orderInfo['shipping_address'] ?? 'Địa chỉ mặc định';
        if ($defaultAddress && $shippingAddress === 'Địa chỉ mặc định') {
            $shippingAddress = implode(', ', array_filter([
                $defaultAddress->address_line,
                $defaultAddress->ward,
                $defaultAddress->district,
                $defaultAddress->province,
            ]));
        }

        $variantId = ! empty($orderInfo['variant_id']) ? (int) $orderInfo['variant_id'] : null;

        if (app()->environment('local') || config('queue.default') === 'sync') {
            OrderProcessingJob::dispatchSync(
                $flashSaleId,
                $productId,
                $userId,
                $quantity,
                $addressId,
                $orderInfo['recipient_name'],
                $orderInfo['recipient_phone'],
                $shippingAddress,
                $orderInfo['payment_method'] ?? 'cod',
                $orderCode,
                $variantId
            );
        } else {
            OrderProcessingJob::dispatch(
                $flashSaleId,
                $productId,
                $userId,
                $quantity,
                $addressId,
                $orderInfo['recipient_name'],
                $orderInfo['recipient_phone'],
                $shippingAddress,
                $orderInfo['payment_method'] ?? 'cod',
                $orderCode,
                $variantId
            );
        }

        return [
            'state' => 'ok',
            'order_code' => $orderCode,
            'remaining' => (int) $remaining,
        ];
    }

    /**
     * Đồng bộ tồn kho lên Redis khi status: draft/ended -> active
     */
    public function syncStockToRedis(FlashSale $flashSale): void
    {
        foreach ($flashSale->items as $item) {
            $key = "flash_sale_{$flashSale->id}_product_{$item->product_id}_stock";
            $remainingStock = max(0, (int) $item->campaign_stock - (int) $item->sold);

            // Set số lượng trên Redis
            Redis::set($key, $remainingStock);

            // Tính TTL: set thời gian tồn tại của key bằng thời gian kết thúc campaign + 1h dự phòng
            $ttl = max(60, now()->diffInSeconds($flashSale->end_time) + 3600);
            Redis::expire($key, (int) $ttl);
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
