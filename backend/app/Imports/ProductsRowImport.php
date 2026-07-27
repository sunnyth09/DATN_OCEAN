<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Đọc một đoạn rows từ file Excel theo offset và limit.
 *
 * Dùng WithStartRow + WithLimit để PHReadExcel chỉ đọc đúng số dòng cần thiết từ disk,
 * KHÔNG tải toàn bộ file vào RAM — an toàn tuyệt đối với file 10.000+ dòng.
 */
class ProductsRowImport implements ToCollection, WithLimit, WithStartRow
{
    protected int $startRowNum;

    protected int $limitNum;

    protected array $rows = [];

    public function __construct(int $startRowNum, int $limitNum)
    {
        $this->startRowNum = $startRowNum;
        $this->limitNum = $limitNum;
    }

    /**
     * Bắt đầu đọc từ dòng này (1-indexed, bỏ qua header ở dòng 1)
     */
    public function startRow(): int
    {
        return $this->startRowNum;
    }

    /**
     * Giới hạn số dòng đọc — Maatwebsite/Excel sẽ dừng đọc file tại đây
     * Điều này giúp tránh tải toàn bộ file vào RAM
     */
    public function limit(): int
    {
        return $this->limitNum;
    }

    public function collection(Collection $rows)
    {
        $this->rows = $rows->toArray();
    }

    public function getRows(): array
    {
        return $this->rows;
    }
}
