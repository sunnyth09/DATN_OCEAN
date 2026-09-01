<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Court extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'court_id';

    protected $fillable = [
        'court_name', 'slug', 'court_code', 'type', 'description',
        'surface', 'max_players', 'status', 'image_url', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'max_players' => 'integer',
    ];

    public function bookings()
    {
        return $this->hasMany(CourtBooking::class, 'court_id', 'court_id');
    }

    public function prices()
    {
        return $this->hasMany(CourtPrice::class, 'court_id', 'court_id');
    }

    public function schedules()
    {
        return $this->hasMany(CourtSchedule::class, 'court_id', 'court_id');
    }

    public function maintenances()
    {
        return $this->hasMany(CourtMaintenance::class, 'court_id', 'court_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAvailable($query, $date, $startTime, $endTime)
    {
        return $query->active()
            ->whereDoesntHave('bookings', function ($q) use ($date, $startTime, $endTime) {
                $q->where('booking_date', $date)
                    ->whereIn('status', CourtBooking::BLOCKING_STATUSES)
                    ->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime);
            })
            ->whereDoesntHave('maintenances', function ($q) use ($date, $startTime, $endTime) {
                $q->whereIn('status', ['scheduled', 'in_progress'])
                    ->where('start_datetime', '<', "$date $endTime")
                    ->where('end_datetime', '>', "$date $startTime");
            });
    }
}
