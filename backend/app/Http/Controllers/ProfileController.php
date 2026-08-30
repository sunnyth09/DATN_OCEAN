<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserProfileResource;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    /**
     * Lấy thực thể người dùng hiện tại từ JWT guard (hỗ trợ cả khách hàng hoặc admin).
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    private function currentUser()
    {
        return auth('api')->user() ?? auth('admin')->user();
    }

    /**
     * Cập nhật thông tin hồ sơ của người dùng (có thể bao gồm ảnh đại diện).
     * Xử lý reprocess ảnh để ngăn chặn mã độc nhúng trong metadata.
     *
     * @param \App\Http\Requests\UpdateProfileRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateProfileRequest $request)
    {
        /** @var User|Admin $user */
        $user = $this->currentUser();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validated();

        $user->full_name = $validated['full_name'];

        // Cho phép xóa số điện thoại bằng cách gửi chuỗi rỗng
        $user->phone = isset($validated['phone']) ? ($validated['phone'] ?: null) : $user->phone;

        // Cập nhật ngày sinh
        if (array_key_exists('date_of_birth', $validated)) {
            $user->date_of_birth = $validated['date_of_birth'] ?: null;
        }

        // FIX C9: Xử lý upload ảnh — reprocess bằng GD để loại bỏ metadata/mã độc
        if ($request->hasFile('avatar')) {
            // Xoá ảnh cũ nếu là ảnh nội bộ (không phải URL Google/bên ngoài)
            if ($user->avatar_url && ! str_starts_with($user->avatar_url, 'http')) {
                $oldPath = ltrim(str_replace('/storage', '', $user->avatar_url), '/');
                Storage::disk('public')->delete($oldPath);
            }

            $file = $request->file('avatar');

            // Reprocess ảnh bằng GD — strip metadata, loại bỏ embedded content
            $gdImage = @imagecreatefromstring(file_get_contents($file->getRealPath()));
            if (! $gdImage) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File ảnh không hợp lệ hoặc bị hỏng.',
                    'errors' => ['avatar' => ['File ảnh không hợp lệ hoặc bị hỏng.']],
                ], 422);
            }

            // Tạo tên file unique (UUID) để tránh đoán tên
            $extension = $file->getClientOriginalExtension() ?: 'jpg';
            $uniqueName = 'avatars/'.Str::uuid().'.'.$extension;
            $savePath = storage_path('app/public/'.$uniqueName);

            // Đảm bảo thư mục tồn tại
            $dir = dirname($savePath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Lưu ảnh đã được reprocess (loại bỏ EXIF metadata)
            $saved = match (strtolower($extension)) {
                'png' => imagepng($gdImage, $savePath),
                'gif' => imagegif($gdImage, $savePath),
                default => imagejpeg($gdImage, $savePath, 90),
            };
            imagedestroy($gdImage);

            if (! $saved) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Không thể lưu ảnh đại diện.',
                ], 500);
            }

            $user->avatar_url = '/storage/'.$uniqueName;
        }

        $user->saveQuietly();

        // FIX C1: Dùng UserProfileResource để lọc data nhạy cảm
        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật tài khoản thành công.',
            'data' => new UserProfileResource($user->fresh()),
        ], 200);
    }

    /**
     * Thay đổi mật khẩu cho người dùng hiện tại.
     *
     * QUAN TRỌNG: Model User/Admin có cast 'password' => 'hashed'.
     * Nếu dùng $user->password = Hash::make($new) thì sẽ bị hash 2 lần (double hashing).
     * Giải pháp: dùng forceFill() để bypass cast, kết hợp saveQuietly() để không trigger model events.
     *
     * @param \App\Http\Requests\ChangePasswordRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        /** @var User|Admin $user */
        $user = $this->currentUser();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validated();

        // Vì cast 'hashed', $user->password vẫn lưu đúng dạng bcrypt hash
        // nên Hash::check() vẫn hoạt động bình thường
        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mật khẩu hiện tại không đúng.',
            ], 400);
        }

        // forceFill(['password' => bcrypt_string]) bypass cast 'hashed'
        // → password được lưu đúng 1 lần hash, không bị hash 2 lần
        $user->forceFill(['password' => Hash::make($validated['new_password'])])->saveQuietly();

        return response()->json([
            'status' => 'success',
            'message' => 'Đổi mật khẩu thành công.',
        ], 200);
    }
}
