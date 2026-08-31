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
     * Lấy ID của người dùng (khách hàng) hiện đang đăng nhập.
     * Chặn quyền truy cập đối với tài khoản nhân viên/quản trị.
     *
     * @return int
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
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
     * Lấy danh sách tất cả địa chỉ của người dùng hiện đang đăng nhập.
     * Hỗ trợ phân trang nếu có truyền tham số `page` và `per_page`.
     * (Route: GET /api/profile/addresses)
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $userId = $this->currentUserId();

        $query = Address::where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at');

        if ($request->has('page') || $request->has('per_page')) {
            $validated = $request->validate([
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:'.Address::MAX_PER_USER,
            ], [
                'page.integer' => 'Trang không hợp lệ.',
                'page.min' => 'Trang không hợp lệ.',
                'per_page.integer' => 'Số địa chỉ mỗi trang không hợp lệ.',
                'per_page.min' => 'Số địa chỉ mỗi trang không hợp lệ.',
                'per_page.max' => 'Số địa chỉ mỗi trang không được vượt quá '.Address::MAX_PER_USER.'.',
            ]);

            $perPage = (int) ($validated['per_page'] ?? 5);

            return response()->json([
                'status' => 'success',
                'data' => $query->paginate($perPage),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get(),
        ]);
    }

    /**
     * Thêm mới một địa chỉ giao hàng vào sổ địa chỉ.
     * Đảm bảo một người dùng có tối đa một số lượng địa chỉ nhất định.
     * Tự động set `is_default` nếu là địa chỉ đầu tiên.
     * (Route: POST /api/profile/addresses)
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
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
                    'address' => ['Bạn chỉ có thể lưu tối đa '.Address::MAX_PER_USER.' địa chỉ.'],
                ]);
            }

            $validated['user_id'] = $userId;
            $validated['country'] = 'Vietnam';

            // Nếu là địa chỉ đầu tiên hoặc user chủ động đặt mặc định → đảm bảo chỉ có 1 default.
            if ($addressCount === 0) {
                $validated['is_default'] = true;
            } elseif (! empty($validated['is_default'])) {
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
     * Cập nhật thông tin địa chỉ đã lưu.
     * Nếu set `is_default`, sẽ gỡ trạng thái mặc định của các địa chỉ khác.
     * (Route: PUT /api/profile/addresses/{id})
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id ID của địa chỉ cần sửa
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
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

            if ($address->is_default && array_key_exists('is_default', $validated) && ! $validated['is_default']) {
                throw ValidationException::withMessages([
                    'is_default' => ['Phải có một địa chỉ mặc định. Vui lòng chọn địa chỉ khác làm mặc định trước.'],
                ]);
            }

            // Nếu đặt làm mặc định → bỏ mặc định tất cả địa chỉ khác trong cùng transaction.
            if (! empty($validated['is_default'])) {
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
     * Xóa vĩnh viễn một địa chỉ khỏi sổ địa chỉ của người dùng.
     * Không cho phép xóa địa chỉ nếu đó là địa chỉ mặc định (phải đổi mặc định trước).
     * (Route: DELETE /api/profile/addresses/{id})
     *
     * @param int $id ID của địa chỉ cần xóa
     * @return \Illuminate\Http\JsonResponse
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
            'recipient_name' => 'required|string|min:2|max:120',
            'phone' => ['required', 'string', 'max:20', 'regex:/^(0|\+84)(3|5|7|8|9)[0-9]{8}$/'],
            'address_line' => 'required|string|min:5|max:255',
            'ward' => 'required|string|max:120',
            'district' => 'nullable|string|max:120', // Ocean Express không có quận
            'province' => 'required|string|max:120',
            'ward_code' => 'nullable|string', // Ocean Express dùng string ID
            'district_code' => 'nullable|string',
            'province_code' => 'nullable|string',
            'postal_code' => 'nullable|string|max:20',
            'address_type' => 'nullable|in:home,office,other',
            'is_default' => 'nullable|boolean',
        ], [
            'recipient_name.required' => 'Vui lòng nhập họ tên người nhận.',
            'recipient_name.min' => 'Họ tên phải có ít nhất 2 ký tự.',
            'recipient_name.max' => 'Họ tên không được vượt quá 120 ký tự.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại không hợp lệ!',
            'address_line.required' => 'Vui lòng nhập địa chỉ cụ thể.',
            'address_line.min' => 'Địa chỉ cụ thể quá ngắn, vui lòng nhập số nhà/tên đường.',
            'address_line.max' => 'Địa chỉ cụ thể không được vượt quá 255 ký tự.',
            'ward.required' => 'Vui lòng chọn Phường/Xã.',
            'province.required' => 'Vui lòng chọn Tỉnh/Thành phố.',
            'ward_code.string' => 'Phường/Xã không hợp lệ.',
            'district_code.string' => 'Quận/Huyện không hợp lệ.',
            'province_code.string' => 'Tỉnh/Thành phố không hợp lệ.',
        ]);
    }
}
