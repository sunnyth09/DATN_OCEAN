<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkLocation extends Model
{
    protected $table = 'work_locations';

    protected $fillable = [
        'name',
        'address',
        'latitude',
        'longitude',
        'radius_meters',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude'      => 'float',
            'longitude'     => 'float',
            'radius_meters' => 'integer',
            'is_active'     => 'boolean',
        ];
    }

    /**
     * Scope: Chỉ lấy vị trí đang hoạt động
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Quan hệ: Vị trí có nhiều bản ghi chấm công
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'work_location_id');
    }
}
