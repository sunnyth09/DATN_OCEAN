<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CourtBookingRealtimeEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        private string $eventName,
        private array $payload
    ) {}

    public function broadcastOn(): array
    {
        $date = $this->payload['booking_date'] ?? now()->toDateString();
        $courtId = $this->payload['court_id'] ?? 'all';
        $userId = $this->payload['user_id'] ?? null;

        $channels = [
            new Channel("court-booking.{$date}"),
            new Channel("court-booking.court.{$courtId}.{$date}"),
            new PrivateChannel('admin-notifications'),
        ];

        // Gửi thêm vào user private channel để user nhận thông báo realtime
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
