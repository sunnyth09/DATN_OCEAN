<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResetCustomerTiers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-customer-tiers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset hạng thành viên hàng tháng: đặt tier_month_spent = 0 và tier_id = NULL (Hạng Đồng)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Bắt đầu reset hạng thành viên tháng mới...');

        try {
            $affected = DB::table('users')
                ->where('role', 'customer')
                ->update([
                    'tier_month_spent' => 0,
                    'tier_id' => null,
                ]);

            $this->info("✅ Đã reset hạng thành viên cho {$affected} khách hàng.");
            Log::info("[ResetCustomerTiers] Reset {$affected} users to tier_month_spent=0, tier_id=NULL.");

        } catch (\Exception $e) {
            $this->error('❌ Lỗi khi reset hạng: ' . $e->getMessage());
            Log::error('[ResetCustomerTiers] ' . $e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
