<?php

namespace App\Http\Controllers;

use App\Models\WorkLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkLocationController extends Controller
{
    /**
     * Danh sách tất cả vị trí làm việc.
     * GET /api/admin/work-locations
     */
    public function index(): JsonResponse
    {
        $locations = WorkLocation::orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data'   => $locations,
        ]);
    }

    /**
     * Tạo vị trí làm việc mới.
     * POST /api/admin/work-locations
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'address'       => 'nullable|string|max:500',
            'latitude'      => 'required|numeric|between:-90,90',
            'longitude'     => 'required|numeric|between:-180,180',
            'radius_meters' => 'required|integer|min:10|max:5000',
            'is_active'     => 'nullable|boolean',
        ], [
            'name.required'     => 'Tên vị trí không được để trống.',
            'latitude.required' => 'Vĩ độ không được để trống.',
            'latitude.between'  => 'Vĩ độ phải từ -90 đến 90.',
            'longitude.required' => 'Kinh độ không được để trống.',
            'longitude.between' => 'Kinh độ phải từ -180 đến 180.',
            'radius_meters.required' => 'Bán kính không được để trống.',
            'radius_meters.min' => 'Bán kính tối thiểu 10 mét.',
            'radius_meters.max' => 'Bán kính tối đa 5000 mét.',
        ]);

        $location = WorkLocation::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Tạo vị trí làm việc thành công!',
            'data'    => $location,
        ], 201);
    }

    /**
     * Cập nhật vị trí làm việc.
     * PUT /api/admin/work-locations/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        $location = WorkLocation::find($id);

        if (!$location) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Không tìm thấy vị trí làm việc.',
            ], 404);
        }

        $validated = $request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'address'       => 'nullable|string|max:500',
            'latitude'      => 'sometimes|required|numeric|between:-90,90',
            'longitude'     => 'sometimes|required|numeric|between:-180,180',
            'radius_meters' => 'sometimes|required|integer|min:10|max:5000',
            'is_active'     => 'nullable|boolean',
        ]);

        $location->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Cập nhật vị trí làm việc thành công!',
            'data'    => $location->fresh(),
        ]);
    }

    /**
     * Xóa (soft) vị trí làm việc — set is_active = false.
     * DELETE /api/admin/work-locations/{id}
     */
    public function destroy($id): JsonResponse
    {
        $location = WorkLocation::find($id);

        if (!$location) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Không tìm thấy vị trí làm việc.',
            ], 404);
        }

        // Soft delete: chỉ vô hiệu hóa, không xóa cứng (vì có FK từ attendances)
        $location->update(['is_active' => false]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Đã vô hiệu hóa vị trí làm việc.',
        ]);
    }
}
