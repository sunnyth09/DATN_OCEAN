<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class SystemNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $title;
    protected $message;
    protected $urlRedirect;
    protected $icon;

    /**
     * Khởi tạo Notification.
     *
     * @param string $title Tiêu đề thông báo
     * @param string $message Nội dung thông báo
     * @param string|null $urlRedirect Link khi click vào (Tùy chọn)
     * @param string|null $icon Icon hiển thị (Tùy chọn)
     */
    public function __construct(string $title, string $message, ?string $urlRedirect = null, ?string $icon = 'bell')
    {
        $this->title = $title;
        $this->message = $message;
        $this->urlRedirect = $urlRedirect;
        $this->icon = $icon;
    }

    /**
     * Kênh gửi thông báo (Lưu DB + Bắn Realtime).
     */
    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Định dạng dữ liệu lưu vào Database (cột data).
     */
    public function toDatabase($notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url_redirect' => $this->urlRedirect,
            'icon' => $this->icon,
        ];
    }

    /**
     * Định dạng dữ liệu đẩy qua WebSocket (Pusher/Reverb).
     * Sự kiện mặc định bên frontend là .Illuminate\Notifications\Events\BroadcastNotificationCreated
     * Hoặc .UserNotificationEvent (nếu đổi tên).
     */
    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'title' => $this->title,
            'message' => $this->message,
            'url_redirect' => $this->urlRedirect,
            'icon' => $this->icon,
        ]);
    }
    
    /**
     * Đổi tên sự kiện broadcast để frontend dễ listen
     */
    public function broadcastType(): string
    {
        return 'UserNotificationEvent';
    }
}
