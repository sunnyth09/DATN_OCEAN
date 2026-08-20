<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SizeGuide;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SizeGuideController extends Controller
{
    public function index()
    {
        $sizeGuides = SizeGuide::with('categories:category_id,name,size_guide_id')->orderBy('id', 'desc')->get();
        return response()->json([
            '_status' => 200,
            'data' => $sizeGuides
        ]);
    }

    public function show($id)
    {
        $sizeGuide = SizeGuide::with('categories:category_id,name,size_guide_id')->find($id);
        if (!$sizeGuide) {
            return response()->json(['_status' => 404, 'message' => 'Không tìm thấy bảng size']);
        }
        return response()->json([
            '_status' => 200,
            'data' => $sizeGuide
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:size_guides,name',
            'table_headers' => 'nullable|array',
            'table_rows' => 'nullable|array',
            'tips' => 'nullable|array',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,category_id'
        ]);

        $sizeGuide = SizeGuide::create($request->only(['name', 'description', 'table_headers', 'table_rows', 'tips']));

        if ($request->has('category_ids')) {
            Category::whereIn('category_id', $request->category_ids)->update(['size_guide_id' => $sizeGuide->id]);
        }

        Cache::flush();

        return response()->json([
            '_status' => 200,
            'message' => 'Tạo bảng size thành công',
            'data' => $sizeGuide
        ]);
    }

    public function update(Request $request, $id)
    {
        $sizeGuide = SizeGuide::find($id);
        if (!$sizeGuide) {
            return response()->json(['_status' => 404, 'message' => 'Không tìm thấy bảng size']);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:size_guides,name,' . $id,
            'table_headers' => 'nullable|array',
            'table_rows' => 'nullable|array',
            'tips' => 'nullable|array',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,category_id'
        ]);

        $sizeGuide->update($request->only(['name', 'description', 'table_headers', 'table_rows', 'tips']));

        // Cập nhật danh mục
        if ($request->has('category_ids')) {
            // Xóa liên kết cũ
            Category::where('size_guide_id', $id)->update(['size_guide_id' => null]);
            // Tạo liên kết mới
            Category::whereIn('category_id', $request->category_ids)->update(['size_guide_id' => $id]);
        }

        Cache::flush();

        return response()->json([
            '_status' => 200,
            'message' => 'Cập nhật bảng size thành công',
            'data' => $sizeGuide
        ]);
    }

    public function destroy($id)
    {
        $sizeGuide = SizeGuide::find($id);
        if (!$sizeGuide) {
            return response()->json(['_status' => 404, 'message' => 'Không tìm thấy bảng size']);
        }
        
        // Remove relationships
        Category::where('size_guide_id', $id)->update(['size_guide_id' => null]);
        
        $sizeGuide->delete();

        Cache::flush();

        return response()->json([
            '_status' => 200,
            'message' => 'Xóa bảng size thành công'
        ]);
    }
}
