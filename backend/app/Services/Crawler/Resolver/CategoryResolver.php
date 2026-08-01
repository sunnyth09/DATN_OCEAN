<?php

namespace App\Services\Crawler\Resolver;

use App\Models\Category;
use Illuminate\Support\Str;

class CategoryResolver
{
    /**
     * Resolve category ID based on sport name and category name.
     * Creates them if they don't exist.
     */
    public function resolve(string $sportName, string $categoryName): int
    {
        $parentSlug = Str::slug($sportName);
        $parent = Category::firstOrCreate(
            ['slug' => $parentSlug],
            [
                'name' => $sportName,
                'parent_id' => null,
                'is_active' => true,
            ]
        );

        $childSlug = Str::slug($sportName.'-'.$categoryName);
        $child = Category::firstOrCreate(
            ['slug' => $childSlug],
            [
                'name' => $categoryName,
                'parent_id' => $parent->category_id,
                'is_active' => true,
            ]
        );

        return $child->category_id;
    }
}
