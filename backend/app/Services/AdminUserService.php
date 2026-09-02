<?php

namespace App\Services;

use App\Models\Admin;
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
     * Danh sách user có tìm kiếm + phân trang + lọc trạng thái/thùng rác.
     */
    public function paginate($filters = [], int $perPage = 20)
    {
        if (is_string($filters)) {
            $filters = ['search' => $filters];
        }

        $query = User::query();

        // Xử lý thùng rác (soft deletes)
        $trashed = $filters['trashed'] ?? null;
        if ($trashed === 'only' || $trashed === 'trash' || $trashed === 'deleted') {
            $query->onlyTrashed();
        } elseif ($trashed === 'with') {
            $query->withTrashed();
        }

        // Lọc theo Role
        if (! empty($filters['role']) && $filters['role'] !== 'all') {
            $query->where('role', $filters['role']);
        }

        // Lọc theo Status
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Tìm kiếm từ khóa
        $search = $filters['search'] ?? null;
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%")
                    ->orWhere('user_id', $search);
            });
        }

        if ($trashed === 'only' || $trashed === 'trash' || $trashed === 'deleted') {
            $query->orderBy('deleted_at', 'DESC');
        } else {
            $query->orderBy('created_at', 'DESC');
        }

        return $query->paginate($perPage);
    }

    /**
     * Thống kê số lượng user theo từng tab trạng thái.
     *
     * @return array{all: int, active: int, inactive: int, banned: int, trashed: int}
     */
    public function getCounts(): array
    {
        return [
            'all' => User::count(),
            'active' => User::where('status', 'active')->count(),
            'inactive' => User::where('status', 'inactive')->count(),
            'banned' => User::where('status', 'banned')->count(),
            'trashed' => User::onlyTrashed()->count(),
        ];
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

        $oldEmail = $user->email;

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

        if (in_array($user->role, ['admin', 'staff', 'seller'])) {
            $admin = Admin::where('email', $oldEmail)->first();
            if ($admin) {
                $adminData = [
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => $user->status,
                    'role' => $user->role,
                ];
                if (! empty($data['password'])) {
                    $adminData['password'] = $data['password'];
                }
                $admin->update($adminData);
            }
        }

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

        $user = User::find($id);
        if (! $user) {
            return ['ok' => false, 'code' => 404, 'message' => 'Không tìm thấy user!'];
        }

        try {
            $user->forceFill(['role' => $role])->save();

            if (in_array($role, ['admin', 'staff', 'seller'])) {
                $admin = Admin::where('email', $user->email)->first();
                if ($admin) {
                    $admin->update(['role' => $role]);
                } else {
                    Admin::create([
                        'full_name' => $user->full_name,
                        'email' => $user->email,
                        'password' => $user->password ?? bcrypt('123456'), // Default password if null
                        'role' => $role,
                        'status' => $user->status ?? 'active',
                        'phone' => $user->phone ?? '0000000000', // Default phone if null
                    ]);
                }
            } else {
                Admin::where('email', $user->email)->delete();
            }

            return ['ok' => true, 'code' => 200, 'message' => "Đã cập nhật role thành '{$role}' thành công!"];
        } catch (\Exception $e) {
            \Log::error('Update Role Error: '.$e->getMessage());

            return ['ok' => false, 'code' => 500, 'message' => 'Lỗi hệ thống: '.$e->getMessage()];
        }
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

        $user = User::find($id);
        if (! $user) {
            return ['ok' => false, 'code' => 404, 'message' => 'Không tìm thấy user!'];
        }

        $user->forceFill(['status' => $status])->save();

        if (in_array($user->role, ['admin', 'staff', 'seller'])) {
            Admin::where('email', $user->email)->update(['status' => $status]);
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

        $email = $user->email;
        $role = $user->role;

        $user->delete();

        if (in_array($role, ['admin', 'staff', 'seller'])) {
            Admin::where('email', $email)->delete();
        }

        return ['ok' => true, 'code' => 200, 'message' => 'Đã chuyển khách hàng vào thùng rác thành công!'];
    }

    /**
     * Khôi phục user đã xóa mềm.
     *
     * @return array{ok: bool, code: int, message: string}
     */
    public function restore($id): array
    {
        $user = User::onlyTrashed()->find($id);
        if (! $user) {
            return ['ok' => false, 'code' => 404, 'message' => 'Không tìm thấy khách hàng trong thùng rác!'];
        }

        $user->restore();

        return ['ok' => true, 'code' => 200, 'message' => "Đã khôi phục tài khoản '{$user->full_name}' thành công!"];
    }

    /**
     * Xóa vĩnh viễn user khỏi cơ sở dữ liệu.
     *
     * @return array{ok: bool, code: int, message: string}
     */
    public function forceDelete($id, $currentUserId): array
    {
        if ($this->isSelf($currentUserId, $id)) {
            return ['ok' => false, 'code' => 403, 'message' => 'Bạn không thể xóa vĩnh viễn chính mình!'];
        }

        $user = User::withTrashed()->find($id);
        if (! $user) {
            return ['ok' => false, 'code' => 404, 'message' => 'Không tìm thấy khách hàng!'];
        }

        $email = $user->email;
        $fullName = $user->full_name;

        // Xóa admin liên kết nếu có
        Admin::where('email', $email)->forceDelete();

        $user->forceDelete();

        return ['ok' => true, 'code' => 200, 'message' => "Đã xóa vĩnh viễn khách hàng '{$fullName}'!"];
    }

    /**
     * Khôi phục hàng loạt users đã xóa mềm.
     *
     * @param array<int> $ids
     * @return array{ok: bool, code: int, message: string, count: int}
     */
    public function bulkRestore(array $ids): array
    {
        if (empty($ids)) {
            return ['ok' => false, 'code' => 422, 'message' => 'Vui lòng chọn ít nhất 1 khách hàng để khôi phục!', 'count' => 0];
        }

        $restored = User::onlyTrashed()->whereIn('user_id', $ids)->restore();

        return [
            'ok' => true,
            'code' => 200,
            'message' => "Đã khôi phục thành công {$restored} khách hàng!",
            'count' => $restored,
        ];
    }

    /**
     * Xóa vĩnh viễn hàng loạt users.
     *
     * @param array<int> $ids
     * @return array{ok: bool, code: int, message: string, count: int}
     */
    public function bulkForceDelete(array $ids, $currentUserId): array
    {
        if (empty($ids)) {
            return ['ok' => false, 'code' => 422, 'message' => 'Vui lòng chọn ít nhất 1 khách hàng để xóa vĩnh viễn!', 'count' => 0];
        }

        // Loại trừ tài khoản của chính mình
        $filteredIds = array_filter($ids, fn ($targetId) => ! $this->isSelf($currentUserId, $targetId));

        if (empty($filteredIds)) {
            return ['ok' => false, 'code' => 403, 'message' => 'Không thể xóa vĩnh viễn tài khoản của chính bạn!', 'count' => 0];
        }

        $users = User::withTrashed()->whereIn('user_id', $filteredIds)->get();
        $count = 0;

        foreach ($users as $user) {
            Admin::where('email', $user->email)->forceDelete();
            $user->forceDelete();
            $count++;
        }

        return [
            'ok' => true,
            'code' => 200,
            'message' => "Đã xóa vĩnh viễn thành công {$count} khách hàng!",
            'count' => $count,
        ];
    }

    private function isSelf($currentUserId, $targetId): bool
    {
        return $currentUserId !== null && (string) $currentUserId === (string) $targetId;
    }
}
