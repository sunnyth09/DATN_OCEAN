<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourtSchedule extends Model
{
    use HasFactory;

    protected $primaryKey = 'schedule_id';

    protected $fillable = [
        'court_id', 'day_of_week', 'open_time', 'close_time', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function court()
    {
        return $this->belongsTo(Court::class, 'court_id', 'court_id');
    }
}
