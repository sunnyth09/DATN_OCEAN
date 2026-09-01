<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Court;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CourtAdminController extends Controller
{
    /**
     * Lấy danh sách tất cả các sân bóng (kèm theo lịch và giá).
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = Court::with(['schedules', 'prices']);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $courts = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => $courts,
        ]);
    }

    /**
     * Thêm mới một sân bóng vào hệ thống.
     * Tự động sinh slug từ tên sân.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'court_name' => 'required|string|max:100',
            'court_code' => 'required|string|max:20|unique:courts,court_code',
            'type' => 'required|in:standard,vip,outdoor,indoor',
            'status' => 'required|in:active,inactive,maintenance,closed',
        ]);

        $validated['slug'] = Str::slug($validated['court_name']);

        $court = Court::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Court created successfully.',
            'data' => $court,
        ]);
    }

    /**
     * Hiển thị thông tin chi tiết một sân bóng theo ID.
     *
     * @param int $id ID của sân bóng
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $court = Court::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $court,
        ]);
    }

    /**
     * Cập nhật thông tin sân bóng.
     * Sẽ cập nhật lại slug nếu có sự thay đổi về tên sân.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id ID của sân bóng
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $court = Court::findOrFail($id);

        $validated = $request->validate([
            'court_name' => 'sometimes|string|max:100',
            'court_code' => 'sometimes|string|max:20|unique:courts,court_code,'.$court->court_id.',court_id',
            'type' => 'sometimes|in:standard,vip,outdoor,indoor',
            'status' => 'sometimes|in:active,inactive,maintenance,closed',
        ]);

        if (isset($validated['court_name'])) {
            $validated['slug'] = Str::slug($validated['court_name']);
        }

        $court->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Court updated successfully.',
            'data' => $court,
        ]);
    }

    /**
     * Xóa mềm (soft delete) một sân bóng khỏi hệ thống.
     *
     * @param int $id ID của sân bóng
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $court = Court::findOrFail($id);
        $court->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Court deleted successfully.',
        ]);
    }
}
