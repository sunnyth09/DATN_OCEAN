<?php

namespace App\Http\Controllers;

use App\Models\ProductComment;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ProductCommentController extends Controller
{
<<<<<<< HEAD
    use AuthorizesRequests;

=======
    public function __construct(
        protected LoyaltyService $loyaltyService
    ) {}  
>>>>>>> origin/Dev
    /**
     * Store a newly created comment.
     */
    public function store(Request $request)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'content' => 'nullable|string|max:1000',
            'product_id' => 'required|exists:products,product_id',
            'order_item_id' => 'required|exists:order_items,order_item_id',
            'images' => 'nullable|array|max:5',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ],[
            'rating.required' => 'Vui lòng nhập đánh giá',
            'rating.integer' => 'Đánh giá phải là số nguyên',
            'rating.min' => 'Đánh giá phải từ 1 đến 5 sao',
            'rating.max' => 'Đánh giá phải từ 1 đến 5 sao',
            'content.max' => 'Nội dung đánh giá không được vượt quá 1000 ký tự',
            'product_id.required' => 'Vui lòng chọn sản phẩm',
            'product_id.exists' => 'Sản phẩm không tồn tại',
            'order_item_id.required' => 'Vui lòng chọn đơn hàng',
            'order_item_id.exists' => 'Đơn hàng không tồn tại',
            'images.max' => 'Chỉ được tải lên tối đa 5 ảnh',
            'images.*.image' => 'Ảnh phải là định dạng ảnh',
            'images.*.mimes' => 'Ảnh phải có định dạng jpeg, png, jpg, gif, webp',
            'images.*.max' => 'Ảnh không được vượt quá 2MB',
        ]);

        $userId = auth('api')->user()?->user_id;
        if (!$userId) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        // Verify order item
        $orderItem = OrderItem::with('order')->find($request->order_item_id);

        if (!$orderItem) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy OrderItem trong DB.'], 404);
        }

        // Policy kiểm tra: ownership + order completed + chưa review
        // Array syntax: [ModelClass, $argument] → Laravel resolve sang ProductCommentPolicy::create($user, $orderItem)
        $this->authorize('create', [\App\Models\ProductComment::class, $orderItem]);

        // Verify product matches item
        if ($orderItem->product_id != $request->product_id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Sản phẩm không khớp với đơn hàng. Tham số truyền lên: ' . $request->product_id . ', trong DB: ' . $orderItem->product_id,
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Lưu ảnh nếu có
            $imagePaths = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    if ($image && $image->isValid()) {
                        $path = $image->store('product_comments', 'public');
                        $imagePaths[] = $path;
                    }
                }
            }

            $comment = ProductComment::create([
                'product_id'     => $request->product_id,
                'user_id'        => $userId,
                'commenter_type' => 'user',
                'order_item_id'  => $request->order_item_id,
                'rating'         => $request->rating,
                'content'        => $request->content,
                'is_approved'    => 0,
                'images'         => !empty($imagePaths) ? json_encode($imagePaths) : null,
            ]);

<<<<<<< HEAD
            // Nếu rating <= 3, tự động tạo Ticket (Khiếu nại) cho admin
            if ($request->rating <= 3) {
                \App\Models\Ticket::create([
                    'user_id'     => $userId,
                    'order_id'    => $orderItem->order_id,
                    'product_id'  => $request->product_id,
                    'reason'      => 'Phản hồi đánh giá thấp (' . $request->rating . ' sao)',
                    'description' => $request->content ?? 'Khách hàng đánh giá chất lượng sản phẩm thấp.',
                    'status'      => 'pending',
                ]);
            }
=======
            // ── Tích điểm Loyalty ──────────────────────────────────────────
            $user = auth('api')->user();
            if ($user) {
                // +20 điểm khi viết nhận xét có nội dung
                if (!empty(trim($request->content ?? ''))) {
                    $this->loyaltyService->earnFromReview($user, $comment->id);
                }
                // +50 điểm bonus khi đính kèm hình ảnh
                if (!empty($imagePaths)) {
                    $this->loyaltyService->earnFromReviewWithImage($user, $comment->id);
                }
            }
            // ───────────────────────────────────────────────────────────────
