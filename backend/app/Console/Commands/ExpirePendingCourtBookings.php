<?php

namespace App\Console\Commands;

use App\Models\CourtBooking;
use App\Services\CourtBookingWorkflowService;
use Illuminate\Console\Command;

class ExpirePendingCourtBookings extends Command
{
    protected $signature = 'court-bookings:expire-pending {--minutes=15}';

    protected $description = 'Expire unpaid pending court bookings after the configured payment window';

    public function handle(CourtBookingWorkflowService $workflowService): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $expired = 0;

        CourtBooking::where('status', 'pending')
            ->where('paid_amount', '<=', 0)
            ->where('created_at', '<=', now()->subMinutes($minutes))
            ->orderBy('booking_id')
            ->chunkById(100, function ($bookings) use ($workflowService, $minutes, &$expired) {
                foreach ($bookings as $booking) {
                    try {
                        $workflowService->transition(
                            $booking,
                            'expired',
                            'system',
                            null,
                            "Auto expired after {$minutes} minutes without payment",
                        );
                        $expired++;
                    } catch (\InvalidArgumentException) {
                        continue;
                    }
                }
            }, 'booking_id');

        $this->info("Expired {$expired} pending court bookings.");

        return self::SUCCESS;
    }
}
