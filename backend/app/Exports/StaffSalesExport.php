<?php

namespace App\Exports;

use App\Repositories\StatisticsRepository;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StaffSalesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    protected \;
    protected \;

    public function __construct(\, \)
    {
        \->startDate = \;
        \->endDate = \;
    }

    public function collection()
    {
        \ = app(StatisticsRepository::class);
        return \->getStaffSales(\->startDate, \->endDate);
    }

    public function headings(): array
    {
        return [
            'ID Nhân Viên',
            'Tên Nhân Viên',
            'Email',
            'Vai Trò',
            'Tổng Số Đơn Hàng',
            'Tổng Doanh Thu (VNĐ)',
        ];
    }

    public function map(\): array
    {
        return [
            \->seller_id,
            \->seller ? \->seller->full_name : 'Không xác định',
            \->seller ? \->seller->email : 'Không xác định',
            \->seller ? \->seller->role : 'Không xác định',
            \->total_orders,
            \->total_revenue,
        ];
    }

    public function styles(Worksheet \)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
