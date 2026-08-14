<?php

namespace App\Exports;

use App\Repositories\StatisticsRepository;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StaffSalesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    protected $startDate;

    protected $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        $statisticsRepository = app(StatisticsRepository::class);

        return $statisticsRepository->getStaffSales($this->startDate, $this->endDate);
    }

    public function headings(): array
    {
        return [
            'ID Nhân Viên',
            'Tên Nhân Viên',
            'Email',
            'Vai Trò',
            'Tổng Số Đơn',
            'Đơn Hoàn Thành',
            'Đơn Hủy / Hoàn',
            'Doanh Thu Thực Thu (VNĐ)',
        ];
    }

    public function map($row): array
    {
        return [
            $row->seller_id,
            $row->seller ? $row->seller->full_name : 'Không xác định',
            $row->seller ? $row->seller->email : 'Không xác định',
            $row->seller ? $row->seller->role : 'Không xác định',
            $row->total_orders,
            $row->completed_orders ?? 0,
            $row->cancelled_orders ?? 0,
            $row->total_revenue,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
