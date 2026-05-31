<?php

namespace App\Services;

use App\Helpers\GeoHelper;
use App\Models\Attendance;
use App\Models\ShiftAssignment;
use App\Models\WorkLocation;
use App\Models\WorkShift;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

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
                'user_id'    => $admin->admin_id,
                'user_type'  => 'admin',
                'full_name'  => $admin->full_name,
                'avatar_url' => $admin->avatar_url,
            ];
        }

        if (Auth::guard('api')->check()) {
            $user = Auth::guard('api')->user();
            return [
                'user_id'    => $user->user_id,
                'user_type'  => 'user',
                'full_name'  => $user->full_name ?? $user->name,
                'avatar_url' => $user->avatar_url,
            ];
        }

        return ['user_id' => null, 'user_type' => 'unknown', 'full_name' => null, 'avatar_url' => null];
    }

    /**
     * Tìm ca đang hoạt động dựa trên giờ hiện tại.
     * Xét buffer (check-in sớm). Có hỗ trợ ca qua đêm.
     *
     * @return WorkShift|null
     */
    public function findCurrentShift(): ?WorkShift
    {
        $now = Carbon::now();
        $currentTime = $now->format('H:i:s');

        $shifts = WorkShift::active()->orderBy('start_time')->get();

        foreach ($shifts as $shift) {
            $start = Carbon::createFromFormat('H:i:s', $shift->start_time);
            $bufferedStart = clone $start;
            $bufferedStart->subMinutes($shift->early_buffer_minutes);
            $startTimeStr = $bufferedStart->format('H:i:s');
            $endTimeStr = $shift->end_time;

            if ($startTimeStr > $endTimeStr) {
                // Ca xuyên đêm (vd: bắt đầu 23:00, kết thúc 06:00) 
                // Hoặc buffer đẩy giờ bắt đầu về hôm qua
                if ($currentTime >= $startTimeStr || $currentTime <= $endTimeStr) {
                    return $shift;
                }
            } else {
                // Ca bình thường trong ngày
                if ($currentTime >= $startTimeStr && $currentTime <= $endTimeStr) {
                    return $shift;
                }
            }
        }

        return null;
    }

    /**
     * Lấy ngày làm việc chuẩn (work_date) của một ca.
     * Trả về hôm qua nếu đang ở đầu giờ sáng hôm nay nhưng ca thuộc về đêm qua.
     */
    public function getWorkDateForShift(WorkShift $shift): string
    {
        $now = Carbon::now();
        $currentTime = $now->format('H:i:s');
        
        $start = Carbon::createFromFormat('H:i:s', $shift->start_time);
        $bufferedStart = (clone $start)->subMinutes($shift->early_buffer_minutes)->format('H:i:s');
        $endStr = $shift->end_time;

        if ($bufferedStart > $endStr && $currentTime <= $endStr) {
            return $now->copy()->subDay()->toDateString();
        }

        return $now->toDateString();
    }

    /**
     * Kiểm tra nhân viên có được phân ca này vào ngày hôm nay không.
     *
     * @param int       $userId
     * @param string    $userType
     * @param WorkShift $shift
     * @return bool
     */
    public function isAssignedToShift(int $userId, string $userType, WorkShift $shift): bool
    {
        $now = Carbon::now();
        $currentTime = $now->format('H:i:s');
        $dayOfWeek = $now->dayOfWeek; // 0=CN, 1=T2, ..., 6=T7

        $start = Carbon::createFromFormat('H:i:s', $shift->start_time);
        $bufferedStart = (clone $start)->subMinutes($shift->early_buffer_minutes)->format('H:i:s');
        $endStr = $shift->end_time;

        // Nếu ca xuyên đêm và đang nằm trong nửa sau của ca (sau 00:00)
        // Thì thực chất đang điểm danh cho ngày hôm qua
        if ($bufferedStart > $endStr && $currentTime <= $endStr) {
            $dayOfWeek = $now->copy()->subDay()->dayOfWeek;
        }

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
        $lat      = (float) $data['latitude'];
        $lng      = (float) $data['longitude'];
        $accuracy = isset($data['accuracy']) ? (float) $data['accuracy'] : null;

        // 1. Tìm ca hiện tại
        $currentShift = $this->findCurrentShift();
        if (!$currentShift) {
            return [
                'success'     => false,
                'message'     => 'Hiện tại không có ca làm việc nào đang hoạt động.',
                'data'        => null,
                'status_code' => 400,
            ];
        }

        // 2. Kiểm tra nhân viên có được phân ca này không
        if (!$this->isAssignedToShift($userId, $userType, $currentShift)) {
            return [
                'success'     => false,
                'message'     => "Bạn không được phân vào \"{$currentShift->name}\" hôm nay. Vui lòng liên hệ quản lý.",
                'data'        => ['shift_name' => $currentShift->name],
                'status_code' => 403,
            ];
        }

        // 3. Cảnh báo GPS accuracy
        $accuracyWarning = null;
        if ($accuracy !== null && $accuracy > self::MAX_ACCEPTABLE_ACCURACY) {
            $accuracyWarning = "GPS accuracy ({$accuracy}m) thấp hơn mức khuyến nghị (" . self::MAX_ACCEPTABLE_ACCURACY . "m).";
            Log::warning('GPS attendance: Low accuracy', [
                'user_id' => $userId, 'user_type' => $userType, 'accuracy' => $accuracy,
            ]);
        }

        // 4. Kiểm tra vị trí GPS
        $locations = WorkLocation::active()->get();
        if ($locations->isEmpty()) {
            return [
                'success'     => false,
                'message'     => 'Chưa có vị trí làm việc nào được cấu hình.',
                'data'        => null,
                'status_code' => 400,
            ];
        }

        $validResult = GeoHelper::findNearestValidLocation($lat, $lng, $locations);
        if (!$validResult) {
            $nearest = GeoHelper::findNearestLocation($lat, $lng, $locations);
            return [
                'success'     => false,
                'message'     => 'Bạn đang ở ngoài phạm vi chấm công cho phép.',
                'data'        => [
                    'nearest_location'      => $nearest ? $nearest['location']->name : 'N/A',
                    'distance_meters'       => $nearest ? $nearest['distance_meters'] : 0,
                    'allowed_radius_meters' => $nearest ? $nearest['location']->radius_meters : 0,
                ],
                'status_code' => 400,
            ];
        }

        // 5. Kiểm tra đã check-in ca này chưa
        $workDate = $this->getWorkDateForShift($currentShift);
        $existing = Attendance::where('user_id', $userId)
            ->where('user_type', $userType)
            ->where('work_date', $workDate)
            ->where('work_shift_id', $currentShift->id)
            ->first();

        if ($existing) {
            if ($existing->status === 'checked_in') {
                return [
                    'success'     => false,
                    'message'     => "Bạn đã check-in \"{$currentShift->name}\" hôm nay và chưa check-out.",
                    'data'        => null,
                    'status_code' => 400,
                ];
            }
            if ($existing->status === 'checked_out') {
                return [
                    'success'     => false,
                    'message'     => "Bạn đã hoàn tất \"{$currentShift->name}\" hôm nay.",
                    'data'        => null,
                    'status_code' => 400,
                ];
            }
        }

        // 6. Lưu ảnh selfie
        $imagePath = $this->saveBase64Image($data['image'] ?? null, 'checkin_' . $userId);

        // 7. Tạo attendance record
        $attendance = Attendance::create([
            'user_id'                  => $userId,
            'user_type'                => $userType,
            'work_location_id'         => $validResult['location']->id,
            'work_shift_id'            => $currentShift->id,
            'work_date'                => $workDate,
            'check_in_at'              => now(),
            'ip_address'               => request()->ip(),
            'latitude'                 => $lat,
            'longitude'                => $lng,
            'check_in_accuracy'        => $accuracy,
            'check_in_distance_meters' => $validResult['distance_meters'],
            'status'                   => 'checked_in',
            'image_path'               => $imagePath,
            'note'                     => $data['note'] ?? null,
        ]);

        return [
            'success'     => true,
            'message'     => "Check-in \"{$currentShift->name}\" thành công!" . ($accuracyWarning ? " Lưu ý: {$accuracyWarning}" : ''),
            'data'        => [
                'id'              => $attendance->id,
                'work_date'       => $attendance->work_date,
                'check_in_time'   => $attendance->check_in_at,
                'shift_name'      => $currentShift->name,
                'location_name'   => $validResult['location']->name,
                'distance_meters' => $validResult['distance_meters'],
                'accuracy'        => $accuracy,
                'status'          => $attendance->status,
            ],
            'status_code' => 200,
        ];
    }

    /**
     * Xử lý check-out chấm công.
     * Check-out cho phép ngoài phạm vi.
     */
    public function checkOut(int $userId, string $userType, array $data): array
    {
        $lat      = (float) $data['latitude'];
        $lng      = (float) $data['longitude'];
        $accuracy = isset($data['accuracy']) ? (float) $data['accuracy'] : null;

        // Tìm attendance đang check-in (chưa check-out)
        $attendance = Attendance::where('user_id', $userId)
            ->where('user_type', $userType)
            ->where('status', 'checked_in')
            ->whereNull('check_out_at')
            ->latest('check_in_at')
            ->first();

        if (!$attendance) {
            return [
                'success'     => false,
                'message'     => 'Bạn chưa check-in hoặc đã check-out rồi!',
                'data'        => null,
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
                $outsideRange = !$result['is_valid'];
            }
        }

        // Lưu ảnh check-out
        $imagePath = $this->saveBase64Image($data['image'] ?? null, 'checkout_' . $userId);

        // Lấy tên ca
        $shiftName = '';
        if ($attendance->work_shift_id) {
            $shift = WorkShift::find($attendance->work_shift_id);
            $shiftName = $shift ? $shift->name : '';
        }

        $attendance->update([
            'check_out_at'              => now(),
            'check_out_latitude'        => $lat,
            'check_out_longitude'       => $lng,
            'check_out_accuracy'        => $accuracy,
            'check_out_distance_meters' => $checkOutDistance,
            'check_out_image_path'      => $imagePath,
            'status'                    => 'checked_out',
        ]);

        $message = "Check-out" . ($shiftName ? " \"{$shiftName}\"" : '') . " thành công!";
        if ($outsideRange) {
            $message .= ' (Lưu ý: Bạn check-out ngoài phạm vi.)';
        }

        return [
            'success'     => true,
            'message'     => $message,
            'data'        => [
                'id'                 => $attendance->id,
                'work_date'          => $attendance->work_date,
                'shift_name'         => $shiftName,
                'check_in_time'      => $attendance->check_in_at,
                'check_out_time'     => $attendance->check_out_at,
                'check_out_distance' => $checkOutDistance,
                'outside_range'      => $outsideRange,
                'status'             => 'checked_out',
            ],
            'status_code' => 200,
        ];
    }

    /**
     * Lấy trạng thái chấm công hôm nay — theo từng ca.
     */
    public function getTodayStatus(int $userId, string $userType): array
    {
        $now = Carbon::now();
        $today = $now->toDateString();
        $yesterday = $now->copy()->subDay()->toDateString();

        // Lấy tất cả ca
        $shifts = WorkShift::active()->orderBy('start_time')->get();

        // Lấy attendance cho cả hôm qua và hôm nay
        $attendances = Attendance::where('user_id', $userId)
            ->where('user_type', $userType)
            ->whereIn('work_date', [$yesterday, $today])
            ->get();

        // Tìm ca hiện tại
        $currentShift = $this->findCurrentShift();

        // Build trạng thái từng ca
        $shiftsStatus = [];
        foreach ($shifts as $shift) {
            $isAssigned = $this->isAssignedToShift($userId, $userType, $shift);
            $workDateForShift = $this->getWorkDateForShift($shift);
            
            $att = $attendances->where('work_shift_id', $shift->id)
                               ->where('work_date', $workDateForShift)
                               ->first();

            $locationName = null;
            if ($att && $att->work_location_id) {
                $loc = WorkLocation::find($att->work_location_id);
                $locationName = $loc ? $loc->name : null;
            }

            $shiftsStatus[] = [
                'shift_id'    => $shift->id,
                'shift_name'  => $shift->name,
                'start_time'  => $shift->start_time,
                'end_time'    => $shift->end_time,
                'is_assigned' => $isAssigned,
                'is_current'  => $currentShift && $currentShift->id === $shift->id,
                'state'       => $att ? $att->status : ($isAssigned ? 'not_checked_in' : 'not_assigned'),
                'attendance'  => $att ? [
                    'id'                       => $att->id,
                    'check_in_at'              => $att->check_in_at,
                    'check_out_at'             => $att->check_out_at,
                    'location_name'            => $locationName,
                    'check_in_distance_meters' => $att->check_in_distance_meters,
                    'note'                     => $att->note,
                ] : null,
            ];
        }

        // Trạng thái tổng thể
        $overallState = 'not_checked_in';
        if ($currentShift) {
            $workDateForCurrent = $this->getWorkDateForShift($currentShift);
            $currentAtt = $attendances->where('work_shift_id', $currentShift->id)
                                      ->where('work_date', $workDateForCurrent)
                                      ->first();
            if ($currentAtt) {
                $overallState = $currentAtt->status;
            }
        }

        return [
            'state'         => $overallState,
            'current_shift' => $currentShift ? [
                'id'         => $currentShift->id,
                'name'       => $currentShift->name,
                'start_time' => $currentShift->start_time,
                'end_time'   => $currentShift->end_time,
                'is_assigned' => $this->isAssignedToShift($userId, $userType, $currentShift),
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
        if (!$attendance) {
            return ['success' => false, 'message' => 'Không tìm thấy bản ghi.', 'status_code' => 404];
        }

        $attendance->update([
            'is_flagged' => $isFlagged,
            'flag_note'  => $isFlagged ? $flagNote : null,
        ]);

        return [
            'success'     => true,
            'message'     => $isFlagged ? 'Đã gắn cờ bất thường.' : 'Đã bỏ gắn cờ.',
            'status_code' => 200,
        ];
    }

    // ============================================================
    //  PRIVATE HELPERS
    // ============================================================

    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['from_date'])) {
            $query->where('work_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('work_date', '<=', $filters['to_date']);
        }
        if (!empty($filters['status'])) {
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
                    $admin = \App\Models\Admin::find($att->user_id);
                    $att->user_name = $admin ? $admin->full_name : 'Unknown';
                    $att->user_role = $admin ? $admin->role : 'N/A';
                    $att->user_avatar = $admin ? $admin->avatar_url : null;
                } else {
                    $user = \App\Models\User::find($att->user_id);
                    $att->user_name = $user ? ($user->full_name ?? $user->name) : 'Unknown';
                    $att->user_role = $user ? $user->role : 'N/A';
                    $att->user_avatar = $user ? $user->avatar_url : null;
                }
            }
        }
    }

    private function saveBase64Image(?string $base64Image, string $prefix): ?string
    {
        if (!$base64Image) {
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

        $fileName = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';
        $path = 'attendances/' . $fileName;
        Storage::disk('public')->put($path, $imageData);

        return '/storage/' . $path;
    }
}
