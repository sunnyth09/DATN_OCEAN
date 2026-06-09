<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourtBookingExtension extends Model
{
    use HasFactory;

    protected $primaryKey = 'extension_id';

    protected $fillable = [
        'booking_id', 'original_end_time', 'extended_end_time',
        'extension_minutes', 'extra_amount', 'approved_by',
    ];

    public function booking()
    {
        return $this->belongsTo(CourtBooking::class, 'booking_id', 'booking_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(Admin::class, 'approved_by', 'admin_id');
    }
}
