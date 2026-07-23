<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reward extends Model
{
    protected $fillable = [
        'name',
        'description',
        'points_required',
        'type',
        'image',
    ];

    protected $casts = [
        'points_required' => 'integer',
    ];
}
