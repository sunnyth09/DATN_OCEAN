<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreCouponRequest;
use App\Http\Requests\Admin\UpdateCouponRequest;
use App\Services\CouponService;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function __construct(
        protected CouponService $couponService
    ) {}

    /**
     * Admin: Danh sách tất cả mã giảm giá (kèm categories + lượt dùng)
     */
    public function index()
    {
        $coupons = $this->couponService->adminPaginate();

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
    public function store(StoreCouponRequest $request)
    {
        $coupon = $this->couponService->adminCreate($request->validated());

        // Gửi email thông báo cho khách hàng
        if ($request->boolean('send_email')) {
            \App\Jobs\SendBulkCouponEmail::dispatch($coupon);
            return response()->json([
                'status' => 'success',
                'message' => "Mã giảm giá hoạt động và đang tiến hành xử lý gửi email hàng loạt ngầm ở hậu trường.",
                'data' => $coupon
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Mã giảm giá đã được tạo thành công',
            'data' => $coupon
        ]);
    }

    /**
     * Admin: Cập nhật mã giảm giá
     */
    public function update(UpdateCouponRequest $request, $id)
    {
        $coupon = $this->couponService->adminUpdate($id, $request->validated());

        if (!$coupon) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy mã giảm giá!'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật mã giảm giá thành công!',
            'data' => $coupon
        ]);
    }

    /**
     * Admin: Xóa mã giảm giá (soft delete)
     */
    public function destroy($id)
    {
        if (!$this->couponService->adminDelete($id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy mã giảm giá!'
            ], 404);
        }

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
        return response()->json([
            'status' => 'success',
            'data' => $this->couponService->getPublicCoupons()
        ]);
    }

    /**
     * Khách hàng lưu mã giảm giá
     */
    public function saveCoupon(Request $request)
    {
        // Chỉ khách hàng (guard 'api', bảng users) mới lưu được mã. KHÔNG fallback sang
        // guard 'admin': admins là bảng riêng, dùng admin_id làm user_id sẽ ghi nhầm mã
        // sang một khách hàng khác có cùng giá trị khóa (đụng không gian id giữa 2 bảng).
        $user = auth('api')->user();
        $userId = $user ? $user->user_id : null;

        if (!$userId && auth('admin')->check()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tài khoản nhân viên/quản trị không thể lưu mã giảm giá. Vui lòng đăng nhập bằng tài khoản khách hàng.'
            ], 403);
        }

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập để lưu mã giảm giá!'
            ], 401);
        }

        $result = $this->couponService->saveForUser($userId, $request->input('coupon_id'));

        if ($result['state'] === 'not_found') {
            return response()->json(['status' => 'error', 'message' => $result['message']], 404);
        }

        if ($result['state'] === 'already_saved') {
            return response()->json(['status' => 'info', 'message' => $result['message']]);
        }

        return response()->json(['status' => 'success', 'message' => $result['message']]);
    }

    /**
     * Lấy danh sách mã giảm giá của tôi (đã lưu)
     */
    public function getUserCoupons()
    {
        $user = auth('api')->user();
        $userId = $user ? $user->user_id : null;

        if (!$userId && auth('admin')->check()) {
            abort(403, 'Tài khoản nhân viên/quản trị không thể lưu mã giảm giá. Vui lòng đăng nhập bằng tài khoản khách hàng.');
        }

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập!'
            ], 401);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->couponService->getSavedForUser($userId)
        ]);
    }

    /**
     * Admin: Xem danh sách users đã dùng coupon cụ thể
     */
    public function getCouponUsages($id)
    {
        $data = $this->couponService->adminUsages($id);

        if (!$data) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy mã giảm giá!'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }
}
