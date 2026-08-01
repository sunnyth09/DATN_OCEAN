<?php

namespace App\Services;

use App\Helpers\GeoHelper;
use App\Models\Admin;
use App\Models\Attendance;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkLocation;
use App\Models\WorkShift;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AttendanceService
{
    /**
     * GPS accuracy tối đa cho phép (mét). Vượt quá sẽ cảnh báo.
     */
    private const MAX_ACCEPTABLE_ACCURACY = 100;

    /**
     * Lấy user ID và user type từ JWT token.
     */
    public function resolveUser(): array
    {
        if (Auth::guard('admin')->check()) {
            $admin = Auth::guard('admin')->user();

            return [
                'user_id' => $admin->admin_id,
                'user_type' => 'admin',
                'full_name' => $admin->full_name,
                'avatar_url' => $admin->avatar_url,
            ];
        }

        if (Auth::guard('api')->check()) {
            $user = Auth::guard('api')->user();

            return [
                'user_id' => $user->user_id,
                'user_type' => 'user',
                'full_name' => $user->full_name ?? $user->name,
                'avatar_url' => $user->avatar_url,
            ];
        }

        return ['user_id' => null, 'user_type' => 'unknown', 'full_name' => null, 'avatar_url' => null];
    }

    /**
     * Tìm ca đang hoạt động dựa trên giờ hiện tại.
     * Xét buffer (check-in sớm).
     */
    public function findCurrentShift(): ?WorkShift
    {
        $now = Carbon::now();
        $currentTime = $now->format('H:i:s');

        $shifts = WorkShift::active()->orderBy('start_time')->get();

        foreach ($shifts as $shift) {
            // Tính giờ bắt đầu có buffer (cho phép check-in sớm)
            $bufferedStart = Carbon::createFromFormat('H:i:s', $shift->start_time)
                ->subMinutes($shift->early_buffer_minutes)
                ->format('H:i:s');

            $endTime = $shift->end_time;

            if ($bufferedStart > $endTime) {
                // Ca vắt qua nửa đêm (VD: 22:00 đến 06:00)
                if ($currentTime >= $bufferedStart || $currentTime <= $endTime) {
                    return $shift;
                }
            } else {
                // Ca bình thường trong ngày
                if ($currentTime >= $bufferedStart && $currentTime <= $endTime) {
                    return $shift;
                }
            }
        }

        return null;
    }

    /**
     * Kiểm tra nhân viên có được phân ca này vào ngày hôm nay không.
     */
    public function isAssignedToShift(int $userId, string $userType, WorkShift $shift): bool
    {
        $dayOfWeek = Carbon::now()->dayOfWeek; // 0=CN, 1=T2, ..., 6=T7

        return ShiftAssignment::where('user_id', $userId)
            ->where('user_type', $userType)
            ->where('work_shift_id', $shift->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Xử lý check-in chấm công.
     */
    public function checkIn(int $userId, string $userType, array $data): array
    {
        $lat = (float) $data['latitude'];
        $lng = (float) $data['longitude'];
        $accuracy = isset($data['accuracy']) ? (float) $data['accuracy'] : null;
        $today = now()->toDateString();

        // 1. Tìm ca hiện tại
        $currentShift = $this->findCurrentShift();
        if (! $currentShift) {
            return [
                'success' => false,
                'message' => 'Hiện tại không có ca làm việc nào đang hoạt động.',
                'data' => null,
                'status_code' => 400,
            ];
        }

        // 2. Kiểm tra nhân viên có được phân ca này không
        if (! $this->isAssignedToShift($userId, $userType, $currentShift)) {
            return [
                'success' => false,
                'message' => "Bạn không được phân vào \"{$currentShift->name}\" hôm nay. Vui lòng liên hệ quản lý.",
                'data' => ['shift_name' => $currentShift->name],
                'status_code' => 403,
            ];
        }

        // 3. Cảnh báo GPS accuracy
        $accuracyWarning = null;
        if ($accuracy !== null && $accuracy > self::MAX_ACCEPTABLE_ACCURACY) {
            $accuracyWarning = "GPS accuracy ({$accuracy}m) thấp hơn mức khuyến nghị (".self::MAX_ACCEPTABLE_ACCURACY.'m).';
            Log::warning('GPS attendance: Low accuracy', [
                'user_id' => $userId, 'user_type' => $userType, 'accuracy' => $accuracy,
            ]);
        }

        // 4. Kiểm tra vị trí GPS
        $locations = WorkLocation::active()->get();
        if ($locations->isEmpty()) {
            return [
                'success' => false,
                'message' => 'Chưa có chi nhánh nào được cấu hình.',
                'data' => null,
                'status_code' => 400,
            ];
        }

        $validResult = GeoHelper::findNearestValidLocation($lat, $lng, $locations);
        if (! $validResult) {
            $nearest = GeoHelper::findNearestLocation($lat, $lng, $locations);

            return [
                'success' => false,
                'message' => 'Bạn đang ở ngoài phạm vi chấm công cho phép.',
                'data' => [
                    'nearest_location' => $nearest ? $nearest['location']->name : 'N/A',
                    'distance_meters' => $nearest ? $nearest['distance_meters'] : 0,
                    'allowed_radius_meters' => $nearest ? $nearest['location']->radius_meters : 0,
                ],
                'status_code' => 400,
            ];
        }

        // 5. Kiểm tra đã check-in ca này hôm nay chưa (Có lock chống double-submit)
        $lockKey = "checkin_{$userId}_{$currentShift->id}_{$today}";
        $lock = Cache::lock($lockKey, 10);

        if (! $lock->get()) {
            return [
                'success' => false,
                'message' => 'Hệ thống đang xử lý, vui lòng không nhấn liên tục.',
                'data' => null,
                'status_code' => 429,
            ];
        }

        try {
            $existing = Attendance::where('user_id', $userId)
                ->where('user_type', $userType)
                ->where('work_date', $today)
                ->where('work_shift_id', $currentShift->id)
                ->first();

            if ($existing) {
                if ($existing->status === 'checked_in') {
                    return [
                        'success' => false,
                        'message' => "Bạn đã check-in \"{$currentShift->name}\" hôm nay và chưa check-out.",
                        'data' => null,
                        'status_code' => 400,
                    ];
                }
                if ($existing->status === 'checked_out') {
                    return [
                        'success' => false,
                        'message' => "Bạn đã hoàn tất \"{$currentShift->name}\" hôm nay.",
                        'data' => null,
                        'status_code' => 400,
                    ];
                }
            }

            // 6. Face Verification (nếu đã đăng ký)
            $faceResult = null;
            if (! empty($data['image'])) {
                $faceService = app(FaceVerificationService::class);
                $faceResult = $faceService->verifyForAttendance($userId, $userType, $data['image']);

                // Nếu đã đăng ký nhưng không match → chặn
                if ($faceResult['registered'] && ! $faceResult['match']) {
                    return [
                        'success' => false,
                        'message' => 'Xác thực khuôn mặt thất bại. Khuôn mặt không khớp với ảnh đã đăng ký.',
                        'data' => [
                            'face_confidence' => $faceResult['confidence'] ?? 0,
                            'face_distance' => $faceResult['distance'] ?? 1.0,
                        ],
                        'status_code' => 403,
                    ];
                }
                // Nếu chưa đăng ký → cảnh báo nhưng vẫn cho qua (grace period)
                // TODO: Sau khi rollout xong, chuyển sang chặn luôn nếu chưa đăng ký
            }

            // 7. Lưu ảnh selfie
            $imagePath = $this->saveBase64Image($data['image'] ?? null, 'checkin_'.$userId);

            // 8. Tạo attendance record
            // Bọc try-catch để bắt unique violation (backstop tầng DB): khi có race
            // vượt qua Cache::lock (lock hết hạn / Redis restart), unique index sẽ chặn
            // bản ghi trùng thay vì tạo dữ liệu rác. Trả message thân thiện thay vì HTTP 500.
            try {
                $attendance = Attendance::create([
                    'user_id' => $userId,
                    'user_type' => $userType,
                    'work_location_id' => $validResult['location']->id,
                    'work_shift_id' => $currentShift->id,
                    'work_date' => $today,
                    'check_in_at' => now(),
                    'ip_address' => request()->ip(),
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'check_in_accuracy' => $accuracy,
                    'check_in_distance_meters' => $validResult['distance_meters'],
                    'status' => 'checked_in',
                    'image_path' => $imagePath,
                    'face_verified' => $faceResult ? $faceResult['match'] : null,
                    'face_confidence' => $faceResult ? $faceResult['confidence'] : null,
                    'face_distance' => $faceResult ? $faceResult['distance'] : null,
                    'note' => $data['note'] ?? null,
                ]);
            } catch (QueryException $e) {
                // 23000 = integrity constraint violation (MySQL 1062 duplicate entry)
                if ($e->getCode() === '23000') {
                    return [
                        'success' => false,
                        'message' => "Bạn đã check-in \"{$currentShift->name}\" hôm nay rồi.",
                        'data' => null,
                        'status_code' => 409,
                    ];
                }
                throw $e;
            }

            return [
                'success' => true,
                'message' => "Check-in \"{$currentShift->name}\" thành công!".($accuracyWarning ? " Lưu ý: {$accuracyWarning}" : ''),
                'data' => [
                    'id' => $attendance->id,
                    'work_date' => $attendance->work_date,
                    'check_in_time' => $attendance->check_in_at,
                    'shift_name' => $currentShift->name,
                    'location_name' => $validResult['location']->name,
                    'distance_meters' => $validResult['distance_meters'],
                    'accuracy' => $accuracy,
                    'face_verified' => $faceResult ? $faceResult['match'] : null,
                    'face_confidence' => $faceResult ? round(($faceResult['confidence'] ?? 0) * 100) : null,
                    'status' => $attendance->status,
                ],
                'status_code' => 200,
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * Xử lý check-out chấm công.
     * Check-out cho phép ngoài phạm vi.
     */
    public function checkOut(int $userId, string $userType, array $data): array
    {
        $lat = (float) $data['latitude'];
        $lng = (float) $data['longitude'];
        $accuracy = isset($data['accuracy']) ? (float) $data['accuracy'] : null;

        // Tìm attendance đang check-in (chưa check-out)
        $attendance = Attendance::where('user_id', $userId)
            ->where('user_type', $userType)
            ->where('status', 'checked_in')
            ->whereNull('check_out_at')
            ->latest('check_in_at')
            ->first();

        if (! $attendance) {
            return [
                'success' => false,
                'message' => 'Bạn chưa check-in hoặc đã check-out rồi!',
                'data' => null,
                'status_code' => 400,
            ];
        }

        // Tính khoảng cách check-out (cho audit, KHÔNG chặn)
        $checkOutDistance = null;
        $outsideRange = false;

        if ($attendance->work_location_id) {
            $workLocation = WorkLocation::find($attendance->work_location_id);
            if ($workLocation) {
                $result = GeoHelper::isWithinRadius(
                    $lat, $lng,
                    (float) $workLocation->latitude,
                    (float) $workLocation->longitude,
                    (float) $workLocation->radius_meters
                );
                $checkOutDistance = $result['distance_meters'];
                $outsideRange = ! $result['is_valid'];
            }
        }

        // Face verification cho check-out
        $faceResult = null;
        if (! empty($data['image'])) {
            $faceService = app(FaceVerificationService::class);
            $faceResult = $faceService->verifyForAttendance($userId, $userType, $data['image']);

            if ($faceResult['registered'] && ! $faceResult['match']) {
                return [
                    'success' => false,
                    'message' => 'Xác thực khuôn mặt thất bại khi check-out.',
                    'data' => [
                        'face_confidence' => $faceResult['confidence'] ?? 0,
                    ],
                    'status_code' => 403,
                ];
            }
        }

        // Lưu ảnh check-out
        $imagePath = $this->saveBase64Image($data['image'] ?? null, 'checkout_'.$userId);

        // Lấy tên ca
        $shiftName = '';
        if ($attendance->work_shift_id) {
            $shift = WorkShift::find($attendance->work_shift_id);
            $shiftName = $shift ? $shift->name : '';
        }

        $attendance->update([
            'check_out_at' => now(),
            'check_out_latitude' => $lat,
            'check_out_longitude' => $lng,
            'check_out_accuracy' => $accuracy,
            'check_out_distance_meters' => $checkOutDistance,
            'check_out_image_path' => $imagePath,
            'status' => 'checked_out',
        ]);

        $message = 'Check-out'.($shiftName ? " \"{$shiftName}\"" : '').' thành công!';
        if ($outsideRange) {
            $message .= ' (Lưu ý: Bạn check-out ngoài phạm vi.)';
        }

        return [
            'success' => true,
            'message' => $message,
            'data' => [
                'id' => $attendance->id,
                'work_date' => $attendance->work_date,
                'shift_name' => $shiftName,
                'check_in_time' => $attendance->check_in_at,
                'check_out_time' => $attendance->check_out_at,
                'check_out_distance' => $checkOutDistance,
                'outside_range' => $outsideRange,
                'status' => 'checked_out',
            ],
            'status_code' => 200,
        ];
    }

    /**
     * Lấy trạng thái chấm công hôm nay — theo từng ca.
     */
    public function getTodayStatus(int $userId, string $userType): array
    {
        $today = now()->toDateString();
        $dayOfWeek = Carbon::now()->dayOfWeek;

        // Lấy tất cả ca
        $shifts = WorkShift::active()->orderBy('start_time')->get();

        // Lấy ca được phân cho hôm nay
        $assignedShiftIds = ShiftAssignment::where('user_id', $userId)
            ->where('user_type', $userType)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->pluck('work_shift_id')
            ->toArray();

        // Lấy attendance hôm nay
        $attendances = Attendance::where('user_id', $userId)
            ->where('user_type', $userType)
            ->where('work_date', $today)
            ->get()
            ->keyBy('work_shift_id');

        // Tìm ca hiện tại
        $currentShift = $this->findCurrentShift();

        // Build trạng thái từng ca
        $shiftsStatus = [];
        foreach ($shifts as $shift) {
            $isAssigned = in_array($shift->id, $assignedShiftIds);
            $att = $attendances->get($shift->id);

            $locationName = null;
            if ($att && $att->work_location_id) {
                $loc = WorkLocation::find($att->work_location_id);
                $locationName = $loc ? $loc->name : null;
            }

            $shiftsStatus[] = [
                'shift_id' => $shift->id,
                'shift_name' => $shift->name,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'is_assigned' => $isAssigned,
                'is_current' => $currentShift && $currentShift->id === $shift->id,
                'state' => $att ? $att->status : ($isAssigned ? 'not_checked_in' : 'not_assigned'),
                'attendance' => $att ? [
                    'id' => $att->id,
                    'check_in_at' => $att->check_in_at,
                    'check_out_at' => $att->check_out_at,
                    'location_name' => $locationName,
                    'check_in_distance_meters' => $att->check_in_distance_meters,
                    'note' => $att->note,
                ] : null,
            ];
        }

        // Trạng thái tổng thể
        $overallState = 'not_checked_in';
        if ($currentShift) {
            $currentAtt = $attendances->get($currentShift->id);
            if ($currentAtt) {
                $overallState = $currentAtt->status;
            }
        }

        return [
            'state' => $overallState,
            'current_shift' => $currentShift ? [
                'id' => $currentShift->id,
                'name' => $currentShift->name,
                'start_time' => $currentShift->start_time,
                'end_time' => $currentShift->end_time,
                'is_assigned' => in_array($currentShift->id, $assignedShiftIds),
            ] : null,
            'shifts' => $shiftsStatus,
        ];
    }

    /**
     * Lấy lịch sử chấm công cá nhân.
     */
    public function getMyHistory(int $userId, string $userType, array $filters = [])
    {
        $query = Attendance::where('user_id', $userId)
            ->where('user_type', $userType)
            ->orderBy('work_date', 'desc')
            ->orderBy('check_in_at', 'desc');

        $this->applyFilters($query, $filters);

        $attendances = $query->paginate(15);
        $this->attachNames($attendances);

        return $attendances;
    }

    /**
     * Lấy danh sách chấm công toàn bộ (cho Admin).
     */
    public function getAllAttendances(array $filters = [])
    {
        $query = Attendance::orderBy('work_date', 'desc')
            ->orderBy('check_in_at', 'desc');

        $this->applyFilters($query, $filters);

        $attendances = $query->paginate(15);
        $this->attachNames($attendances, true);

        return $attendances;
    }

    /**
     * Flag bản ghi chấm công bất thường.
     */
    public function flagAttendance(int $attendanceId, bool $isFlagged, ?string $flagNote): array
    {
        $attendance = Attendance::find($attendanceId);
        if (! $attendance) {
            return ['success' => false, 'message' => 'Không tìm thấy bản ghi.', 'status_code' => 404];
        }

        $attendance->update([
            'is_flagged' => $isFlagged,
            'flag_note' => $isFlagged ? $flagNote : null,
        ]);

        return [
            'success' => true,
            'message' => $isFlagged ? 'Đã gắn cờ bất thường.' : 'Đã bỏ gắn cờ.',
            'status_code' => 200,
        ];
    }

    // ============================================================
    //  PRIVATE HELPERS
    // ============================================================

    private function applyFilters($query, array $filters): void
    {
        if (! empty($filters['from_date'])) {
            $query->where('work_date', '>=', $filters['from_date']);
        }
        if (! empty($filters['to_date'])) {
            $query->where('work_date', '<=', $filters['to_date']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['is_flagged'])) {
            $query->where('is_flagged', filter_var($filters['is_flagged'], FILTER_VALIDATE_BOOLEAN));
        }
    }

    private function attachNames($attendances, bool $includeUser = false): void
    {
        // Location + Shift names
        $locationIds = $attendances->pluck('work_location_id')->filter()->unique();
        $locations = WorkLocation::whereIn('id', $locationIds)->pluck('name', 'id');

        $shiftIds = $attendances->pluck('work_shift_id')->filter()->unique();
        $shifts = WorkShift::whereIn('id', $shiftIds)->pluck('name', 'id');

        foreach ($attendances as $att) {
            $att->location_name = $att->work_location_id ? ($locations[$att->work_location_id] ?? 'N/A') : 'N/A';
            $att->shift_name = $att->work_shift_id ? ($shifts[$att->work_shift_id] ?? 'N/A') : 'N/A';

            if ($includeUser) {
                if ($att->user_type === 'admin') {
                    $admin = Admin::find($att->user_id);
                    $att->user_name = $admin ? $admin->full_name : 'Unknown';
                    $att->user_role = $admin ? $admin->role : 'N/A';
                    $att->user_avatar = $admin ? $admin->avatar_url : null;
                } else {
                    $user = User::find($att->user_id);
                    $att->user_name = $user ? ($user->full_name ?? $user->name) : 'Unknown';
                    $att->user_role = $user ? $user->role : 'N/A';
                    $att->user_avatar = $user ? $user->avatar_url : null;
                }
            }
        }
    }

    private function saveBase64Image(?string $base64Image, string $prefix): ?string
    {
        if (! $base64Image) {
            return null;
        }

        $imageParts = explode(';base64,', $base64Image);
        if (count($imageParts) !== 2) {
            return null;
        }

        $imageData = base64_decode($imageParts[1]);
        if ($imageData === false) {
            return null;
        }

        if (strlen($imageData) > 2 * 1024 * 1024) {
            return null;
        }

        $fileName = $prefix.'_'.time().'_'.bin2hex(random_bytes(4)).'.jpg';
        $path = 'attendances/'.$fileName;
        Storage::disk('public')->put($path, $imageData);

        return '/storage/'.$path;
    }
}
