<?php

namespace App\Http\Controllers;

use App\Models\PostComment;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Exception;

class PostCommentController extends Controller
{
    /**
     * Get comments of a post (approved only).
     */
    public function getByPost($postId)
    {
        $comments = PostComment::with('user:user_id,full_name,avatar_url')
            ->where('post_id', $postId)
            ->where('is_approved', true)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $comments
        ]);
    }

    /**
     * Post a comment on a post with spam protection.
     */
    public function store(Request $request, $postId)
    {
        // 1. Basic validation
        $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $post = Post::find($postId);
        if (!$post) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy bài viết.'], 404);
        }

        // Get authenticated user
        $userId = auth('api')->id() ?? auth('admin')->id();
        if (!$userId) {
            return response()->json(['status' => 'error', 'message' => 'Bạn phải đăng nhập mới được bình luận.'], 401);
        }

        $content = $request->input('content');

        // 2. Block spam links
        if (preg_match('/(https?:\/\/[^\s]+|www\.[^\s]+)/i', $content)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nội dung bình luận không được chứa liên kết (link).'
            ], 422);
        }

        // 3. Block keywords
        $blacklist = ['sex', 'casino', 'cờ bạc', 'lừa đảo', 'hack', 'vip', '18+', 'đánh bài', 'nhà cái'];
        foreach ($blacklist as $word) {
            if (stripos($content, $word) !== false) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bình luận chứa từ ngữ không hợp lệ.'
                ], 422);
            }
        }

        // Since first-time moderation is removed, all comments are auto-approved directly
        $comment = PostComment::create([
            'post_id' => $postId,
            'user_id' => $userId,
            'content' => $content,
            'is_approved' => true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng bình luận thành công.',
            'data' => $comment->load('user:user_id,full_name,avatar_url')
        ], 201);
    }

    /**
     * Admin: List all comments for moderation.
     */
    public function adminIndex(Request $request)
    {
        $query = PostComment::with([
            'user:user_id,full_name,email,avatar_url',
            'post:post_id,title,slug'
        ]);

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('is_approved', $request->status === 'approved' ? 1 : 0);
        }

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->whereHas('post', fn($p) => $p->where('title', 'like', $search))
                  ->orWhereHas('user', fn($u) => $u->where('full_name', 'like', $search))
                  ->orWhere('content', 'like', $search);
            });
        }

        $comments = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $comments
        ]);
    }

    /**
     * Admin: Approve a comment.
     */
    public function approve($id)
    {
        $comment = PostComment::findOrFail($id);
        $comment->is_approved = true;
        $comment->save();

        return response()->json(['status' => 'success', 'message' => 'Đã duyệt bình luận.']);
    }

    /**
     * Admin: Delete a comment.
     */
    public function destroy($id)
    {
        $comment = PostComment::findOrFail($id);
        $comment->delete();

        return response()->json(['status' => 'success', 'message' => 'Đã xóa bình luận.']);
    }
}
