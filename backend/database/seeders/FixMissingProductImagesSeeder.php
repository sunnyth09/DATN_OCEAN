<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductImage;

class FixMissingProductImagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Hình ảnh mặc định cho các sản phẩm chưa có ảnh
        $defaultImage = 'https://placehold.co/600x600/f3f4f6/6b7280.png?text=No+Image';
        $products = Product::all();
        $count = 0;

        foreach ($products as $product) {
            $updated = false;

            // Kiểm tra và cập nhật thumbnail_url nếu trống
            if (empty($product->thumbnail_url)) {
                $product->thumbnail_url = $defaultImage;
                $product->save();
                $updated = true;
            }

            // Kiểm tra và thêm ảnh vào bảng product_images nếu chưa có
            if ($product->images()->count() === 0) {
                ProductImage::create([
                    'product_id' => $product->product_id,
                    'image_url' => $defaultImage,
                    'is_main' => 1,
                    'alt_text' => $product->name,
                    'sort_order' => 1
                ]);
                $updated = true;
            }

            if ($updated) {
                $count++;
            }
        }

        $this->command->info("Đã cập nhật ảnh mặc định thành công cho {$count} sản phẩm.");
    }
}
