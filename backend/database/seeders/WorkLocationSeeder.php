<?php

namespace Database\Seeders;

use App\Models\WorkLocation;
use Illuminate\Database\Seeder;

class WorkLocationSeeder extends Seeder
{
    /**
     * Seed vị trí làm việc mẫu.
     * Sử dụng tọa độ từ biến môi trường STORE_LAT/STORE_LNG hiện tại.
     */
    public function run(): void
    {
        WorkLocation::firstOrCreate(
            ['name' => 'Cửa hàng chính'],
            [
                'address'       => 'Nha Trang, Khánh Hòa',
                'latitude'      => env('STORE_LAT', 12.7098567),
                'longitude'     => env('STORE_LNG', 108.0733147),
                'radius_meters' => 200,
                'is_active'     => true,
            ]
        );

        // Thêm 1 vị trí mẫu nữa để demo đa chi nhánh
        WorkLocation::firstOrCreate(
            ['name' => 'Chi nhánh 2 - Trung tâm'],
            [
                'address'       => 'Trung tâm Nha Trang, Khánh Hòa',
                'latitude'      => 12.2388,
                'longitude'     => 109.1967,
                'radius_meters' => 200,
                'is_active'     => true,
            ]
        );

        // Chi nhánh Buôn Ma Thuột
        WorkLocation::firstOrCreate(
            ['name' => '18 Mười Tháng Ba - Buôn Ma Thuột'],
            [
                'address'       => '18 Mười Tháng Ba, Buôn Ma Thuột, Đắk Lắk 630000',
                'latitude'      => 12.6863485,
                'longitude'     => 108.0179717,
                'radius_meters' => 200,
                'is_active'     => true,
            ]
        );
    }
}
