<?php

namespace App\Models;
use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use Searchable;
    use SoftDeletes;
    protected $table = 'products';
    protected $primaryKey = 'product_id';
    protected $fillable = [
        'category_id',
        'brand_id',
        'seller_id',
        'name',
        'slug',
        'short_description',
        'description',
        'thumbnail_url',
        'product_type',
        'status',
        'is_featured',
        'min_price',
        'max_price',
        'rating_avg',
        'rating_count',
        'view_count',
        'sold_count',
        'weight',
        'published_at',
        'deleted_at',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'category_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id', 'brand_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id', 'user_id');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class, 'product_id', 'product_id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id', 'product_id');
    }

    public function comments()
    {
        return $this->hasMany(ProductComment::class, 'product_id', 'product_id');
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class, 'product_id', 'product_id');
    }
    public function mainImage()
    {
        return $this->hasOne(ProductImage::class, 'product_id')->where('is_main', 1);
    }
    public function lowestPriceVariant()
    {
        return $this->hasOne(ProductVariant::class, 'product_id')->orderBy('price', 'asc');
    }

    // ─── Scout / Meilisearch ───────────────────────────────────────────────

    /**
     * Đặt tên index trong Meilisearch
     */
    public function searchableAs(): string
    {
        return 'products';
    }

    /**
     * Dữ liệu được đưa vào Meilisearch index
     */
    public function toSearchableArray(): array
    {
        return [
            'id'                => $this->product_id,
            'product_id'        => $this->product_id,
            'name'              => $this->name,
            'slug'              => $this->slug,
            'short_description' => $this->short_description ?? '',
            'category_id'       => $this->category_id,
            'brand_id'          => $this->brand_id,
            'status'            => $this->status,
            'is_featured'       => (bool) $this->is_featured,
            'min_price'         => (float) ($this->min_price ?? 0),
            'max_price'         => (float) ($this->max_price ?? 0),
            'thumbnail_url'     => $this->thumbnail_url ?? '',
            'rating_avg'        => (float) ($this->rating_avg ?? 0),
            'sold_count'        => (int) ($this->sold_count ?? 0),
        ];
    }

    /**
     * Chỉ index sản phẩm đang active (không index draft/inactive)
     */
    public function shouldBeSearchable(): bool
    {
        return $this->status === 'active';
    }
}
