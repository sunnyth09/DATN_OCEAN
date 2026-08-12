<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SyncProductSoldCount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:sync-sold-count';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Đồng bộ lại số lượng bán (sold_count) cho toàn bộ sản phẩm từ lịch sử đơn hàng thực tế';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Bắt đầu đồng bộ số lượng bán (sold_count) cho toàn bộ sản phẩm...');

        // 1. Tính tổng số lượng đã bán từ các đơn hàng hợp lệ (không phải bị hủy)
        $salesData = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.order_id')
            ->join('product_variants', 'order_items.variant_id', '=', 'product_variants.variant_id')
            ->whereNotIn('orders.fulfillment_status', ['cancelled', 'failed'])
            ->select('product_variants.product_id', DB::raw('SUM(order_items.quantity) as total_sold'))
            ->groupBy('product_variants.product_id')
            ->pluck('total_sold', 'product_id');

        // 2. Reset tất cả sold_count về 0 trước
        Product::query()->update(['sold_count' => 0]);

        $updatedCount = 0;

        // 3. Cập nhật lại sold_count theo số liệu tính được
        foreach ($salesData as $productId => $totalSold) {
            Product::where('product_id', $productId)->update(['sold_count' => (int) $totalSold]);
            $updatedCount++;
        }

        // 4. Xóa Cache sản phẩm bán chạy
        Cache::tags(['products:best-selling'])->flush();

        $this->info("Đã đồng bộ thành công sold_count cho {$updatedCount} sản phẩm!");
        $this->info('Đã làm mới bộ nhớ đệm (Cache) bán chạy.');

        return Command::SUCCESS;
    }
}
