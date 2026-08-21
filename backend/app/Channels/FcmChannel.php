<?php

namespace App\Channels;

use App\Services\FcmService;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    public function __construct(
        protected FcmService $fcmService
    ) {}

    /**
     * Send the given notification via FCM.
     */
    public function send($notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toFcm')) {
            return;
        }

        $fcmData = $notification->toFcm($notifiable);
        if (empty($fcmData)) {
            return;
        }

        $title = $fcmData['title'] ?? 'Ocean Sport';
        $body = $fcmData['body'] ?? ($fcmData['message'] ?? '');
        $data = $fcmData['data'] ?? [];

        $this->fcmService->sendToUser($notifiable, $title, $body, $data);
    }
}
