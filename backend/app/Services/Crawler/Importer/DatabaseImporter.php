<?php

namespace App\Services\Crawler\Importer;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Support\Str;

class DatabaseImporter
{
    /**
     * Import product data into database.
     */
    public function import(array $productData, array $variantsData, int $categoryId, ?int $brandId, array $imagesData): ?Product
    {
        // Skip duplicate
        if (Product::where('slug', $productData['slug'])->exists()) {
            return null;
        }

        $product = Product::create([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'seller_id' => 1, // Default seller (admin)
            'name' => $productData['name'],
            'slug' => $productData['slug'],
            'short_description' => $productData['short_description'],
            'description' => $productData['description'],
            'status' => 'active',
            'min_price' => $productData['sale_price'],
            'max_price' => $productData['original_price'],
        ]);

        foreach ($variantsData as $v) {
            ProductVariant::create([
                'product_id' => $product->product_id,
                'sku' => empty($v['sku']) ? 'SKU-'.strtoupper(Str::random(8)) : $v['sku'],
                'variant_name' => $v['variant_name'],
                'color' => $v['color'],
                'size' => $v['size'],
                'price' => $v['price'],
                'sale_price' => $v['sale_price'],
                'stock' => $v['stock'],
                'status' => 'active',
            ]);
        }

        foreach ($imagesData as $index => $imageUrl) {
            ProductImage::create([
                'product_id' => $product->product_id,
                'variant_id' => null,
                'image_url' => $imageUrl,
                'sort_order' => $index,
                'is_main' => $index === 0 ? 1 : 0,
            ]);
        }

        if (! empty($imagesData)) {
            $product->update(['thumbnail_url' => $imagesData[0]]);
        }

        return $product;
    }
}
