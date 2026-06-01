<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminUserController extends Controller
{
    /**
     * Danh sách tất cả users.
     */
    public function index(Request $request)
    {
        $search = $request->input('search', '');

        $query = User::query();

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'DESC')->get();

        return response()->json([
            'status' => 'success',
            'data' => $users,
            'total' => $users->count()
        ]);
    }

    /**
     * Xem chi tiết 1 user.
     */
    public function show($id)
    {
        $user = User::withTrashed()->find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy khách hàng!'
            ], 404);
        }

        $addresses = $user->addresses()->get();

        $savedCoupons = DB::table('user_coupons')
            ->join('coupons', 'user_coupons.coupon_id', '=', 'coupons.id')
            ->where('user_coupons.user_id', $id)
            ->select('coupons.code', 'coupons.type', 'coupons.value', 'user_coupons.used_count', 'user_coupons.created_at as saved_at')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $user,
            'addresses' => $addresses,
            'saved_coupons' => $savedCoupons,
        ]);
    }

    /**
     * Tạo user mới từ admin.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'password'  => [
                'required', 
                'string', 
                'min:8', 
                'regex:/[A-Z]/', 
                'regex:/[0-9]/', 
                'regex:/[^A-Za-z0-9]/'
            ],
            'phone'     => ['nullable', 'string', 'regex:/^(0[0-9]{9})$/', 'unique:users,phone'],
            'role'      => 'nullable|in:customer,seller,staff,admin',
            'status'    => 'nullable|in:active,inactive,banned',
        ], [
            'full_name.required' => 'Họ tên là bắt buộc!',
            'email.required'     => 'Email là bắt buộc!',
            'email.email'        => 'Email không hợp lệ!',
            'email.unique'       => 'Email này đã được sử dụng!',
            'password.required'  => 'Mật khẩu là bắt buộc!',
            'password.min'       => 'Mật khẩu tối thiểu 8 ký tự!',
            'password.regex'     => 'Mật khẩu phải chứa ít nhất 1 chữ hoa, 1 chữ số và 1 ký tự đặc biệt!',
            'phone.regex'        => 'Số điện thoại không hợp lệ (gồm 10 số và bắt đầu bằng 0)!',
            'phone.unique'       => 'Số điện thoại này đã được sử dụng!',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        // FIX C2 compatibility: role/status không còn trong $fillable,
        // dùng forceFill() cho các field nhạy cảm (safe vì đã có admin middleware)
        $user = new User();
        $user->full_name = $request->full_name;
        $user->email     = $request->email;
        $user->password  = $request->password;
        $user->phone     = $request->phone;
        $user->forceFill([
            'role'   => $request->role ?? 'customer',
            'status' => $request->status ?? 'active',
        ]);
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Tạo khách hàng thành công!',
            'data' => $user->fresh()
        ], 201);
    }

    /**
     * Cập nhật thông tin user.
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy khách hàng!'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'full_name' => 'sometimes|required|string|max:255',
            'email'     => 'sometimes|required|email|unique:users,email,' . $id . ',user_id',
            'phone'     => ['nullable', 'string', 'regex:/^(0[0-9]{9})$/', 'unique:users,phone,' . $id . ',user_id'],
            'password'  => [
                'nullable',
                'string',
                'min:8',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/'
            ],
            'role'      => 'nullable|in:customer,seller,staff,admin',
            'status'    => 'nullable|in:active,inactive,banned',
        ], [
            'email.unique'    => 'Email này đã được sử dụng!',
            'password.min'    => 'Mật khẩu tối thiểu 8 ký tự!',
            'password.regex'  => 'Mật khẩu phải chứa ít nhất 1 chữ hoa, 1 chữ số và 1 ký tự đặc biệt!',
            'phone.regex'     => 'Số điện thoại không hợp lệ (gồm 10 số và bắt đầu bằng 0)!',
            'phone.unique'    => 'Số điện thoại này đã được sử dụng!',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['full_name', 'email', 'phone']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        // FIX C2 compatibility: role/status dùng forceFill
        $user->fill($data);
        $sensitiveData = [];
        if ($request->filled('role')) {
            $sensitiveData['role'] = $request->role;
        }
        if ($request->filled('status')) {
            $sensitiveData['status'] = $request->status;
        }
        if (!empty($sensitiveData)) {
            $user->forceFill($sensitiveData);
        }
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật khách hàng thành công!',
            'data' => $user->fresh()
        ]);
    }

    /**
     * Cập nhật role của user.
     */
    public function updateRole(Request $request, $id)
    {
        $role = $request->input('role');
        $allowedRoles = ['admin', 'staff', 'customer', 'seller'];

        if (!in_array($role, $allowedRoles)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role không hợp lệ!'
            ], 422);
        }

        $currentUser = auth('admin')->user() ?? auth('api')->user();
        if ($currentUser && (string)($currentUser->user_id ?? $currentUser->admin_id) === (string)$id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không thể đổi role của chính mình!'
            ], 403);
        }

        $affected = DB::update("UPDATE users SET role = ?, updated_at = NOW() WHERE user_id = ?", [$role, $id]);

        if ($affected === 0) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy user!'], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => "Đã cập nhật role thành '{$role}' thành công!"
        ]);
    }

    /**
     * Cập nhật status của user.
     */
    public function updateStatus(Request $request, $id)
    {
        $status = $request->input('status');
        $allowedStatuses = ['active', 'inactive', 'banned'];

        if (!in_array($status, $allowedStatuses)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Status không hợp lệ!'
            ], 422);
        }

        $currentUser = auth('admin')->user() ?? auth('api')->user();
        if ($currentUser && (string)($currentUser->user_id ?? $currentUser->admin_id) === (string)$id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không thể đổi status của chính mình!'
            ], 403);
        }

        $affected = DB::update("UPDATE users SET status = ?, updated_at = NOW() WHERE user_id = ?", [$status, $id]);

        if ($affected === 0) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy user!'], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => "Đã cập nhật status thành '{$status}' thành công!"
        ]);
    }

    /**
     * Xóa mềm user.
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy khách hàng!'
            ], 404);
        }

        $currentUser = auth('admin')->user() ?? auth('api')->user();
        if ($currentUser && ($currentUser->user_id ?? $currentUser->admin_id) == $id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không thể xóa chính mình!'
            ], 403);
        }

        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xóa khách hàng thành công!'
        ]);
    }
}
