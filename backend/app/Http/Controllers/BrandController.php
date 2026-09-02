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

        if (filter_var($path, FILTER_VALIDATE_URL) || Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $cleanPath = ltrim(preg_replace('/^storage\//', '', $path), '/');
        $baseUrl = rtrim(config('app.url', 'http://localhost:8383'), '/');
        if (! Str::startsWith($baseUrl, ['http://', 'https://'])) {
            $baseUrl = 'http://'.$baseUrl;
        }

        return $baseUrl.'/storage/'.$cleanPath;
    }

    private function cleanDescription(?string $desc): ?string
    {
        if (empty($desc)) {
            return null;
        }

        $decoded = html_entity_decode(html_entity_decode($desc, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $clean = trim(strip_tags($decoded));

        return $clean !== '' ? $clean : null;
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
                $brand->description = $this->cleanDescription($brand->description);

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
        $rules = [
            'name' => 'required|string|max:120|unique:brands,name',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];

        if ($request->hasFile('image')) {
            $rules['image'] = 'nullable|file|mimes:jpeg,jpg,png,webp,gif,svg,bmp,avif|max:5120';
        } else {
            $rules['image'] = 'nullable|string';
        }

        $messages = [
            'name.required' => 'Vui lòng nhập tên thương hiệu.',
            'name.max' => 'Tên thương hiệu không được vượt quá 120 ký tự.',
            'name.unique' => 'Tên thương hiệu này đã tồn tại.',
            'image.file' => 'File tải lên phải là hình ảnh hợp lệ.',
            'image.mimes' => 'Hình ảnh phải có định dạng: jpeg, jpg, png, webp, gif, svg.',
            'image.max' => 'Kích thước hình ảnh không được vượt quá 5MB.',
        ];

        $request->validate($rules, $messages);

        $data = $request->except(['image']);
        if (isset($data['description'])) {
            $data['description'] = $this->cleanDescription($data['description']);
        }

        $slug = Str::slug($request->name);
        $originalSlug = $slug;
        $count = 1;
        while (Brand::where('slug', $slug)->exists()) {
            $slug = $originalSlug.'-'.$count++;
        }
        $data['slug'] = $slug;
        $data['is_active'] = $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : true;

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('brands', 'public');
            $data['logo_url'] = $path;
        } elseif ($request->filled('image') && filter_var($request->image, FILTER_VALIDATE_URL)) {
            $data['logo_url'] = $request->image;
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

        $rules = [
            'name' => 'required|string|max:120|unique:brands,name,'.$id.',brand_id',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];

        if ($request->hasFile('image')) {
            $rules['image'] = 'nullable|file|mimes:jpeg,jpg,png,webp,gif,svg,bmp,avif|max:5120';
        } else {
            $rules['image'] = 'nullable|string';
        }

        $messages = [
            'name.required' => 'Vui lòng nhập tên thương hiệu.',
            'name.max' => 'Tên thương hiệu không được vượt quá 120 ký tự.',
            'name.unique' => 'Tên thương hiệu này đã tồn tại.',
            'image.file' => 'File tải lên phải là hình ảnh hợp lệ.',
            'image.mimes' => 'Hình ảnh phải có định dạng: jpeg, jpg, png, webp, gif, svg.',
            'image.max' => 'Kích thước hình ảnh không được vượt quá 5MB.',
        ];

        $request->validate($rules, $messages);

        $data = $request->except(['image']);
        if (isset($data['description'])) {
            $data['description'] = $this->cleanDescription($data['description']);
        }

        $slug = Str::slug($request->name);
        $originalSlug = $slug;
        $count = 1;
        while (Brand::where('slug', $slug)->where('brand_id', '!=', $id)->exists()) {
            $slug = $originalSlug.'-'.$count++;
        }
        $data['slug'] = $slug;

        if ($request->has('is_active')) {
            $data['is_active'] = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->hasFile('image')) {
            if ($brand->logo_url && ! filter_var($brand->logo_url, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($brand->logo_url);
            }
            $path = $request->file('image')->store('brands', 'public');
            $data['logo_url'] = $path;
        } elseif ($request->filled('image') && filter_var($request->image, FILTER_VALIDATE_URL)) {
            $data['logo_url'] = $request->image;
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
