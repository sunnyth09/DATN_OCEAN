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
     * Scope lọc đơn hàng tính doanh thu hợp lệ (Net Revenue)
     * Điều kiện: (Đã thanh toán OR Đã hoàn thành) VÀ KHÔNG nằm trong danh sách Hủy/Hoàn trả VÀ không phải giỏ hàng bỏ rơi.
     */
    private function applyValidRevenueFilter($query)
    {
        return $query->where(function ($q) {
            $q->where('payment_status', PaymentStatus::PAID->value)
                ->orWhere('fulfillment_status', OrderStatus::COMPLETED->value);
        })
        ->whereNotIn('fulfillment_status', [
            OrderStatus::CANCELLED->value,
            OrderStatus::RETURN_APPROVED->value,
            OrderStatus::RETURNED->value,
            OrderStatus::REFUNDED->value,
        ])
        ->where(function ($q) {
            $q->whereNull('is_abandoned_checkout')
                ->orWhere('is_abandoned_checkout', 0);
        });
    }

    /**
     * Scope lọc đơn hàng hợp lệ (loại bỏ giỏ hàng bỏ rơi)
     */
    private function applyValidOrderFilter($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('is_abandoned_checkout')
                ->orWhere('is_abandoned_checkout', 0);
        });
    }

    /**
     * Tổng doanh thu thực tế theo khoảng ngày
     */
    public function getRevenue($startDate, $endDate): float
    {
        $query = Order::whereBetween('created_at', [$startDate, $endDate]);

        return (float) $this->applyValidRevenueFilter($query)->sum('grand_total');
    }

    /**
     * Tổng số đơn hàng hợp lệ (loại bỏ đơn hủy và giỏ hàng bỏ rơi)
     */
    public function getOrderCount($startDate, $endDate): int
    {
        return $this->applyValidOrderFilter(Order::whereBetween('created_at', [$startDate, $endDate]))
            ->where('fulfillment_status', '!=', OrderStatus::CANCELLED->value)
            ->count();
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
        $todayRevenue = (float) $this->applyValidRevenueFilter(Order::whereDate('created_at', Carbon::today()))
            ->sum('grand_total');

        $todayOrders = $this->applyValidOrderFilter(Order::whereDate('created_at', Carbon::today()))
            ->where('fulfillment_status', '!=', OrderStatus::CANCELLED->value)
            ->count();

        return ['revenue' => $todayRevenue, 'orders' => $todayOrders];
    }

    /**
     * Đơn hàng pending + cancelled theo khoảng ngày
     */
    public function getPendingAndCancelledCounts($startDate, $endDate): array
    {
        $pending = Order::whereIn('fulfillment_status', OrderStatus::pendingLikeValues())
            ->where(function ($q) {
                $q->whereNull('is_abandoned_checkout')->orWhere('is_abandoned_checkout', 0);
            })
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
        $query = Order::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(grand_total) as revenue')
        )->whereBetween('created_at', [$startDate, $endDate]);

        return $this->applyValidRevenueFilter($query)
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
        $query = Order::select(
            DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
            DB::raw('SUM(grand_total) as revenue')
        )->whereBetween('created_at', [$startDate, $endDate]);

        return $this->applyValidRevenueFilter($query)
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
            ->where(function ($q) {
                $q->whereNull('is_abandoned_checkout')->orWhere('is_abandoned_checkout', 0);
            })
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
                    ->where(function ($sub) {
                        $sub->where('payment_status', PaymentStatus::PAID->value)
                            ->orWhere('fulfillment_status', OrderStatus::COMPLETED->value);
                    })
                    ->whereNotIn('fulfillment_status', [
                        OrderStatus::CANCELLED->value,
                        OrderStatus::RETURN_APPROVED->value,
                        OrderStatus::RETURNED->value,
                        OrderStatus::REFUNDED->value,
                    ]);
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
        $query = Order::select(
            'user_id',
            'recipient_name',
            'recipient_phone',
            DB::raw('COUNT(order_id) as total_orders'),
            DB::raw('SUM(grand_total) as total_spent'),
            DB::raw('MAX(created_at) as last_order_date')
        )->whereBetween('created_at', [$startDate, $endDate]);

        return $this->applyValidRevenueFilter($query)
            ->groupBy('user_id', 'recipient_name', 'recipient_phone')
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->with('user')
            ->get();
    }

    /**
     * Revenue report table (theo ngày - đồng bộ 100% với getRevenue)
     */
    public function getRevenueReport($startDate, $endDate)
    {
        $query = Order::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(order_id) as total_orders'),
            DB::raw('SUM(grand_total) as total_revenue')
        )->whereBetween('created_at', [$startDate, $endDate]);

        return $this->applyValidRevenueFilter($query)
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
            DB::raw('SUM(CASE WHEN (payment_status = "paid" OR fulfillment_status = "completed") AND fulfillment_status NOT IN ("cancelled", "refunded", "returned", "return_approved") THEN grand_total ELSE 0 END) as total_revenue'),
            DB::raw('SUM(CASE WHEN fulfillment_status = "completed" THEN 1 ELSE 0 END) as completed_orders'),
            DB::raw('SUM(CASE WHEN fulfillment_status IN ("cancelled", "refunded", "returned", "return_approved") THEN 1 ELSE 0 END) as cancelled_orders')
        )
            ->whereNotNull('seller_id')
            ->where(function ($q) {
                $q->whereNull('is_abandoned_checkout')->orWhere('is_abandoned_checkout', 0);
            })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('seller_id')
            ->orderByDesc('total_revenue')
            ->with('seller:admin_id,full_name,email,role')
            ->get();
    }

    /**
     * Lấy danh sách sản phẩm tồn kho lâu & bán chậm (Dead Stock / Slow Moving Inventory)
     *
     * @param int $daysThreshold Số ngày đăng bán tối thiểu (mặc định 60 ngày)
     * @param int $salesLimit Ngưỡng số lượng bán ra trong 30 ngày qua (mặc định <= 2 đơn vị)
     * @param int $limit Số lượng bản ghi tối đa
     */
    public function getSlowMovingProducts(int $daysThreshold = 60, int $salesLimit = 2, int $limit = 20)
    {
        $cutoffDate = Carbon::now()->subDays($daysThreshold);
        $salesWindow = Carbon::now()->subDays(30);

        return Product::select(
                'products.product_id',
                'products.name',
                'products.slug',
                'products.thumbnail_url',
                'products.min_price',
                'products.category_id',
                'products.brand_id',
                'products.created_at',
                'products.published_at'
            )
            ->with(['category:category_id,name', 'brand:brand_id,name'])
            ->withSum('variants as total_stock', 'stock')
            ->withSum(['orderItems as recent_sold' => function ($q) use ($salesWindow) {
                $q->whereHas('order', function ($oq) use ($salesWindow) {
                    $oq->where('created_at', '>=', $salesWindow)
                        ->whereNotIn('fulfillment_status', [
                            OrderStatus::CANCELLED->value,
                            OrderStatus::RETURN_APPROVED->value,
                            OrderStatus::RETURNED->value,
                            OrderStatus::REFUNDED->value,
                        ]);
                });
            }], 'quantity')
            ->where('products.status', 'active')
            ->where(function ($q) use ($cutoffDate) {
                $q->where('products.published_at', '<=', $cutoffDate)
                    ->orWhere(function ($sub) use ($cutoffDate) {
                        $sub->whereNull('products.published_at')
                            ->where('products.created_at', '<=', $cutoffDate);
                    });
            })
            ->having('total_stock', '>', 0)
            ->havingRaw('COALESCE(recent_sold, 0) <= ?', [$salesLimit])
            ->orderByDesc('total_stock')
            ->limit($limit)
            ->get();
    }

    /**
     * Thống kê tổng hợp số lượng sản phẩm tồn lâu và tổng vốn tồn đọng
     */
    public function getSlowMovingSummary(int $daysThreshold = 60, int $salesLimit = 2): array
    {
        $products = $this->getSlowMovingProducts($daysThreshold, $salesLimit, 1000);

        $totalCount = $products->count();
        $totalStock = $products->sum('total_stock');
        $totalCapital = $products->sum(function ($p) {
            return ($p->total_stock ?? 0) * ($p->min_price ?? 0);
        });

        return [
            'slow_moving_count' => $totalCount,
            'total_stagnant_stock' => $totalStock,
            'tied_up_capital' => (float) $totalCapital,
        ];
    }
}
