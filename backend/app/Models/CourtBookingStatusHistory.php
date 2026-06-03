<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourtBookingStatusHistory extends Model
{
    use HasFactory;

    protected $primaryKey = 'history_id';
    
    public $timestamps = false; // Only created_at is used in migration

    protected $fillable = [
        'booking_id', 'old_status', 'new_status', 'note',
        'actor_type', 'actor_id', 'meta', 'created_at',
    ];

    protected $casts = [
        'meta' => 'json',
        'created_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(CourtBooking::class, 'booking_id', 'booking_id');
    }
}
