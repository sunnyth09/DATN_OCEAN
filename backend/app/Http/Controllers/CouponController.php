<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\UserCoupon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CouponController extends Controller
{
    /**
     * Admin: Danh sách tất cả mã giảm giá (kèm categories + lượt dùng)
     */
    public function index()
    {
        $coupons = Coupon::with(['categories:category_id,name', 'userCoupons'])->paginate(20);

        // Thêm thông tin thống kê cho mỗi coupon
        collect($coupons->items())->each(function ($coupon) {
            $coupon->total_users_used = $coupon->userCoupons->where('used_count', '>', 0)->count();
            $coupon->category_ids = $coupon->categories->pluck('category_id');
        });

        return response()->json([
            'status' => 'success',
            'data' => $coupons->items(),
            'total' => $coupons->total(),
            'current_page' => $coupons->currentPage(),
            'last_page' => $coupons->lastPage(),
        ]);
    }

    /**
     * Admin: Tạo mã giảm giá mới
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:20|unique:coupons,code',
            'type' => 'required|in:percent,fixed,free_ship',
            'value' => 'required|numeric|min:0' . ($request->input('type') === 'percent' ? '|max:100' : ''),
            'max_discount_value' => 'nullable|numeric|min:0',
            'min_order_value' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'user_usage_limit' => 'required|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'is_first_order' => 'boolean',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,category_id',
            'send_email' => 'boolean',
        ]);

        $coupon = Coupon::create($request->only([
            'code', 'type', 'value', 'max_discount_value', 'min_order_value',
            'usage_limit', 'user_usage_limit', 'start_date', 'end_date',
            'is_active', 'is_public', 'is_first_order'
        ]));

        // Sync danh mục áp dụng
        if ($request->has('category_ids') && is_array($request->category_ids)) {
            $coupon->categories()->sync($request->category_ids);
        }
        
        Cache::forget('coupons:public_active');

        // Gửi email thông báo cho khách hàng
        if ($request->input('send_email')) {
            \App\Jobs\SendBulkCouponEmail::dispatch($coupon);
            return response()->json([
                'status' => 'success',
                'message' => "Mã giảm giá hoạt động và đang tiến hành xử lý gửi email hàng loạt ngầm ở hậu trường.",
                'data' => $coupon->load('categories:category_id,name')
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Mã giảm giá đã được tạo thành công',
            'data' => $coupon->load('categories:category_id,name')
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Coupon $coupon)
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Coupon $coupon)
    {
        //
    }

    /**
     * Admin: Cập nhật mã giảm giá
     */
    public function update(Request $request, $id)
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy mã giảm giá!'
            ], 404);
        }

        $request->validate([
            'code' => 'required|string|max:20|unique:coupons,code,' . $id,
            'type' => 'required|in:percent,fixed,free_ship',
            'value' => 'required|numeric|min:0' . ($request->input('type') === 'percent' ? '|max:100' : ''),
            'max_discount_value' => 'nullable|numeric|min:0',
            'min_order_value' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'user_usage_limit' => 'required|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'is_first_order' => 'boolean',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,category_id',
        ]);

        $coupon->update($request->except(['category_ids']));

        // Sync lại danh mục áp dụng
        if ($request->has('category_ids')) {
            $coupon->categories()->sync($request->category_ids ?? []);
        }
        
        Cache::forget('coupons:public_active');

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật mã giảm giá thành công!',
            'data' => $coupon->load('categories:category_id,name')
        ]);
    }

    /**
     * Admin: Xóa mã giảm giá (soft delete)
     */
    public function destroy($id)
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy mã giảm giá!'
            ], 404);
        }

        $coupon->delete();
        Cache::forget('coupons:public_active');

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xóa mã giảm giá thành công!'
        ]);
    }

    /**
     * Lấy danh sách mã giảm giá công khai cho khách hàng
     */
    public function getPublicCoupons()
    {
        $coupons = Cache::remember('coupons:public_active', 1800, function () {
            $now = now();
            return Coupon::where('is_public', true)
                ->where('is_active', true)
                ->where(function ($query) use ($now) {
                    $query->whereNull('start_date')
                          ->orWhere('start_date', '<=', $now);
                })
                ->where(function ($query) use ($now) {
                    $query->whereNull('end_date')
                          ->orWhere('end_date', '>=', $now);
                })
                ->with('categories:category_id,name')
                ->get();
        });

        return response()->json([
            'status' => 'success',
            'data' => $coupons
        ]);
    }

    /**
     * Khách hàng lưu mã giảm giá
     */
    public function saveCoupon(Request $request)
    {
        // Ưu tiên guard 'api' (bảng users), fallback sang 'admin' (bảng admins)
        $user = auth('api')->user();
        $userId = $user ? $user->user_id : null;

        if (!$userId && auth('admin')->check()) {
            $adminUser = auth('admin')->user();
            $userId = $adminUser->getKey();
        }
        
        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập để lưu mã giảm giá!'
            ], 401);
        }

        $couponId = $request->input('coupon_id');
        $coupon = Coupon::find($couponId);

        if (!$coupon || !$coupon->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mã giảm giá không tồn tại hoặc đã hết hạn!'
            ], 404);
        }

        // Kiểm tra xem đã lưu chưa
        $userCoupon = UserCoupon::where('user_id', $userId)
            ->where('coupon_id', $couponId)
            ->first();

        if ($userCoupon) {
            return response()->json([
                'status' => 'info',
                'message' => 'Bạn đã lưu mã giảm giá này rồi!'
            ]);
        }

        UserCoupon::create([
            'user_id' => $userId,
            'coupon_id' => $couponId,
            'is_saved' => true
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã lưu mã giảm giá thành công!'
        ]);
    }

    /**
     * Lấy danh sách mã giảm giá của tôi (đã lưu)
     */
    public function getUserCoupons()
    {
        $user = auth('api')->user();
        $userId = $user ? $user->user_id : null;

        if (!$userId && auth('admin')->check()) {
            $userId = auth('admin')->user()->getKey();
        }

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập!'
            ], 401);
        }

        // Lấy các user_coupons kèm thông tin coupon
        /** @var \Illuminate\Database\Eloquent\Builder $query */
        $query = UserCoupon::with('coupon');
        $userCoupons = $query->where('user_id', $userId)
            ->where('is_saved', true)
            ->get()
            ->filter(function ($userCoupon) {
                $coupon = $userCoupon->coupon;
                if (!$coupon) return false;
                
                // Ẩn các mã mà user đã dùng hết số lần cho phép
                if ($coupon->user_usage_limit && $userCoupon->used_count >= $coupon->user_usage_limit) {
                    return false;
                }
                
                return true;
            })->values();

        return response()->json([
            'status' => 'success',
            'data' => $userCoupons->pluck('coupon')
        ]);
    }

    /**
     * Admin: Xem danh sách users đã dùng coupon cụ thể
     */
    public function getCouponUsages($id)
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy mã giảm giá!'
            ], 404);
        }

        /** @var \Illuminate\Database\Eloquent\Builder $query */
        $query = UserCoupon::with('user:user_id,full_name,email,phone,avatar_url');
        $usages = $query->where('coupon_id', $id)
            ->orderByDesc('used_count')
            ->get()
            ->map(function ($uc) {
                return [
                    'user_id' => $uc->user_id,
                    'full_name' => $uc->user->full_name ?? 'N/A',
                    'email' => $uc->user->email ?? '',
                    'phone' => $uc->user->phone ?? '',
                    'avatar_url' => $uc->user->avatar_url ?? null,
                    'used_count' => $uc->used_count,
                    'is_saved' => $uc->is_saved,
                    'saved_at' => $uc->created_at,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'coupon' => [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'used_count' => $coupon->used_count,
                    'usage_limit' => $coupon->usage_limit,
                ],
                'usages' => $usages,
                'total_saved' => $usages->count(),
                'total_used' => $usages->filter(function($u) { return $u['used_count'] > 0; })->count(),
            ]
        ]);
    }

    // Private send email function is moved to App\Jobs\SendBulkCouponEmail
}
