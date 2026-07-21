<?php

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StatisticsRepository
{
    /**
     * Tổng doanh thu theo khoảng ngày
     */
    public function getRevenue($startDate, $endDate): float
    {
        return Order::where(function ($q) {
            $q->where('payment_status', PaymentStatus::PAID->value)
              ->orWhere('fulfillment_status', OrderStatus::COMPLETED->value);
        })->whereBetween('created_at', [$startDate, $endDate])->sum('grand_total');
    }

    /**
     * Tổng số đơn hàng
     */
    public function getOrderCount($startDate, $endDate): int
    {
        return Order::whereBetween('created_at', [$startDate, $endDate])->count();
    }

    /**
     * Tổng khách hàng mới
     */
    public function getNewCustomerCount($startDate, $endDate): int
    {
        return User::where('role', 'customer')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
    }

    /**
     * Đơn hàng hôm nay
     */
    public function getTodayStats(): array
    {
        $todayRevenue = Order::whereDate('created_at', Carbon::today())
            ->where(function ($q) {
                $q->where('payment_status', PaymentStatus::PAID->value)
                  ->orWhere('fulfillment_status', OrderStatus::COMPLETED->value);
            })->sum('grand_total');

        $todayOrders = Order::whereDate('created_at', Carbon::today())->count();

        return ['revenue' => $todayRevenue, 'orders' => $todayOrders];
    }

    /**
     * Đơn hàng pending + cancelled theo khoảng ngày
     */
    public function getPendingAndCancelledCounts($startDate, $endDate): array
    {
        $pending = Order::whereIn('fulfillment_status', OrderStatus::pendingLikeValues())
            ->whereBetween('created_at', [$startDate, $endDate])->count();

        $cancelled = Order::where('fulfillment_status', OrderStatus::CANCELLED->value)
            ->whereBetween('created_at', [$startDate, $endDate])->count();

        return ['pending' => $pending, 'cancelled' => $cancelled];
    }

    /**
     * Doanh thu theo ngày (cho chart)
     */
    public function getRevenueByDay($startDate, $endDate)
    {
        return Order::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(grand_total) as revenue')
        )
            ->where(function ($q) {
                $q->where('payment_status', PaymentStatus::PAID->value)
                  ->orWhere('fulfillment_status', OrderStatus::COMPLETED->value);
            })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get()
            ->keyBy('date');
    }

    /**
     * Doanh thu theo tháng (cho chart > 60 ngày)
     */
    public function getRevenueByMonth($startDate, $endDate)
    {
        return Order::select(
            DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
            DB::raw('SUM(grand_total) as revenue')
        )
            ->where(function ($q) {
                $q->where('payment_status', PaymentStatus::PAID->value)
                  ->orWhere('fulfillment_status', OrderStatus::COMPLETED->value);
            })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->get()
            ->keyBy('month');
    }

    /**
     * Phân bổ trạng thái đơn hàng
     */
    public function getOrderStatusCounts($startDate, $endDate)
    {
        return Order::select('fulfillment_status', DB::raw('count(*) as total'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('fulfillment_status')
            ->get();
    }

    /**
     * Top sản phẩm bán chạy
     */
    public function getTopSellingProducts($startDate, $endDate, int $limit = 10)
    {
        return OrderItem::select(
            'product_id',
            'product_name',
            DB::raw('SUM(quantity) as total_sold'),
            DB::raw('SUM(line_total) as total_revenue')
        )
            ->whereHas('order', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                  ->whereNotIn('fulfillment_status', OrderStatus::revenueExcludedValues());
            })
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_sold')
            ->limit($limit)
            ->with(['product' => function ($q) {
                $q->select('product_id', 'thumbnail_url');
            }, 'product.variants' => function ($q) {
                $q->select('product_id', 'stock');
            }])
            ->get();
    }

    /**
     * Top khách hàng chi tiêu nhiều nhất
     */
    public function getTopSpendingCustomers($startDate, $endDate, int $limit = 10)
    {
        return Order::select(
            'user_id',
            'recipient_name',
            'recipient_phone',
            DB::raw('COUNT(order_id) as total_orders'),
            DB::raw('SUM(grand_total) as total_spent'),
            DB::raw('MAX(created_at) as last_order_date')
        )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotIn('fulfillment_status', [
                OrderStatus::CANCELLED->value,
                OrderStatus::RETURN_APPROVED->value,
                OrderStatus::RETURNED->value,
                OrderStatus::REFUNDED->value,
            ])
            ->groupBy('user_id', 'recipient_name', 'recipient_phone')
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->with('user')
            ->get();
    }

    /**
     * Revenue report table (theo ngày)
     */
    public function getRevenueReport($startDate, $endDate)
    {
        return Order::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(order_id) as total_orders'),
            DB::raw('SUM(grand_total) as total_revenue')
        )
            ->whereNotIn('fulfillment_status', [
                OrderStatus::CANCELLED->value,
                OrderStatus::RETURN_APPROVED->value,
                OrderStatus::RETURNED->value,
                OrderStatus::REFUNDED->value,
            ])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date', 'DESC')
            ->get();
    }

    /**
     * Doanh số theo nhân viên
     */
    public function getStaffSales($startDate, $endDate)
    {
        return Order::select(
            'seller_id',
            DB::raw('COUNT(order_id) as total_orders'),
            DB::raw('SUM(grand_total) as total_revenue')
        )
            ->whereNotNull('seller_id')
            ->whereNotIn('fulfillment_status', [
                OrderStatus::CANCELLED->value,
                OrderStatus::RETURN_APPROVED->value,
                OrderStatus::RETURNED->value,
                OrderStatus::REFUNDED->value,
            ])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('seller_id')
            ->orderByDesc('total_revenue')
            ->with('seller:admin_id,full_name,email,role')
            ->get();
    }
}
