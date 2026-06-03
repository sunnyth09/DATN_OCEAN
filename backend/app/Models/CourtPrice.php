<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourtPrice extends Model
{
    use HasFactory;

    protected $primaryKey = 'price_id';

    protected $fillable = [
        'court_id', 'price_name', 'day_type', 'from_time', 'to_time',
        'price_per_hour', 'is_active', 'effective_from', 'effective_to',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];

    public function court()
    {
        return $this->belongsTo(Court::class, 'court_id', 'court_id');
    }
}
