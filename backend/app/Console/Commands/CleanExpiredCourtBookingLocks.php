<?php

namespace App\Console\Commands;

use App\Models\CourtBookingLock;
use Illuminate\Console\Command;

class CleanExpiredCourtBookingLocks extends Command
{
    protected $signature = 'court-bookings:clean-expired-locks';

    protected $description = 'Delete expired temporary court booking locks';

    public function handle(): int
    {
        $deleted = CourtBookingLock::where('expires_at', '<=', now())->delete();

        $this->info("Deleted {$deleted} expired court booking locks.");

        return self::SUCCESS;
    }
}
