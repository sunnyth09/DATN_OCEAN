<?php

namespace App\Http\Controllers;

use App\Services\StatisticsService;
use Illuminate\Http\Request;

class AdminStatisticsController extends Controller
{
    public function __construct(
        protected StatisticsService $statisticsService
    ) {}

    /**
     * Get basic overview statistics cards
     */
    public function getOverview(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->statisticsService->getOverview($request),
        ]);
    }

    /**
     * Get revenue chart data
     */
    public function getRevenueChart(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->statisticsService->getRevenueChart($request),
        ]);
    }

    /**
     * Get order status chart
     */
    public function getOrderStatusChart(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->statisticsService->getOrderStatusChart($request),
        ]);
    }

    /**
     * Get top selling products
     */
    public function getTopProducts(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->statisticsService->getTopProducts($request),
        ]);
    }

    /**
     * Get top customers
     */
    public function getTopCustomers(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->statisticsService->getTopCustomers($request),
        ]);
    }

    /**
     * Get detailed revenue report table
     */
    public function getRevenueReport(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->statisticsService->getRevenueReport($request),
        ]);
    }

    /**
     * Xuất file Excel doanh thu tháng trước
     */
    public function exportLastMonthRevenue()
    {
        return $this->statisticsService->exportLastMonthRevenue();
    }
}
