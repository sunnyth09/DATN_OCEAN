<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FcmService
{
    protected $messaging;

    public function __construct()
    {
        // Trỏ đường dẫn tới file JSON bạn vừa thả vào ở Bước 1
        $credentialsPath = storage_path('app/firebase/firebase_credentials.json');

        $factory = (new Factory)->withServiceAccount($credentialsPath);
        $this->messaging = $factory->createMessaging();
    }

    /**
     * Hàm gửi thông báo
     */
    public function sendNotification(string $deviceToken, string $title, string $body, array $data = [])
    {
        $notification = Notification::create($title, $body);

        $message = CloudMessage::withTarget('token', $deviceToken)
            ->withNotification($notification)
            ->withData($data);

        try {
            $this->messaging->send($message);

            return true;
        } catch (\Exception $e) {
            Log::error('Lỗi gửi FCM: '.$e->getMessage());

            return false;
        }
    }
}
