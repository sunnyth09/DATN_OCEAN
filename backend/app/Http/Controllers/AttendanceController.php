<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceCheckInRequest;
use App\Http\Requests\AttendanceCheckOutRequest;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    private AttendanceService $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    /**
     * Check-in chấm công.
     * POST /api/admin/attendance/check-in
     *
     * Request body: { latitude, longitude, accuracy?, note?, image? }
     * User ID lấy từ JWT token, KHÔNG nhận từ request body.
     */
    public function checkIn(AttendanceCheckInRequest $request): JsonResponse
    {
        $user = $this->attendanceService->resolveUser();

        if (! $user['user_id']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không xác định được người dùng.',
            ], 401);
        }

        $result = $this->attendanceService->checkIn(
            $user['user_id'],
            $user['user_type'],
            $request->validated()
        );

        return response()->json([
            'status' => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
            'data' => $result['data'],
        ], $result['status_code']);
    }

    /**
     * Check-out chấm công.
     * POST /api/admin/attendance/check-out
     *
     * Request body: { latitude, longitude, accuracy?, image? }
     * Cho phép check-out ngoài phạm vi (chỉ cảnh báo).
     */
    public function checkOut(AttendanceCheckOutRequest $request): JsonResponse
    {
        $user = $this->attendanceService->resolveUser();

        if (! $user['user_id']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không xác định được người dùng.',
            ], 401);
        }

        $result = $this->attendanceService->checkOut(
            $user['user_id'],
            $user['user_type'],
            $request->validated()
        );

        return response()->json([
            'status' => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
            'data' => $result['data'],
        ], $result['status_code']);
    }

    /**
     * Lấy trạng thái chấm công hôm nay.
     * GET /api/admin/attendance/today
     *
     * Response: { state: 'not_checked_in' | 'checked_in' | 'checked_out', attendance: {...} }
     */
    public function today(): JsonResponse
    {
        $user = $this->attendanceService->resolveUser();

        if (! $user['user_id']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không xác định được người dùng.',
            ], 401);
        }

        $result = $this->attendanceService->getTodayStatus(
            $user['user_id'],
            $user['user_type']
        );

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    /**
     * Lấy lịch sử chấm công cá nhân.
     * GET /api/admin/attendance/my-history
     *
     * Query params: from_date, to_date, status
     */
    public function myHistory(Request $request): JsonResponse
    {
        $user = $this->attendanceService->resolveUser();

        if (! $user['user_id']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không xác định được người dùng.',
            ], 401);
        }

        $filters = $request->only(['from_date', 'to_date', 'status']);

        $attendances = $this->attendanceService->getMyHistory(
            $user['user_id'],
            $user['user_type'],
            $filters
        );

        return response()->json([
            'status' => 'success',
            'data' => $attendances,
        ]);
    }

    /**
     * Danh sách chấm công toàn bộ nhân viên (Admin only).
     * GET /api/admin/attendance
     *
     * Query params: from_date, to_date, status, is_flagged
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['from_date', 'to_date', 'status', 'is_flagged']);

        $attendances = $this->attendanceService->getAllAttendances($filters);

        return response()->json([
            'status' => 'success',
            'data' => $attendances,
        ]);
    }

    /**
     * Gắn cờ / bỏ cờ bất thường cho bản ghi chấm công.
     * PUT /api/admin/attendance/{id}/flag
     *
     * Request body: { is_flagged: true, flag_note: "Không phải nhân viên" }
     */
    public function flag(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'is_flagged' => 'required|boolean',
            'flag_note' => 'nullable|string|max:500',
        ]);

        $result = $this->attendanceService->flagAttendance(
            (int) $id,
            $validated['is_flagged'],
            $validated['flag_note'] ?? null
        );

        return response()->json([
            'status' => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
        ], $result['status_code']);
    }
}
