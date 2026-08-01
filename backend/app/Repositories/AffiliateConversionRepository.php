<?php

namespace App\Repositories;

use App\Models\AffiliateConversion;
use Illuminate\Support\Facades\DB;

class AffiliateConversionRepository
{
    public function create(array $data): AffiliateConversion
    {
        return AffiliateConversion::create($data);
    }

    public function findByOrderId(int $orderId): ?AffiliateConversion
    {
        return AffiliateConversion::where('order_id', $orderId)->first();
    }

    /**
     * Danh sách conversions của 1 referrer (có phân trang)
     */
    public function getByReferrer(int $referrerId, int $perPage = 15)
    {
        return AffiliateConversion::with(['buyer:user_id,full_name,email', 'order:order_id,order_code,grand_total,fulfillment_status,created_at'])
            ->where('referrer_id', $referrerId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Thống kê tổng hợp cho 1 referrer
     */
    public function getSummaryByReferrer(int $referrerId): array
    {
        $result = AffiliateConversion::where('referrer_id', $referrerId)
            ->select(
                DB::raw('COUNT(*) as total_conversions'),
                DB::raw('SUM(total_amount) as total_revenue'),
                DB::raw("SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END) as pending_commission"),
                DB::raw("SUM(CASE WHEN status = 'approved' THEN commission_amount ELSE 0 END) as approved_commission"),
                DB::raw("SUM(CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END) as paid_commission")
            )
            ->first();

        return [
            'total_conversions' => (int) ($result->total_conversions ?? 0),
            'total_revenue' => (float) ($result->total_revenue ?? 0),
            'pending_commission' => (float) ($result->pending_commission ?? 0),
            'approved_commission' => (float) ($result->approved_commission ?? 0),
            'paid_commission' => (float) ($result->paid_commission ?? 0),
        ];
    }

    /**
     * Thống kê theo ngày/tháng/năm
     */
    public function getStatsByReferrer(int $referrerId, string $type = 'month'): array
    {
        $groupBy = match ($type) {
            'day' => DB::raw('DATE(created_at) as period'),
            'year' => DB::raw('YEAR(created_at) as period'),
            default => DB::raw("DATE_FORMAT(created_at, '%Y-%m') as period"),
        };

        return AffiliateConversion::where('referrer_id', $referrerId)
            ->where('status', '!=', 'cancelled')
            ->select(
                $groupBy,
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(total_amount) as total_revenue'),
                DB::raw('SUM(commission_amount) as total_commission')
            )
            ->groupBy('period')
            ->orderByDesc('period')
            ->limit(30)
            ->get()
            ->toArray();
    }

    public function updateStatus(int $id, string $status): bool
    {
        return AffiliateConversion::where('id', $id)->update(['status' => $status]) > 0;
    }

    /**
     * Cập nhật status theo order_id
     */
    public function updateStatusByOrderId(int $orderId, string $status): bool
    {
        return AffiliateConversion::where('order_id', $orderId)->update(['status' => $status]) > 0;
    }

    /**
     * Chuyển approved → cancelled bằng conditional UPDATE atomic.
     * Chỉ request THẮNG race (status đang 'approved') mới nhận true → clawback
     * hoa hồng đúng 1 lần dù 2 request hủy đồng thời (không phụ thuộc rowCount
     * semantics của PDO như khi so sánh $oldStatus đọc trước).
     */
    public function markCancelledFromApproved(int $orderId): bool
    {
        return AffiliateConversion::where('order_id', $orderId)
            ->where('status', 'approved')
            ->update(['status' => 'cancelled']) > 0;
    }

    /**
     * Admin: danh sách tất cả conversions (có phân trang)
     */
    public function adminList(int $perPage = 15)
    {
        return AffiliateConversion::with([
            'referrer:user_id,full_name,email',
            'buyer:user_id,full_name,email',
            'order:order_id,order_code,grand_total,fulfillment_status',
        ])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
