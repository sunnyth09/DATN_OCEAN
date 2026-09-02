<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    private function buildImageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return url('/api/image-proxy?path='.$path);
    }

    /**
     * Lấy danh sách tất cả các thương hiệu (Brands).
     * Dữ liệu được cache trong 1 ngày (86400 giây) để tăng hiệu suất.
     *
     * @return JsonResponse
     */
    public function index()
    {
        $brands = Cache::remember('brands:all', 86400, function () {
            $data = Brand::orderBy('brand_id', 'desc')->get();
            $data->transform(function ($brand) {
                $brand->image_url = $this->buildImageUrl($brand->logo_url);

                return $brand;
            });

            return $data;
        });

        return response()->json($brands);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:2048',
        ]);

        $data = $request->except(['image']);
        $data['slug'] = Str::slug($request->name);
        $data['is_active'] = $request->has('is_active') ? $request->is_active : 1;

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('brands', 'public');
            $data['logo_url'] = $path;
        }

        $brand = Brand::create($data);
        Cache::forget('brands:all');

        $brand->image_url = $this->buildImageUrl($brand->logo_url);

        return response()->json([
            'status' => 'success',
            'message' => 'Thêm thương hiệu thành công',
            'data' => $brand,
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id)
    {
        $brand = Brand::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:2048',
        ]);

        $data = $request->except(['image']);
        $data['slug'] = Str::slug($request->name);
        if ($request->has('is_active')) {
            $data['is_active'] = $request->is_active;
        }

        if ($request->hasFile('image')) {
            if ($brand->logo_url && ! filter_var($brand->logo_url, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($brand->logo_url);
            }
            $path = $request->file('image')->store('brands', 'public');
            $data['logo_url'] = $path;
        }

        $brand->update($data);
        Cache::forget('brands:all');

        $result = $brand->fresh()->toArray();
        $result['image_url'] = $this->buildImageUrl($brand->fresh()->logo_url);

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật thương hiệu thành công',
            'data' => $result,
        ]);
    }

    /**
     * Xóa ảnh của thương hiệu
     */
    public function deleteImage($id)
    {
        $brand = Brand::findOrFail($id);

        if ($brand->logo_url) {
            if (! filter_var($brand->logo_url, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($brand->logo_url);
            }
            $brand->update(['logo_url' => null]);
            Cache::forget('brands:all');
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Xóa logo thành công',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        $brand = Brand::findOrFail($id);

        if ($brand->logo_url && ! filter_var($brand->logo_url, FILTER_VALIDATE_URL)) {
            Storage::disk('public')->delete($brand->logo_url);
        }

        // Đặt brand_id của các sản phẩm thuộc thương hiệu này về null để tránh lỗi tham chiếu
        Product::where('brand_id', $id)->update(['brand_id' => null]);

        $brand->delete();
        Cache::forget('brands:all');

        return response()->json([
            'status' => 'success',
            'message' => 'Xóa thương hiệu thành công',
        ]);
    }
}
