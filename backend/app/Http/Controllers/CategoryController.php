<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    private function buildTree(array $elements, $parentId = 0)
    {
        $branch = [];
        foreach ($elements as $element) {
            $idKey = array_key_exists('category_id', $element) ? 'category_id' : 'id';

            if ($element['parent_id'] == $parentId) {
                $children = $this->buildTree($elements, $element[$idKey]);
                if ($children) {
                    $element['children'] = $children;
                }
                $branch[] = $element;
            }
        }

        return $branch;
    }

    /**
     * Tạo public URL cho ảnh danh mục
     */
    private function buildImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        // Nếu đã là URL tuyệt đối thì trả về ngay
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return url('/api/image-proxy?path='.$path);
    }

    /**
     * Ánh xạ image_url vào toàn bộ danh sách (đệ quy)
     */
    private function appendImageUrl(array $categories): array
    {
        return array_map(function ($cat) {
            $cat['image_url'] = $this->buildImageUrl($cat['image'] ?? null);
            if (! empty($cat['children'])) {
                $cat['children'] = $this->appendImageUrl($cat['children']);
            }

            return $cat;
        }, $categories);
    }

    /**
     * Lấy danh sách tất cả các danh mục dưới dạng cây (Tree).
     * Dữ liệu được cache vĩnh viễn (cho đến khi có thay đổi).
     *
     * @return JsonResponse
     */
    public function index()
    {
        $tree = Cache::rememberForever('categories:tree', function () {
            $cats = Category::all()->toArray();

            return $this->buildTree($cats);
        });

        $tree = $this->appendImageUrl($tree);

        return response()->json([
            'status' => 'success',
            'data' => $tree,
        ]);
    }

    /**
     * Thêm mới một danh mục (Category) vào hệ thống.
     * Hỗ trợ lưu trữ ảnh đại diện và tự động sinh slug từ tên danh mục.
     *
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi xác thực dữ liệu',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->except(['image']);

        // Nếu parent_id = 0, treat như null (danh mục gốc)
        if (isset($data['parent_id']) && $data['parent_id'] == 0) {
            $data['parent_id'] = null;
        }
        $data['slug'] = Str::slug($request->name);

        $originalSlug = $data['slug'];
        $count = 1;
        while (Category::where('slug', $data['slug'])->exists()) {
            $data['slug'] = $originalSlug.'-'.$count++;
        }

        // Upload ảnh nếu có
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('categories', 'public');
            $data['image'] = $path;
        }

        $category = Category::create($data);
        $category->image_url = $this->buildImageUrl($category->image);

        Cache::forget('categories:tree');

        return response()->json([
            'status' => 'success',
            'message' => 'Tạo danh mục thành công',
            'data' => $category,
        ], 201);
    }

    public function show($id)
    {
        $category = Category::find($id);

        if (! $category) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy danh mục',
            ], 404);
        }

        $data = $category->toArray();
        $data['image_url'] = $this->buildImageUrl($category->image);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $category = Category::find($id);

        if (! $category) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy danh mục',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'parent_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi xác thực dữ liệu',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->except(['image']);

        // Nếu parent_id = 0, treat như null (danh mục gốc)
        if (isset($data['parent_id']) && $data['parent_id'] == 0) {
            $data['parent_id'] = null;
        }
        if ($request->has('name')) {
            $data['slug'] = Str::slug($request->name);

            // Kiểm tra trùng slug (trừ chính nó)
            $originalSlug = $data['slug'];
            $count = 1;
            while (Category::where('slug', $data['slug'])->where('category_id', '!=', $id)->exists()) {
                $data['slug'] = $originalSlug.'-'.$count++;
            }
        }

        // Upload ảnh mới nếu có
        if ($request->hasFile('image')) {
            // Xóa ảnh cũ
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $path = $request->file('image')->store('categories', 'public');
            $data['image'] = $path;
        }

        $category->update($data);
        Cache::forget('categories:tree');

        $result = $category->fresh()->toArray();
        $result['image_url'] = $this->buildImageUrl($category->fresh()->image);

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật danh mục thành công',
            'data' => $result,
        ]);
    }

    /**
     * Xóa ảnh của danh mục (không xóa danh mục).
     */
    public function deleteImage($id)
    {
        $category = Category::find($id);

        if (! $category) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy danh mục',
            ], 404);
        }

        if ($category->image) {
            Storage::disk('public')->delete($category->image);
            $category->update(['image' => null]);
            Cache::forget('categories:tree');
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xóa ảnh danh mục',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $category = Category::find($id);

        if (! $category) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy danh mục',
            ], 404);
        }

        $hasChildren = Category::where('parent_id', $id)->exists();
        if ($hasChildren) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể xóa danh mục có danh mục con',
            ], 400);
        }

        // Xóa ảnh kèm theo khi xóa danh mục
        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }

        $category->delete();
        Cache::forget('categories:tree');

        return response()->json([
            'status' => 'success',
            'message' => 'Xóa danh mục thành công',
        ]);
    }
}
