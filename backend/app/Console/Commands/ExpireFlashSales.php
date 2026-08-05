<?php

namespace App\Console\Commands;

use App\Models\FlashSale;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class ExpireFlashSales extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:expire-flash-sales';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Chuyển trạng thái các Flash Sale đã hết hạn về ended và dọn dẹp Redis';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiredSales = FlashSale::where('status', 'active')
            ->where('end_time', '<=', now())
            ->with('items')
            ->get();

        if ($expiredSales->isEmpty()) {
            $this->info('Không có Flash Sale nào hết hạn.');

            return;
        }

        foreach ($expiredSales as $sale) {
            // Đổi trạng thái thành ended
            $sale->update(['status' => 'ended']);

            // Xóa Redis keys của các items
            foreach ($sale->items as $item) {
                $stockKey = "flash_sale_{$sale->id}_product_{$item->product_id}_stock";
                Redis::del($stockKey);
            }

            // Xóa cache public
            Cache::forget("flash_sale_meta_{$sale->id}");
            $this->info("Đã kết thúc Flash Sale ID: {$sale->id}");
        }

        Cache::forget('flash_sale_public_list');
        $this->info('Đã hoàn tất dọn dẹp Flash Sale hết hạn.');
    }
}
