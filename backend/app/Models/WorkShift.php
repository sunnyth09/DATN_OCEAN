<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkShift extends Model
{
    protected $table = 'work_shifts';

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'early_buffer_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'early_buffer_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function assignments()
    {
        return $this->hasMany(ShiftAssignment::class, 'work_shift_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'work_shift_id');
    }
}
