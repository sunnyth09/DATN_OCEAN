<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OpenPlayRealtimeEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        private string $eventName,
        private array $payload
    ) {}

    public function broadcastOn(): array
    {
        $openPlayId = $this->payload['open_play_id'] ?? 'all';
        $userId = $this->payload['target_user_id'] ?? null;

        $channels = [
            new Channel('open-plays'),
            new Channel("open-play.{$openPlayId}"),
        ];

        if ($userId) {
            $channels[] = new PrivateChannel("user.{$userId}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return $this->eventName;
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
