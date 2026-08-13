<?php

namespace App\Http\Controllers;

use App\Events\TicketCreatedAdmin;
use App\Helpers\ProfanityFilter;
use App\Models\Admin;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductComment;
use App\Models\Ticket;
use App\Services\LoyaltyService;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProductCommentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected LoyaltyService $loyaltyService
    ) {}

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
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ], [
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
            'images.*.max' => 'Ảnh không được vượt quá 5MB',
        ]);

        $userId = auth('api')->user()?->user_id;
        if (! $userId) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        // Verify order item
        $orderItem = OrderItem::with('order')->find($request->order_item_id);

        if (! $orderItem) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy OrderItem trong DB.'], 404);
        }

        // Policy kiểm tra: ownership + order completed + chưa review
        // Array syntax: [ModelClass, $argument] → Laravel resolve sang ProductCommentPolicy::create($user, $orderItem)
        $this->authorize('create', [ProductComment::class, $orderItem]);

        // Double-check DB: kiểm tra order_item thuộc về user hiện tại (tránh review hộ người khác)
        if ($orderItem->order->user_id !== $userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không có quyền đánh giá sản phẩm này.',
            ], 403);
        }

        // Double-check DB: đảm bảo chưa review order_item này (chống race condition bypass Policy)
        $alreadyReviewed = ProductComment::where('order_item_id', $request->order_item_id)
            ->where('user_id', $userId)
            ->exists();

        if ($alreadyReviewed) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn đã đánh giá sản phẩm này rồi.',
            ], 409);
        }

        // Verify product matches item
        if ($orderItem->product_id != $request->product_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sản phẩm không khớp với đơn hàng. Tham số truyền lên: '.$request->product_id.', trong DB: '.$orderItem->product_id,
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'images' => 'nullable|array|max:5',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            ]);

            $validator->after(function ($validator) use ($request) {
                if (ProfanityFilter::hasProfanity($request->content)) {
                    $validator->errors()->add('content', 'Nội dung chứa từ ngữ không phù hợp. Vui lòng chỉnh sửa lại.');
                }
            });

            $validator->validate();

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

            $filteredContent = ProfanityFilter::filter($request->content);

            $comment = ProductComment::create([
                'product_id' => $request->product_id,
                'user_id' => $userId,
                'commenter_type' => 'user',
                'order_item_id' => $request->order_item_id,
                'rating' => $request->rating,
                'content' => $filteredContent,
                'is_approved' => ($request->rating >= 3 && empty($imagePaths)) ? 1 : 0,
                'images' => ! empty($imagePaths) ? $imagePaths : null,
            ]);

            // Nếu rating <= 3, tự động tạo Ticket (Khiếu nại) cho admin
            if ($request->rating <= 3) {
                $autoTicket = Ticket::create([
                    'user_id' => $userId,
                    'order_id' => $orderItem->order_id,
                    'product_id' => $request->product_id,
                    'reason' => 'Phản hồi đánh giá thấp ('.$request->rating.' sao)',
                    'description' => $filteredContent ?? 'Khách hàng đánh giá chất lượng sản phẩm thấp.',
                    'status' => 'pending',
                ]);
                event(new TicketCreatedAdmin($autoTicket));
            } elseif ($comment->is_approved == 0) {
                // Đánh giá mới chờ duyệt (ví dụ: đánh giá có hình ảnh)
                $dummyTicket = new Ticket([
                    'ticket_id' => $comment->comment_id,
                    'reason' => 'Đánh giá sản phẩm mới chờ duyệt',
                    'status' => 'pending',
                ]);
                event(new TicketCreatedAdmin($dummyTicket));
            }

            // ── Tích điểm Loyalty ──────────────────────────────────────────
            $user = auth('api')->user();
            if ($user) {
                if (! empty($imagePaths)) {
                    // +50 điểm bonus khi đính kèm hình ảnh
                    $this->loyaltyService->earnFromReviewWithImage($user, $comment->comment_id);
                } elseif (! empty(trim($request->content ?? ''))) {
                    // +20 điểm khi viết nhận xét có nội dung
                    $this->loyaltyService->earnFromReview($user, $comment->comment_id);
                }
            }
            // ───────────────────────────────────────────────────────────────

            // Recalculate average rating for the product using approved comments
            $this->recalculateProductRating($request->product_id);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Đánh giá sản phẩm thành công.',
                'data' => $comment->load('user:user_id,full_name,avatar_url'),
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Product comment store failed', ['error' => $e->getMessage()]);

            return response()->json(['status' => 'error', 'message' => 'Đã xảy ra lỗi, vui lòng thử lại sau.'], 500);
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
                $admin = Admin::find($comment->user_id);
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
            'data' => $comments,
        ]);
    }

    /**
     * Admin: List all comments with filters, search, and pagination.
     */
    public function adminIndex(Request $request)
    {
        $query = ProductComment::with([
            'user:user_id,full_name,email,avatar_url',
            'product:product_id,name,thumbnail_url',
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
            $search = '%'.$request->search.'%';
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', fn ($p) => $p->where('name', 'like', $search))
                    ->orWhereHas('user', fn ($u) => $u->where('full_name', 'like', $search));
            });
        }

        $comments = $query->orderBy('created_at', 'desc')->paginate(10);

        // Append commenter_info từ đúng bảng (users hoặc admins)
        $comments->getCollection()->transform(function ($comment) {
            if ($comment->commenter_type === 'admin') {
                $admin = Admin::find($comment->user_id);
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
            'data' => $comments,
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
     * Store multiple comments in a single transaction (batch).
     */
    public function storeBatch(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.rating' => 'required|integer|min:1|max:5',
            'items.*.content' => 'nullable|string|max:1000',
            'items.*.product_id' => 'required|exists:products,product_id',
            'items.*.order_item_id' => 'required|exists:order_items,order_item_id',
            'items.*.images' => 'nullable|array|max:5',
            'items.*.images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ], [
            'items.*.rating.required' => 'Vui lòng nhập đánh giá cho tất cả sản phẩm.',
            'items.*.rating.integer' => 'Đánh giá phải là số nguyên.',
            'items.*.rating.min' => 'Đánh giá phải từ 1 đến 5 sao.',
            'items.*.rating.max' => 'Đánh giá phải từ 1 đến 5 sao.',
            'items.*.content.max' => 'Nội dung đánh giá không được vượt quá 1000 ký tự.',
            'items.*.product_id.required' => 'Vui lòng chọn sản phẩm.',
            'items.*.product_id.exists' => 'Sản phẩm không tồn tại.',
            'items.*.order_item_id.required' => 'Vui lòng chọn đơn hàng.',
            'items.*.order_item_id.exists' => 'Đơn hàng không tồn tại.',
            'items.*.images.max' => 'Mỗi sản phẩm chỉ được tải lên tối đa 5 ảnh.',
            'items.*.images.*.image' => 'Ảnh phải là định dạng ảnh.',
            'items.*.images.*.mimes' => 'Ảnh phải có định dạng jpeg, png, jpg, gif, webp.',
            'items.*.images.*.max' => 'Ảnh không được vượt quá 5MB.',
        ]);

        $userId = auth('api')->user()?->user_id;
        if (! $userId) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        // Validate all items before database transaction (all-or-nothing check)
        $itemsData = $request->input('items');
        $validatedItems = [];

        foreach ($itemsData as $index => $item) {
            $orderItemId = $item['order_item_id'];
            $productId = $item['product_id'];

            $orderItem = OrderItem::with('order')->find($orderItemId);
            if (! $orderItem) {
                return response()->json(['status' => 'error', 'message' => 'Không tìm thấy chi tiết đơn hàng.'], 404);
            }

            // Check ownership
            if ($orderItem->order->user_id !== $userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bạn không có quyền đánh giá sản phẩm này.',
                ], 403);
            }

            // Check duplicate review
            $alreadyReviewed = ProductComment::where('order_item_id', $orderItemId)
                ->where('user_id', $userId)
                ->exists();
            if ($alreadyReviewed) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bạn đã đánh giá sản phẩm này rồi.',
                ], 409);
            }

            // Check product matches item
            if ($orderItem->product_id != $productId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sản phẩm không khớp với đơn hàng.',
                ], 400);
            }

            // Profanity filter validation
            if (ProfanityFilter::hasProfanity($item['content'] ?? '')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Dữ liệu không hợp lệ.',
                    'errors' => [
                        "items.{$index}.content" => ['Nội dung đánh giá chứa từ ngữ không phù hợp. Vui lòng chỉnh sửa lại.']
                    ]
                ], 422);
            }

            $validatedItems[] = [
                'orderItem' => $orderItem,
                'data' => $item,
                'index' => $index,
            ];
        }

        DB::beginTransaction();
        try {
            $comments = [];
            foreach ($validatedItems as $itemInfo) {
                $orderItem = $itemInfo['orderItem'];
                $data = $itemInfo['data'];
                $index = $itemInfo['index'];

                // Retrieve files for this nested item index
                $files = $request->file("items.{$index}.images") ?? [];

                $imagePaths = [];
                if (! empty($files)) {
                    foreach ($files as $image) {
                        if ($image && $image->isValid()) {
                            $path = $image->store('product_comments', 'public');
                            $imagePaths[] = $path;
                        }
                    }
                }

                $filteredContent = ProfanityFilter::filter($data['content'] ?? '');

                $comment = ProductComment::create([
                    'product_id' => $data['product_id'],
                    'user_id' => $userId,
                    'commenter_type' => 'user',
                    'order_item_id' => $data['order_item_id'],
                    'rating' => $data['rating'],
                    'content' => $filteredContent,
                    'is_approved' => ($data['rating'] >= 3 && empty($imagePaths)) ? 1 : 0,
                    'images' => ! empty($imagePaths) ? $imagePaths : null,
                ]);

                // Create ticket if rating <= 3
                if ($data['rating'] <= 3) {
                    Ticket::create([
                        'user_id' => $userId,
                        'order_id' => $orderItem->order_id,
                        'product_id' => $data['product_id'],
                        'reason' => 'Phản hồi đánh giá thấp ('.$data['rating'].' sao)',
                        'description' => $filteredContent ?? 'Khách hàng đánh giá chất lượng sản phẩm thấp.',
                        'status' => 'pending',
                    ]);
                }

                // Loyalty points
                $user = auth('api')->user();
                if ($user) {
                    if (! empty($imagePaths)) {
                        $this->loyaltyService->earnFromReviewWithImage($user, $comment->comment_id);
                    } elseif (! empty(trim($data['content'] ?? ''))) {
                        $this->loyaltyService->earnFromReview($user, $comment->comment_id);
                    }
                }

                // Recalculate rating
                $this->recalculateProductRating($data['product_id']);

                $comments[] = $comment->load('user:user_id,full_name,avatar_url');
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Đánh giá sản phẩm thành công.',
                'data' => $comments,
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Product comment batch store failed', ['error' => $e->getMessage()]);

            return response()->json(['status' => 'error', 'message' => 'Đã xảy ra lỗi, vui lòng thử lại sau.'], 500);
        }
    }

    /**
     * Helper: Recalculate and save average rating for a product.
     */
    private function recalculateProductRating($productId)
    {
        $product = Product::find($productId);
        if (! $product) {
            return;
        }

        $avgRating = ProductComment::where('product_id', $productId)
            ->where('is_approved', 1)->avg('rating');
        $countRating = ProductComment::where('product_id', $productId)
            ->where('is_approved', 1)->count();

        $product->rating_avg = round($avgRating ?? 0, 1);
        $product->rating_count = $countRating;
        $product->save();
    }
}
