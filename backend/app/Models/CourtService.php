<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourtService extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'service_id';

    protected $fillable = [
        'service_name', 'service_code', 'unit', 'unit_price',
        'description', 'image_url', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
