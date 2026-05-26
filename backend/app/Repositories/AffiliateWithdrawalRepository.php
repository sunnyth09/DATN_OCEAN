<?php

namespace App\Repositories;

use App\Models\AffiliateWithdrawal;
use Illuminate\Support\Facades\DB;

class AffiliateWithdrawalRepository
{
    public function create(array $data): AffiliateWithdrawal
    {
        return AffiliateWithdrawal::create($data);
    }

    /**
     * Danh sách withdrawal của 1 user
     */
    public function getByUser(int $userId)
    {
        return AffiliateWithdrawal::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Kiểm tra có yêu cầu rút tiền đang pending không
     */
    public function hasPendingWithdrawal(int $userId): bool
    {
        return AffiliateWithdrawal::where('user_id', $userId)
            ->where('status', 'pending')
            ->exists();
    }

    /**
     * Tổng tiền đã rút approved nhưng chưa paid + đang pending
     * (dùng để tính số dư khả dụng)
     */
    public function getTotalWithdrawnOrPending(int $userId): float
    {
        return (float) AffiliateWithdrawal::where('user_id', $userId)
            ->whereIn('status', ['pending', 'approved', 'paid'])
            ->sum('amount');
    }

    public function updateStatus(int $id, string $status, ?string $note = null): bool
    {
        $data = ['status' => $status];
        if ($note !== null) {
            $data['note'] = $note;
        }
        return AffiliateWithdrawal::where('id', $id)->update($data) > 0;
    }

    /**
     * Admin: danh sách tất cả withdrawals
     */
    public function adminList(int $perPage = 15)
    {
        return AffiliateWithdrawal::with('user:user_id,full_name,email')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findById(int $id): ?AffiliateWithdrawal
    {
        return AffiliateWithdrawal::find($id);
    }
}
