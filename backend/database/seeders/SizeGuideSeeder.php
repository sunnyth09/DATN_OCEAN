<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SizeGuide;

class SizeGuideSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Giày
        SizeGuide::firstOrCreate(['name' => 'Bảng size Giày'], [
            'description' => 'Bảng tính mặc định được thiết kế dựa trên số đo chuẩn của người Việt Nam. Nếu bạn có số đo nằm giữa 2 size, lời khuyên là nên chọn size lớn hơn để có sự thoải mái nhất.',
            'table_headers' => ['Size', 'Chiều dài chân (cm)', 'Gợi ý form dáng'],
            'table_rows' => [
                ['38', '23.5 - 24 cm', 'Vừa vặn'],
                ['39', '24 - 24.5 cm', 'Vừa vặn'],
                ['40', '24.5 - 25 cm', 'Vừa vặn'],
                ['41', '25.5 - 26 cm', 'Vừa vặn'],
                ['42', '26 - 26.5 cm', 'Vừa vặn'],
                ['43', '27 - 27.5 cm', 'Vừa vặn'],
                ['44', '27.5 - 28 cm', 'Vừa vặn']
            ],
            'tips' => [
                'Nên đo chiều dài chân vào buổi chiều tối để có kích thước chuẩn xác nhất.',
                'Nếu form chân bè, dày hoặc thường mang bít tất (vớ) dày, bạn nên chọn lớn hơn 1 size.'
            ]
        ]);

        // 2. Quần áo
        SizeGuide::firstOrCreate(['name' => 'Bảng size Quần áo'], [
            'description' => 'Bảng tính mặc định được thiết kế dựa trên số đo chuẩn của người Việt Nam. Nếu bạn có số đo nằm giữa 2 size, lời khuyên là nên chọn size lớn hơn để có sự thoải mái nhất.',
            'table_headers' => ['Size', 'Cân nặng (kg)', 'Chiều cao (cm)', 'Gợi ý form dáng'],
            'table_rows' => [
                ['S', '45 - 52 kg', 'Dưới 1m60', 'Ôm gọn, tôn dáng'],
                ['M', '53 - 59 kg', '1m60 - 1m65', 'Vừa vặn, thoải mái'],
                ['L', '60 - 68 kg', '1m66 - 1m72', 'Thoải mái vận động'],
                ['XL', '69 - 76 kg', '1m73 - 1m78', 'Rộng rãi, che khuyết điểm'],
                ['XXL', 'Trên 76 kg', 'Trên 1m78', 'Oversize rộng rãi']
            ],
            'tips' => [
                'Sản phẩm có độ co giãn nhẹ khoảng 2-3cm ở vòng bụng.',
                'Màu sắc thực tế có thể chênh lệch 3-5% do độ phân giải và ánh sáng màn hình.'
            ]
        ]);

        // 3. Vợt
        SizeGuide::firstOrCreate(['name' => 'Bảng kích cỡ Cán Vợt (Grip)'], [
            'description' => 'Bảng tính mặc định được thiết kế dựa trên số đo chuẩn của người Việt Nam. Nếu bạn có số đo nằm giữa 2 size, lời khuyên là nên chọn size lớn hơn để có sự thoải mái nhất.',
            'table_headers' => ['Size Cán (Grip)', 'Chu vi cán vợt', 'Đối tượng khuyên dùng'],
            'table_rows' => [
                ['G4', '3.25 inch (~8.25 cm)', 'Người lớn tay trung bình (phổ biến nhất)'],
                ['G5', '3.12 inch (~7.90 cm)', 'Người lớn tay nhỏ, thích xoay vợt linh hoạt'],
                ['G6', '3.00 inch (~7.60 cm)', 'Trẻ em hoặc phụ nữ có bàn tay rất nhỏ']
            ],
            'tips' => [
                'Chu vi cán vợt ảnh hưởng trực tiếp đến cảm giác vung và kiểm soát cầu/bóng.',
                'Nên chọn cán vừa tay. Nếu phân vân, hãy chọn cán nhỏ hơn (bạn luôn có thể quấn thêm băng quấn).'
            ]
        ]);
    }
}
