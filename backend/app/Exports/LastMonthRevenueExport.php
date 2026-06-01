<?php

namespace App\Exports;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LastMonthRevenueExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function collection()
    {
        $startDate = Carbon::now()->subMonth()->startOfMonth();
        $endDate = Carbon::now()->subMonth()->endOfMonth();

        // Query successful orders from last month
        return Order::with(['user', 'seller'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where(function ($q) {
                $q->where('payment_status', PaymentStatus::PAID->value)
                  ->orWhere('fulfillment_status', OrderStatus::COMPLETED->value);
            })
            ->orderBy('created_at', 'ASC')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Mã Đơn Hàng',
            'Ngày Khởi Tạo',
            'Người Mua Hàng',
            'SĐT Người Mua',
            'Nhân Viên Phụ Trách',
            'Tổng Tiền (VNĐ)',
            'Trạng Thái',
        ];
    }

    public function map($order): array
    {
        return [
            $order->order_code,
            $order->created_at->format('d/m/Y H:i:s'),
            $order->user ? $order->user->full_name : $order->recipient_name,
            $order->user ? $order->user->phone : $order->recipient_phone,
            $order->seller ? $order->seller->full_name : 'Hệ thống / Chưa phân công',
            $order->grand_total,
            $order->fulfillment_status,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
