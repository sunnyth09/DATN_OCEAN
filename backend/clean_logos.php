<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

echo "Bắt đầu xoá logo (file .svg) khỏi sản phẩm...\n";

// 1. Lấy tất cả các hình ảnh SVG trong product_images
$svgImages = DB::table('product_images')->where('image_url', 'like', '%.svg%')->get();

$deletedCount = 0;
$updatedProducts = 0;

foreach ($svgImages as $svg) {
    // Xóa file vật lý
    $filePath = str_replace('/storage/', '', $svg->image_url);
    if (Storage::disk('public')->exists($filePath)) {
        Storage::disk('public')->delete($filePath);
    }

    // Xóa khỏi DB
    DB::table('product_images')->where('image_id', $svg->image_id)->delete();
    $deletedCount++;

    // Cập nhật lại thumbnail và is_main cho sản phẩm này
    $productId = $svg->product_id;

    // Lấy hình ảnh tiếp theo của sản phẩm (sắp xếp theo sort_order)
    $nextImages = DB::table('product_images')
        ->where('product_id', $productId)
        ->orderBy('sort_order')
        ->get();

    if ($nextImages->count() > 0) {
        $firstImage = $nextImages->first();

        // Đặt ảnh này thành main
        DB::table('product_images')->where('image_id', $firstImage->image_id)->update([
            'is_main' => 1,
            'sort_order' => 0,
        ]);

        // Cập nhật lại sort_order cho các ảnh còn lại
        $order = 1;
        foreach ($nextImages as $img) {
            if ($img->image_id !== $firstImage->image_id) {
                DB::table('product_images')->where('image_id', $img->image_id)->update([
                    'sort_order' => $order,
                ]);
                $order++;
            }
        }

        // Cập nhật thumbnail_url cho product
        DB::table('products')->where('product_id', $productId)->update([
            'thumbnail_url' => $firstImage->image_url,
        ]);

        $updatedProducts++;
    } else {
        // Nếu không còn ảnh nào, set null
        DB::table('products')->where('product_id', $productId)->update([
            'thumbnail_url' => null,
        ]);
    }
}

echo "Đã xoá $deletedCount logo (.svg).\n";
echo "Đã cập nhật lại ảnh chính cho $updatedProducts sản phẩm.\n";
