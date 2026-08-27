<?php

namespace App\Exports;

use App\Models\Product;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    protected array $filters;

    protected int $rowNumber = 0;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $status = $this->filters['status'] ?? '';

        $query = Product::query()->with(['category', 'brand', 'variants']);

        if ($status === 'deleted') {
            $query->onlyTrashed();
        } elseif ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        // Category filter
        if (! empty($this->filters['category_id'])) {
            $query->where('category_id', $this->filters['category_id']);
        }

        // Brand filter
        if (! empty($this->filters['brand_id'])) {
            $query->where('brand_id', $this->filters['brand_id']);
        }

        // Date filter
        $preset = $this->filters['date_preset'] ?? 'all';
        switch ($preset) {
            case 'today':
                $query->whereDate('created_at', Carbon::today());
                break;
            case 'this_week':
                $query->whereBetween('created_at', [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek(),
                ]);
                break;
            case 'this_month':
                $query->whereBetween('created_at', [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth(),
                ]);
                break;
            case 'custom':
                if (! empty($this->filters['from_date'])) {
                    $query->whereDate('created_at', '>=', $this->filters['from_date']);
                }
                if (! empty($this->filters['to_date'])) {
                    $query->whereDate('created_at', '<=', $this->filters['to_date']);
                }
                break;
        }

        return $query->orderBy('product_id', 'desc');
    }

    public function headings(): array
    {
        $exportType = $this->filters['export_type'] ?? 'variant';

        if ($exportType === 'summary') {
            return [
                'STT',
                'ID',
                'TÊN SẢN PHẨM',
                'LOẠI SP',
                'MÃ SKU',
                'DANH MỤC',
                'THƯƠNG HIỆU',
                'GIÁ THẤP NHẤT',
                'GIÁ CAO NHẤT',
                'TỔNG TỒN KHO',
                'TRẠNG THÁI',
                'NGÀY TẠO',
            ];
        }

        return [
            'STT',
            'ID SP',
            'TÊN SẢN PHẨM',
            'LOẠI SP',
            'DANH MỤC',
            'THƯƠNG HIỆU',
            'MÃ SKU BIẾN THỂ',
            'BARCODE',
            'MÀU SẮC',
            'KÍCH THƯỚC',
            'GIÁ BÁN',
            'GIÁ GỐC',
            'TỒN KHO',
            'TRẠNG THÁI',
            'CHẤT LIỆU',
            'XUẤT XỨ',
            'KIỂU DÁNG',
            'NGÀY TẠO',
        ];
    }

    public function map($product): array
    {
        $exportType = $this->filters['export_type'] ?? 'variant';
        $rows = [];

        $typeLabel = $product->product_type === 'simple' ? 'Đơn giản' : 'Biến thể';
        $statusMap = [
            'active' => 'Đang bán',
            'draft' => 'Bản nháp',
            'inactive' => 'Tạm ẩn',
            'out_of_stock' => 'Hết hàng',
            'deleted' => 'Đã xóa',
        ];
        $statusLabel = $statusMap[$product->status] ?? $product->status;
        $createdAt = $product->created_at ? $product->created_at->format('d/m/Y H:i') : '-';

        if ($exportType === 'summary') {
            $this->rowNumber++;
            $totalStock = $product->variants->sum('stock');

            return [
                $this->rowNumber,
                $product->product_id,
                $product->name,
                $typeLabel,
                $product->sku ?: '-',
                $product->category?->name ?: '-',
                $product->brand?->name ?: '-',
                $product->min_price ?: 0,
                $product->max_price ?: 0,
                $totalStock,
                $statusLabel,
                $createdAt,
            ];
        }

        // Export type: variant
        if ($product->variants->isEmpty()) {
            $this->rowNumber++;
            $rows[] = [
                $this->rowNumber,
                $product->product_id,
                $product->name,
                $typeLabel,
                $product->category?->name ?: '-',
                $product->brand?->name ?: '-',
                $product->sku ?: '-',
                '-',
                '-',
                '-',
                $product->min_price ?: 0,
                0,
                0,
                $statusLabel,
                $product->material ?: '-',
                $product->origin ?: '-',
                $product->style ?: '-',
                $createdAt,
            ];
        } else {
            foreach ($product->variants as $variant) {
                $this->rowNumber++;
                $variantStatus = $statusMap[$variant->status] ?? ($variant->status ?: $statusLabel);
                $rows[] = [
                    $this->rowNumber,
                    $product->product_id,
                    $product->name,
                    $typeLabel,
                    $product->category?->name ?: '-',
                    $product->brand?->name ?: '-',
                    $variant->sku ?: $product->sku ?: '-',
                    $variant->barcode ?: '-',
                    $variant->color ?: '-',
                    $variant->size ?: '-',
                    $variant->price ?: 0,
                    $variant->compare_at_price ?: 0,
                    $variant->stock ?: 0,
                    $variantStatus,
                    $product->material ?: '-',
                    $product->origin ?: '-',
                    $product->style ?: '-',
                    $createdAt,
                ];
            }
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 11,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '167A70'], // Seafoam / Ocean Theme
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }
}
