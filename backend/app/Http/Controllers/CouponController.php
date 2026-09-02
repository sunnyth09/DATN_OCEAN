<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreCouponRequest;
use App\Http\Requests\Admin\UpdateCouponRequest;
use App\Jobs\SendBulkCouponEmail;
use App\Services\CouponService;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function __construct(
        protected CouponService $couponService
    ) {}

    /**
     * Admin: Danh sách tất cả mã giảm giá (kèm categories + lượt dùng + lọc thùng rác)
     */
    public function index(Request $request)
    {
        $filters = [
            'status' => $request->input('status'),
            'trashed' => $request->input('trashed'),
            'search' => $request->input('search'),
        ];

        $coupons = $this->couponService->adminPaginate($filters, (int) $request->input('per_page', 20));

        return response()->json([
            'status' => 'success',
            'data' => $coupons->items(),
            'total' => $coupons->total(),
            'current_page' => $coupons->currentPage(),
            'last_page' => $coupons->lastPage(),
        ]);
    }

    /**
     * Admin: Thống kê số lượng mã giảm giá theo từng tab trạng thái.
     */
    public function counts()
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->couponService->getCounts(),
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
            SendBulkCouponEmail::dispatch($coupon);

            return response()->json([
                'status' => 'success',
                'message' => 'Mã giảm giá hoạt động và đang tiến hành xử lý gửi email hàng loạt ngầm ở hậu trường.',
                'data' => $coupon,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Mã giảm giá đã được tạo thành công',
            'data' => $coupon,
        ]);
    }

    /**
     * Admin: Cập nhật mã giảm giá
     */
    public function update(UpdateCouponRequest $request, $id)
    {
        $coupon = $this->couponService->adminUpdate($id, $request->validated());

        if (! $coupon) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy mã giảm giá!',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật mã giảm giá thành công!',
            'data' => $coupon,
        ]);
    }

    /**
     * Admin: Xóa mã giảm giá (soft delete)
     */
    public function destroy($id)
    {
        if (! $this->couponService->adminDelete($id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy mã giảm giá!',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Đã chuyển mã giảm giá vào thùng rác thành công!',
        ]);
    }

    /**
     * Admin: Khôi phục mã giảm giá
     */
    public function restore($id)
    {
        if (! $this->couponService->adminRestore($id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy mã giảm giá trong thùng rác!',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Đã khôi phục mã giảm giá thành công!',
        ]);
    }

    /**
     * Admin: Xóa vĩnh viễn mã giảm giá
     */
    public function forceDelete($id)
    {
        if (! $this->couponService->adminForceDelete($id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy mã giảm giá!',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xóa vĩnh viễn mã giảm giá!',
        ]);
    }

    /**
     * Admin: Khôi phục hàng loạt mã giảm giá
     */
    public function bulkRestore(Request $request)
    {
        $ids = (array) $request->input('ids', []);
        $count = $this->couponService->adminBulkRestore($ids);

        return response()->json([
            'status' => 'success',
            'message' => "Đã khôi phục thành công {$count} mã giảm giá!",
            'count' => $count,
        ]);
    }

    /**
     * Admin: Xóa vĩnh viễn hàng loạt mã giảm giá
     */
    public function bulkForceDelete(Request $request)
    {
        $ids = (array) $request->input('ids', []);
        $count = $this->couponService->adminBulkForceDelete($ids);

        return response()->json([
            'status' => 'success',
            'message' => "Đã xóa vĩnh viễn thành công {$count} mã giảm giá!",
            'count' => $count,
        ]);
    }

    /**
     * Lấy danh sách mã giảm giá công khai cho khách hàng
     */
    public function getPublicCoupons()
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->couponService->getPublicCoupons(),
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

        if (! $userId && auth('admin')->check()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tài khoản nhân viên/quản trị không thể lưu mã giảm giá. Vui lòng đăng nhập bằng tài khoản khách hàng.',
            ], 403);
        }

        if (! $userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập để lưu mã giảm giá!',
            ], 401);
        }

        $validated = $request->validate([
            'coupon_id' => 'required|integer',
        ]);

        $result = $this->couponService->saveForUser($userId, (int) $validated['coupon_id']);

        if ($result['state'] === 'not_found') {
            return response()->json(['status' => 'error', 'message' => $result['message']], 404);
        }

        if ($result['state'] === 'not_eligible') {
            return response()->json(['status' => 'error', 'message' => $result['message']], 400);
        }

        if ($result['state'] === 'already_saved') {
            return response()->json(['status' => 'info', 'message' => $result['message']]);
        }

        return response()->json(['status' => 'success', 'message' => $result['message']]);
    }

    /**
     * Khách hàng kiểm tra/áp dụng mã giảm giá nhập tay
     */
    public function checkCoupon(Request $request)
    {
        $user = auth('api')->user();
        $userId = $user ? $user->user_id : 0;

        $validated = $request->validate([
            'code' => 'required|string',
            'subtotal' => 'required|numeric|min:0',
            'items' => 'nullable|array',
        ]);

        $result = $this->couponService->checkCoupon(
            $userId,
            $validated['code'],
            (float) $validated['subtotal'],
            $validated['items'] ?? null
        );

        if (! $result['success']) {
            return response()->json([
                'status' => 'error',
                'message' => $result['message'],
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'data' => $result['coupon'],
        ]);
    }

    /**
     * Lấy danh sách mã giảm giá của tôi (đã lưu)
     */
    public function getUserCoupons()
    {
        $user = auth('api')->user();
        $userId = $user ? $user->user_id : null;

        if (! $userId && auth('admin')->check()) {
            abort(403, 'Tài khoản nhân viên/quản trị không thể lưu mã giảm giá. Vui lòng đăng nhập bằng tài khoản khách hàng.');
        }

        if (! $userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập!',
            ], 401);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->couponService->getSavedForUser($userId),
        ]);
    }

    /**
     * Admin: Xem danh sách users đã dùng coupon cụ thể
     */
    public function getCouponUsages($id)
    {
        $data = $this->couponService->adminUsages($id);

        if (! $data) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy mã giảm giá!',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }
}
