<?php

namespace App\Console\Commands;

use App\Services\LoyaltyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * ExpireLoyaltyPoints — Artisan Command hàng ngày expire điểm hết hạn
 *
 * CHẠY: php artisan loyalty:expire-points
 * LỊCH: Hàng ngày lúc 02:00 AM (đặt trong Kernel.php)
 */
class ExpireLoyaltyPoints extends Command
{
    protected $signature   = 'loyalty:expire-points';
    protected $description = 'Expire điểm thưởng đã hết hạn của tất cả user';

    public function __construct(
        protected LoyaltyService $loyaltyService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🕐 [' . now()->format('Y-m-d H:i:s') . '] Bắt đầu expire điểm thưởng hết hạn...');

        try {
            $count = $this->loyaltyService->expirePoints();

            if ($count === 0) {
                $this->info('✅ Không có điểm nào hết hạn.');
            } else {
                $this->info("✅ Đã expire điểm cho {$count} user.");
            }

            Log::info("LoyaltyExpiry Command: Expired points for {$count} users.");

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Lỗi: ' . $e->getMessage());
            Log::error('LoyaltyExpiry Command failed: ' . $e->getMessage());
            return 1;
        }
    }
}
