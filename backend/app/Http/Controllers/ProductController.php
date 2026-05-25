<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\CartItem;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ProductsImport;
use App\Exports\ProductsTemplateExport;

class ProductController extends Controller
{
    /**
     * Tạo mã barcode duy nhất cho biến thể
     */
    private function generateUniqueBarcode(): string
    {
        do {
            $barcode = 'OCN' . strtoupper(Str::random(10)) . rand(10, 99);
        } while (ProductVariant::where('barcode', $barcode)->exists());
        return $barcode;
    }

    /**
     * Tạo QR code PNG từ barcode và lưu vào storage
     * @return string Đường dẫn file QR code (relative to public disk)
     */
    private function generateQrCodeImage(string $barcode): string
    {
        $storageDisk = Storage::disk('public');
        if (!$storageDisk->exists('products/qrcodes')) {
            $storageDisk->makeDirectory('products/qrcodes');
        }

        $builder = new Builder(writer: new PngWriter());
        $result = $builder->build(data: $barcode, size: 400, margin: 15);

        $filePath = 'products/qrcodes/' . $barcode . '.png';
        $storageDisk->put($filePath, $result->getString());

        return $filePath;
    }

    /**
     * Admin: danh sách sản phẩm (phân trang, tìm kiếm, lọc status)
     */
    public function index(Request $request)
    {
        $page    = $request->query('page', 1);
        $limit   = $request->query('limit', 12);
        $search  = $request->query('search', '');
        $status  = $request->query('status', '');
        $offset  = ($page - 1) * $limit;

        $query = Product::with([
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
        ])->withSum('variants', 'stock');

        // ── Tìm kiếm qua Meilisearch Scout ─────────────────────────────────
        if ($search) {
            try {
                $matchedIds = Product::search($search)->keys()->toArray();
                if (empty($matchedIds)) {
                    return response()->json([
                        'data'        => [],
                        'total'       => 0,
                        'total_pages' => 0,
                        'page'        => (int) $page,
                        'limit'       => (int) $limit,
                    ]);
                }
                $query->whereIn('product_id', $matchedIds);
            } catch (\Throwable $e) {
                // Fallback: nếu Meilisearch down thì dùng LIKE
                Log::warning('[Scout] Meilisearch fallback: ' . $e->getMessage());
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('slug', 'like', "%{$search}%");
                });
            }
        }

        if ($status) {
            if ($status === 'deleted') {
                $query->onlyTrashed();
            } elseif (in_array($status, ['draft', 'active', 'inactive', 'out_of_stock'])) {
                $query->where('status', $status)->whereNull('deleted_at');
            }
        }

        // Lọc theo danh mục (bao gồm cả danh mục con)
        $categoryId = $request->query('category_id');
        if ($categoryId && $categoryId !== 'All') {
            $categoryIds = [$categoryId];
            $childIds    = \App\Models\Category::where('parent_id', $categoryId)->pluck('category_id')->toArray();
            $categoryIds = array_merge($categoryIds, $childIds);
            $query->whereIn('category_id', $categoryIds);
        }

        // Lọc theo thương hiệu
        $brandIds = $request->query('brand_ids');
        if ($brandIds) {
            $brands = explode(',', $brandIds);
            $query->whereIn('brand_id', $brands);
        }

        // Lọc theo giá lớn nhất (slider)
        $maxPrice = $request->query('max_price');
        if ($maxPrice !== null) {
            $query->where('min_price', '<=', $maxPrice);
        }

        // Lọc theo khoảng giá tĩnh (nếu còn dùng)
        $priceRange = $request->query('price_range');
        if ($priceRange === 'under-500k') {
            $query->where('min_price', '<', 500000);
        } elseif ($priceRange === '500k-1m') {
            $query->whereBetween('min_price', [500000, 1000000]);
        } elseif ($priceRange === 'above-1m') {
            $query->where('min_price', '>', 1000000);
        }

        // Sắp xếp
        $sortBy = $request->query('sort_by');
        if ($sortBy === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } elseif ($sortBy === 'price-asc') {
            $query->orderBy('min_price', 'asc');
        } elseif ($sortBy === 'price-desc') {
            $query->orderBy('min_price', 'desc');
        } else {
            $query->orderBy('product_id', 'desc');
        }

        $total    = $query->count();
        $products = $query->offset($offset)->limit($limit)->get();

        return response()->json([
            'data'        => $products,
            'total'       => $total,
            'total_pages' => ceil($total / $limit),
            'page'        => (int) $page,
            'limit'       => (int) $limit,
        ]);
    }
    public function productFeatured(Request $request)
    {
        $products = Cache::remember('products:productFeatured', 1800, function () {
            $query = Product::with([
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
            ]);
            return $query->orderBy('product_id', 'desc')
                ->where('is_featured', true)
                ->where('status', 'active')
                ->limit(4)
                ->orderBy('created_at', 'desc')
                ->get();
        });

        return response()->json([
            'data' => $products
        ]);
    }


    /**
     * Chi tiết sản phẩm theo slug (client)
     */
    public function show($identifier)
    {
        $product = Cache::remember("product:identifier:{$identifier}", 1800, function () use ($identifier) {
            $query = Product::with([
                'category',
                'brand',
                'images',
                'variants' => function ($q) {
                    // Sắp xếp variants theo giá tăng dần để frontend dễ tính premium upsell
                    $q->where('status', 'active')->orderBy('price', 'asc');
                },
            ]);

            if (is_numeric($identifier)) {
                $query->where('product_id', $identifier)->orWhere('slug', $identifier);
            } else {
                $query->where('slug', $identifier);
            }

            return $query->first();
        });

        if (!$product) {
            return response()->json(['status' => 'error', 'message' => 'Product not found'], 404);
        }

        return response()->json($product);
    }

    /**
     * Sản phẩm liên quan theo slug (cùng danh mục, loại trừ SP hiện tại)
     * GET /products/{slug}/related
     */
    public function related($slug)
    {
        $product = Cache::remember("product:identifier:{$slug}", 1800, function () use ($slug) {
            $query = Product::with(['category', 'brand', 'images', 'variants']);
            if (is_numeric($slug)) {
                $query->where('product_id', $slug)->orWhere('slug', $slug);
            } else {
                $query->where('slug', $slug);
            }
            return $query->first();
        });

        if (!$product) {
            return response()->json(['status' => 'error', 'message' => 'Product not found'], 404);
        }

        $cacheKey = "products:related:{$product->product_id}";
        $related = Cache::remember($cacheKey, 900, function () use ($product) {
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
                ->where('category_id', $product->category_id)
                ->where('product_id', '!=', $product->product_id)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->orderBy('product_id', 'desc')
                ->limit(4)
                ->get()
                ->map(function ($p) {
                    return [
                        'product_id'  => $p->product_id,
                        'name'        => $p->name,
                        'slug'        => $p->slug,
                        'min_price'   => $p->min_price,
                        'thumbnail_url' => $p->mainImage?->image_url ?? $p->thumbnail_url,
                    ];
                });
        });

        return response()->json([
            'status' => 'success',
            'data'   => $related,
        ]);
    }

    /**

     * Danh sách sản phẩm nổi bật
     */
    public function featured()
    {
        $products = Cache::remember('products:featured', 1800, function () {
            return Product::with([
                'mainImage' => function ($q) {
                    $q->select('image_id', 'image_url', 'product_id');
                },
                'lowestPriceVariant' => function ($q) {
                    $q->select('variant_id', 'price', 'compare_at_price', 'sale_price', 'sale_starts_at', 'sale_ends_at', 'stock', 'product_id');
                }
            ])
                ->where('status', 'active')
                ->where('is_featured', true)
                ->get();
        });
        
        return response()->json([
            'status' => 'success',
            'data' => $products
        ]);
    }

    /**
     * GET /products/{id}/variants — Lấy danh sách biến thể của sản phẩm (public)
     */
    public function getVariants($id)
    {
        $product = Product::with(['variants' => function ($q) {
            $q->where('status', 'active')->orderBy('color')->orderBy('size');
        }])->where('product_id', $id)->first();

        if (!$product) {
            return response()->json(['status' => 'error', 'message' => 'Sản phẩm không tồn tại.'], 404);
        }

        $variants = $product->variants->map(function ($v) {
            return [
                'variant_id'       => $v->variant_id,
                'color'            => $v->color,
                'size'             => $v->size,
                'variant_name'     => $v->variant_name,
                'price'            => $v->price,
                'compare_at_price' => $v->compare_at_price,
                'stock'            => $v->stock,
                'status'           => $v->status,
                'image_url'        => $v->image_url,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $variants,
        ]);
    }



    /**
     * Danh sách tất cả sản phẩm (public, phân trang)
     */
    public function all(Request $request)
    {
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 12);
        $offset = ($page - 1) * $limit;
        
        $cacheKey = "products:all:page:{$page}:limit:{$limit}";

        $products = Cache::remember($cacheKey, 1800, function () use ($offset, $limit) {
            return Product::with([
                'mainImage' => function ($q) {
                    $q->select('image_id', 'image_url', 'product_id');
                },
                'lowestPriceVariant' => function ($q) {
                    $q->select('variant_id', 'price', 'compare_at_price', 'sale_price', 'sale_starts_at', 'sale_ends_at', 'stock', 'product_id');
                }
            ])
                ->where('status', 'active')
                ->offset($offset)
                ->limit($limit)
                ->get();
        });
        
        return response()->json([
            'status' => 'success',
            'data' => $products
        ]);
    }

    /**
     * Lấy chi tiết sản phẩm theo ID (admin edit)
     */
    public function edit($id)
    {
        $product = Product::with(['category', 'brand', 'images', 'variants'])
            ->findOrFail($id);
        return response()->json($product);
    }

    /**
     * Thêm sản phẩm mới
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'              => 'required|string|max:200',
            'category_id'       => 'required|exists:categories,category_id',
            'brand_id'          => 'nullable|exists:brands,brand_id',
            'seller_id'         => 'nullable|exists:users,user_id',
            'short_description' => 'nullable|string|max:500',
            'description'       => 'nullable|string',
            'product_type'      => 'required|in:simple,variant',
            'status'            => 'required|in:draft,active,inactive,out_of_stock',
            'is_featured'       => 'boolean',
            'thumbnail'         => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'gallery'           => 'nullable|array',
            'gallery.*'         => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'variant_images'    => 'nullable|array',
            'variant_images.*'  => 'nullable|array',
            'variant_images.*.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'price'             => 'nullable|numeric|min:100000',
            'compare_at_price'  => 'nullable|numeric|min:100000',
            'stock'             => 'nullable|integer|min:0',
            'sale_price'        => 'nullable|numeric|min:1',
            'sale_starts_at'    => 'nullable|date',
            'sale_ends_at'      => 'nullable|date|after_or_equal:sale_starts_at',
            'variants'          => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // 0. Đảm bảo thư mục storage tồn tại
            $storageDisk = Storage::disk('public');
            foreach (['products/thumbnails', 'products/gallery', 'products/variants'] as $dir) {
                if (!$storageDisk->exists($dir)) {
                    $storageDisk->makeDirectory($dir);
                }
            }

            // 1. Parse variants JSON (FE gửi dạng JSON string)
            $variantsData = [];
            if ($request->filled('variants')) {
                $variantsData = json_decode($request->variants, true);
                if (!is_array($variantsData)) {
                    return response()->json(['message' => 'Dữ liệu variants không hợp lệ.'], 422);
                }
                // Validate giá biến thể >= 100.000đ
                foreach ($variantsData as $vIdx => $vItem) {
                    foreach (($vItem['sizes'] ?? []) as $sIdx => $sItem) {
                        $sPrice = $sItem['price'] ?? 0;
                        if ($sPrice < 100000) {
                            return response()->json([
                                'message' => "Giá biến thể #{$vIdx}-size #{$sIdx} phải tối thiểu 100.000đ.",
                            ], 422);
                        }
                    }
                }
            }

            // 2. Upload thumbnail
            $thumbnailPath = null;
            Log::info('[ProductStore] hasFile thumbnail: ' . ($request->hasFile('thumbnail') ? 'YES' : 'NO'));
            Log::info('[ProductStore] all files: ', array_keys($request->allFiles()));

            if ($request->hasFile('thumbnail')) {
                $file = $request->file('thumbnail');
                Log::info('[ProductStore] thumbnail file info', [
                    'original_name' => $file->getClientOriginalName(),
                    'mime'          => $file->getClientMimeType(),
                    'size'          => $file->getSize(),
                ]);

                $thumbnailPath = $file->store('products/thumbnails', 'public');
                Log::info('[ProductStore] store() returned: ' . var_export($thumbnailPath, true));

                if (!$thumbnailPath || $thumbnailPath === false) {
                    $reason = !$file->isValid() ? $file->getErrorMessage() : 'Kiểm tra quyền ghi thư mục storage.';
                    throw new \Exception('Lỗi lưu thumbnail: ' . $reason);
                }
            }

            // 3. Tạo sản phẩm
            $slug = Str::slug($request->name) . '-' . Str::random(5);
            $product = Product::create([
                'category_id'       => $request->category_id,
                'brand_id'          => $request->brand_id ?: null,
                'seller_id'         => $request->seller_id ?: null,
                'name'              => $request->name,
                'slug'              => $slug,
                'short_description' => $request->short_description,
                'description'       => $request->description,
                'thumbnail_url'     => $thumbnailPath,
                'product_type'      => $request->product_type,
                'status'            => $request->status,
                'is_featured'       => $request->boolean('is_featured'),
                'min_price'         => 0,
                'max_price'         => 0,
            ]);

            // 4. Main image → product_images
            if ($thumbnailPath) {
                ProductImage::create([
                    'product_id' => $product->product_id,
                    'image_url'  => $thumbnailPath,
                    'is_main'    => true,
                    'sort_order' => 0,
                ]);
            }

            // 5. Gallery images
            if ($request->hasFile('gallery')) {
                foreach ($request->file('gallery') as $i => $file) {
                    $path = $file->store('products/gallery', 'public');
                    if (!$path || $path === false) {
                        $reason = !$file->isValid() ? $file->getErrorMessage() : 'Kiểm tra quyền ghi thư mục storage.';
                        throw new \Exception('Lỗi lưu ảnh gallery: ' . $reason);
                    }
                    ProductImage::create([
                        'product_id' => $product->product_id,
                        'image_url'  => $path,
                        'is_main'    => false,
                        'sort_order' => $i + 1,
                    ]);
                }
            }

            // 6. Tạo variants
            $allPrices = [];

            if ($request->product_type === 'simple') {
                $price = $request->price ?? 0;
                $allPrices[] = $price;
                $barcode = $this->generateUniqueBarcode();
                ProductVariant::create([
                    'product_id'       => $product->product_id,
                    'sku'              => $slug . '-default',
                    'barcode'          => $barcode,
                    'price'            => $price,
                    'compare_at_price' => $request->compare_at_price,
                    'stock'            => $request->stock ?? 0,
                    'sale_price'       => $request->sale_price ?: null,
                    'sale_starts_at'   => $request->sale_starts_at ?: null,
                    'sale_ends_at'     => $request->sale_ends_at ?: null,
                    'status'           => 'active',
                ]);
                $this->generateQrCodeImage($barcode);
            } else {
                // Variant product: mỗi color có nhiều sizes
                // FE gửi: [{ color: "Đỏ", sizes: [{ size: "M", price: 100, stock: 10 }, ...] }, ...]
                $combinations = [];

                foreach ($variantsData as $vIndex => $vData) {
                    $color = $vData['color'] ?? null;
                    $sizes = $vData['sizes'] ?? [];

                    // Upload variant images (nhiều ảnh mỗi biến thể)
                    $variantImagePaths = [];
                    if ($request->hasFile("variant_images.{$vIndex}")) {
                        foreach ($request->file("variant_images.{$vIndex}") as $imgFile) {
                            $imgPath = $imgFile->store('products/variants', 'public');
                            if (!$imgPath || $imgPath === false) {
                                $reason = !$imgFile->isValid() ? $imgFile->getErrorMessage() : 'Kiểm tra quyền ghi thư mục storage.';
                                throw new \Exception('Lỗi lưu ảnh biến thể: ' . $reason);
                            }
                            $variantImagePaths[] = $imgPath;
                        }
                    }

                    foreach ($sizes as $sData) {
                        $size = $sData['size'] ?? null;
                        $combo = strtolower(trim($color ?? '')) . '|' . strtolower(trim($size ?? ''));

                        if (in_array($combo, $combinations)) {
                            throw new \Exception("Biến thể trùng lặp: Màu [{$color}] - Size [{$size}]");
                        }
                        $combinations[] = $combo;

                        $vPrice = $sData['price'] ?? 0;
                        $allPrices[] = $vPrice;

                        $barcode = $this->generateUniqueBarcode();
                        $variant = ProductVariant::create([
                            'product_id'     => $product->product_id,
                            'sku'            => $slug . '-' . Str::slug($color ?? 'def') . '-' . Str::slug($size ?? 'def') . '-' . Str::random(4),
                            'barcode'        => $barcode,
                            'color'          => $color,
                            'size'           => $size,
                            'price'          => $vPrice,
                            'stock'          => $sData['stock'] ?? 0,
                            'sale_price'     => !empty($sData['sale_price']) ? $sData['sale_price'] : null,
                            'sale_starts_at' => !empty($sData['sale_starts_at']) ? $sData['sale_starts_at'] : null,
                            'sale_ends_at'   => !empty($sData['sale_ends_at']) ? $sData['sale_ends_at'] : null,
                            'image_url'      => $variantImagePaths[0] ?? null,
                            'status'         => 'active',
                        ]);
                        $this->generateQrCodeImage($barcode);

                        // Lưu ảnh biến thể vào product_images
                        foreach ($variantImagePaths as $imgIndex => $imgPath) {
                            ProductImage::create([
                                'product_id' => $product->product_id,
                                'variant_id' => $variant->variant_id,
                                'image_url'  => $imgPath,
                                'is_main'    => false,
                                'sort_order' => $imgIndex + 1,
                            ]);
                        }
                    }
                }
            }

            // 7. Update min/max price
            if (count($allPrices) > 0) {
                $product->update([
                    'min_price' => min($allPrices),
                    'max_price' => max($allPrices),
                ]);
            }

            DB::commit();
            Cache::flush();

            return response()->json([
                'success' => true,
                'message' => 'Thêm sản phẩm thành công.',
                'data'    => $product->load('variants', 'images'),
            ], 201);

        } catch (\Throwable $e) {
            $isDbError = $e instanceof \Illuminate\Database\QueryException || $e instanceof \PDOException;
            $errorMsg = $isDbError ? 'Lỗi hệ thống.' : $e->getMessage();
            return response()->json([
                'success' => false,
                'message' => 'Thêm sản phẩm thất bại: ' . $errorMsg,
            ], 500);
        }
    }

    /**
     * Cập nhật sản phẩm
     */
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'name'              => 'required|string|max:200',
            'category_id'       => 'required|exists:categories,category_id',
            'brand_id'          => 'nullable|exists:brands,brand_id',
            'seller_id'         => 'nullable|exists:users,user_id',
            'short_description' => 'nullable|string|max:500',
            'description'       => 'nullable|string',
            'product_type'      => 'required|in:simple,variant',
            'status'            => 'required|in:draft,active,inactive,out_of_stock',
            'is_featured'       => 'boolean',
            'thumbnail'         => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'price'             => 'nullable|numeric|min:100000',
            'compare_at_price'  => 'nullable|numeric|min:100000',
            'stock'             => 'nullable|integer|min:0',
            'sale_price'        => 'nullable|numeric|min:1',
            'sale_starts_at'    => 'nullable|date',
            'sale_ends_at'      => 'nullable|date|after_or_equal:sale_starts_at',
            'gallery'           => 'nullable|array',
            'gallery.*'         => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'variant_images'    => 'nullable|array',
            'variant_images.*'  => 'nullable|array',
            'variant_images.*.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'deleted_gallery_ids'   => 'nullable|array',
            'deleted_gallery_ids.*' => 'integer',
            'variants'              => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // 0. Đảm bảo thư mục storage tồn tại
            $storageDisk = Storage::disk('public');
            foreach (['products/thumbnails', 'products/gallery', 'products/variants'] as $dir) {
                if (!$storageDisk->exists($dir)) {
                    $storageDisk->makeDirectory($dir);
                }
            }

            // 1. Parse variants JSON
            $variantsData = [];
            if ($request->filled('variants')) {
                $variantsData = json_decode($request->variants, true);
                if (!is_array($variantsData)) {
                    return response()->json(['message' => 'Dữ liệu variants không hợp lệ.'], 422);
                }
                // Validate giá biến thể >= 100.000đ
                foreach ($variantsData as $vIdx => $vItem) {
                    foreach (($vItem['sizes'] ?? []) as $sIdx => $sItem) {
                        $sPrice = $sItem['price'] ?? 0;
                        if ($sPrice < 100000) {
                            return response()->json([
                                'message' => "Giá biến thể #{$vIdx}-size #{$sIdx} phải tối thiểu 100.000đ.",
                            ], 422);
                        }
                    }
                }
            }


            // 2. Thumbnail
            $thumbnailPath = $product->thumbnail_url;
            if ($request->hasFile('thumbnail')) {
                if ($thumbnailPath && is_string($thumbnailPath) && $thumbnailPath !== '0') {
                    Storage::disk('public')->delete($thumbnailPath);
                }
                $thumbnailPath = $request->file('thumbnail')->store('products/thumbnails', 'public');
                if (!$thumbnailPath || $thumbnailPath === false) {
                    $file = $request->file('thumbnail');
                    $reason = !$file->isValid() ? $file->getErrorMessage() : 'Kiểm tra quyền ghi thư mục storage.';
                    throw new \Exception('Lỗi lưu thumbnail: ' . $reason);
                }

                // Update main image
                ProductImage::where('product_id', $product->product_id)
                    ->where('is_main', true)->delete();

                ProductImage::create([
                    'product_id' => $product->product_id,
                    'image_url'  => $thumbnailPath,
                    'is_main'    => true,
                    'sort_order' => 0,
                ]);
            } else {
                // Nếu không upload ảnh mới và thumbnail cũ bị lỗi (là "0"), reset về null
                if ($thumbnailPath === '0' || $thumbnailPath === 0) {
                    $thumbnailPath = null;
                }
            }

            // 3. Update product info
            $product->update([
                'category_id'       => $request->category_id,
                'brand_id'          => $request->brand_id ?: null,
                'seller_id'         => $request->seller_id ?: null,
                'name'              => $request->name,
                'slug'              => $request->slug ?: Str::slug($request->name),
                'short_description' => $request->short_description,
                'description'       => $request->description,
                'thumbnail_url'     => $thumbnailPath,
                'product_type'      => $request->product_type,
                'status'            => $request->status,
                'is_featured'       => $request->boolean('is_featured'),
            ]);

            // 4. Xóa gallery cũ (nếu có)
            if ($request->filled('deleted_gallery_ids')) {
                $ids = $request->deleted_gallery_ids;
                $imagesToDelete = ProductImage::whereIn('image_id', $ids)
                    ->where('product_id', $product->product_id)->get();
                foreach ($imagesToDelete as $img) {
                    Storage::disk('public')->delete($img->image_url);
                    $img->delete();
                }
            }

            // 5. Thêm gallery mới
            if ($request->hasFile('gallery')) {
                $maxSort = ProductImage::where('product_id', $product->product_id)->max('sort_order') ?? 0;
                foreach ($request->file('gallery') as $i => $file) {
                    $path = $file->store('products/gallery', 'public');
                    if (!$path || $path === false) {
                        $reason = !$file->isValid() ? $file->getErrorMessage() : 'Kiểm tra quyền ghi thư mục storage.';
                        throw new \Exception('Lỗi lưu ảnh gallery: ' . $reason);
                    }
                    ProductImage::create([
                        'product_id' => $product->product_id,
                        'image_url'  => $path,
                        'is_main'    => false,
                        'sort_order' => $maxSort + $i + 1,
                    ]);
                }
            }

            // 6. Xử lý variants
            $allPrices = [];

            if ($request->product_type === 'simple') {
                $price = $request->price ?? 0;
                $stock = $request->stock ?? 0;
                $allPrices[] = $price;

                $defaultVariant = $product->variants()->first();
                if ($defaultVariant) {
                    $updateData = [
                        'price'            => $price,
                        'compare_at_price' => $request->compare_at_price,
                        'stock'            => $stock,
                        'sale_price'       => $request->sale_price ?: null,
                        'sale_starts_at'   => $request->sale_starts_at ?: null,
                        'sale_ends_at'     => $request->sale_ends_at ?: null,
                    ];
                    // Nếu chưa có barcode thì tạo mới
                    if (empty($defaultVariant->barcode)) {
                        $newBarcode = $this->generateUniqueBarcode();
                        $updateData['barcode'] = $newBarcode;
                        $this->generateQrCodeImage($newBarcode);
                    }
                    $defaultVariant->update($updateData);
                } else {
                    $barcode = $this->generateUniqueBarcode();
                    ProductVariant::create([
                        'product_id'       => $product->product_id,
                        'sku'              => Str::slug($product->name) . '-default',
                        'barcode'          => $barcode,
                        'price'            => $price,
                        'compare_at_price' => $request->compare_at_price,
                        'stock'            => $stock,
                        'sale_price'       => $request->sale_price ?: null,
                        'sale_starts_at'   => $request->sale_starts_at ?: null,
                        'sale_ends_at'     => $request->sale_ends_at ?: null,
                        'status'           => 'active',
                    ]);
                    $this->generateQrCodeImage($barcode);
                }
            } else {
                // Variant product
                $existingVariantIds = $product->variants()->pluck('variant_id')->toArray();

                // Xóa cart_items tham chiếu đến variants cũ (tránh FK constraint)
                if (!empty($existingVariantIds)) {
                    CartItem::whereIn('variant_id', $existingVariantIds)->delete();
                }

                // 1. Xóa ảnh biến thể mà user đã ấn nút xóa thủ công
                $deletedImageIds = $request->input('deleted_variant_image_ids', []);
                if (!empty($deletedImageIds)) {
                    $imagesToDelete = ProductImage::whereIn('image_id', $deletedImageIds)
                        ->where('product_id', $product->product_id)
                        ->get();
                    foreach ($imagesToDelete as $img) {
                        Storage::disk('public')->delete($img->image_url);
                        $img->delete();
                    }
                }

                // 2. QUAN TRỌNG: Set variant_id = NULL cho ảnh TRƯỚC khi xóa variant
                // (vì FK ON DELETE CASCADE sẽ xóa ảnh theo variant nếu không)
                $oldVariantImagesMap = []; // color => [ProductImage records]
                foreach ($product->variants as $oldVariant) {
                    $color = $oldVariant->color ?? 'default';
                    if (!isset($oldVariantImagesMap[$color])) {
                        $oldVariantImagesMap[$color] = [];
                    }
                    $variantOldImages = ProductImage::where('product_id', $product->product_id)
                        ->where('variant_id', $oldVariant->variant_id)
                        ->get();
                    foreach ($variantOldImages as $img) {
                        $img->update(['variant_id' => null]); // Tạm tách khỏi variant
                        $oldVariantImagesMap[$color][] = $img;
                    }
                }

                // 3. Xóa tất cả variant cũ (ảnh đã được tách ra, không bị CASCADE xóa)
                ProductVariant::where('product_id', $product->product_id)->delete();

                // 4. Tạo lại variant mới
                $combinations = [];
                foreach ($variantsData as $vIndex => $vData) {
                    $color = $vData['color'] ?? null;
                    $sizes = $vData['sizes'] ?? [];

                    // Upload variant images MỚI
                    $variantImagePaths = [];
                    if ($request->hasFile("variant_images.{$vIndex}")) {
                        foreach ($request->file("variant_images.{$vIndex}") as $imgFile) {
                            $imgPath = $imgFile->store('products/variants', 'public');
                            if (!$imgPath || $imgPath === false) {
                                throw new \Exception('Lỗi lưu ảnh biến thể.');
                            }
                            $variantImagePaths[] = $imgPath;
                        }
                    }

                    // Lấy ảnh cũ còn tồn tại cho color này
                    $colorKey = $color ?? 'default';
                    $existingColorImages = $oldVariantImagesMap[$colorKey] ?? [];

                    // Xác định image_url cho variant
                    $mainImageUrl = null;
                    if (!empty($existingColorImages)) {
                        $mainImageUrl = $existingColorImages[0]->image_url;
                    } elseif (!empty($variantImagePaths)) {
                        $mainImageUrl = $variantImagePaths[0];
                    }

                    $firstVariantForColor = null;

                    foreach ($sizes as $sData) {
                        $size = $sData['size'] ?? null;
                        $combo = strtolower(trim($color ?? '')) . '|' . strtolower(trim($size ?? ''));

                        if (in_array($combo, $combinations)) {
                            throw new \Exception("Biến thể trùng lặp: Màu [{$color}] - Size [{$size}]");
                        }
                        $combinations[] = $combo;

                        $vPrice = $sData['price'] ?? 0;
                        $allPrices[] = $vPrice;

                        $barcode = $this->generateUniqueBarcode();
                        $variant = ProductVariant::create([
                            'product_id'     => $product->product_id,
                            'sku'            => Str::slug($product->name) . '-' . Str::slug($color ?? 'def') . '-' . Str::slug($size ?? 'def') . '-' . Str::random(4),
                            'barcode'        => $barcode,
                            'color'          => $color,
                            'size'           => $size,
                            'price'          => $vPrice,
                            'stock'          => $sData['stock'] ?? 0,
                            'sale_price'     => !empty($sData['sale_price']) ? $sData['sale_price'] : null,
                            'sale_starts_at' => !empty($sData['sale_starts_at']) ? $sData['sale_starts_at'] : null,
                            'sale_ends_at'   => !empty($sData['sale_ends_at']) ? $sData['sale_ends_at'] : null,
                            'image_url'      => $mainImageUrl,
                            'status'         => 'active',
                        ]);
                        $this->generateQrCodeImage($barcode);

                        if (!$firstVariantForColor) {
                            $firstVariantForColor = $variant;
                        }
                    }

                    // Gán ảnh cũ + ảnh mới cho variant ĐẦU TIÊN của màu này
                    if ($firstVariantForColor) {
                        // Cập nhật ảnh cũ → gán variant_id mới
                        foreach ($existingColorImages as $oldImg) {
                            $oldImg->update(['variant_id' => $firstVariantForColor->variant_id]);
                        }
                        // Tạo record cho ảnh mới upload
                        foreach ($variantImagePaths as $imgIndex => $imgPath) {
                            ProductImage::create([
                                'product_id' => $product->product_id,
                                'variant_id' => $firstVariantForColor->variant_id,
                                'image_url'  => $imgPath,
                                'is_main'    => false,
                                'sort_order' => count($existingColorImages) + $imgIndex + 1,
                            ]);
                        }
                    }
                }
            }

            // 7. Update min/max price
            if (count($allPrices) > 0) {
                $product->update([
                    'min_price' => min($allPrices),
                    'max_price' => max($allPrices),
                ]);
            }

            DB::commit();
            Cache::flush();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật sản phẩm thành công.',
                'data'    => $product->load('variants', 'images'),
            ]);

        } catch (\Throwable $e) {
            $isDbError = $e instanceof \Illuminate\Database\QueryException || $e instanceof \PDOException;
            $errorMsg = $isDbError ? 'Lỗi hệ thống.' : $e->getMessage();
            return response()->json([
                'success' => false,
                'message' => 'Cập nhật thất bại: ' . $errorMsg,
            ], 500);
        }
    }

    /**
     * Xóa sản phẩm (soft delete)
     */
    public function destroy($id)
    {
        try {
            $product = Product::withTrashed()->findOrFail($id);
            
            if ($product->trashed()) {
                $product->forceDelete();
                $msg = 'Xóa vĩnh viễn sản phẩm thành công.';
            } else {
                $product->delete();
                $msg = 'Xóa sản phẩm thành công.';
            }

            Cache::flush();
            return response()->json([
                'status'  => 'success',
                'message' => $msg,
            ]);
        } catch (\Exception $e) {
            $isDbError = $e instanceof \Illuminate\Database\QueryException || $e instanceof \PDOException;
            $errorMsg = $isDbError ? 'Lỗi hệ thống.' : $e->getMessage();
            return response()->json([
                'status'  => 'error',
                'message' => 'Lỗi khi xóa: ' . $errorMsg,
            ], 500);
        }
    }

    /**
     * Khôi phục sản phẩm (restore)
     */
    public function restore($id)
    {
        try {
            $product = Product::withTrashed()->findOrFail($id);
            $product->restore();
            Cache::flush();
            return response()->json([
                'status'  => 'success',
                'message' => 'Khôi phục sản phẩm thành công.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Không thể khôi phục sản phẩm.',
            ], 500);
        }
    }

    /**
     * Import sản phẩm từ Excel - Kiến trúc CHUNK THEO FILE DISK
     *
     * FLOW:
     * B1: Lưu file vào disk → KHÔNG parse toàn bộ, không tốn RAM
     * B2: Trả về session_id + total_chunks ngay lập tức
     * B3: Frontend gọi process-chunk từng phần → mỗi request chỉ đọc 200 dòng
     */
    public function importExcel(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls|max:20480',
        ], [
            'excel_file.required' => 'Vui lòng chọn file Excel.',
            'excel_file.mimes'    => 'File phải có định dạng .xlsx hoặc .xls.',
            'excel_file.max'      => 'File không được vượt quá 20MB.',
        ]);

        try {
            $rowsPerChunk = 50; // 50 dòng/chunk → mỗi request ~5-15s, an toàn với Nginx 300s

            // B1: Lưu file vào disk — không parse, không tốn RAM
            $file        = $request->file('excel_file');
            $sessionId   = Str::random(30);
            $ext         = $file->getClientOriginalExtension();
            $storagePath = 'imports/' . $sessionId . '.' . $ext;
            Storage::disk('local')->put($storagePath, file_get_contents($file->getRealPath()));
            $filePath = Storage::disk('local')->path($storagePath);

            // B2: Đếm số dòng KHÔNG dùng SpreadSheet library (zero RAM overhead)
            // .xlsx là file ZIP chứa XML → đọc thẳng rows XML bằng regex
            $totalDataRows = 0;
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            if ($ext === 'xlsx') {
                // Đọc sheet1.xml từ trong zip, đếm thẻ <row> bằng stream
                $zip = new \ZipArchive();
                if ($zip->open($filePath) === true) {
                    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
                    $zip->close();
                    if ($sheetXml !== false) {
                        // Đếm thẻ <row ...> (trừ 1 header row)
                        $rowCount = preg_match_all('/<row\s/', $sheetXml);
                        $totalDataRows = max(0, $rowCount - 1);
                    }
                    unset($sheetXml);
                }
            } else {
                // Với .xls: dùng PhpSpreadsheet nhưng chỉ đọc cột A
                $filter = new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
                    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool {
                        return $columnAddress === 'A';
                    }
                };
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
                $reader->setReadDataOnly(true);
                $reader->setReadFilter($filter);
                $spreadsheet   = $reader->load($filePath);
                $highestRow    = $spreadsheet->getActiveSheet()->getHighestDataRow('A');
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet, $reader, $filter);
                $totalDataRows = max(0, $highestRow - 1);
            }


            if ($totalDataRows === 0) {
                Storage::disk('local')->delete($storagePath);
                return response()->json(['success' => false, 'message' => 'File không có dữ liệu hợp lệ.'], 400);
            }

            $totalChunks = (int)ceil($totalDataRows / $rowsPerChunk);

            // B3: Lưu metadata nhỏ vào cache (không serialize data lớn)
            Cache::put('import_meta_' . $sessionId, [
                'storage_path'   => $storagePath,
                'rows_per_chunk' => $rowsPerChunk,
                'total_chunks'   => $totalChunks,
                'total_rows'     => $totalDataRows,
            ], 7200);

            return response()->json([
                'success'      => true,
                'session_id'   => $sessionId,
                'total_chunks' => $totalChunks,
                'total_rows'   => $totalDataRows,
            ]);

        } catch (\Throwable $e) {
            Log::error('[ProductImportExcel] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            // Dọn file nếu có lỗi
            if (isset($storagePath)) {
                Storage::disk('local')->delete($storagePath);
            }
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi đọc file: ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Xử lý 1 chunk — Frontend gọi lặp với chunk_index = 0, 1, 2, ...
     * Mỗi request chỉ đọc 200 dòng từ file đã lưu trên disk → không timeout
     */
    public function processImportChunk(Request $request)
    {
        $request->validate([
            'session_id'  => 'required|string',
            'chunk_index' => 'required|integer|min:0',
        ]);

        $sessionId  = $request->session_id;
        $chunkIndex = (int)$request->chunk_index;

        $meta = Cache::get('import_meta_' . $sessionId);
        if (!$meta) {
            return response()->json([
                'success' => false,
                'error'   => 'Session đã hết hạn. Vui lòng upload lại file.',
            ], 400);
        }

        $storagePath  = $meta['storage_path'];
        $rowsPerChunk = $meta['rows_per_chunk'];
        $totalChunks  = $meta['total_chunks'];

        // Dòng bắt đầu đọc (dòng 1 là header, data từ dòng 2)
        $chunkStartRow = 2 + ($chunkIndex * $rowsPerChunk);
        $filePath = Storage::disk('local')->path($storagePath);

        if (!file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'error'   => 'File không tồn tại trên server.',
            ], 400);
        }

        try {
            ini_set('memory_limit', '512M');
            set_time_limit(120);

            // Chỉ đọc $rowsPerChunk dòng từ dòng $chunkStartRow
            $rowImport = new \App\Imports\ProductsRowImport($chunkStartRow, $rowsPerChunk);
            Excel::import($rowImport, $filePath);
            $rawRows = $rowImport->getRows();
            unset($rowImport);

            if (empty($rawRows)) {
                return response()->json(['success' => true, 'success_count' => 0, 'errors' => []]);
            }

            // Gom nhóm theo tên sản phẩm trong chunk này
            $groups = [];
            $groupOrder = [];
            foreach ($rawRows as $idx => $row) {
                $name = trim((string)($row[0] ?? ''));
                if (empty($name)) continue;
                $key = mb_strtolower($name);
                if (!isset($groups[$key])) {
                    $groups[$key] = [];
                    $groupOrder[] = $key;
                }
                $groups[$key][] = [
                    'row'      => array_values((array)$row),
                    'excelRow' => $chunkStartRow + $idx,
                ];
            }
            unset($rawRows);

            $import = new ProductsImport();
            foreach ($groupOrder as $key) {
                $import->processProductGroup($groups[$key]);
            }
            unset($groups);

            // Chunk cuối → dọn dẹp file tạm và refresh cache
            $isLastChunk = ($chunkIndex >= $totalChunks - 1);
            if ($isLastChunk) {
                Storage::disk('local')->delete($storagePath);
                Cache::forget('import_meta_' . $sessionId);
                Cache::flush();
            }

            return response()->json([
                'success'       => true,
                'success_count' => $import->getSuccessCount(),
                'errors'        => $import->getErrors(),
            ]);

        } catch (\Throwable $e) {
            Log::error('[ProductImportChunk] chunk=' . $chunkIndex . ' ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error'   => 'Lỗi chunk ' . $chunkIndex . ': ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Tải file Excel mẫu để người dùng tải về điền dữ liệu
     */
    public function downloadTemplate()
    {
        return Excel::download(new ProductsTemplateExport(), 'mau_import_san_pham.xlsx');
    }
}
