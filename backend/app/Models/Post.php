<?php

namespace App\Models;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'posts';

    protected $primaryKey = 'post_id';

    protected $fillable = [
        'post_category_id',
        'author_id',
        'title',
        'slug',
        'summary',
        'content',
        'thumbnail_url',
        'banner_url',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'post_type',
        'status',
        'is_featured',
        'view_count',
        'published_at',
    ];

    public function category()
    {
        return $this->belongsTo(PostCategory::class, 'post_category_id', 'post_category_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id', 'user_id');
    }

    public function getThumbnailUrlAttribute($value)
    {
        if ($value && ! str_starts_with($value, 'http')) {
            return asset($value);
        }

        return $value;
    }

    public function getBannerUrlAttribute($value)
    {
        if ($value && ! str_starts_with($value, 'http')) {
            return asset($value);
        }

        return $value;
    }
}
