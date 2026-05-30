<?php

namespace Database\Seeders;

use App\Models\WorkShift;
use Illuminate\Database\Seeder;

class WorkShiftSeeder extends Seeder
{
    public function run(): void
    {
        $shifts = [
            [
                'name'                 => 'Ca sáng',
                'start_time'           => '08:00:00',
                'end_time'             => '12:30:00',
                'early_buffer_minutes' => 30,
                'is_active'            => true,
            ],
            [
                'name'                 => 'Ca chiều',
                'start_time'           => '12:30:00',
                'end_time'             => '17:00:00',
                'early_buffer_minutes' => 30,
                'is_active'            => true,
            ],
            [
                'name'                 => 'Ca tối',
                'start_time'           => '17:00:00',
                'end_time'             => '21:00:00',
                'early_buffer_minutes' => 30,
                'is_active'            => true,
            ],
        ];

        foreach ($shifts as $shift) {
            WorkShift::firstOrCreate(
                ['name' => $shift['name']],
                $shift
            );
        }
    }
}
