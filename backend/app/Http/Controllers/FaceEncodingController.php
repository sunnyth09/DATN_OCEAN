<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\FaceEncoding;
use App\Services\AttendanceService;
use App\Services\FaceVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller quản lý đăng ký và xác thực khuôn mặt cho chấm công.
 *
 * Endpoints:
 * - POST   /admin/face/register     — Đăng ký khuôn mặt (nhiều ảnh)
 * - GET    /admin/face/status       — Kiểm tra đã đăng ký chưa
 * - DELETE /admin/face/{id}         — Xóa ảnh đăng ký (admin only)
 * - POST   /admin/face/reset        — Xóa toàn bộ và đăng ký lại (admin only)
 */
class FaceEncodingController extends Controller
{
    private FaceVerificationService $faceService;

    private AttendanceService $attendanceService;

    public function __construct(
        FaceVerificationService $faceService,
        AttendanceService $attendanceService
    ) {
        $this->faceService = $faceService;
        $this->attendanceService = $attendanceService;
    }

    /**
     * Đăng ký khuôn mặt — nhận nhiều ảnh base64.
     * POST /admin/face/register
     *
     * Body: { images: [{ image: "base64...", label: "front" }, ...] }
     * Tối đa 5 ảnh, mỗi ảnh tối đa ~2MB base64.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'images' => 'required|array|min:1|max:5',
            'images.*.image' => 'required|string|max:2800000', // ~2MB base64
            'images.*.label' => 'nullable|string|max:50',
        ], [
            'images.required' => 'Vui lòng chụp ít nhất 1 ảnh.',
            'images.max' => 'Tối đa 5 ảnh đăng ký.',
            'images.*.image.max' => 'Ảnh quá lớn (tối đa ~2MB).',
        ]);

        $user = $this->attendanceService->resolveUser();

        if (! $user['user_id']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không xác định được người dùng.',
            ], 401);
        }

        $result = $this->faceService->registerFaces(
            $user['user_id'],
            $user['user_type'],
            $validated['images']
        );

        return response()->json([
            'status' => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
            'data' => [
                'total' => $result['total'],
                'success_count' => $result['success_count'],
                'results' => $result['results'],
            ],
        ], $result['success'] ? 200 : 400);
    }

    /**
     * Kiểm tra trạng thái đăng ký khuôn mặt.
     * GET /admin/face/status
     */
    public function status(): JsonResponse
    {
        $user = $this->attendanceService->resolveUser();

        if (! $user['user_id']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không xác định được người dùng.',
            ], 401);
        }

        $result = $this->faceService->getRegistrationStatus(
            $user['user_id'],
            $user['user_type']
        );

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    /**
     * Xóa một ảnh đăng ký cụ thể (admin only hoặc chính mình).
     * DELETE /admin/face/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $user = $this->attendanceService->resolveUser();

        if (! $user['user_id']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không xác định được người dùng.',
            ], 401);
        }

        $encoding = FaceEncoding::find($id);

        if (! $encoding) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy bản ghi.',
            ], 404);
        }

        // Chỉ admin hoặc chính chủ mới được xóa
        if ($user['user_type'] !== 'admin' || $user['user_id'] !== 1) {
            // Nếu không phải admin, kiểm tra ownership
            if ($encoding->user_id !== $user['user_id'] || $encoding->user_type !== $user['user_type']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bạn không có quyền xóa bản ghi này.',
                ], 403);
            }
        }

        $encoding->update(['is_active' => false]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xóa ảnh đăng ký.',
        ]);
    }

    /**
     * Xóa toàn bộ ảnh đăng ký và cho phép đăng ký lại.
     * POST /admin/face/reset
     */
    public function reset(): JsonResponse
    {
        $user = $this->attendanceService->resolveUser();

        if (! $user['user_id']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không xác định được người dùng.',
            ], 401);
        }

        FaceEncoding::where('user_id', $user['user_id'])
            ->where('user_type', $user['user_type'])
            ->update(['is_active' => false]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xóa toàn bộ ảnh đăng ký. Bạn có thể đăng ký lại.',
        ]);
    }

    // ================================================================
    //  ADMIN MANAGEMENT — Quản lý khuôn mặt toàn bộ nhân viên
    // ================================================================

    /**
     * Danh sách tất cả nhân viên + trạng thái đăng ký khuôn mặt.
     * GET /admin/face/management
     *
     * Trả về: danh sách admins kèm face_count, face_encodings
     */
    public function management(Request $request): JsonResponse
    {
        $search = $request->input('search', '');
        $query = Admin::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        $admins = $query->orderBy('full_name')->get();

        // Lấy face encodings cho tất cả admins
        $allEncodings = FaceEncoding::where('user_type', 'admin')
            ->where('is_active', true)
            ->get()
            ->groupBy('user_id');

        // Build response
        $result = $admins->map(function ($admin) use ($allEncodings) {
            $encodings = $allEncodings->get($admin->admin_id, collect());

            return [
                'admin_id' => $admin->admin_id,
                'full_name' => $admin->full_name,
                'email' => $admin->email,
                'role' => $admin->role,
                'avatar_url' => $admin->avatar_url,
                'status' => $admin->status ?? 'active',
                'face_registered' => $encodings->isNotEmpty(),
                'face_count' => $encodings->count(),
                'face_encodings' => $encodings->map(fn ($e) => [
                    'id' => $e->id,
                    'label' => $e->label,
                    'image_path' => $e->image_path,
                    'created_at' => $e->created_at?->format('d/m/Y H:i'),
                ])->values()->toArray(),
                'face_registered_at' => $encodings->isNotEmpty()
                    ? $encodings->first()->created_at?->format('d/m/Y H:i')
                    : null,
            ];
        });

        // Thống kê
        $totalStaff = $admins->count();
        $registeredCount = $result->where('face_registered', true)->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'staff' => $result->values(),
                'total' => $totalStaff,
                'registered_count' => $registeredCount,
                'not_registered' => $totalStaff - $registeredCount,
            ],
        ]);
    }

    /**
     * Admin reset face cho một nhân viên cụ thể.
     * POST /admin/face/reset-user/{userId}
     */
    public function adminResetUser(int $userId): JsonResponse
    {
        $admin = Admin::find($userId);
        if (! $admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy nhân viên.',
            ], 404);
        }

        $count = FaceEncoding::where('user_id', $userId)
            ->where('user_type', 'admin')
            ->where('is_active', true)
            ->count();

        FaceEncoding::where('user_id', $userId)
            ->where('user_type', 'admin')
            ->update(['is_active' => false]);

        return response()->json([
            'status' => 'success',
            'message' => "Đã xóa {$count} ảnh đăng ký của {$admin->full_name}.",
        ]);
    }
}
