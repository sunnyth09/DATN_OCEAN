<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FcmService
{
    protected $messaging = null;

    public function __construct()
    {
        $credentialsPath = storage_path('app/firebase/firebase_credentials.json');

        if (file_exists($credentialsPath)) {
            try {
                $factory = (new Factory)->withServiceAccount($credentialsPath);
                $this->messaging = $factory->createMessaging();
            } catch (\Throwable $e) {
                Log::warning('Không thể khởi tạo Firebase Messaging: '.$e->getMessage());
            }
        }
    }

    /**
     * Gửi thông báo đến 1 token thiết bị cụ thể
     */
    public function sendNotification(string $deviceToken, string $title, string $body, array $data = []): bool
    {
        if (! $this->messaging || empty($deviceToken)) {
            return false;
        }

        try {
            $notification = Notification::create($title, $body);

            // Chuyển toàn bộ value trong $data thành string theo chuẩn FCM
            $stringData = array_map(fn ($val) => is_array($val) ? json_encode($val) : (string) $val, $data);

            $message = CloudMessage::withTarget('token', $deviceToken)
                ->withNotification($notification)
                ->withData($stringData);

            $this->messaging->send($message);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Lỗi gửi FCM token ['.substr($deviceToken, 0, 12).'...]: '.$e->getMessage());

            // Nếu token không hợp lệ hoặc đã hết hạn, tự động dọn dẹp khỏi bảng user_devices
            if (str_contains($e->getMessage(), 'NOT_FOUND') || str_contains($e->getMessage(), 'UNREGISTERED')) {
                UserDevice::where('fcm_token', $deviceToken)->delete();
            }

            return false;
        }
    }

    /**
     * Gửi thông báo đến tất cả thiết bị của 1 User
     */
    public function sendToUser($user, string $title, string $body, array $data = []): int
    {
        $userId = $user instanceof User ? $user->user_id : $user;
        if (! $userId) {
            return 0;
        }

        $devices = UserDevice::where('user_id', $userId)->get();
        if ($devices->isEmpty()) {
            return 0;
        }

        $successCount = 0;
        foreach ($devices as $device) {
            if ($this->sendNotification($device->fcm_token, $title, $body, $data)) {
                $successCount++;
            }
        }

        return $successCount;
    }

    /**
     * Gửi thông báo đến danh sách nhiều token
     */
    public function sendToMultipleTokens(array $tokens, string $title, string $body, array $data = []): int
    {
        $successCount = 0;
        foreach ($tokens as $token) {
            if ($token && $this->sendNotification($token, $title, $body, $data)) {
                $successCount++;
            }
        }

        return $successCount;
    }
}
