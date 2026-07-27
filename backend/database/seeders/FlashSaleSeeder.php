<?php

namespace Database\Seeders;

use App\Models\FlashSale;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Redis;

class FlashSaleSeeder extends Seeder
{
    public function run(): void
    {
        // Lấy sản phẩm đầu tiên có sẵn trong DB
        $product = Product::first();

        if (! $product) {
            $this->command->warn('Không tìm thấy sản phẩm nào. Vui lòng seed products trước.');

            return;
        }

        $originalPrice = (float) ($product->price ?? 500000);
        $salePrice = round($originalPrice * 0.5); // Giảm 50%

        $flashSale = FlashSale::create([
            'product_id' => $product->product_id,
            'variant_id' => null,
            'title' => '⚡ FLASH SALE - Giá Sốc Hôm Nay!',
            'description' => 'Ưu đãi có 1-0-2, số lượng cực kỳ có hạn. Mua ngay kẻo lỡ!',
            'total_stock' => 100,
            'sold_count' => 0,
            'sale_price' => $salePrice,
            'original_price' => $originalPrice,
            'max_per_user' => 1,
            'starts_at' => now(),
            'ends_at' => now()->addHours(3), // Kéo dài 3 tiếng
            'status' => 'active',
        ]);

        // Seed stock vào Redis ngay
        $flashSale->seedStockToRedis();

        $this->command->info('✅ Flash Sale tạo thành công!');
        $this->command->info("   ID       : #{$flashSale->id}");
        $this->command->info("   Product  : {$product->name}");
        $this->command->info('   Giá Sale : '.number_format($salePrice, 0, ',', '.').'đ');
        $this->command->info('   Giá Gốc  : '.number_format($originalPrice, 0, ',', '.').'đ');
        $this->command->info('   Stock    : 100 sản phẩm');
        $this->command->info('   Redis Key: '.$flashSale->stockKey());
        $this->command->info('   Ends At  : '.$flashSale->ends_at->format('H:i:s d/m/Y'));
    }
}
