<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SizeGuide extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'table_headers',
        'table_rows',
        'tips',
    ];

    protected $casts = [
        'table_headers' => 'array',
        'table_rows' => 'array',
        'tips' => 'array',
    ];

    public function categories()
    {
        return $this->hasMany(Category::class, 'size_guide_id', 'id');
    }
}
