<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function getDashboardData(Request $request)
    {
        $totalCustomers = User::where('role', 'customer')->count();
        $totalProducts = Product::count();
        $totalOrders = Order::whereNotIn('fulfillment_status', ['cancelled'])->count();

        $totalRevenue = Order::where('payment_status', 'paid')
            ->orWhere('fulfillment_status', 'completed')
            ->sum('grand_total');

        $last7Days = collect();
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $label = Carbon::now()->subDays($i)->isoFormat('dddd');
            $shortLabel = $this->getShortDayLabel($label);

            $dayRevenue = Order::whereDate('created_at', $date)
                ->where(function ($q) {
                    $q->where('payment_status', 'paid')
                        ->orWhere('fulfillment_status', 'completed');
                })->sum('grand_total');

            $last7Days->push([
                'label' => $shortLabel,
                'valRaw' => $dayRevenue,
                'date' => $date,
            ]);
        }

        $maxRevenue = $last7Days->max('valRaw') ?: 1;
        $revenueChart = $last7Days->map(function ($item) use ($maxRevenue) {
            $h = ($item['valRaw'] / $maxRevenue) * 100;

            return [
                'label' => $item['label'],
                'val' => number_format($item['valRaw'], 0).' đ',
                'h' => $item['valRaw'] > 0 ? max($h, 5) : 0,
            ];
        });

        $recentOrders = Order::with(['user', 'items.product'])
            ->latest()
            ->limit(4)
            ->get()
            ->map(function ($order) {
                // Determine product label
                $productName = 'No Product';
                if ($order->items->isNotEmpty() && $order->items->first()->product) {
                    $productName = $order->items->first()->product->name;
                    $itemCount = $order->items->count();
                    if ($itemCount > 1) {
                        $productName .= ' + '.($itemCount - 1).' items';
                    }
                }

                // Determine user label
                $userName = $order->user ? $order->user->full_name : $order->recipient_name;
                if (! $userName) {
                    $userName = 'Khách lẻ';
                }

                $initials = 'NA';
                $parts = explode(' ', trim($userName));
                if (count($parts) > 0 && ! empty($parts[0])) {
                    $initials = mb_strtoupper(mb_substr($parts[0], 0, 1));
                    if (count($parts) > 1) {
                        $initials .= mb_strtoupper(mb_substr(end($parts), 0, 1));
                    }
                }

                $statusText = 'Chờ xử lý';
                $statusClass = 'pending';
                if ($order->fulfillment_status == 'completed' || $order->fulfillment_status == 'delivered') {
                    $statusText = 'Hoàn thành';
                    $statusClass = 'done';
                } elseif ($order->fulfillment_status == 'shipped' || $order->fulfillment_status == 'shipping') {
                    $statusText = 'Đang giao';
                    $statusClass = 'shipped';
                } elseif ($order->fulfillment_status == 'cancelled') {
                    $statusText = 'Đã hủy';
                    $statusClass = 'coral'; // Will need some CSS mapped for coral background if not exists
                }

                return [
                    'id' => $order->order_id,
                    'name' => $userName,
                    'product' => $productName,
                    'amount' => number_format($order->grand_total, 0).' đ',
                    'status' => $statusClass,
                    'statusText' => $statusText,
                    'init' => $initials,
                    'bg' => $this->getRandomColor($initials),
                ];
            });

        // MONTHLY REVENUE (Last 6 Months)
        $lastMonths = collect();
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = Carbon::now()->startOfMonth()->subMonths($i);
            $monthLabel = 'T'.$monthDate->format('n');

            $monthRevenue = Order::whereYear('created_at', $monthDate->year)
                ->whereMonth('created_at', $monthDate->month)
                ->where(function ($q) {
                    $q->where('payment_status', 'paid')
                        ->orWhere('fulfillment_status', 'completed');
                })->sum('grand_total');

            $lastMonths->push([
                'label' => $monthLabel,
                'valRaw' => $monthRevenue,
            ]);
        }
        $maxMonthRevenue = $lastMonths->max('valRaw') ?: 1;
        $revenueChartMonth = $lastMonths->map(function ($item) use ($maxMonthRevenue) {
            $h = ($item['valRaw'] / $maxMonthRevenue) * 100;

            return [
                'label' => $item['label'],
                'val' => number_format($item['valRaw'], 0).' đ',
                'h' => $item['valRaw'] > 0 ? max($h, 5) : 0,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'stats' => [
                    'revenue' => number_format($totalRevenue, 0).' đ',
                    'orders' => number_format($totalOrders, 0),
                    'products' => number_format($totalProducts, 0),
                    'customers' => number_format($totalCustomers, 0),
                ],
                'revenue_chart' => $revenueChart,
                'revenue_chart_month' => $revenueChartMonth,
                'recent_orders' => $recentOrders,
            ],
        ]);
    }

    private function getShortDayLabel($englishDay)
    {
        $map = [
            'Monday' => 'T2',
            'Tuesday' => 'T3',
            'Wednesday' => 'T4',
            'Thursday' => 'T5',
            'Friday' => 'T6',
            'Saturday' => 'T7',
            'Sunday' => 'CN',
        ];

        return $map[$englishDay] ?? 'T2';
    }

    private function getRandomColor($string)
    {
        $colors = ['#0288d1', '#26a69a', '#ffa726', '#7e57c2', '#ef5350', '#66bb6a', '#ec407a'];
        $sum = 0;
        for ($i = 0; $i < strlen($string); $i++) {
            $sum += ord($string[$i]);
        }

        return $colors[$sum % count($colors)];
    }

    /**
     * GET /admin/sidebar-badges
     * Trả về các con số badge cho sidebar menu:
     * đơn hàng chờ xỮd lý, hoàn hàng chờ duyệt, ticket chưa giải quyết, live chat chưa đọc.
     */
    public function getSidebarBadges()
    {
        // Đơn hàng mới chưa xác nhận (pending)
        $pendingOrders = Order::where('fulfillment_status', 'pending')->count();

        // Hoàn hàng đang chờ duyệt
        $pendingReturns = 0;
        if (class_exists('\App\Models\ReturnRequest')) {
            $pendingReturns = ReturnRequest::where('status', 'return_pending')->count();
        }

        // Ticket khiếu nại chưa giải quyết + Đánh giá sản phẩm chờ duyệt
        $openTickets = 0;
        if (class_exists('\\App\\Models\\Ticket')) {
            $openTickets += Ticket::whereIn('status', ['pending', 'processing'])->count();
        }
        if (class_exists('\\App\\Models\\ProductComment')) {
            $openTickets += ProductComment::where('is_approved', 0)->count();
        }

        // Yêu cầu liên hệ chưa phản hồi (status = pending)
        $pendingContacts = 0;
        if (class_exists('\\App\\Models\\Contact')) {
            $pendingContacts = Contact::where('status', 'pending')->count();
        }

        // Live chat session chưa được xử lý (status = open)
        $unrepliedChats = ChatSession::where('status', 'open')->count();

        // Tổng số tin nhắn chưa đọc
        $unreadChats = 0;
        if (class_exists('\App\Models\ChatMessage')) {
            $unreadChats = ChatMessage::where('sender_type', 'user')->where('is_read', false)->count();
        }

        // Đánh giá chờ duyệt
        $pendingReviews = 0;
        if (class_exists('\App\Models\ProductComment')) {
            $pendingReviews = \App\Models\ProductComment::where('is_approved', false)->count();
        }

        // Liên hệ chưa xử lý
        $pendingContacts = 0;
        if (class_exists('\App\Models\Contact')) {
            $pendingContacts = \App\Models\Contact::where('status', 'pending')->count();
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'pending_orders' => $pendingOrders,
                'pending_returns' => $pendingReturns,
                'open_tickets' => $openTickets,
                'pending_contacts' => $pendingContacts,
                'unreplied_chats' => $unrepliedChats,
                'unread_chats' => $unreadChats,
                'pending_reviews' => $pendingReviews,
            ],
        ]);
    }
}
