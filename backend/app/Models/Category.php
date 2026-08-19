<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'categories';

    protected $primaryKey = 'category_id';

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'image',
        'description',
        'sort_order',
        'is_active',
        'size_guide_id',
    ];

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id', 'category_id');
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id', 'category_id');
    }

    public function sizeGuide()
    {
        return $this->belongsTo(SizeGuide::class, 'size_guide_id', 'id');
    }
}
