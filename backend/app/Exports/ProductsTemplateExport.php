<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * ProductsTemplateExport — File Excel mẫu chuẩn 20 cột
 *
 * Hỗ trợ cả sản phẩm đơn (simple) và sản phẩm có biến thể (variant).
 * Dữ liệu mẫu bao gồm 1 SP variant (3 biến thể) + 1 SP simple.
 * Cột Q-T: Thông số kỹ thuật (SKU, Chất liệu, Xuất xứ, Kiểu dáng)
 */
class ProductsTemplateExport implements FromArray, WithColumnWidths, WithHeadings, WithStyles
{
    /**
     * Header row — 16 cột
     */
    public function headings(): array
    {
        return [
            'TÊN SẢN PHẨM (*)',          // A
            'LOẠI SP (*)',                // B — simple | variant
            'MÃ DANH MỤC (*)',           // C
            'MÃ THƯƠNG HIỆU',           // D
            'MÔ TẢ NGẮN',               // E
            'MÔ TẢ CHI TIẾT',           // F
            'TRẠNG THÁI',               // G — active | draft
            'NỔI BẬT',                  // H — 1 | 0
            'ẢNH CHÍNH (URL)',           // I
            'ẢNH PHỤ (URLs)',            // J — cách nhau dấu phẩy
            'MÀU SẮC',                  // K
            'KÍCH CỠ',                  // L
            'GIÁ BÁN (*)',              // M
            'GIÁ GỐC',                  // N
            'SỐ LƯỢNG KHO (*)',         // O
            'ẢNH BIẾN THỂ (URLs)',       // P — cách nhau dấu phẩy
            'MÃ SKU',                    // Q — Mã sản phẩm chung
            'CHẤT LIỆU',                // R — Chất liệu sản phẩm
            'XUẤT XỨ',                  // S — Xuất xứ / Nơi sản xuất
            'KIỂU DÁNG',                // T — Kiểu dáng / Style
        ];
    }

    /**
     * Dữ liệu mẫu — Người dùng tham khảo cách điền
     */
    public function array(): array
    {
        return [
            // === SP 1: Áo Khoác Gió (variant) — 3 biến thể ===
            // Biến thể 1: Đỏ - M (dòng đầu chứa đầy đủ thông tin SP)
            [
                'Áo Khoác Gió Ocean Pro',       // A: Tên SP
                'variant',                        // B: Loại
                1,                                // C: Danh mục (Giày=1, Vợt=2, Quần áo=3, ...)
                '',                               // D: Thương hiệu
                'Áo khoác chống gió cao cấp',    // E: Mô tả ngắn
                'Chất liệu trượt nước, 2 lớp',   // F: Mô tả chi tiết
                'active',                         // G: Trạng thái
                1,                                // H: Nổi bật
                'https://picsum.photos/id/26/800/800',  // I: Ảnh chính
                '',                               // J: Ảnh phụ
                'Đỏ',                             // K: Màu sắc
                'M',                              // L: Kích cỡ
                550000,                           // M: Giá bán
                700000,                           // N: Giá gốc
                50,                               // O: Kho
                'https://picsum.photos/id/27/400/400',  // P: Ảnh biến thể
                'AKG-OCEAN-001',                  // Q: Mã SKU
                'Polyester chống thấm',           // R: Chất liệu
                'Việt Nam',                       // S: Xuất xứ
                'Áo khoác thể thao',              // T: Kiểu dáng
            ],
            // Biến thể 2: Đỏ - L (chỉ cần điền tên SP + cột biến thể)
            [
                'Áo Khoác Gió Ocean Pro',       // A: Cùng tên → cùng SP
                '', '', '', '', '', '', '',       // B-H: Để trống
                '', '',                           // I-J: Để trống
                'Đỏ',                             // K
                'L',                              // L
                550000,                           // M
                700000,                           // N
                30,                               // O
                'https://picsum.photos/id/27/400/400',  // P
                '', '', '', '',                   // Q-T: Để trống (chỉ điền ở dòng đầu)
            ],
            // Biến thể 3: Xanh - M
            [
                'Áo Khoác Gió Ocean Pro',
                '', '', '', '', '', '', '',
                '', '',
                'Xanh Navy',                      // K
                'M',                              // L
                560000,                           // M
                700000,                           // N
                40,                               // O
                'https://picsum.photos/id/28/400/400',  // P
                '', '', '', '',                   // Q-T: Để trống
            ],

            // === SP 2: Giày Nike (simple) — 1 dòng duy nhất ===
            [
                'Giày Thể Thao Ocean Runner',    // A
                'simple',                         // B
                1,                                // C
                '',                               // D
                'Giày chạy bộ êm ái',            // E
                'Đệm khí, đế cao su chống trượt', // F
                'active',                         // G
                0,                                // H
                'https://picsum.photos/id/21/800/800',  // I
                '',                               // J
                '',                               // K: Để trống (simple)
                '',                               // L: Để trống
                1250000,                          // M
                1500000,                          // N
                25,                               // O
                '',                               // P: Để trống
                'GTT-RUNNER-01',                  // Q: Mã SKU
                'Vải mesh + Cao su',              // R: Chất liệu
                'Trung Quốc',                     // S: Xuất xứ
                'Giày chạy bộ',                   // T: Kiểu dáng
            ],
        ];
    }

    /**
     * Styling đẹp mắt, chuyên nghiệp
     */
    public function styles(Worksheet $sheet): array
    {
        $lastDataRow = 5; // 1 header + 4 data rows

        // Tô nền phân biệt từng sản phẩm
        // SP1 (Áo khoác): rows 2-4 → nền xanh nhạt
        $sheet->getStyle('A2:T4')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E3F2FD'],
            ],
        ]);

        // SP2 (Giày): row 5 → nền cam nhạt
        $sheet->getStyle('A5:T5')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFF3E0'],
            ],
        ]);

        // Wrap text cho cột mô tả
        $sheet->getStyle("E2:F{$lastDataRow}")->getAlignment()->setWrapText(true);

        // Row height
        $sheet->getRowDimension(1)->setRowHeight(28);

        return [
            // Header row
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 11,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0277BD'], // Ocean Blue đậm
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    /**
     * Chiều rộng cột tối ưu cho dễ đọc/nhập
     */
    public function columnWidths(): array
    {
        return [
            'A' => 30,  // Tên SP
            'B' => 12,  // Loại
            'C' => 14,  // Danh mục
            'D' => 16,  // Thương hiệu
            'E' => 25,  // Mô tả ngắn
            'F' => 30,  // Mô tả chi tiết
            'G' => 14,  // Trạng thái
            'H' => 10,  // Nổi bật
            'I' => 40,  // Ảnh chính URL
            'J' => 40,  // Ảnh phụ URLs
            'K' => 14,  // Màu sắc
            'L' => 12,  // Kích cỡ
            'M' => 14,  // Giá bán
            'N' => 14,  // Giá gốc
            'O' => 16,  // Kho
            'P' => 40,  // Ảnh biến thể URLs
            'Q' => 18,  // Mã SKU
            'R' => 20,  // Chất liệu
            'S' => 18,  // Xuất xứ
            'T' => 18,  // Kiểu dáng
        ];
    }
}
