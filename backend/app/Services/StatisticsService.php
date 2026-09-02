<?php

namespace App\Services;

use App\Exports\LastMonthRevenueExport;
use App\Exports\StaffSalesExport;
use App\Models\Product;
use App\Repositories\StatisticsRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class StatisticsService
{
    public function __construct(
        protected StatisticsRepository $statsRepository
    ) {}

    public function getDateRange(Request $request): array
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $preset = $request->input('preset');

        if ($preset && $preset !== 'custom') {
            return match ($preset) {
                'today' => [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()],
                '7days' => [Carbon::now()->subDays(6)->startOfDay(), Carbon::now()->endOfDay()],
                '30days' => [Carbon::now()->subDays(29)->startOfDay(), Carbon::now()->endOfDay()],
                'this_month' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
                'this_year' => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()],
                default => [Carbon::now()->subDays(29)->startOfDay(), Carbon::now()->endOfDay()],
            };
        }

        return [
            $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->subDays(29)->startOfDay(),
            $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay(),
        ];
    }

    public function getOverview(Request $request): array
    {
        [$startDate, $endDate] = $this->getDateRange($request);

        $diffInDays = $startDate->diffInDays($endDate) + 1;
        $prevStartDate = (clone $startDate)->subDays($diffInDays);
        $prevEndDate = (clone $endDate)->subDays($diffInDays);

        $totalRevenue = $this->statsRepository->getRevenue($startDate, $endDate);
        $prevTotalRevenue = $this->statsRepository->getRevenue($prevStartDate, $prevEndDate);
        $revenueChange = $this->calculateChange($prevTotalRevenue, $totalRevenue);

        $totalOrders = $this->statsRepository->getOrderCount($startDate, $endDate);
        $prevTotalOrders = $this->statsRepository->getOrderCount($prevStartDate, $prevEndDate);
        $ordersChange = $this->calculateChange($prevTotalOrders, $totalOrders);

        $totalCustomers = $this->statsRepository->getNewCustomerCount($startDate, $endDate);
        $prevTotalCustomers = $this->statsRepository->getNewCustomerCount($prevStartDate, $prevEndDate);
        $customersChange = $this->calculateChange($prevTotalCustomers, $totalCustomers);

        $allProducts = Product::count();
        $today = $this->statsRepository->getTodayStats();
        $pendingCancelled = $this->statsRepository->getPendingAndCancelledCounts($startDate, $endDate);
        $slowMovingSummary = $this->statsRepository->getSlowMovingSummary(60, 2);

        $aov = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 0) : 0;
        $prevAov = $prevTotalOrders > 0 ? round($prevTotalRevenue / $prevTotalOrders, 0) : 0;
        $aovChange = $this->calculateChange($prevAov, $aov);

        return [
            'total_revenue' => [
                'value' => $totalRevenue,
                'isUp' => $revenueChange >= 0,
                'change' => abs(round($revenueChange, 1)).'%',
            ],
            'total_orders' => [
                'value' => $totalOrders,
                'isUp' => $ordersChange >= 0,
                'change' => abs(round($ordersChange, 1)).'%',
            ],
            'total_customers' => [
                'value' => $totalCustomers,
                'isUp' => $customersChange >= 0,
                'change' => abs(round($customersChange, 1)).'%',
            ],
            'aov' => [
                'value' => $aov,
                'isUp' => $aovChange >= 0,
                'change' => abs(round($aovChange, 1)).'%',
            ],
            'total_products' => ['value' => $allProducts],
            'today_revenue' => $today['revenue'],
            'today_orders' => $today['orders'],
            'pending_orders' => $pendingCancelled['pending'],
            'cancelled_orders' => $pendingCancelled['cancelled'],
            'slow_moving_count' => $slowMovingSummary['slow_moving_count'],
            'tied_up_capital' => $slowMovingSummary['tied_up_capital'],
        ];
    }

    public function getRevenueChart(Request $request): array
    {
        [$startDate, $endDate] = $this->getDateRange($request);
        $maxDays = $startDate->diffInDays($endDate);

        $labels = [];
        $data = [];

        if ($maxDays > 60) {
            $revenueData = $this->statsRepository->getRevenueByMonth($startDate, $endDate);
            $currentMonth = (clone $startDate)->startOfMonth();
            while ($currentMonth <= $endDate) {
                $key = $currentMonth->format('Y-m');
                $labels[] = 'Tháng '.$currentMonth->format('m/Y');
                $data[] = isset($revenueData[$key]) ? $revenueData[$key]->revenue : 0;
                $currentMonth->addMonth();
            }
        } else {
            $revenueData = $this->statsRepository->getRevenueByDay($startDate, $endDate);
            $currentDate = clone $startDate;
            for ($i = 0; $i <= $maxDays; $i++) {
                $dateString = $currentDate->format('Y-m-d');
                $labels[] = $currentDate->format('d/m/Y');
                $data[] = isset($revenueData[$dateString]) ? $revenueData[$dateString]->revenue : 0;
                $currentDate->addDay();
            }
        }

        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Doanh thu',
                'data' => $data,
                'borderColor' => '#0288d1',
                'backgroundColor' => 'rgba(2, 136, 209, 0.1)',
                'fill' => true,
                'tension' => 0.4,
            ]],
        ];
    }

    public function getOrderStatusChart(Request $request): array
    {
        [$startDate, $endDate] = $this->getDateRange($request);

        $statusCounts = $this->statsRepository->getOrderStatusCounts($startDate, $endDate);

        $statusMapping = [
            // Đơn hàng đang luân chuyển
            'pending' => ['label' => 'Chờ xác nhận', 'color' => '#f59e0b'],
            'confirmed' => ['label' => 'Đã xác nhận', 'color' => '#3b82f6'],
            'processing' => ['label' => 'Đang xử lý', 'color' => '#06b6d4'],
            'packing' => ['label' => 'Đang đóng gói', 'color' => '#0ea5e9'],
            'awaiting_pickup' => ['label' => 'Chờ lấy hàng', 'color' => '#6366f1'],
            'shipping' => ['label' => 'Đang giao hàng', 'color' => '#0284c7'],
            'delivered' => ['label' => 'Đã giao hàng', 'color' => '#14b8a6'],
            'completed' => ['label' => 'Hoàn thành', 'color' => '#10b981'],
            'cancelled' => ['label' => 'Đã hủy', 'color' => '#ef4444'],

            // Đổi trả / Hoàn hàng
            'return_requested' => ['label' => 'Yêu cầu đổi/trả', 'color' => '#ea580c'],
            'return_approved' => ['label' => 'Đã duyệt đổi/trả', 'color' => '#4f46e5'],
            'return_rejected' => ['label' => 'Từ chối đổi/trả', 'color' => '#e11d48'],
            'returning' => ['label' => 'Khách đang gửi hoàn', 'color' => '#8b5cf6'],
            'warehouse_received' => ['label' => 'Kho đã nhận hoàn', 'color' => '#7c3aed'],
            'inspection_failed' => ['label' => 'Hoàn không đạt QC', 'color' => '#be123c'],
            'inspected_ok' => ['label' => 'Hoàn đạt QC', 'color' => '#059669'],
            'returned' => ['label' => 'Đã nhận hàng hoàn', 'color' => '#64748b'],
            'refunded' => ['label' => 'Đã hoàn tiền', 'color' => '#475569'],
        ];

        $labels = [];
        $data = [];
        $backgroundColors = [];

        foreach ($statusCounts as $status) {
            $key = (string) $status->fulfillment_status;
            $labels[] = $statusMapping[$key]['label'] ?? ucfirst(str_replace('_', ' ', $key));
            $data[] = (int) $status->total;
            $backgroundColors[] = $statusMapping[$key]['color'] ?? '#94a3b8';
        }

        return [
            'labels' => $labels,
            'datasets' => [[
                'data' => $data,
                'backgroundColor' => $backgroundColors,
            ]],
        ];
    }

    public function getTopProducts(Request $request): array
    {
        [$startDate, $endDate] = $this->getDateRange($request);

        $topProducts = $this->statsRepository->getTopSellingProducts($startDate, $endDate);

        return $topProducts->map(function ($item) {
            return [
                'id' => $item->product_id,
                'name' => $item->product_name,
                'image' => $item->product && $item->product->thumbnail_url
                    ? $item->product->thumbnail_url : null,
                'sold' => (int) $item->total_sold,
                'revenue' => (float) $item->total_revenue,
                'stock' => $item->product ? $item->product->variants->sum('stock') : 0,
            ];
        })->toArray();
    }

    public function getTopCustomers(Request $request): array
    {
        [$startDate, $endDate] = $this->getDateRange($request);

        $topCustomers = $this->statsRepository->getTopSpendingCustomers($startDate, $endDate);

        return $topCustomers->map(function ($order) {
            return [
                'id' => $order->user_id,
                'name' => $order->recipient_name,
                'email' => $order->user ? $order->user->email : $order->recipient_phone,
                'total_orders' => (int) $order->total_orders,
                'total_spent' => (float) $order->total_spent,
                'last_order' => Carbon::parse($order->last_order_date)->format('d/m/Y'),
            ];
        })->toArray();
    }

    public function getRevenueReport(Request $request): array
    {
        [$startDate, $endDate] = $this->getDateRange($request);

        $reportData = $this->statsRepository->getRevenueReport($startDate, $endDate);

        return $reportData->map(function ($row) {
            return [
                'date' => Carbon::parse($row->date)->format('d/m/Y'),
                'raw_date' => $row->date,
                'orders' => $row->total_orders,
                'revenue' => $row->total_revenue,
            ];
        })->toArray();
    }

    public function exportLastMonthRevenue()
    {
        $fileName = 'Doanh_Thu_Thang_Truoc_'.Carbon::now()->format('Y_m').'.xlsx';

        return Excel::download(new LastMonthRevenueExport, $fileName);
    }

    public function exportStaffSales(Request $request)
    {
        [$startDate, $endDate] = $this->getDateRange($request);

        $fileName = 'Doanh_Thu_Nhan_Vien_'.Carbon::now()->format('Y_m_d_His').'.xlsx';

        return Excel::download(new StaffSalesExport($startDate, $endDate), $fileName);
    }

    public function getStaffSales(Request $request): array
    {
        [$startDate, $endDate] = $this->getDateRange($request);

        $staffSales = $this->statsRepository->getStaffSales($startDate, $endDate);

        return $staffSales->map(function ($row) {
            return [
                'staff_id' => $row->seller_id,
                'staff_name' => $row->seller ? $row->seller->full_name : 'Unknown',
                'staff_email' => $row->seller ? $row->seller->email : 'Unknown',
                'role' => $row->seller ? $row->seller->role : 'Unknown',
                'total_orders' => (int) $row->total_orders,
                'completed_orders' => (int) ($row->completed_orders ?? 0),
                'cancelled_orders' => (int) ($row->cancelled_orders ?? 0),
                'total_revenue' => (float) $row->total_revenue,
            ];
        })->toArray();
    }

    /**
     * Lấy danh sách và tóm tắt sản phẩm tồn kho lâu / bán chậm
     */
    public function getSlowMovingProducts(Request $request): array
    {
        $daysThreshold = (int) $request->input('days_threshold', 60);
        $salesLimit = (int) $request->input('sales_limit', 2);
        $limit = (int) $request->input('limit', 20);

        $products = $this->statsRepository->getSlowMovingProducts($daysThreshold, $salesLimit, $limit);
        $summary = $this->statsRepository->getSlowMovingSummary($daysThreshold, $salesLimit);

        $formattedProducts = $products->map(function ($item) {
            $publishDate = $item->published_at ? Carbon::parse($item->published_at) : Carbon::parse($item->created_at);
            $daysInInventory = (int) Carbon::now()->diffInDays($publishDate);
            $stock = (int) ($item->total_stock ?? 0);
            $minPrice = (float) ($item->min_price ?? 0);
            $tiedUpCapital = $stock * $minPrice;

            return [
                'id' => $item->product_id,
                'name' => $item->name,
                'slug' => $item->slug,
                'thumbnail_url' => $item->thumbnail_url,
                'category_name' => $item->category ? $item->category->name : 'Chưa phân loại',
                'brand_name' => $item->brand ? $item->brand->name : 'N/A',
                'base_price' => $minPrice,
                'stock' => $stock,
                'sold_last_30d' => (int) ($item->recent_sold ?? 0),
                'days_in_inventory' => $daysInInventory,
                'tied_up_capital' => $tiedUpCapital,
                'published_at' => $publishDate->format('d/m/Y'),
            ];
        })->toArray();

        return [
            'summary' => $summary,
            'products' => $formattedProducts,
        ];
    }

    private function calculateChange(float $prev, float $current): float
    {

        if ($prev == 0) {
            return $current > 0 ? 100 : 0;
        }

        return (($current - $prev) / $prev) * 100;
    }
}
