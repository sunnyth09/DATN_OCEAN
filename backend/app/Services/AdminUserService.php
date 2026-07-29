<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * AdminUserService — logic quản lý người dùng phía admin.
 *
 * Field nhạy cảm (role/status) KHÔNG nằm trong $fillable của User (FIX C2),
 * nên mọi thao tác ghi role/status đều qua forceFill(). An toàn vì các route
 * gọi service này đã bọc admin middleware.
 */
class AdminUserService
{
    private const ALLOWED_ROLES = ['customer', 'seller', 'staff', 'admin'];

    private const ALLOWED_STATUSES = ['active', 'inactive', 'banned'];

    /**
     * Danh sách user có tìm kiếm + phân trang.
     */
    public function paginate(?string $search, int $perPage = 20)
    {
        $query = User::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'DESC')->paginate($perPage);
    }

    /**
     * Chi tiết 1 user kèm địa chỉ + coupon đã lưu.
     *
     * @return array{user: User, addresses: mixed, saved_coupons: mixed}|null
     */
    public function getDetail($id): ?array
    {
        $user = User::withTrashed()->find($id);
        if (! $user) {
            return null;
        }

        $savedCoupons = DB::table('user_coupons')
            ->join('coupons', 'user_coupons.coupon_id', '=', 'coupons.id')
            ->where('user_coupons.user_id', $id)
            ->select('coupons.code', 'coupons.type', 'coupons.value', 'user_coupons.used_count', 'user_coupons.created_at as saved_at')
            ->get();

        return [
            'user' => $user,
            'addresses' => $user->addresses()->get(),
            'saved_coupons' => $savedCoupons,
        ];
    }

    /**
     * Tạo user mới. role/status qua forceFill.
     */
    public function create(array $data): User
    {
        $user = new User;
        $user->full_name = $data['full_name'];
        $user->email = $data['email'];
        $user->password = $data['password'];
        $user->phone = $data['phone'] ?? null;
        $user->forceFill([
            'role' => $data['role'] ?? 'customer',
            'status' => $data['status'] ?? 'active',
        ]);
        $user->save();

        return $user->fresh();
    }

    /**
     * Cập nhật user. Field thường qua fill(); role/status qua forceFill().
     *
     * @return User|null null nếu không tìm thấy user.
     */
    public function update($id, array $data): ?User
    {
        $user = User::find($id);
        if (! $user) {
            return null;
        }

        $fillable = array_filter([
            'full_name' => $data['full_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ], fn ($v) => $v !== null);

        if (! empty($data['password'])) {
            $fillable['password'] = Hash::make($data['password']);
        }

        $user->fill($fillable);

        $sensitive = [];
        if (! empty($data['role'])) {
            $sensitive['role'] = $data['role'];
        }
        if (! empty($data['status'])) {
            $sensitive['status'] = $data['status'];
        }
        if (! empty($sensitive)) {
            $user->forceFill($sensitive);
        }

        $user->save();

        return $user->fresh();
    }

    /**
     * Đổi role. Trả về mã kết quả để controller map sang HTTP status.
     *
     * @return array{ok: bool, code: int, message: string}
     */
    public function updateRole($id, ?string $role, $currentUserId): array
    {
        if (! in_array($role, self::ALLOWED_ROLES, true)) {
            return ['ok' => false, 'code' => 422, 'message' => 'Role không hợp lệ!'];
        }

        if ($this->isSelf($currentUserId, $id)) {
            return ['ok' => false, 'code' => 403, 'message' => 'Bạn không thể đổi role của chính mình!'];
        }

        $affected = User::where('user_id', (int) $id)->update(['role' => $role, 'updated_at' => now()]);
        if ($affected === 0) {
            return ['ok' => false, 'code' => 404, 'message' => 'Không tìm thấy user!'];
        }

        return ['ok' => true, 'code' => 200, 'message' => "Đã cập nhật role thành '{$role}' thành công!"];
    }

    /**
     * Đổi status. Trả về mã kết quả để controller map sang HTTP status.
     *
     * @return array{ok: bool, code: int, message: string}
     */
    public function updateStatus($id, ?string $status, $currentUserId): array
    {
        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            return ['ok' => false, 'code' => 422, 'message' => 'Status không hợp lệ!'];
        }

        if ($this->isSelf($currentUserId, $id)) {
            return ['ok' => false, 'code' => 403, 'message' => 'Bạn không thể đổi status của chính mình!'];
        }

        $affected = User::where('user_id', (int) $id)->update(['status' => $status, 'updated_at' => now()]);
        if ($affected === 0) {
            return ['ok' => false, 'code' => 404, 'message' => 'Không tìm thấy user!'];
        }

        return ['ok' => true, 'code' => 200, 'message' => "Đã cập nhật status thành '{$status}' thành công!"];
    }

    /**
     * Xóa mềm user.
     *
     * @return array{ok: bool, code: int, message: string}
     */
    public function delete($id, $currentUserId): array
    {
        $user = User::find($id);
        if (! $user) {
            return ['ok' => false, 'code' => 404, 'message' => 'Không tìm thấy khách hàng!'];
        }

        if ($this->isSelf($currentUserId, $id)) {
            return ['ok' => false, 'code' => 403, 'message' => 'Bạn không thể xóa chính mình!'];
        }

        $user->delete();

        return ['ok' => true, 'code' => 200, 'message' => 'Đã xóa khách hàng thành công!'];
    }

    private function isSelf($currentUserId, $targetId): bool
    {
        return $currentUserId !== null && (string) $currentUserId === (string) $targetId;
    }
}
