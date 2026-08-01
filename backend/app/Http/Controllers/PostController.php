<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PostController extends Controller
{
    private const SUMMARY_MAX_LENGTH = 500;

    private const SEO_DESCRIPTION_MAX_LENGTH = 500;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $posts = Post::with('category')->get();

        return response()->json($posts);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'summary' => 'nullable|string|max:'.self::SUMMARY_MAX_LENGTH,
            'content' => 'nullable|string',
            'post_category_id' => 'required|exists:post_categories,post_category_id',
            'post_type' => 'nullable|string',
            'status' => 'nullable|string',
            'is_featured' => 'nullable|boolean',
            'view_count' => 'nullable|integer',
            'published_at' => 'nullable|date',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'banner' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:'.self::SEO_DESCRIPTION_MAX_LENGTH,
            'seo_keywords' => 'nullable|string|max:255',
        ], [
            'summary.max' => 'Tóm tắt nội dung không được vượt quá '.self::SUMMARY_MAX_LENGTH.' ký tự.',
            'seo_description.max' => 'Mô tả SEO không được vượt quá '.self::SEO_DESCRIPTION_MAX_LENGTH.' ký tự.',
        ]);
        $post = Post::where('slug', Str::slug($request->title))->first();
        if ($post) {
            $request['slug'] = Str::slug($request->title).'-'.rand(1, 100);
        } else {
            $request['slug'] = Str::slug($request->title);
        }
        $request['post_type'] = $request->post_type ?? 'news';
        $request['status'] = $request->status ?? 'draft';
        $request['is_featured'] = $request->is_featured ?? false;
        $request['view_count'] = $request->view_count ?? 0;
        $request['published_at'] = $request->published_at ?? date('Y-m-d H:i:s');
        $request['thumbnail'] = $request->thumbnail ?? null;
        $request['banner'] = $request->banner ?? null;
        $request['seo_title'] = $request->seo_title ?? null;
        $request['seo_description'] = $request->seo_description ?? null;
        $request['seo_keywords'] = $request->seo_keywords ?? null;

        if ($request->hasFile('thumbnail')) {
            $thumbnail = $request->file('thumbnail');
            $thumbnailPath = $thumbnail->store('uploads/posts', 'public');
            $request['thumbnail'] = 'storage/'.$thumbnailPath;
        }

        if ($request->hasFile('banner')) {
            $banner = $request->file('banner');
            $bannerPath = $banner->store('uploads/posts', 'public');
            $request['banner'] = 'storage/'.$bannerPath;
        }

        $post = Post::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Thêm bài viết thành công',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Post $post)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Post $post)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Post $post)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Post $post)
    {
        //
    }
}
