<?php

namespace App\Console\Commands;

use App\Models\CourtBooking;
use App\Services\CourtBookingWorkflowService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MarkCourtBookingNoShows extends Command
{
    protected $signature = 'court-bookings:mark-no-shows {--grace=15}';

    protected $description = 'Mark pending or confirmed court bookings as no-show after check-in grace time';

    public function handle(CourtBookingWorkflowService $workflowService): int
    {
        $graceMinutes = (int) $this->option('grace');
        $count = 0;

        CourtBooking::whereIn('status', ['pending', 'confirmed'])
            ->where('booking_date', '<=', today()->toDateString())
            ->orderBy('booking_id')
            ->chunkById(100, function ($bookings) use ($workflowService, $graceMinutes, &$count) {
                foreach ($bookings as $booking) {
                    $startAt = Carbon::parse($booking->booking_date->format('Y-m-d') . ' ' . $booking->start_time);
                    if (now()->lt($startAt->copy()->addMinutes($graceMinutes))) {
                        continue;
                    }

                    try {
                        $workflowService->transition(
                            $booking,
                            'no_show',
                            'system',
                            null,
                            "Auto no-show after {$graceMinutes} minutes",
                            [],
                            null
                        );
                        $count++;
                    } catch (\InvalidArgumentException) {
                        continue;
                    }
                }
            }, 'booking_id');

        $this->info("Marked {$count} court bookings as no-show.");

        return self::SUCCESS;
    }
}
