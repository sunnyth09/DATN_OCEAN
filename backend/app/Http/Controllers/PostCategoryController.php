<?php

namespace App\Http\Controllers;

use App\Models\PostCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PostCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    private function buildCategoryTree($categories, $parentId = null)
    {
        $tree = [];
        foreach ($categories as $category) {
            if ($category->parent_id == $parentId) {
                $children = $this->buildCategoryTree($categories, $category->post_category_id);
                $category->children = $children;
                $tree[] = $category;
            }
        }

        return $tree;
    }

    public function index()
    {
        $postCategories = PostCategory::all();
        $categoryTree = $this->buildCategoryTree($postCategories);

        return response()->json([
            'status' => 'success',
            'message' => 'Post categories retrieved successfully',
            'data' => $categoryTree,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:post_categories,post_category_id',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $request->merge(['slug' => Str::slug($request->name)]);

        $postCategory = PostCategory::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Thêm danh mục bài viết thành công',
            'data' => $postCategory,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Display the specified resource.
     */
    public function show(PostCategory $postCategory) {}

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, $id)
    {
        $postCategory = PostCategory::findOrFail($id);
        if ($postCategory->post_category_id == $request->parent_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Danh mục cha không được là chính nó',
            ], 422);
        }
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:post_categories,post_category_id',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $request->merge(['slug' => Str::slug($request->name)]);

        $postCategory->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật danh mục bài viết thành công',
            'data' => $postCategory,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PostCategory $postCategory)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $postCategory = PostCategory::findOrFail($id);

        if ($postCategory->children()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể xóa danh mục này vì vẫn còn danh mục con',
            ], 400);
        }

        if ($postCategory->posts()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể xóa danh mục này vì vẫn còn bài viết liên kết',
            ], 400);
        }

        $postCategory->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xóa danh mục thành công',
        ]);
    }
}
