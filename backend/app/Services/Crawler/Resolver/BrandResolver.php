<?php

namespace App\Services\Crawler\Resolver;

use App\Models\Brand;
use Illuminate\Support\Str;

class BrandResolver
{
    /**
     * Resolve brand ID based on brand name.
     * Creates the brand if it doesn't exist.
     */
    public function resolve(?string $brandName): ?int
    {
        if (empty($brandName) || strtolower(trim($brandName)) === 'đang cập nhật' || strtolower(trim($brandName)) === 'khác') {
            return null;
        }

        $brandName = trim($brandName);
        $slug = Str::slug($brandName);

        $brand = Brand::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $brandName,
                'is_active' => true,
            ]
        );

        return $brand->brand_id;
    }
}
