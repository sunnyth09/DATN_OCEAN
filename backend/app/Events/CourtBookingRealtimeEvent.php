<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CourtBookingRealtimeEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        private string $eventName,
        private array $payload
    ) {
    }

    public function broadcastOn(): array
    {
        $date = $this->payload['booking_date'] ?? now()->toDateString();
        $courtId = $this->payload['court_id'] ?? 'all';

        return [
            new PrivateChannel("court-booking.{$date}"),
            new PrivateChannel("court-booking.court.{$courtId}.{$date}"),
            new PrivateChannel('admin-notifications'),
        ];
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
