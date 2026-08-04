<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PostController extends Controller
{
    private const SUMMARY_MAX_LENGTH = 500;

    private const SEO_DESCRIPTION_MAX_LENGTH = 500;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Post::with(['category', 'author']);

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('post_type')) {
            $query->where('post_type', $request->query('post_type'));
        }

        if ($request->has('is_featured')) {
            $isFeatured = $request->query('is_featured');
            $query->where('is_featured', $isFeatured === 'true' || $isFeatured === '1' || $isFeatured === 1);
        }

        $query->orderBy('published_at', 'desc');

        if ($request->has('limit')) {
            $posts = $query->limit((int)$request->query('limit'))->get();
        } else {
            $posts = $query->get();
        }

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
        $authorId = auth('admin')->id() ?? auth('api')->id();

        $slug = $request->filled('slug') ? Str::slug($request->slug) : Str::slug($request->title);
        $slug = substr($slug, 0, 100);
        $exists = Post::where('slug', $slug)->exists();
        if ($exists) {
            $slug = substr($slug, 0, 95) . '-' . rand(1, 999);
        }

        $request->merge([
            'slug' => $slug,
            'author_id' => $authorId,
            'post_type' => $request->post_type ?? 'news',
            'status' => $request->status ?? 'draft',
            'is_featured' => filter_var($request->is_featured, FILTER_VALIDATE_BOOLEAN) ?? false,
            'view_count' => $request->view_count ?? 0,
            'published_at' => $request->published_at ?? date('Y-m-d H:i:s'),
        ]);

        if ($request->hasFile('thumbnail')) {
            $thumbnail = $request->file('thumbnail');
            $thumbnailPath = $thumbnail->store('uploads/posts', 'public');
            $request->merge(['thumbnail_url' => 'storage/'.$thumbnailPath]);
        }

        if ($request->hasFile('banner')) {
            $banner = $request->file('banner');
            $bannerPath = $banner->store('uploads/posts', 'public');
            $request->merge(['banner_url' => 'storage/'.$bannerPath]);
        }

        $post = Post::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Thêm bài viết thành công',
            'data' => $post
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
    public function show($idOrSlug)
    {
        $post = Post::with(['category', 'author'])
            ->where(function($query) use ($idOrSlug) {
                $query->where('post_id', $idOrSlug)
                      ->orWhere('slug', $idOrSlug);
            })
            ->firstOrFail();

        if ($post->status === 'published') {
            // Redis-based view count throttle: 5-minute cooldown per IP
            $ip = request()->ip();
            $cacheKey = 'post_viewed:' . $post->post_id . ':' . $ip;

            try {
                if (!Redis::exists($cacheKey)) {
                    $post->increment('view_count');
                    Redis::setex($cacheKey, 300, 1); // 300 seconds = 5 minutes
                }
            } catch (\Exception $e) {
                // Fallback: update DB nếu Redis không khả dụng
                $post->increment('view_count');
            }
        }

        return response()->json($post);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $post = Post::findOrFail($id);
        return response()->json($post);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $post = Post::findOrFail($id);

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

        $slug = $request->filled('slug') ? Str::slug($request->slug) : Str::slug($request->title);
        $slug = substr($slug, 0, 100);
        $exists = Post::where('slug', $slug)->where('post_id', '!=', $id)->exists();
        if ($exists) {
            $slug = substr($slug, 0, 95) . '-' . rand(1, 999);
        }

        $post->title = $request->title;
        $post->slug = $slug;
        $post->summary = $request->summary;
        $post->content = $request->content;
        $post->post_category_id = $request->post_category_id;
        $post->post_type = $request->post_type ?? 'news';
        $post->status = $request->status ?? 'draft';
        $post->is_featured = filter_var($request->is_featured, FILTER_VALIDATE_BOOLEAN) ?? false;
        if ($request->filled('published_at')) {
            $post->published_at = $request->published_at;
        }
        $post->seo_title = $request->seo_title;
        $post->seo_description = $request->seo_description;
        $post->seo_keywords = $request->seo_keywords;

        if ($request->hasFile('thumbnail')) {
            $thumbnail = $request->file('thumbnail');
            $thumbnailPath = $thumbnail->store('uploads/posts', 'public');
            $post->thumbnail_url = 'storage/'.$thumbnailPath;
        }

        if ($request->hasFile('banner')) {
            $banner = $request->file('banner');
            $bannerPath = $banner->store('uploads/posts', 'public');
            $post->banner_url = 'storage/'.$bannerPath;
        }

        $post->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật bài viết thành công',
            'data' => $post
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $post = Post::findOrFail($id);
        $post->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Xóa bài viết thành công',
        ]);
    }
}
