<?php

namespace App\Models;

use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'type',            // fixed | percent | free_ship | combo
        'value',
        'max_discount_value',
        'min_order_value',
        'usage_limit',
        'used_count',
        'user_usage_limit',
        'is_public',
        'is_first_order',
        'start_date',
        'end_date',
        'is_active',
        'auto_apply',      // true = tự động áp dụng (combo voucher, không cần nhập code)
        'min_product_qty', // Số lượng sản phẩm tối thiểu trong cart để trigger combo
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'is_first_order' => 'boolean',
        'auto_apply' => 'boolean',
        'min_product_qty' => 'integer',
        'value' => 'decimal:2',
        'max_discount_value' => 'decimal:2',
        'min_order_value' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    /**
     * Danh mục áp dụng (nếu trống = áp dụng tất cả sản phẩm)
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'coupon_categories', 'coupon_id', 'category_id');
    }

    /**
     * Sản phẩm bắt buộc phải có trong cart để trigger combo voucher (type=combo)
     */
    public function products()
    {
        return $this->belongsToMany(
            Product::class,
            'coupon_products',
            'coupon_id',
            'product_id',
            'id',
            'product_id'
        )->withPivot('min_qty')->withTimestamps();
    }

    /**
     * Danh sách user đã lưu/dùng coupon này
     */
    public function userCoupons()
    {
        return $this->hasMany(UserCoupon::class);
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    public function isCombo(): bool
    {
        return $this->type === 'combo';
    }

    public function isAutoApply(): bool
    {
        return (bool) $this->auto_apply;
    }
}
