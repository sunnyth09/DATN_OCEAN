<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Court;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourtAdminController extends Controller
{
    /**
     * Lấy danh sách tất cả các sân bóng (kèm theo lịch và giá).
     *
     * @return JsonResponse
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
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'court_name' => 'required|string|max:100',
            'court_code' => 'required|string|max:20|unique:courts,court_code',
            'type' => 'required|in:standard,vip,outdoor,indoor',
            'surface' => 'nullable|string|max:50',
            'max_players' => 'nullable|integer|min:1|max:20',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'required|in:active,inactive,maintenance,closed',
        ]);

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
     * @param  int  $id  ID của sân bóng
     * @return JsonResponse
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
     *
     * @param  int  $id  ID của sân bóng
     * @return JsonResponse
     */
    public function update(Request $request, $id)
    {
        $court = Court::findOrFail($id);

        $validated = $request->validate([
            'court_name' => 'sometimes|string|max:100',
            'court_code' => 'sometimes|string|max:20|unique:courts,court_code,'.$court->court_id.',court_id',
            'type' => 'sometimes|in:standard,vip,outdoor,indoor',
            'surface' => 'nullable|string|max:50',
            'max_players' => 'nullable|integer|min:1|max:20',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'sometimes|in:active,inactive,maintenance,closed',
        ]);

        $court->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Court updated successfully.',
            'data' => $court,
        ]);
    }

    /**
     * Upload ảnh đại diện sân
     *
     * @return JsonResponse
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp,svg|max:5120',
        ]);

        $file = $request->file('image');
        $path = $file->store('uploads/courts', 'public');
        $url = asset('storage/'.$path);

        return response()->json([
            'status' => 'success',
            'message' => 'Image uploaded successfully.',
            'data' => [
                'path' => $path,
                'url' => $url,
            ],
        ]);
    }

    /**
     * Xóa mềm (soft delete) một sân bóng khỏi hệ thống.
     *
     * @param  int  $id  ID của sân bóng
     * @return JsonResponse
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
