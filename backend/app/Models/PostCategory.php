<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostCategory extends Model
{
    protected $table = 'post_categories';

    protected $primaryKey = 'post_category_id';

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'thumbnail_url',
        'sort_order',
        'is_active',
    ];

    public function posts()
    {
        return $this->hasMany(Post::class, 'post_category_id', 'post_category_id');
    }

    public function parent()
    {
        return $this->belongsTo(PostCategory::class, 'parent_id', 'post_category_id');
    }

    public function children()
    {
        return $this->hasMany(PostCategory::class, 'parent_id', 'post_category_id');
    }
}
