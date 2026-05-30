<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $table = 'attendances';

    protected $fillable = [
        'user_id',
        'user_type',
        'work_location_id',
        'work_shift_id',
        'work_date',
        'check_in_at',
        'check_out_at',
        'ip_address',
        'latitude',
        'longitude',
        'check_in_accuracy',
        'check_in_distance_meters',
        'check_out_latitude',
        'check_out_longitude',
        'check_out_accuracy',
        'check_out_distance_meters',
        'wifi_ssid',
        'wifi_bssid',
        'image_path',
        'check_out_image_path',
        'status',
        'is_flagged',
        'flag_note',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'work_date'                => 'date',
            'check_in_at'              => 'datetime',
            'check_out_at'             => 'datetime',
            'latitude'                 => 'float',
            'longitude'                => 'float',
            'check_in_accuracy'        => 'float',
            'check_in_distance_meters' => 'float',
            'check_out_latitude'       => 'float',
            'check_out_longitude'      => 'float',
            'check_out_accuracy'       => 'float',
            'check_out_distance_meters' => 'float',
            'is_flagged'               => 'boolean',
        ];
    }

    /**
     * Quan hệ: Vị trí làm việc
     */
    public function workLocation()
    {
        return $this->belongsTo(WorkLocation::class, 'work_location_id');
    }

    /**
     * Quan hệ: Ca làm việc
     */
    public function workShift()
    {
        return $this->belongsTo(WorkShift::class, 'work_shift_id');
    }

    /**
     * Scope: Lấy theo ngày
     */
    public function scopeOfDate($query, string $date)
    {
        return $query->where('work_date', $date);
    }

    /**
     * Scope: Lấy theo user
     */
    public function scopeOfUser($query, int $userId, string $userType)
    {
        return $query->where('user_id', $userId)->where('user_type', $userType);
    }
}
