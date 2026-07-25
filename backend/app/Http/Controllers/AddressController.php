<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddressController extends Controller
{
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
        $validated = $this->validateAddress($request);

        $address = DB::transaction(function () use ($userId, $validated) {
            User::where('user_id', $userId)->lockForUpdate()->firstOrFail();

            $addressCount = Address::where('user_id', $userId)->count();
            if ($addressCount >= Address::MAX_PER_USER) {
                throw ValidationException::withMessages([
                    'address' => ['Bạn chỉ có thể lưu tối đa ' . Address::MAX_PER_USER . ' địa chỉ.'],
                ]);
            }

            $validated['user_id'] = $userId;
            $validated['country'] = 'Vietnam';

            // Nếu là địa chỉ đầu tiên hoặc user chủ động đặt mặc định → đảm bảo chỉ có 1 default.
            if ($addressCount === 0) {
                $validated['is_default'] = true;
            } elseif (!empty($validated['is_default'])) {
                Address::where('user_id', $userId)->update(['is_default' => false]);
            }

            return Address::create($validated);
        });

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
        $validated = $this->validateAddress($request);

        $address = DB::transaction(function () use ($userId, $id, $validated) {
            $address = Address::where('address_id', $id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($address->is_default && array_key_exists('is_default', $validated) && !$validated['is_default']) {
                throw ValidationException::withMessages([
                    'is_default' => ['Phải có một địa chỉ mặc định. Vui lòng chọn địa chỉ khác làm mặc định trước.'],
                ]);
            }

            // Nếu đặt làm mặc định → bỏ mặc định tất cả địa chỉ khác trong cùng transaction.
            if (!empty($validated['is_default'])) {
                Address::where('user_id', $userId)
                    ->where('address_id', '!=', $id)
                    ->update(['is_default' => false]);
            }

            $address->update($validated);

            return $address->fresh();
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật địa chỉ thành công!',
            'data' => $address,
        ]);
    }

    /**
     * Xóa địa chỉ
     * DELETE /api/profile/addresses/{id}
     */
    public function destroy($id)
    {
        $userId = $this->currentUserId();

        DB::transaction(function () use ($userId, $id) {
            $address = Address::where('address_id', $id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            $wasDefault = $address->is_default;
            $address->delete();

            // Nếu xóa địa chỉ mặc định → đặt địa chỉ mới nhất còn lại làm mặc định.
            if ($wasDefault) {
                $nextAddress = Address::where('user_id', $userId)
                    ->lockForUpdate()
                    ->orderByDesc('created_at')
                    ->first();

                if ($nextAddress) {
                    $nextAddress->update(['is_default' => true]);
                }
            }
        });

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

        $address = DB::transaction(function () use ($userId, $id) {
            $address = Address::where('address_id', $id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($address->is_default) {
                return $address->fresh();
            }

            Address::where('user_id', $userId)->update(['is_default' => false]);
            $address->update(['is_default' => true]);

            return $address->fresh();
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Đã đặt làm địa chỉ mặc định!',
            'data' => $address,
        ]);
    }

    private function validateAddress(Request $request): array
    {
        return $request->validate([
            'recipient_name' => 'required|string|max:120',
            'phone' => ['required', 'string', 'max:20', 'regex:/^(0|\+84)(3|5|7|8|9)[0-9]{8}$/'],
            'address_line' => 'required|string|max:255',
            'ward' => 'required|string|max:120',
            'district' => 'required|string|max:120',
            'province' => 'required|string|max:120',
            'ward_code' => 'nullable|integer',
            'district_code' => 'nullable|integer',
            'province_code' => 'nullable|integer',
            'postal_code' => 'nullable|string|max:20',
            'address_type' => 'nullable|in:home,office,other',
            'is_default' => 'nullable|boolean',
        ], [
            'phone.regex' => 'Số điện thoại không hợp lệ!',
        ]);
    }
}
