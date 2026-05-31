<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\ShiftAssignment;
use App\Models\WorkShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkShiftController extends Controller
{
    // ============================================================
    //  CRUD CA LÀM VIỆC
    // ============================================================

    /**
     * Danh sách tất cả ca làm việc.
     * GET /api/admin/work-shifts
     */
    public function index(): JsonResponse
    {
        $shifts = WorkShift::orderBy('start_time')->get();

        return response()->json([
            'status' => 'success',
            'data'   => $shifts,
        ]);
    }

    /**
     * Tạo ca làm việc mới.
     * POST /api/admin/work-shifts
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                 => 'required|string|max:100',
            'start_time'           => 'required|date_format:H:i',
            'end_time'             => 'required|date_format:H:i',
            'early_buffer_minutes' => 'nullable|integer|min:0|max:120',
            'is_active'            => 'nullable|boolean',
        ], [
            'name.required'       => 'Tên ca không được để trống.',
            'start_time.required' => 'Giờ bắt đầu không được để trống.',
            'end_time.required'   => 'Giờ kết thúc không được để trống.',
        ]);

        $shift = WorkShift::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Tạo ca làm việc thành công!',
            'data'    => $shift,
        ], 201);
    }

    /**
     * Cập nhật ca làm việc.
     * PUT /api/admin/work-shifts/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        $shift = WorkShift::find($id);
        if (!$shift) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy ca.'], 404);
        }

        $validated = $request->validate([
            'name'                 => 'sometimes|required|string|max:100',
            'start_time'           => 'sometimes|required|date_format:H:i',
            'end_time'             => 'sometimes|required|date_format:H:i',
            'early_buffer_minutes' => 'nullable|integer|min:0|max:120',
            'is_active'            => 'nullable|boolean',
        ]);

        $shift->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Cập nhật ca thành công!',
            'data'    => $shift->fresh(),
        ]);
    }

    /**
     * Xóa (vô hiệu hóa) ca.
     * DELETE /api/admin/work-shifts/{id}
     */
    public function destroy($id): JsonResponse
    {
        $shift = WorkShift::find($id);
        if (!$shift) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy ca.'], 404);
        }

        $shift->update(['is_active' => false]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Đã vô hiệu hóa ca.',
        ]);
    }

    // ============================================================
    //  PHÂN CA CHO NHÂN VIÊN
    // ============================================================

    /**
     * Lấy danh sách nhân viên kèm phân ca theo tuần.
     * GET /api/admin/shift-assignments
     *
     * Response: danh sách nhân viên, mỗi người có mảng assignments (shift_id + day_of_week)
     */
    public function getAssignments(): JsonResponse
    {
        // Lấy tất cả nhân viên (admins có role staff, seller, admin)
        $staff = Admin::whereIn('role', ['admin', 'seller', 'staff'])
            ->where('status', 'active')
            ->orderBy('full_name')
            ->get(['admin_id', 'full_name', 'email', 'role', 'avatar_url']);

        // Lấy tất cả phân ca đang active
        $assignments = ShiftAssignment::where('user_type', 'admin')
            ->where('is_active', true)
            ->get();

        // Lấy tất cả ca active
        $shifts = WorkShift::active()->orderBy('start_time')->get();

        // Group assignments theo user
        $assignmentMap = [];
        foreach ($assignments as $a) {
            $key = $a->user_id;
            if (!isset($assignmentMap[$key])) {
                $assignmentMap[$key] = [];
            }
            $assignmentMap[$key][] = [
                'id'            => $a->id,
                'work_shift_id' => $a->work_shift_id,
                'day_of_week'   => $a->day_of_week,
            ];
        }

        // Build response
        $result = [];
        foreach ($staff as $s) {
            $result[] = [
                'user_id'     => $s->admin_id,
                'user_type'   => 'admin',
                'full_name'   => $s->full_name,
                'email'       => $s->email,
                'role'        => $s->role,
                'avatar_url'  => $s->avatar_url,
                'assignments' => $assignmentMap[$s->admin_id] ?? [],
            ];
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'staff'  => $result,
                'shifts' => $shifts,
            ],
        ]);
    }

    /**
     * Lưu phân ca cho nhân viên (bulk save).
     * POST /api/admin/shift-assignments
     *
     * Body: {
     *   user_id: 1,
     *   user_type: "admin",
     *   assignments: [
     *     { work_shift_id: 1, day_of_week: 1 },
     *     { work_shift_id: 1, day_of_week: 2 },
     *     { work_shift_id: 2, day_of_week: 1 },
     *   ]
     * }
     *
     * Logic: Xóa hết assignment cũ của user → tạo mới theo danh sách gửi lên.
     */
    public function saveAssignments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id'                       => 'required|integer',
            'user_type'                     => 'required|string|in:admin,user',
            'assignments'                   => 'present|array',
            'assignments.*.work_shift_id'   => 'required|integer|exists:work_shifts,id',
            'assignments.*.day_of_week'     => 'required|integer|between:0,6',
        ], [
            'user_id.required' => 'Chưa chọn nhân viên.',
            'assignments.*.work_shift_id.exists' => 'Ca làm việc không hợp lệ.',
            'assignments.*.day_of_week.between'  => 'Ngày trong tuần không hợp lệ (0-6).',
        ]);

        $userId   = $validated['user_id'];
        $userType = $validated['user_type'];

        // Xóa tất cả phân ca cũ của user
        ShiftAssignment::where('user_id', $userId)
            ->where('user_type', $userType)
            ->delete();

        // Tạo mới
        foreach ($validated['assignments'] as $a) {
            ShiftAssignment::create([
                'user_id'       => $userId,
                'user_type'     => $userType,
                'work_shift_id' => $a['work_shift_id'],
                'day_of_week'   => $a['day_of_week'],
                'is_active'     => true,
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Lưu phân ca thành công!',
        ]);
    }
}
