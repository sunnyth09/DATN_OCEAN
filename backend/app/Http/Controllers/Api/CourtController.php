<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Court;
use App\Models\CourtSchedule;
use App\Models\CourtPrice;
use App\Models\CourtBooking;
use App\Models\CourtBookingLock;
use App\Models\CourtMaintenance;
use App\Models\CourtService;
use Carbon\Carbon;

class CourtController extends Controller
{
    /**
     * Get list of courts (public)
     */
    public function index(Request $request)
    {
        $query = Court::active();
        
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $courts = $query->orderBy('sort_order')->get();
        return response()->json([
            'status' => 'success',
            'data' => $courts
        ]);
    }

    /**
     * Get single court with schedules & prices
     */
    public function show($id)
    {
        $court = Court::with(['schedules', 'prices'])->findOrFail($id);
        return response()->json([
            'status' => 'success',
            'data' => $court
        ]);
    }

    /**
     * Generate hourly time slots with availability status for a given date.
     * Returns an array of slots: [ { start_time, end_time, price, status } ]
     */
    public function availability(Request $request, $id)
    {
        $date = $request->query('date', now()->format('Y-m-d'));
        $court = Court::findOrFail($id);

        // 1. Determine open/close from court_schedules for this day_of_week
        $dayOfWeek = Carbon::parse($date)->dayOfWeek; // 0=Sun..6=Sat
        $schedule = CourtSchedule::where('court_id', $id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();

        // Fallback: default 06:00 - 22:00 if no schedule configured
        $openTime = $schedule ? $schedule->open_time : '06:00:00';
        $closeTime = $schedule ? $schedule->close_time : '22:00:00';

        // 2. Get all blocking bookings for this court on this date
        $bookings = CourtBooking::where('court_id', $id)
            ->where('booking_date', $date)
            ->whereIn('status', CourtBooking::BLOCKING_STATUSES)
            ->get(['booking_id', 'start_time', 'end_time', 'status']);

        // 3. Get active locks (not expired)
        $locks = CourtBookingLock::where('court_id', $id)
            ->where('booking_date', $date)
            ->where('expires_at', '>', now())
            ->get(['lock_id', 'start_time', 'end_time', 'expires_at', 'user_id', 'lock_token']);

        // 4. Get maintenance windows
        $maintenances = CourtMaintenance::where('court_id', $id)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->where('start_datetime', '<', "$date $closeTime")
            ->where('end_datetime', '>', "$date $openTime")
            ->get(['start_datetime', 'end_datetime']);

        // 5. Get pricing rules
        $isWeekend = ($dayOfWeek == 0 || $dayOfWeek == 6);
        $dayType = $isWeekend ? 'weekend' : 'weekday';
        $prices = CourtPrice::where('court_id', $id)
            ->where('is_active', true)
            ->where(function ($q) use ($dayType) {
                $q->where('day_type', $dayType)->orWhere('day_type', 'all');
            })
            ->get();

        // 6. Generate hourly slots
        $slots = [];
        $current = Carbon::parse("$date $openTime");
        $close = Carbon::parse("$date $closeTime");
        $now = Carbon::now();

        while ($current->lt($close)) {
            $slotStart = $current->format('H:i:s');
            $slotEnd = $current->copy()->addMinutes(30)->format('H:i:s');

            // Determine status
            $status = 'available';
            $bookingId = null;
            $lockExpiresAt = null;
            $isMyLock = false;
            $myLockToken = null;

            if ($court->status !== 'active') {
                $status = $court->status === 'maintenance' ? 'maintenance' : 'closed';
            }

            // Past time check (only for today)
            if ($status === 'available' && Carbon::parse($date)->isToday() && $current->lt($now)) {
                $status = 'past';
            }

            // Check booking overlap
            if ($status === 'available') {
                foreach ($bookings as $booking) {
                    if ($slotStart < $booking->end_time && $slotEnd > $booking->start_time) {
                        $status = 'booked';
                        $bookingId = $booking->booking_id;
                        break;
                    }
                }
            }

            // Check lock overlap
            if ($status === 'available') {
                foreach ($locks as $lock) {
                    if ($slotStart < $lock->end_time && $slotEnd > $lock->start_time) {
                        $status = 'locked';
                        $lockExpiresAt = $lock->expires_at;
                        if (auth('api')->check() && $lock->user_id === auth('api')->id()) {
                            $isMyLock = true;
                            $myLockToken = $lock->lock_token;
                        }
                        break;
                    }
                }
            }

            // Check maintenance overlap
            if ($status === 'available') {
                foreach ($maintenances as $m) {
                    $mStart = Carbon::parse($m->start_datetime)->format('H:i:s');
                    $mEnd = Carbon::parse($m->end_datetime)->format('H:i:s');
                    if ($slotStart < $mEnd && $slotEnd > $mStart) {
                        $status = 'maintenance';
                        break;
                    }
                }
            }

            // Find matching price
            $price = 0;
            foreach ($prices as $p) {
                if ($slotStart >= $p->from_time && $slotEnd <= $p->to_time) {
                    $price = $p->price_per_hour / 2;
                    break;
                }
            }
            // Fallback price
            if ($price == 0 && $prices->isNotEmpty()) {
                $price = $prices->first()->price_per_hour / 2;
            }

            $slots[] = [
                'start_time' => $slotStart,
                'end_time' => $slotEnd,
                'price' => $price,
                'status' => $status,
                'booking_id' => $bookingId,
                'lock_expires_at' => $lockExpiresAt,
                'is_my_lock' => $isMyLock,
                'lock_token' => $myLockToken,
            ];

            $current->addMinutes(30);
        }

        return response()->json([
            'status' => 'success',
            'data' => $slots,
            'meta' => [
                'date' => $date,
                'day_of_week' => $dayOfWeek,
                'open_time' => $openTime,
                'close_time' => $closeTime,
                'has_schedule' => (bool) $schedule,
            ]
        ]);
    }

    /**
     * Public: get active court services (no admin auth needed)
     */
    public function publicServices()
    {
        $services = CourtService::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $services
        ]);
    }
}
