<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Builder
 */
class UserCoupon extends Model
{
    use HasFactory;

    // Chỉ định rõ tên bảng (đề phòng Laravel không map đúng số nhiều)
    protected $table = 'user_coupons';

    // Cho phép Cập nhật, Thêm dữ liệu vào các cột này
    protected $fillable = [
        'user_id',
        'coupon_id',
        'used_count',
        'is_saved',
    ];

    /**
     * Lấy thông tin User đã lưu mã
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Lấy chi tiết thông tin Mã giảm giá
     */
    public function coupon()
    {
        return $this->belongsTo(Coupon::class, 'coupon_id', 'id');
    }
}
