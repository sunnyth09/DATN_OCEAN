<?php

namespace App\Notifications;

use App\Events\UserNotificationEvent;
use App\Models\OpenPlay;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OpenPlayNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public OpenPlay $openPlay,
        public string $eventType,
        public string $title,
        public string $message,
        public array $extra = []
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return array_merge([
            'title' => $this->title,
            'message' => $this->message,
            'type' => 'open_play',
            'event_type' => $this->eventType,
            'open_play_id' => $this->openPlay->id,
            'booking_id' => $this->openPlay->booking_id,
            'open_play_code' => $this->openPlay->open_play_code,
            'match_title' => $this->openPlay->title,
            'url' => '/profile/court-bookings?booking_id='.$this->openPlay->booking_id.'&open_play_id='.$this->openPlay->id,
        ], $this->extra);
    }

    public static function sendToUser(User|int $user, OpenPlay $openPlay, string $eventType, string $title, string $message, array $extra = []): void
    {
        $userId = $user instanceof User ? $user->user_id : $user;
        $notificationData = array_merge([
            'title' => $title,
            'message' => $message,
            'type' => 'open_play',
            'event_type' => $eventType,
            'open_play_id' => $openPlay->id,
            'booking_id' => $openPlay->booking_id,
            'open_play_code' => $openPlay->open_play_code,
            'match_title' => $openPlay->title,
            'url' => '/profile/court-bookings?booking_id='.$openPlay->booking_id.'&open_play_id='.$openPlay->id,
        ], $extra);

        try {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => 'open_play',
                'notifiable_type' => User::class,
                'notifiable_id' => $userId,
                'data' => json_encode($notificationData, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            event(new UserNotificationEvent((int) $userId, $notificationData));
        } catch (\Throwable $e) {
            Log::warning('Failed to send open play notification', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
