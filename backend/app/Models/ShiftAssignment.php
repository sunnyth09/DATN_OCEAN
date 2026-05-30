<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftAssignment extends Model
{
    protected $table = 'shift_assignments';

    protected $fillable = [
        'user_id',
        'user_type',
        'work_shift_id',
        'day_of_week',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_active'   => 'boolean',
        ];
    }

    public function workShift()
    {
        return $this->belongsTo(WorkShift::class, 'work_shift_id');
    }
}
