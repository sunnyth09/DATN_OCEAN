<?php

namespace App\Repositories;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Carbon\Carbon;

class ProductRepository
{
    // ─── Eager-load helpers ────────────────────────────────────────────

    /**
     * Base eager loads dùng cho danh sách sản phẩm (admin + public)
     */
    private function listEagerLoads(): array
    {
        return [
            'mainImage' => function ($q) {
                $q->select('image_id', 'image_url', 'product_id');
            },
            'lowestPriceVariant' => function ($q) {
                $q->select('variant_id', 'price', 'compare_at_price', 'sale_price', 'sale_starts_at', 'sale_ends_at', 'stock', 'product_id');
            },
            'variants' => function ($q) {
                $q->select('variant_id', 'price', 'compare_at_price', 'sale_price', 'sale_starts_at', 'sale_ends_at', 'product_id');
            },
            'category:category_id,name',
            'brand:brand_id,name',
        ];
    }

    // ─── Admin queries ─────────────────────────────────────────────────

    /**
     * Admin: danh sách sản phẩm (phân trang, tìm kiếm, lọc)
     *
     * @param  array  $matchedIds  Mảng ID từ Meilisearch (null nếu không search)
     * @param  array  $filters  [status, category_id, price_range, sort_by]
     */
    public function getAdminProducts(?array $matchedIds, array $filters, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;

        $query = Product::with($this->listEagerLoads())
            ->withSum('variants', 'stock');

        // Search — đã được xử lý ở Service: truyền matchedIds hoặc LIKE
        if ($matchedIds !== null) {
            $query->whereIn('product_id', $matchedIds);
        } elseif (! empty($filters['search_like'])) {
            $search = $filters['search_like'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Status
        if (! empty($filters['status'])) {
            if ($filters['status'] === 'deleted') {
                $query->onlyTrashed();
            } elseif (in_array($filters['status'], ['draft', 'active', 'inactive', 'out_of_stock'])) {
                $query->where('status', $filters['status'])->whereNull('deleted_at');
            }
        }

        // Category (bao gồm cả danh mục con)
        if (! empty($filters['category_ids'])) {
            $query->whereIn('category_id', $filters['category_ids']);
        }

        // Price range
        if (! empty($filters['price_range'])) {
            match ($filters['price_range']) {
                'under-500k' => $query->where('min_price', '<', 500000),
                '500k-1m' => $query->whereBetween('min_price', [500000, 1000000]),
                'above-1m' => $query->where('min_price', '>', 1000000),
                default => null,
            };
        }

        // Max price slider
        if (isset($filters['max_price']) && is_numeric($filters['max_price'])) {
            $query->where('min_price', '<=', $filters['max_price']);
        }

        // Brand ids
        if (! empty($filters['brand_ids'])) {
            $brandIds = explode(',', $filters['brand_ids']);
            $query->whereIn('brand_id', $brandIds);
        }

        // Push out-of-stock items to the bottom
        $query->orderByRaw('COALESCE(variants_sum_stock, 0) > 0 DESC');

        // Sort
        match ($filters['sort_by'] ?? null) {
            'oldest' => $query->orderBy('created_at', 'asc'),
            'price-asc' => $query->orderBy('min_price', 'asc'),
            'price-desc' => $query->orderBy('min_price', 'desc'),
            default => $query->orderBy('product_id', 'desc'),
        };

        $total = $query->count();
        $products = $query->offset($offset)->limit($limit)->get();
        $maxPriceLimit = Product::where('status', 'active')->max('max_price') ?? 10000000;

        return [
            'data' => $products,
            'total' => $total,
            'total_pages' => ceil($total / $limit),
            'page' => $page,
            'limit' => $limit,
            'max_price_limit' => (float) $maxPriceLimit,
        ];
    }

    // ─── Public queries ────────────────────────────────────────────────

    /**
     * Public: danh sách tất cả sản phẩm active (phân trang)
     */
    public function getPublicProducts(int $offset, int $limit)
    {
        return Product::with([
            'mainImage' => function ($q) {
                $q->select('image_id', 'image_url', 'product_id');
            },
            'lowestPriceVariant' => function ($q) {
                $q->select('variant_id', 'price', 'compare_at_price', 'sale_price', 'sale_starts_at', 'sale_ends_at', 'stock', 'product_id');
            },
        ])
            ->withSum('variants', 'stock')
            ->where('status', 'active')
            ->orderByRaw('COALESCE(variants_sum_stock, 0) > 0 DESC')
            ->orderBy('product_id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    /**
     * Public: sản phẩm nổi bật (is_featured = true)
     */
    public function getFeaturedProducts(int $limit = 4)
    {
        return Product::with($this->listEagerLoads())
            ->withSum('variants', 'stock')
            ->where('is_featured', true)
            ->where('status', 'active')
            ->orderByRaw('COALESCE(variants_sum_stock, 0) > 0 DESC')
            ->orderBy('product_id', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Public: sản phẩm bán chạy nhất (theo sold_count)
     */
    public function getBestSellingProducts(int $limit = 8)
    {
        return Product::with($this->listEagerLoads())
            ->withSum('variants', 'stock')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->orderByRaw('COALESCE(variants_sum_stock, 0) > 0 DESC')
            ->orderBy('sold_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Public: sản phẩm đang sale
     * Bắt cả 2 trường hợp:
     *   1. Có sale_price active (trong khung thời gian hoặc vô thời hạn)
     *   2. Có compare_at_price > price (giá gốc gạch ngang)
     */
    public function getOnSaleProducts(int $limit = 8)
    {
        $now = Carbon::now();

        return Product::with($this->listEagerLoads())
            ->withSum('variants', 'stock')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereHas('variants', function ($q) use ($now) {
                $q->where('status', 'active')
                    ->where(function ($q2) use ($now) {
                        // Trường hợp 1: Có sale_price active
                        $q2->where(function ($q3) use ($now) {
                            $q3->whereNotNull('sale_price')
                                ->where('sale_price', '>', 0)
                                ->where(function ($q4) use ($now) {
                                    // Không có thời hạn → sale vô thời hạn
                                    $q4->where(function ($q5) {
                                        $q5->whereNull('sale_starts_at')
                                            ->whereNull('sale_ends_at');
                                    })
                                    // Hoặc đang trong khoảng thời gian sale
                                        ->orWhere(function ($q5) use ($now) {
                                            $q5->where('sale_starts_at', '<=', $now)
                                                ->where('sale_ends_at', '>=', $now);
                                        })
                                    // Hoặc chỉ có starts_at (chưa hết hạn)
                                        ->orWhere(function ($q5) use ($now) {
                                            $q5->whereNotNull('sale_starts_at')
                                                ->whereNull('sale_ends_at')
                                                ->where('sale_starts_at', '<=', $now);
                                        });
                                });
                        })
                        // Trường hợp 2: compare_at_price > price (giá so sánh)
                            ->orWhere(function ($q3) {
                                $q3->whereNotNull('compare_at_price')
                                    ->whereColumn('compare_at_price', '>', 'price');
                            });
                    });
            })
            ->orderByRaw('COALESCE(variants_sum_stock, 0) > 0 DESC')
            ->orderBy('sold_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Public: sản phẩm nổi bật (all featured — không limit)
     */
    public function getAllFeaturedProducts()
    {
        return Product::with([
            'mainImage' => function ($q) {
                $q->select('image_id', 'image_url', 'product_id');
            },
            'lowestPriceVariant' => function ($q) {
                $q->select('variant_id', 'price', 'compare_at_price', 'sale_price', 'sale_starts_at', 'sale_ends_at', 'stock', 'product_id');
            },
        ])
            ->withSum('variants', 'stock')
            ->where('status', 'active')
            ->where('is_featured', true)
            ->orderByRaw('COALESCE(variants_sum_stock, 0) > 0 DESC')
            ->orderBy('product_id', 'desc')
            ->get();
    }

    /**
     * Tìm sản phẩm theo slug hoặc ID (full relations)
     */
    public function findByIdentifier($identifier)
    {
        $query = Product::with([
            'category',
            'brand',
            'images',
            'variants' => function ($q) {
                $q->where('status', 'active')->orderBy('price', 'asc');
            },
        ]);

        if (is_numeric($identifier)) {
            $query->where('product_id', $identifier)->orWhere('slug', $identifier);
        } else {
            $query->where('slug', $identifier);
        }

        return $query->first();
    }

    /**
     * Tìm sản phẩm theo slug hoặc ID (basic relations, dùng cho related)
     */
    public function findByIdentifierBasic($identifier)
    {
        $query = Product::with(['category', 'brand', 'images', 'variants']);

        if (is_numeric($identifier)) {
            $query->where('product_id', $identifier)->orWhere('slug', $identifier);
        } else {
            $query->where('slug', $identifier);
        }

        return $query->first();
    }

    /**
     * Sản phẩm liên quan (cùng category, loại trừ SP hiện tại)
     */
    public function getRelatedProducts(int $productId, int $categoryId, int $limit = 4)
    {
        return Product::with([
            'mainImage' => function ($q) {
                $q->select('image_id', 'image_url', 'product_id');
            },
            'lowestPriceVariant' => function ($q) {
                $q->select('variant_id', 'price', 'compare_at_price', 'sale_price', 'sale_starts_at', 'sale_ends_at', 'stock', 'product_id');
            },
            'variants' => function ($q) {
                $q->select('variant_id', 'price', 'compare_at_price', 'sale_price', 'sale_starts_at', 'sale_ends_at', 'product_id');
            },
            'category:category_id,name',
        ])
            ->withSum('variants', 'stock')
            ->where('category_id', $categoryId)
            ->where('product_id', '!=', $productId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->orderByRaw('COALESCE(variants_sum_stock, 0) > 0 DESC')
            ->orderBy('product_id', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Lấy variants active của sản phẩm
     */
    public function getActiveVariants(int $productId)
    {
        $product = Product::with(['variants' => function ($q) {
            $q->where('status', 'active')->orderBy('color')->orderBy('size');
        }])->where('product_id', $productId)->first();

        return $product;
    }

    /**
     * Admin: lấy sản phẩm theo ID (với full relations)
     */
    public function findForEdit(int $id)
    {
        return Product::with(['category', 'brand', 'images', 'variants'])
            ->findOrFail($id);
    }

    // ─── Create / Update / Delete ─────────────────────────────────────

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(Product $product, array $data): bool
    {
        return $product->update($data);
    }

    public function softDelete(int $id): string
    {
        $product = Product::withTrashed()->findOrFail($id);

        if ($product->trashed()) {
            $product->forceDelete();

            return 'Xóa vĩnh viễn sản phẩm thành công.';
        }

        $product->delete();

        return 'Xóa sản phẩm thành công.';
    }

    public function restore(int $id): void
    {
        $product = Product::withTrashed()->findOrFail($id);
        $product->restore();
    }

    // ─── Variant operations ────────────────────────────────────────────

    public function createVariant(array $data): ProductVariant
    {
        return ProductVariant::create($data);
    }

    public function deleteProductVariants(int $productId): void
    {
        ProductVariant::where('product_id', $productId)->delete();
    }

    /**
     * Xóa cart items tham chiếu đến variants cũ (tránh FK constraint khi update variants)
     */
    public function deleteCartItemsByVariants(array $variantIds): void
    {
        if (! empty($variantIds)) {
            CartItem::whereIn('variant_id', $variantIds)->delete();
        }
    }

    /**
     * Lấy tất cả variant IDs của 1 product
     */
    public function getVariantIds(int $productId): array
    {
        return Product::find($productId)
            ?->variants()
            ->pluck('variant_id')
            ->toArray() ?? [];
    }

    /**
     * Lấy variant đầu tiên của product (dùng cho simple product)
     */
    public function getFirstVariant(int $productId): ?ProductVariant
    {
        return Product::find($productId)?->variants()->first();
    }

    // ─── Image operations ──────────────────────────────────────────────

    public function createImage(array $data): ProductImage
    {
        return ProductImage::create($data);
    }

    public function deleteMainImage(int $productId): void
    {
        ProductImage::where('product_id', $productId)
            ->where('is_main', true)
            ->delete();
    }

    public function deleteImagesByIds(array $ids, int $productId)
    {
        return ProductImage::whereIn('image_id', $ids)
            ->where('product_id', $productId)
            ->get();
    }

    public function getMaxImageSortOrder(int $productId): int
    {
        return ProductImage::where('product_id', $productId)->max('sort_order') ?? 0;
    }

    /**
     * Lấy variant images trước khi xóa variants (để tái gán)
     */
    public function getVariantImages(int $productId, int $variantId)
    {
        return ProductImage::where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->get();
    }

    /**
     * Update min/max price từ danh sách giá
     */
    public function updateMinMaxPrice(Product $product, array $prices = []): void
    {
        // Tính toán lại min_price và max_price thực tế từ các variants (bỏ qua mảng $prices truyền vào vì thiếu sale_price)
        $variants = $product->variants()->get();
        if ($variants->isEmpty()) {
            return;
        }

        $minPrice = null;
        $maxPrice = null;
        $now = now();

        foreach ($variants as $variant) {
            // Lấy giá cơ bản
            $effectivePrice = $variant->price;

            // Nếu có sale_price hợp lệ và đang trong thời gian sale thì lấy sale_price
            if ($variant->sale_price !== null && $variant->sale_price > 0) {
                $start = $variant->sale_starts_at ? Carbon::parse($variant->sale_starts_at) : null;
                $end = $variant->sale_ends_at ? Carbon::parse($variant->sale_ends_at) : null;

                $isActiveSale = true;
                if ($start && $now->lt($start)) {
                    $isActiveSale = false;
                }
                if ($end && $now->gt($end)) {
                    $isActiveSale = false;
                }

                if ($isActiveSale) {
                    $effectivePrice = $variant->sale_price;
                }
            }

            if ($minPrice === null || $effectivePrice < $minPrice) {
                $minPrice = $effectivePrice;
            }
            if ($maxPrice === null || $effectivePrice > $maxPrice) {
                $maxPrice = $effectivePrice;
            }
        }

        $product->update([
            'min_price' => $minPrice ?? 0,
            'max_price' => $maxPrice ?? 0,
        ]);
    }
}
