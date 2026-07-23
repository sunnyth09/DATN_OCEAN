<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $table = 'brands';
    protected $primaryKey = 'brand_id';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo_url',
        'is_active',
    ];
}
