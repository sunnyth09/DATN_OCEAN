<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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
        'sale_price'      => 'float',
        'sale_starts_at'  => 'datetime',
        'sale_ends_at'    => 'datetime',
    ];

    /**
     * Computed attributes tự động append vào JSON response.
     */
    protected $appends = ['effective_price', 'is_on_sale', 'discount_percent'];

    // ── Accessors ────────────────────────────────────────────────────────────

    /**
     * Kiểm tra variant có đang nằm trong khung giảm giá hay không.
     */
    public function getIsOnSaleAttribute(): bool
    {
        if (!$this->sale_price || $this->sale_price <= 0) {
            return false;
        }
        
        $now = Carbon::now();

        // Nếu không có thời gian bắt đầu và kết thúc -> Sale vô thời hạn (dài hạn)
        if (!$this->sale_starts_at && !$this->sale_ends_at) {
            return true;
        }

        // Nếu chỉ có thời gian bắt đầu
        if ($this->sale_starts_at && !$this->sale_ends_at) {
            return $now->gte($this->sale_starts_at);
        }

        // Nếu chỉ có thời gian kết thúc
        if (!$this->sale_starts_at && $this->sale_ends_at) {
            return $now->lte($this->sale_ends_at);
        }

        // Nếu có cả hai
        return $now->gte($this->sale_starts_at) && $now->lte($this->sale_ends_at);
    }

    /**
     * Giá hiệu lực: trả về sale_price nếu đang sale, ngược lại trả price gốc.
     */
    public function getEffectivePriceAttribute(): float
    {
        return $this->is_on_sale ? (float) $this->sale_price : (float) $this->price;
    }

    /**
     * Phần trăm giảm giá (0 nếu không đang sale).
     */
    public function getDiscountPercentAttribute(): int
    {
        if (!$this->is_on_sale || $this->price <= 0) {
            return 0;
        }
        return (int) round(($this->price - $this->sale_price) / $this->price * 100);
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
