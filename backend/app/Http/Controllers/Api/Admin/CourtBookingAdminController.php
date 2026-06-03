<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CourtBooking;
use App\Models\CourtBookingStatusHistory;
use App\Models\CourtBookingExtension;
use App\Models\CourtBookingService;
use App\Models\CourtService;
use App\Models\Court;
use App\Services\CourtBookingWorkflowService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CourtBookingAdminController extends Controller
{
    public function __construct(private CourtBookingWorkflowService $workflowService)
    {
    }

    /**
     * Danh sách booking + filter (date, court_id, status, search)
     */
    public function index(Request $request)
    {
        $query = CourtBooking::with(['user', 'court', 'services.service', 'payments', 'extensions'])
            ->orderBy('booking_date', 'desc')
            ->orderBy('start_time', 'desc');

        // Filter by date
        if ($request->filled('date')) {
            $query->whereDate('booking_date', $request->date);
        }

        // Filter by court
        if ($request->filled('court_id')) {
            $query->where('court_id', $request->court_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->whereDate('booking_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('booking_date', '<=', $request->to_date);
        }

        // Search by booking_code or user name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('booking_code', 'like', "%$search%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('full_name', 'like', "%$search%")
                         ->orWhere('phone', 'like', "%$search%");
                  });
            });
        }

        $bookings = $query->paginate($request->per_page ?? 20);

        return response()->json([
            'status' => 'success',
            'data' => $bookings
        ]);
    }

    /**
     * Tạo booking cho khách vãng lai (POS / walk-in)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'court_id' => 'required|exists:courts,court_id',
            'user_id' => 'nullable|exists:users,user_id',
            'booking_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'payment_method' => 'nullable|in:cash,vnpay,momo,bank_transfer,pos_card,pos_transfer',
            'note' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $startTime = $validated['start_time'] . ':00';
            $endTime = $validated['end_time'] . ':00';

            // Check overlap
            $conflict = CourtBooking::where('court_id', $validated['court_id'])
                ->where('booking_date', $validated['booking_date'])
                ->whereIn('status', CourtBooking::BLOCKING_STATUSES)
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->lockForUpdate()
                ->exists();

            if ($conflict) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sân đã được đặt trong khung giờ này.'
                ], 409);
            }

            $lockConflict = DB::table('court_booking_locks')
                ->where('court_id', $validated['court_id'])
                ->where('booking_date', $validated['booking_date'])
                ->where('expires_at', '>', now())
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->lockForUpdate()
                ->exists();

            if ($lockConflict) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Khung giờ này đang được khách giữ chỗ tạm thời.'
                ], 409);
            }

            $maintenanceConflict = DB::table('court_maintenances')
                ->where('court_id', $validated['court_id'])
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->where('start_datetime', '<', "{$validated['booking_date']} $endTime")
                ->where('end_datetime', '>', "{$validated['booking_date']} $startTime")
                ->exists();

            if ($maintenanceConflict) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sân đang bảo trì trong khung giờ này.'
                ], 409);
            }

            // Calculate duration
            $durationMinutes = Carbon::parse($startTime)->diffInMinutes(Carbon::parse($endTime));
            $dayOfWeek = Carbon::parse($validated['booking_date'])->dayOfWeek;
            $dayType = in_array($dayOfWeek, [0, 6]) ? 'weekend' : 'weekday';
            $courtPrice = \App\Models\CourtPrice::where('court_id', $validated['court_id'])
                ->where('is_active', true)
                ->where(function ($q) use ($dayType) {
                    $q->where('day_type', $dayType)->orWhere('day_type', 'all');
                })
                ->where('from_time', '<=', $startTime)
                ->where('to_time', '>=', $endTime)
                ->first();
            $pricePerHour = $courtPrice ? $courtPrice->price_per_hour : 100000;
            $originalPrice = (int) round(($pricePerHour / 60) * $durationMinutes);

            // Generate booking code
            $bookingCode = 'BK-' . now()->format('Ymd') . '-' . str_pad(
                CourtBooking::whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT
            );

            $booking = CourtBooking::create([
                'booking_code' => $bookingCode,
                'court_id' => $validated['court_id'],
                'user_id' => $validated['user_id'] ?? null,
                'staff_id' => auth()->guard('admin')->id(),
                'booking_date' => $validated['booking_date'],
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration_minutes' => $durationMinutes,
                'status' => 'confirmed',
                'original_price' => $originalPrice,
                'total_amount' => $originalPrice,
                'payment_method' => $validated['payment_method'] ?? 'cash',
                'payment_status' => 'unpaid',
                'confirmed_at' => now(),
                'source' => 'pos',
                'note' => $validated['note'] ?? null,
            ]);

            // Log status history
            CourtBookingStatusHistory::create([
                'booking_id' => $booking->booking_id,
                'old_status' => null,
                'new_status' => 'confirmed',
                'note' => 'Đặt sân tại quầy (POS)',
                'actor_type' => 'admin',
                'actor_id' => auth()->guard('admin')->id(),
            ]);

            $this->workflowService->logActivity('booking.pos.created', $booking, null, $booking->toArray(), 'admin', auth()->guard('admin')->id(), $request);
            $this->workflowService->broadcast('CourtBookingCreated', $booking, ['source' => 'pos']);

            return response()->json([
                'status' => 'success',
                'message' => 'Đặt sân thành công.',
                'data' => $booking->load(['user', 'court'])
            ], 201);
        });
    }

    /**
     * Chi tiết booking (full relations)
     */
    public function show($id)
    {
        $booking = CourtBooking::with([
            'user', 'court', 'staff',
            'services.service', 'payments.processedBy',
            'statusHistories', 'extensions'
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $booking
        ]);
    }

    /**
     * Xác nhận booking (pending → confirmed)
     */
    public function confirm(Request $request, $id)
    {
        $booking = CourtBooking::findOrFail($id);

        if ($booking->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Chỉ có thể xác nhận booking ở trạng thái "Chờ duyệt".'
            ], 400);
        }

        $oldStatus = $booking->status;
        $booking->status = 'confirmed';
        $booking->confirmed_at = now();
        $booking->save();
        $this->workflowService->logActivity('booking.confirmed', $booking, ['status' => $oldStatus], ['status' => 'confirmed'], 'admin', auth()->guard('admin')->id(), $request);
        $this->workflowService->broadcast('CourtBookingStatusChanged', $booking, ['old_status' => $oldStatus, 'new_status' => 'confirmed']);

        CourtBookingStatusHistory::create([
            'booking_id' => $booking->booking_id,
            'old_status' => $oldStatus,
            'new_status' => 'confirmed',
            'note' => $request->note ?? 'Admin xác nhận booking',
            'actor_type' => 'admin',
            'actor_id' => auth()->guard('admin')->id(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Xác nhận booking thành công.',
            'data' => $booking
        ]);
    }

    /**
     * Check-in (confirmed/pending → checked_in)
     */
    public function checkIn(Request $request, $id)
    {
        $booking = CourtBooking::findOrFail($id);

        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking phải ở trạng thái "Chờ duyệt" hoặc "Đã xác nhận" để check-in.'
            ], 400);
        }

        try {
            $this->workflowService->assertCheckInWindow($booking);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

        $oldStatus = $booking->status;
        $booking->status = 'checked_in';
        $booking->checked_in_at = now();
        $booking->save();
        $this->workflowService->logActivity('booking.checked_in', $booking, ['status' => $oldStatus], ['status' => 'checked_in'], 'admin', auth()->guard('admin')->id(), $request);
        $this->workflowService->broadcast('CourtBookingStatusChanged', $booking, ['old_status' => $oldStatus, 'new_status' => 'checked_in']);

        CourtBookingStatusHistory::create([
            'booking_id' => $booking->booking_id,
            'old_status' => $oldStatus,
            'new_status' => 'checked_in',
            'note' => 'Check-in nhận sân',
            'actor_type' => 'admin',
            'actor_id' => auth()->guard('admin')->id(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Check-in thành công.',
            'data' => $booking
        ]);
    }

    /**
     * Check-out (checked_in/playing/extended → completed)
     */
    public function checkOut(Request $request, $id)
    {
        $booking = CourtBooking::with(['services', 'extensions'])->findOrFail($id);

        if (!in_array($booking->status, ['checked_in', 'playing', 'extended'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking phải ở trạng thái "Đang chơi" để check-out.'
            ], 400);
        }

        $oldStatus = $booking->status;
        $booking->status = 'completed';
        $booking->checked_out_at = now();
        $booking->payment_status = 'paid';
        $booking->paid_amount = $booking->total_amount;
        $booking->save();
        $this->workflowService->logActivity('booking.completed', $booking, ['status' => $oldStatus], ['status' => 'completed'], 'admin', auth()->guard('admin')->id(), $request);
        $this->workflowService->broadcast('CourtBookingStatusChanged', $booking, ['old_status' => $oldStatus, 'new_status' => 'completed']);

        CourtBookingStatusHistory::create([
            'booking_id' => $booking->booking_id,
            'old_status' => $oldStatus,
            'new_status' => 'completed',
            'note' => 'Check-out trả sân & thanh toán',
            'actor_type' => 'admin',
            'actor_id' => auth()->guard('admin')->id(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Check-out thành công.',
            'data' => $booking
        ]);
    }

    /**
     * Thêm dịch vụ vào booking
     */
    public function addService(Request $request, $id)
    {
        $booking = CourtBooking::findOrFail($id);

        $validated = $request->validate([
            'service_id' => 'required|exists:court_services,service_id',
            'quantity' => 'required|integer|min:1',
            'note' => 'nullable|string'
        ]);

        $service = CourtService::findOrFail($validated['service_id']);
        $subtotal = $service->unit_price * $validated['quantity'];

        $bookingService = CourtBookingService::create([
            'booking_id' => $booking->booking_id,
            'service_id' => $service->service_id,
            'quantity' => $validated['quantity'],
            'unit_price' => $service->unit_price,
            'subtotal' => $subtotal,
            'note' => $validated['note'] ?? null,
            'added_by' => auth()->guard('admin')->id(),
        ]);

        $booking->service_amount += $subtotal;
        $booking->total_amount += $subtotal;
        $booking->save();
        $this->workflowService->logActivity('booking.service.added', $booking, null, $bookingService->toArray(), 'admin', auth()->guard('admin')->id(), $request);
        $this->workflowService->broadcast('CourtBookingServiceAdded', $booking, ['service_amount' => $booking->service_amount, 'total_amount' => $booking->total_amount]);

        return response()->json([
            'status' => 'success',
            'message' => 'Thêm dịch vụ thành công.',
            'data' => $bookingService->load('service')
        ]);
    }

    /**
     * Gia hạn giờ chơi
     */
    public function extend(Request $request, $id)
    {
        $booking = CourtBooking::findOrFail($id);

        $validated = $request->validate([
            'extension_minutes' => 'required|integer|min:15',
        ]);

        $newEndTime = Carbon::parse($booking->end_time)
            ->addMinutes($validated['extension_minutes'])
            ->format('H:i:s');

        // Check conflict
        $conflict = DB::table('court_bookings')
            ->where('court_id', $booking->court_id)
            ->where('booking_date', $booking->booking_date)
            ->where('booking_id', '!=', $booking->booking_id)
            ->whereIn('status', CourtBooking::BLOCKING_STATUSES)
            ->where('start_time', '<', $newEndTime)
            ->where('end_time', '>', $booking->end_time)
            ->exists();

        if ($conflict) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể gia hạn. Sân đã được đặt cho khung giờ tiếp theo.'
            ], 400);
        }

        $lockConflict = DB::table('court_booking_locks')
            ->where('court_id', $booking->court_id)
            ->where('booking_date', $booking->booking_date)
            ->where('expires_at', '>', now())
            ->where('start_time', '<', $newEndTime)
            ->where('end_time', '>', $booking->end_time)
            ->exists();

        if ($lockConflict) {
            return response()->json([
                'status' => 'error',
                'message' => 'Khong the gia han vi khung gio tiep theo dang duoc giu cho.'
            ], 400);
        }

        $maintenanceConflict = DB::table('court_maintenances')
            ->where('court_id', $booking->court_id)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->where('start_datetime', '<', "{$booking->booking_date->format('Y-m-d')} $newEndTime")
            ->where('end_datetime', '>', "{$booking->booking_date->format('Y-m-d')} {$booking->end_time}")
            ->exists();

        if ($maintenanceConflict) {
            return response()->json([
                'status' => 'error',
                'message' => 'Khong the gia han vi san co lich bao tri.'
            ], 400);
        }

        $extraAmount = (100000 / 60) * $validated['extension_minutes'];

        $extension = CourtBookingExtension::create([
            'booking_id' => $booking->booking_id,
            'original_end_time' => $booking->end_time,
            'extended_end_time' => $newEndTime,
            'extension_minutes' => $validated['extension_minutes'],
            'extra_amount' => $extraAmount,
            'approved_by' => auth()->guard('admin')->id(),
        ]);

        $oldStatus = $booking->status;
        $booking->end_time = $newEndTime;
        $booking->duration_minutes += $validated['extension_minutes'];
        $booking->total_amount += $extraAmount;
        $booking->status = 'extended';
        $booking->save();
        $this->workflowService->logActivity('booking.extended', $booking, ['status' => $oldStatus], [
            'status' => 'extended',
            'end_time' => $newEndTime,
            'extension_minutes' => $validated['extension_minutes'],
            'extra_amount' => $extraAmount,
        ], 'admin', auth()->guard('admin')->id(), $request);
        $this->workflowService->broadcast('CourtBookingStatusChanged', $booking, ['old_status' => $oldStatus, 'new_status' => 'extended']);

        CourtBookingStatusHistory::create([
            'booking_id' => $booking->booking_id,
            'old_status' => $oldStatus,
            'new_status' => 'extended',
            'note' => "Gia hạn thêm {$validated['extension_minutes']} phút",
            'actor_type' => 'admin',
            'actor_id' => auth()->guard('admin')->id(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Gia hạn thành công.',
            'data' => $extension
        ]);
    }

    /**
     * Cập nhật booking
     */
    public function update(Request $request, $id)
    {
        $booking = CourtBooking::findOrFail($id);
        $booking->update($request->only(['note', 'payment_method', 'payment_status']));

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật thành công.',
            'data' => $booking
        ]);
    }

    /**
     * Xóa booking (soft delete)
     */
    public function destroy($id)
    {
        $booking = CourtBooking::findOrFail($id);
        $booking->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xóa booking.'
        ]);
    }

    /**
     * Dashboard Lễ Tân — Trạng thái realtime 7 sân
     */
    public function cancel(Request $request, $id)
    {
        $booking = CourtBooking::whereIn('status', ['pending', 'confirmed'])->findOrFail($id);
        $validated = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        try {
            $booking = $this->workflowService->transition(
                $booking,
                'cancelled',
                'admin',
                auth()->guard('admin')->id(),
                $validated['reason'] ?? 'Admin cancelled booking',
                [
                    'cancel_reason_type' => 'other',
                    'cancel_reason' => $validated['reason'] ?? 'Admin cancelled booking',
                    'cancelled_at' => now(),
                ],
                $request
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Booking cancelled successfully.',
            'data' => $booking,
        ]);
    }

    public function recordPayment(Request $request, $id)
    {
        $booking = CourtBooking::findOrFail($id);
        $validated = $request->validate([
            'payment_method' => 'required|in:cash,vnpay,momo,bank_transfer,pos_card,pos_transfer',
            'payment_type' => 'nullable|in:deposit,full,additional',
            'amount' => 'nullable|integer|min:1000',
            'status' => 'nullable|in:pending,success,failed',
            'transaction_code' => 'nullable|string|max:120',
            'note' => 'nullable|string|max:255',
        ]);

        $payment = $this->workflowService->recordPayment($booking, $validated, 'admin', auth()->guard('admin')->id(), $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Payment recorded successfully.',
            'data' => $payment,
        ], 201);
    }

    public function qrCheckIn(Request $request, $id)
    {
        $booking = CourtBooking::findOrFail($id);
        $validated = $request->validate([
            'qr_token' => 'required|string',
        ]);

        try {
            $this->workflowService->assertValidQrToken($booking, $validated['qr_token']);
            $this->workflowService->assertCheckInWindow($booking);
            $booking = $this->workflowService->transition(
                $booking,
                'checked_in',
                'admin',
                auth()->guard('admin')->id(),
                'QR check-in',
                ['checked_in_at' => now()],
                $request
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'QR check-in successful.',
            'data' => $booking,
        ]);
    }

    public function calendar(Request $request)
    {
        $mode = $request->query('mode', 'day');
        $date = Carbon::parse($request->query('date', today()->toDateString()));
        [$fromDate, $toDate] = match ($mode) {
            'week' => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()],
            'month' => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
            default => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
        };

        $bookings = CourtBooking::with(['court:court_id,court_name,court_code', 'user:user_id,full_name,phone'])
            ->whereBetween('booking_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->when($request->filled('court_id'), fn ($query) => $query->where('court_id', $request->court_id))
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'mode' => $mode,
                'from_date' => $fromDate->toDateString(),
                'to_date' => $toDate->toDateString(),
                'items' => $bookings,
                'by_date' => $bookings->groupBy(fn ($booking) => $booking->booking_date->format('Y-m-d')),
            ],
        ]);
    }

    public function dashboard(Request $request)
    {
        $date = $request->date ?? today()->toDateString();
        $now = now()->format('H:i:s');

        $courts = Court::with(['schedules'])->orderBy('sort_order')->get();

        $courtsData = $courts->map(function ($court) use ($date, $now) {
            // Current booking (đang chơi ngay bây giờ)
            $currentBooking = CourtBooking::with(['user'])
                ->where('court_id', $court->court_id)
                ->where('booking_date', $date)
                ->whereIn('status', ['checked_in', 'playing', 'extended'])
                ->where('start_time', '<=', $now)
                ->where('end_time', '>', $now)
                ->first();

            // Next booking
            $nextBooking = CourtBooking::with(['user'])
                ->where('court_id', $court->court_id)
                ->where('booking_date', $date)
                ->whereIn('status', ['pending', 'confirmed'])
                ->where('start_time', '>', $now)
                ->orderBy('start_time')
                ->first();

            // All bookings today
            $todayBookings = CourtBooking::where('court_id', $court->court_id)
                ->where('booking_date', $date)
                ->whereIn('status', CourtBooking::BLOCKING_STATUSES)
                ->orderBy('start_time')
                ->count();

            // Check maintenance
            $hasMaintenance = DB::table('court_maintenances')
                ->where('court_id', $court->court_id)
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->where('start_datetime', '<=', "$date 23:59:59")
                ->where('end_datetime', '>=', "$date 00:00:00")
                ->exists();

            // Determine real-time status
            $realtimeStatus = $court->status;
            if ($hasMaintenance) {
                $realtimeStatus = 'maintenance';
            } elseif ($currentBooking) {
                $realtimeStatus = 'playing';
            } elseif ($court->status === 'active') {
                $realtimeStatus = 'available';
            }

            return [
                'court_id' => $court->court_id,
                'court_name' => $court->court_name,
                'court_code' => $court->court_code,
                'type' => $court->type,
                'status' => $court->status,
                'realtime_status' => $realtimeStatus,
                'current_booking' => $currentBooking ? [
                    'booking_id' => $currentBooking->booking_id,
                    'booking_code' => $currentBooking->booking_code,
                    'user_name' => $currentBooking->user->full_name ?? 'Khách vãng lai',
                    'start_time' => $currentBooking->start_time,
                    'end_time' => $currentBooking->end_time,
                    'checked_in_at' => $currentBooking->checked_in_at,
                    'status' => $currentBooking->status,
                    'total_amount' => $currentBooking->total_amount,
                ] : null,
                'next_booking' => $nextBooking ? [
                    'booking_id' => $nextBooking->booking_id,
                    'user_name' => $nextBooking->user->full_name ?? 'Khách vãng lai',
                    'start_time' => $nextBooking->start_time,
                    'end_time' => $nextBooking->end_time,
                    'status' => $nextBooking->status,
                ] : null,
                'today_booking_count' => $todayBookings,
                'has_maintenance' => $hasMaintenance,
            ];
        });

        // Summary stats
        $todayStats = [
            'total_bookings' => CourtBooking::whereDate('booking_date', $date)->count(),
            'playing_now' => CourtBooking::whereDate('booking_date', $date)
                ->whereIn('status', ['checked_in', 'playing', 'extended'])
                ->where('start_time', '<=', $now)
                ->where('end_time', '>', $now)
                ->count(),
            'pending' => CourtBooking::whereDate('booking_date', $date)->where('status', 'pending')->count(),
            'completed' => CourtBooking::whereDate('booking_date', $date)->where('status', 'completed')->count(),
            'revenue_today' => CourtBooking::whereDate('booking_date', $date)
                ->where('status', 'completed')
                ->sum('total_amount'),
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'courts' => $courtsData,
                'stats' => $todayStats,
                'server_time' => now()->format('H:i:s'),
                'date' => $date,
            ]
        ]);
    }

    /**
     * Thống kê doanh thu & hiệu suất
     */
    public function stats(Request $request)
    {
        $period = $request->period ?? 'month'; // day, week, month
        $fromDate = match ($period) {
            'day' => today(),
            'week' => today()->startOfWeek(),
            'month' => today()->startOfMonth(),
            default => today()->startOfMonth(),
        };
        $toDate = today();

        // Revenue by court
        $revenueByCourt = CourtBooking::select('court_id', DB::raw('SUM(total_amount) as revenue'), DB::raw('COUNT(*) as booking_count'))
            ->whereBetween('booking_date', [$fromDate, $toDate])
            ->where('status', 'completed')
            ->groupBy('court_id')
            ->with('court:court_id,court_name,court_code')
            ->get();

        // Revenue by day (for chart)
        $revenueByDay = CourtBooking::select(
                DB::raw('DATE(booking_date) as date'),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as count')
            )
            ->whereBetween('booking_date', [$fromDate, $toDate])
            ->where('status', 'completed')
            ->groupBy(DB::raw('DATE(booking_date)'))
            ->orderBy('date')
            ->get();

        // Top services
        $topServices = CourtBookingService::select(
                'service_id',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(subtotal) as total_revenue')
            )
            ->whereHas('booking', function ($q) use ($fromDate, $toDate) {
                $q->whereBetween('booking_date', [$fromDate, $toDate]);
            })
            ->groupBy('service_id')
            ->orderByDesc('total_revenue')
            ->with('service:service_id,service_name,unit_price')
            ->limit(10)
            ->get();

        // Court utilization (% hours booked vs total hours open)
        $courts = Court::where('status', 'active')->get();
        $daysInPeriod = $fromDate->diffInDays($toDate) + 1;
        $hoursPerDay = 17; // 05:00 - 22:00

        $utilization = $courts->map(function ($court) use ($fromDate, $toDate, $daysInPeriod, $hoursPerDay) {
            $totalBookedMinutes = CourtBooking::where('court_id', $court->court_id)
                ->whereBetween('booking_date', [$fromDate, $toDate])
                ->whereIn('status', ['completed', 'checked_in', 'playing', 'extended'])
                ->sum('duration_minutes');

            $totalAvailableMinutes = $daysInPeriod * $hoursPerDay * 60;
            $rate = $totalAvailableMinutes > 0 ? round(($totalBookedMinutes / $totalAvailableMinutes) * 100, 1) : 0;

            return [
                'court_id' => $court->court_id,
                'court_name' => $court->court_name,
                'booked_hours' => round($totalBookedMinutes / 60, 1),
                'total_hours' => $daysInPeriod * $hoursPerDay,
                'utilization_rate' => $rate,
            ];
        });

        // Overall KPIs
        $totalRevenue = CourtBooking::whereBetween('booking_date', [$fromDate, $toDate])
            ->where('status', 'completed')->sum('total_amount');
        $totalBookings = CourtBooking::whereBetween('booking_date', [$fromDate, $toDate])->count();
        $completedBookings = CourtBooking::whereBetween('booking_date', [$fromDate, $toDate])
            ->where('status', 'completed')->count();
        $cancelledBookings = CourtBooking::whereBetween('booking_date', [$fromDate, $toDate])
            ->where('status', 'cancelled')->count();
        $noShowBookings = CourtBooking::whereBetween('booking_date', [$fromDate, $toDate])
            ->where('status', 'no_show')->count();
        $serviceRevenue = CourtBookingService::whereHas('booking', function ($q) use ($fromDate, $toDate) {
            $q->whereBetween('booking_date', [$fromDate, $toDate]);
        })->sum('subtotal');

        return response()->json([
            'status' => 'success',
            'data' => [
                'period' => $period,
                'from_date' => $fromDate->toDateString(),
                'to_date' => $toDate->toDateString(),
                'kpis' => [
                    'total_revenue' => $totalRevenue,
                    'total_bookings' => $totalBookings,
                    'completed_bookings' => $completedBookings,
                    'cancelled_bookings' => $cancelledBookings,
                    'no_show_bookings' => $noShowBookings,
                    'service_revenue' => $serviceRevenue,
                    'completion_rate' => $totalBookings > 0 ? round(($completedBookings / $totalBookings) * 100, 1) : 0,
                    'no_show_rate' => $totalBookings > 0 ? round(($noShowBookings / $totalBookings) * 100, 1) : 0,
                ],
                'revenue_by_court' => $revenueByCourt,
                'revenue_by_day' => $revenueByDay,
                'top_services' => $topServices,
                'utilization' => $utilization,
            ]
        ]);
    }
}
