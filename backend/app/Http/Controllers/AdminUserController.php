<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Services\AdminUserService;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function __construct(
        protected AdminUserService $adminUserService
    ) {}

    /**
     * Danh sách tất cả users.
     */
    public function index(Request $request)
    {
        $users = $this->adminUserService->paginate($request->input('search', ''));

        return response()->json([
            'status' => 'success',
            'data' => $users->items(),
            'total' => $users->total(),
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
        ]);
    }

    /**
     * Xem chi tiết 1 user.
     */
    public function show($id)
    {
        $detail = $this->adminUserService->getDetail($id);

        if (!$detail) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy khách hàng!'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $detail['user'],
            'addresses' => $detail['addresses'],
            'saved_coupons' => $detail['saved_coupons'],
        ]);
    }

    /**
     * Tạo user mới từ admin.
     */
    public function store(StoreAdminUserRequest $request)
    {
        $user = $this->adminUserService->create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Tạo khách hàng thành công!',
            'data' => $user
        ], 201);
    }

    /**
     * Cập nhật thông tin user.
     */
    public function update(UpdateAdminUserRequest $request, $id)
    {
        $user = $this->adminUserService->update($id, $request->validated());

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy khách hàng!'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật khách hàng thành công!',
            'data' => $user
        ]);
    }

    /**
     * Cập nhật role của user.
     */
    public function updateRole(Request $request, $id)
    {
        $result = $this->adminUserService->updateRole($id, $request->input('role'), $this->currentUserId());

        return response()->json([
            'status' => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
        ], $result['code']);
    }

    /**
     * Cập nhật status của user.
     */
    public function updateStatus(Request $request, $id)
    {
        $result = $this->adminUserService->updateStatus($id, $request->input('status'), $this->currentUserId());

        return response()->json([
            'status' => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
        ], $result['code']);
    }

    /**
     * Xóa mềm user.
     */
    public function destroy($id)
    {
        $result = $this->adminUserService->delete($id, $this->currentUserId());

        return response()->json([
            'status' => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
        ], $result['code']);
    }

    /**
     * ID của người dùng đang đăng nhập (hỗ trợ cả guard admin và api).
     */
    private function currentUserId()
    {
        $currentUser = auth('admin')->user() ?? auth('api')->user();
        if (!$currentUser) {
            return null;
        }

        return $currentUser->user_id ?? $currentUser->admin_id;
    }
}
