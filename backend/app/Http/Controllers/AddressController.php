<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    private function currentUser()
    {
        return auth('api')->user();
    }

    /**
     * Lấy user_id cho bảng addresses
     */
    private function currentUserId()
    {
        $user = auth('api')->user();
        if ($user) {
            return $user->user_id;
        }
        
        abort(403, 'Tài khoản nhân viên/quản trị không thể sử dụng tính năng của khách hàng. Vui lòng đăng nhập bằng tài khoản khách hàng.');
    }

    /**
     * Lấy tất cả địa chỉ của user đang đăng nhập
     * GET /api/profile/addresses
     */
    public function index()
    {
        $userId = $this->currentUserId();

        $addresses = Address::where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $addresses,
        ]);
    }

    /**
     * Tạo địa chỉ mới
     * POST /api/profile/addresses
     */
    public function store(Request $request)
    {
        $userId = $this->currentUserId();

        $validated = $request->validate([
            'recipient_name' => 'required|string|max:120',
            'phone' => 'required|string|max:20',
            'address_line' => 'nullable|string|max:255',
            'ward' => 'nullable|string|max:120',
            'district' => 'nullable|string|max:120',
            'province' => 'nullable|string|max:120',
            'postal_code' => 'nullable|string|max:20',
            'address_type' => 'nullable|in:home,office,other',
            'is_default' => 'nullable|boolean',
        ]);

        // Thêm location codes nếu có
        foreach (['ward_code', 'district_code', 'province_code'] as $codeField) {
            if ($request->has($codeField) && is_numeric($request->input($codeField))) {
                $validated[$codeField] = (int) $request->input($codeField);
            }
        }

        $validated['user_id'] = $userId;
        $validated['country'] = 'Vietnam';

        // Nếu đặt làm mặc định → bỏ mặc định tất cả địa chỉ khác
        if (!empty($validated['is_default'])) {
            Address::where('user_id', $userId)->update(['is_default' => false]);
        }

        // Nếu chưa có địa chỉ nào → tự động đặt mặc định
        $addressCount = Address::where('user_id', $userId)->count();
        if ($addressCount === 0) {
            $validated['is_default'] = true;
        }

        try {
            $address = Address::create($validated);
        } catch (\Exception $e) {
            // Nếu lỗi do cột _code chưa tồn tại → thử lại không có _code
            unset($validated['ward_code'], $validated['district_code'], $validated['province_code']);
            $address = Address::create($validated);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Thêm địa chỉ thành công!',
            'data' => $address,
        ], 201);
    }

    /**
     * Cập nhật địa chỉ
     * PUT /api/profile/addresses/{id}
     */
    public function update(Request $request, $id)
    {
        $userId = $this->currentUserId();
        $address = Address::where('address_id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $validated = $request->validate([
            'recipient_name' => 'required|string|max:120',
            'phone' => 'required|string|max:20',
            'address_line' => 'nullable|string|max:255',
            'ward' => 'nullable|string|max:120',
            'district' => 'nullable|string|max:120',
            'province' => 'nullable|string|max:120',
            'postal_code' => 'nullable|string|max:20',
            'address_type' => 'nullable|in:home,office,other',
            'is_default' => 'nullable|boolean',
        ]);

        // Thêm location codes nếu có
        foreach (['ward_code', 'district_code', 'province_code'] as $codeField) {
            if ($request->has($codeField) && is_numeric($request->input($codeField))) {
                $validated[$codeField] = (int) $request->input($codeField);
            }
        }

        // Nếu đặt làm mặc định → bỏ mặc định tất cả địa chỉ khác
        if (!empty($validated['is_default'])) {
            Address::where('user_id', $userId)
                ->where('address_id', '!=', $id)
                ->update(['is_default' => false]);
        }

        try {
            $address->update($validated);
        } catch (\Exception $e) {
            unset($validated['ward_code'], $validated['district_code'], $validated['province_code']);
            $address->update($validated);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật địa chỉ thành công!',
            'data' => $address->fresh(),
        ]);
    }

    /**
     * Xóa địa chỉ
     * DELETE /api/profile/addresses/{id}
     */
    public function destroy($id)
    {
        $userId = $this->currentUserId();
        $address = Address::where('address_id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $wasDefault = $address->is_default;
        $address->delete();

        // Nếu xóa địa chỉ mặc định → đặt địa chỉ mới nhất làm mặc định
        if ($wasDefault) {
            $nextAddress = Address::where('user_id', $userId)
                ->orderByDesc('created_at')
                ->first();
            if ($nextAddress) {
                $nextAddress->update(['is_default' => true]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Xóa địa chỉ thành công!',
        ]);
    }

    /**
     * Đặt địa chỉ mặc định
     * PUT /api/profile/addresses/{id}/default
     */
    public function setDefault($id)
    {
        $userId = $this->currentUserId();
        $address = Address::where('address_id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        // Bỏ mặc định tất cả
        Address::where('user_id', $userId)->update(['is_default' => false]);

        // Đặt mặc định
        $address->update(['is_default' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã đặt làm địa chỉ mặc định!',
            'data' => $address->fresh(),
        ]);
    }
}
