<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $table = 'product_variants';

    protected $primaryKey = 'variant_id';

    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'variant_name',
        'color',
        'size',
        'material',
        'weight_gram',
        'cost_price',
        'price',
        'compare_at_price',
        'sale_price',
        'sale_starts_at',
        'sale_ends_at',
        'stock',
        'reserved_stock',
        'safety_stock',
        'image_url',
        'status',
    ];

    protected $casts = [
        'attributes_json' => 'array',
        'sale_price' => 'float',
        'sale_starts_at' => 'datetime',
        'sale_ends_at' => 'datetime',
    ];

    /**
     * Computed attributes tự động append vào JSON response.
     */
    protected $appends = ['effective_price', 'is_on_sale', 'discount_percent', 'original_price'];

    /**
     * Cache tĩnh active Flash Sale theo request để tối ưu tốc độ.
     */
    protected static ?array $activeFlashSaleCache = null;

    public static function clearFlashSaleCache(): void
    {
        self::$activeFlashSaleCache = null;
    }

    protected static function getActiveFlashSaleCampaignPrice(int $productId): ?float
    {
        if (self::$activeFlashSaleCache === null) {
            try {
                $now = now();
                $items = FlashSaleItem::whereHas('flashSale', function ($q) use ($now) {
                    $q->where('status', 'active')
                        ->where('start_time', '<=', $now)
                        ->where('end_time', '>=', $now);
                })->get();

                self::$activeFlashSaleCache = [];
                foreach ($items as $item) {
                    $stockKey = "flash_sale_{$item->flash_sale_id}_product_{$item->product_id}_stock";
                    $remaining = \Illuminate\Support\Facades\Redis::exists($stockKey)
                        ? (int) \Illuminate\Support\Facades\Redis::get($stockKey)
                        : ($item->campaign_stock - $item->sold);

                    if ($remaining > 0) {
                        self::$activeFlashSaleCache[$item->product_id] = (float) $item->campaign_price;
                    }
                }
            } catch (\Throwable $e) {
                self::$activeFlashSaleCache = [];
            }
        }

        return isset(self::$activeFlashSaleCache[$productId])
            ? (float) self::$activeFlashSaleCache[$productId]
            : null;
    }

    // ── Accessors ────────────────────────────────────────────────────────────

    /**
     * Kiểm tra variant có đang nằm trong khung giảm giá hoặc Flash Sale active hay không.
     */
    public function getIsOnSaleAttribute(): bool
    {
        if ($this->sale_price && $this->sale_price > 0) {
            $now = Carbon::now();

            // Nếu không có thời gian bắt đầu và kết thúc -> Sale vô thời hạn (dài hạn)
            if (! $this->sale_starts_at && ! $this->sale_ends_at) {
                return true;
            }

            // Nếu chỉ có thời gian bắt đầu
            if ($this->sale_starts_at && ! $this->sale_ends_at) {
                return $now->gte($this->sale_starts_at);
            }

            // Nếu chỉ có thời gian kết thúc
            if (! $this->sale_starts_at && $this->sale_ends_at) {
                return $now->lte($this->sale_ends_at);
            }

            // Nếu có cả hai
            if ($now->gte($this->sale_starts_at) && $now->lte($this->sale_ends_at)) {
                return true;
            }
        }

        // Kiểm tra Flash Sale active
        if ($this->product_id) {
            $fsPrice = self::getActiveFlashSaleCampaignPrice((int) $this->product_id);
            if ($fsPrice !== null && $fsPrice > 0 && ($this->price <= 0 || $fsPrice < $this->price)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Giá hiệu lực: trả về giá Flash Sale (nếu có), hoặc sale_price nếu đang sale, ngược lại trả price gốc.
     */
    public function getEffectivePriceAttribute(): float
    {
        // 1. Kiểm tra Flash Sale active trước (ưu tiên giá sốc Flash Sale)
        if ($this->product_id) {
            $fsPrice = self::getActiveFlashSaleCampaignPrice((int) $this->product_id);
            if ($fsPrice !== null && $fsPrice > 0 && ($this->price <= 0 || $fsPrice < $this->price)) {
                return (float) $fsPrice;
            }
        }

        // 2. Kiểm tra sale_price thông thường
        if ($this->sale_price && $this->sale_price > 0) {
            $now = Carbon::now();
            $isSale = false;
            if (! $this->sale_starts_at && ! $this->sale_ends_at) {
                $isSale = true;
            } elseif ($this->sale_starts_at && ! $this->sale_ends_at) {
                $isSale = $now->gte($this->sale_starts_at);
            } elseif (! $this->sale_starts_at && $this->sale_ends_at) {
                $isSale = $now->lte($this->sale_ends_at);
            } elseif ($now->gte($this->sale_starts_at) && $now->lte($this->sale_ends_at)) {
                $isSale = true;
            }

            if ($isSale) {
                return (float) $this->sale_price;
            }
        }

        return (float) $this->price;
    }

    /**
     * Phần trăm giảm giá (0 nếu không đang sale).
     */
    public function getDiscountPercentAttribute(): int
    {
        $effectivePrice = $this->effective_price;
        if ($this->price <= 0 || $effectivePrice >= $this->price) {
            return 0;
        }

        return (int) round(($this->price - $effectivePrice) / $this->price * 100);
    }

    /**
     * Giá gốc (gạch ngang) nếu variant có giảm giá hoặc compare_at_price > effective_price.
     */
    public function getOriginalPriceAttribute(): ?float
    {
        if ($this->compare_at_price && (float) $this->compare_at_price > $this->effective_price) {
            return (float) $this->compare_at_price;
        }
        if ($this->effective_price < (float) $this->price) {
            return (float) $this->price;
        }

        return null;
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'variant_id');
    }
}
