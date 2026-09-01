<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    private const SUMMARY_MAX_LENGTH = 500;

    private const SEO_DESCRIPTION_MAX_LENGTH = 500;

    private const PUBLIC_POST_LIMIT_MAX = 50;

    private function generateUniqueSlug(string $source, ?int $ignorePostId = null): string
    {
        $baseSlug = Str::slug($source);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'bai-viet';
        $baseSlug = substr($baseSlug, 0, 95);

        $slug = $baseSlug;
        $counter = 1;

        while (Post::where('slug', $slug)
            ->when($ignorePostId, fn ($query) => $query->where('post_id', '!=', $ignorePostId))
            ->exists()) {
            $suffix = '-'.$counter++;
            $slug = substr($baseSlug, 0, 100 - strlen($suffix)).$suffix;
        }

        return $slug;
    }

    /**
     * Lấy danh sách bài viết hiển thị công khai (Public).
     * Chỉ lấy các bài viết có trạng thái 'published'.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:'.self::PUBLIC_POST_LIMIT_MAX,
            'post_type' => 'nullable|string|max:50',
            'is_featured' => 'nullable|boolean',
        ]);

        $query = Post::with(['category', 'author'])
            ->where('status', 'published');

        if (! empty($validated['post_type'])) {
            $query->where('post_type', $validated['post_type']);
        }

        if ($request->has('is_featured')) {
            $query->where('is_featured', $request->boolean('is_featured'));
        }

        $posts = $query
            ->orderBy('published_at', 'desc')
            ->when(! empty($validated['limit']), fn ($query) => $query->limit($validated['limit']))
            ->get();

        return response()->json(PostResource::collection($posts)->resolve());
    }

    /**
     * Lấy danh sách bài viết dành cho Admin.
     * Cho phép lọc theo trạng thái (published, draft, hidden), tìm kiếm và thùng rác.
     *
     * @return JsonResponse
     */
    public function adminIndex(Request $request)
    {
        $trashed = $request->input('trashed');
        $status = $request->input('status');
        $search = $request->input('search');
        $postType = $request->input('post_type');

        $query = Post::with(['category', 'author']);

        if ($trashed === 'only' || $trashed === 'trash' || $trashed === 'deleted') {
            $query->onlyTrashed();
        } elseif ($trashed === 'with') {
            $query->withTrashed();
        }

        if (! empty($status) && $status !== 'all') {
            $query->where('status', $status);
        }

        if (! empty($postType) && $postType !== 'all') {
            $query->where('post_type', $postType);
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('summary', 'LIKE', "%{$search}%")
                    ->orWhere('slug', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('is_featured')) {
            $query->where('is_featured', $request->boolean('is_featured'));
        }

        if ($trashed === 'only' || $trashed === 'trash' || $trashed === 'deleted') {
            $query->orderBy('deleted_at', 'desc');
        } else {
            $query->orderByRaw('published_at IS NULL')
                ->orderBy('published_at', 'desc')
                ->orderBy('created_at', 'desc');
        }

        $limit = $request->input('limit');
        if (! empty($limit)) {
            $query->limit((int) $limit);
        }

        $posts = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => PostResource::collection($posts)->resolve(),
            'total' => $posts->count(),
        ]);
    }

    /**
     * Thống kê số lượng bài viết theo từng trạng thái & thùng rác.
     *
     * @return JsonResponse
     */
    public function counts()
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'all' => Post::count(),
                'published' => Post::where('status', 'published')->count(),
                'draft' => Post::where('status', 'draft')->count(),
                'hidden' => Post::where('status', 'hidden')->count(),
                'trashed' => Post::onlyTrashed()->count(),
            ],
        ]);
    }

    /**
     * Thêm mới một bài viết vào hệ thống.
     * Tự động sinh slug nếu không được cung cấp và gắn tác giả là người dùng hiện tại.
     *
     * @return JsonResponse
     *
     * @throws ValidationException
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

        $slugSource = $request->filled('slug') ? $request->slug : $request->title;
        $slug = $this->generateUniqueSlug($slugSource);

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
            'data' => $post,
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
            ->where('status', 'published')
            ->where(function ($query) use ($idOrSlug) {
                $query->where('post_id', $idOrSlug)
                    ->orWhere('slug', $idOrSlug);
            })
            ->firstOrFail();

        // Redis-based view count throttle: 3-minute cooldown per IP
        $ip = request()->ip();
        $cacheKey = 'post_viewed:'.$post->post_id.':'.$ip;

        try {
            if (Redis::setnx($cacheKey, 1)) {
                Redis::expire($cacheKey, 180);
                $post->increment('view_count');
            }
        } catch (\Exception $e) {
            $fallbackKey = 'post_viewed_fallback_'.$post->post_id;
            if (! session()->has($fallbackKey)) {
                $post->increment('view_count');
                session()->put($fallbackKey, true);
            }
        }

        return response()->json((new PostResource($post))->resolve());
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

        $slugSource = $request->filled('slug') ? $request->slug : $request->title;
        $slug = $this->generateUniqueSlug($slugSource, (int) $id);

        $post->title = $request->title;
        $post->slug = $slug;
        $post->summary = $request->summary;
        $post->content = $request->input('content');
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
            'data' => $post,
        ]);
    }

    /**
     * Upload ảnh nội dung bài viết cho editor Quill
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
        ], [
            'image.required' => 'Vui lòng chọn ảnh.',
            'image.image' => 'File phải là ảnh.',
            'image.mimes' => 'Chỉ hỗ trợ định dạng: JPEG, PNG, JPG, GIF, WEBP.',
            'image.max' => 'Ảnh không được vượt quá 4MB.',
        ]);

        $path = $request->file('image')->store('uploads/post_content', 'public');

        return response()->json([
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    /**
     * Remove the specified resource from storage (Soft delete).
     */
    public function destroy($id)
    {
        $post = Post::findOrFail($id);
        $post->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Đã chuyển bài viết vào thùng rác thành công!',
        ]);
    }

    /**
     * Khôi phục bài viết đã xóa mềm.
     */
    public function restore($id)
    {
        $post = Post::onlyTrashed()->findOrFail($id);
        $post->restore();

        return response()->json([
            'status' => 'success',
            'message' => "Đã khôi phục bài viết '{$post->title}' thành công!",
        ]);
    }

    /**
     * Xóa vĩnh viễn bài viết khỏi hệ thống.
     */
    public function forceDelete($id)
    {
        $post = Post::withTrashed()->findOrFail($id);
        $title = $post->title;
        $post->forceDelete();

        return response()->json([
            'status' => 'success',
            'message' => "Đã xóa vĩnh viễn bài viết '{$title}'!",
        ]);
    }

    /**
     * Khôi phục hàng loạt bài viết.
     */
    public function bulkRestore(Request $request)
    {
        $ids = (array) $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['status' => 'error', 'message' => 'Vui lòng chọn ít nhất 1 bài viết!'], 422);
        }

        $count = Post::onlyTrashed()->whereIn('post_id', $ids)->restore();

        return response()->json([
            'status' => 'success',
            'message' => "Đã khôi phục thành công {$count} bài viết!",
            'count' => $count,
        ]);
    }

    /**
     * Xóa vĩnh viễn hàng loạt bài viết.
     */
    public function bulkForceDelete(Request $request)
    {
        $ids = (array) $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['status' => 'error', 'message' => 'Vui lòng chọn ít nhất 1 bài viết!'], 422);
        }

        $count = Post::withTrashed()->whereIn('post_id', $ids)->forceDelete();

        return response()->json([
            'status' => 'success',
            'message' => "Đã xóa vĩnh viễn thành công {$count} bài viết!",
            'count' => $count,
        ]);
    }
}
