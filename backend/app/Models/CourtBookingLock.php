<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourtBookingLock extends Model
{
    use HasFactory;

    protected $primaryKey = 'lock_id';

    protected $fillable = [
        'court_id', 'booking_date', 'start_time', 'end_time',
        'user_id', 'lock_token', 'expires_at',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'expires_at' => 'datetime',
    ];

    public function court()
    {
        return $this->belongsTo(Court::class, 'court_id', 'court_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function setBookingDateAttribute($value)
    {
        $this->attributes['booking_date'] = $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : substr((string) $value, 0, 10);
    }
}
