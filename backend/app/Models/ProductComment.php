<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductComment extends Model
{
    protected $table = 'product_comments';

    protected $primaryKey = 'comment_id';

    protected $fillable = [
        'product_id',
        'user_id',
        'commenter_type',
        'order_item_id',
        'rating',
        'content',
        'images',
        'is_approved',
    ];

    protected $casts = [
        'images' => 'json',
    ];

    /**
     * Relationship gốc: luôn trỏ vào bảng users.
     * Giữ lại để backward-compatible với code cũ (getByProduct, v.v.).
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Relationship mới: trả đúng model dựa theo commenter_type.
     * - commenter_type = 'user'  → load từ bảng users (user_id)
     * - commenter_type = 'admin' → load từ bảng admins (admin_id)
     */
    public function commenter()
    {
        if ($this->commenter_type === 'admin') {
            return $this->belongsTo(Admin::class, 'user_id', 'admin_id');
        }

        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }
}