>>>>>>> origin/Dev

            // Recalculate average rating for the product using approved comments
            $this->recalculateProductRating($request->product_id);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Đánh giá sản phẩm thành công.',
                'data' => $comment->load('user:user_id,full_name,avatar_url')
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Đã xảy ra lỗi: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display a listing of comments for a specific product.
     */
    public function getByProduct($productId)
    {
        $comments = ProductComment::with('user:user_id,full_name,avatar_url')
                        ->where('product_id', $productId)
                        ->where('is_approved', 1)
                        ->orderBy('created_at', 'desc')
                        ->paginate(10);

        // Append commenter_info từ đúng bảng
        $comments->getCollection()->transform(function ($comment) {
            if ($comment->commenter_type === 'admin') {
                $admin = \App\Models\Admin::find($comment->user_id);
                $comment->commenter_info = $admin ? [
                    'full_name' => $admin->full_name,
                    'avatar_url' => $admin->avatar_url,
                ] : null;
            } else {
                $comment->commenter_info = $comment->user ? [
                    'full_name' => $comment->user->full_name,
                    'avatar_url' => $comment->user->avatar_url,
                ] : null;
            }
            return $comment;
        });
                        
        return response()->json([
            'status' => 'success',
            'data' => $comments
        ]);
    }

    /**
     * Admin: List all comments with filters, search, and pagination.
     */
    public function adminIndex(Request $request)
    {
        $query = ProductComment::with([
            'user:user_id,full_name,email,avatar_url',
            'product:product_id,name,thumbnail_url'
        ]);

        // Filter by approval status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('is_approved', $request->status === 'approved' ? 1 : 0);
        }

        // Filter by rating
        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        // Search by product name or user name
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', fn($p) => $p->where('name', 'like', $search))
                  ->orWhereHas('user', fn($u) => $u->where('full_name', 'like', $search));
            });
        }

        $comments = $query->orderBy('created_at', 'desc')->paginate(15);

        // Append commenter_info từ đúng bảng (users hoặc admins)
        $comments->getCollection()->transform(function ($comment) {
            if ($comment->commenter_type === 'admin') {
                $admin = \App\Models\Admin::find($comment->user_id);
                $comment->commenter_info = $admin ? [
                    'full_name' => $admin->full_name,
                    'email' => $admin->email,
                    'avatar_url' => $admin->avatar_url,
                ] : null;
            } else {
                $comment->commenter_info = $comment->user ? [
                    'full_name' => $comment->user->full_name,
                    'email' => $comment->user->email,
                    'avatar_url' => $comment->user->avatar_url,
                ] : null;
            }
            return $comment;
        });

        return response()->json([
            'status' => 'success',
            'data' => $comments
        ]);
    }

    /**
     * Admin: Approve a comment and recalculate product rating.
     */
    public function approve($id)
    {
        $this->authorize('moderate', ProductComment::class); // Admin/Staff only

        $comment = ProductComment::findOrFail($id);
        $comment->is_approved = 1;
        $comment->save();

        $this->recalculateProductRating($comment->product_id);

        return response()->json(['status' => 'success', 'message' => 'Đã duyệt đánh giá.']);
    }

    /**
     * Admin: Reject (hide) a comment and recalculate product rating.
     */
    public function reject($id)
    {
        $this->authorize('moderate', ProductComment::class); // Admin/Staff only

        $comment = ProductComment::findOrFail($id);
        $comment->is_approved = 0;
        $comment->save();

        $this->recalculateProductRating($comment->product_id);

        return response()->json(['status' => 'success', 'message' => 'Đã ẩn đánh giá.']);
    }

    /**
     * Admin: Delete a comment and recalculate product rating.
     */
    public function destroy($id)
    {
        $this->authorize('delete', ProductComment::class); // Admin/Staff via Policy before(); customer via policy

        $comment = ProductComment::findOrFail($id);
        $productId = $comment->product_id;
        $comment->delete();

        $this->recalculateProductRating($productId);

        return response()->json(['status' => 'success', 'message' => 'Đã xóa đánh giá.']);
    }

    /**
     * Helper: Recalculate and save average rating for a product.
     */
    private function recalculateProductRating($productId)
    {
        $product = Product::find($productId);
        if (!$product) return;

        $avgRating = ProductComment::where('product_id', $productId)
                        ->where('is_approved', 1)->avg('rating');
        $countRating = ProductComment::where('product_id', $productId)
                        ->where('is_approved', 1)->count();

        $product->rating_avg = round($avgRating ?? 0, 1);
        $product->rating_count = $countRating;
        $product->save();
    }
}
