<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public $sessionToken;

    public $senderType;

    /**
     * Create a new event instance.
     */
    public function __construct(ChatMessage $message, string $sessionToken)
    {
        $this->message = $message;
        $this->sessionToken = $sessionToken;
        $this->senderType = $message->sender_type;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // Public channel cho guest (UUID token làm bảo mật)
            new Channel('chat.'.$this->sessionToken),
            // Public/Private channel cho Admin
            new Channel('admin.chats'), // Tạm dùng public channel để tránh lỗi auth cho admin dashboard nếu setup chưa xong
        ];
    }

    public function broadcastAs()
    {
        return 'message.sent';
    }
}
