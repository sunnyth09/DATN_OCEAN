<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerTier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'min_spent',
        'discount_percent',
        'icon_url',
        'color',
        'is_active',
    ];

    protected $casts = [
        'min_spent' => 'float',
        'discount_percent' => 'float',
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'tier_id', 'id');
    }
}
