<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CourtBooking;
use App\Http\Requests\CourtBooking\StoreCourtBookingRequest;
use App\Http\Requests\CourtBooking\LockCourtBookingRequest;
use App\Services\CourtBookingService;
use App\Services\CourtBookingWorkflowService;

class CourtBookingController extends Controller
{
    protected $bookingService;
    protected $workflowService;

    public function __construct(CourtBookingService $bookingService, CourtBookingWorkflowService $workflowService)
    {
        $this->bookingService = $bookingService;
        $this->workflowService = $workflowService;
    }

    /**
     * Lock slot temporarily
     */
    public function lock(LockCourtBookingRequest $request)
    {
        try {
            $lock = $this->bookingService->lockSlot($request->validated());
            return response()->json([
                'status' => 'success',
                'message' => 'Slot locked successfully for 10 minutes.',
                'data' => $lock
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function releaseLock(Request $request)
    {
        $validated = $request->validate([
            'lock_token' => 'required|string|max:64',
        ]);

        $released = $this->bookingService->releaseLock($validated['lock_token'], auth()->guard('api')->id());

        return response()->json([
            'status' => 'success',
            'released' => $released,
        ]);
    }

    /**
     * Create booking
     */
    public function store(StoreCourtBookingRequest $request)
    {
        try {
            $booking = $this->bookingService->createBooking($request->validated());
            return response()->json([
                'status' => 'success',
                'message' => 'Booking created successfully.',
                'data' => $booking
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get user bookings
     */
    public function index(Request $request)
    {
        $bookings = CourtBooking::where('user_id', auth()->guard('api')->id())
            ->with(['court'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'status' => 'success',
            'data' => $bookings
        ]);
    }

    /**
     * Get booking details
     */
    public function show($id)
    {
        $booking = CourtBooking::where('user_id', auth()->guard('api')->id())
            ->with(['court', 'services', 'payments', 'statusHistories'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $booking
        ]);
    }

    /**
     * Cancel booking
     */
    public function cancel(Request $request, $id)
    {
        $booking = CourtBooking::where('user_id', auth()->guard('api')->id())
            ->whereIn('status', ['pending', 'confirmed'])
            ->findOrFail($id);

        $booking = $this->workflowService->cancelByUser($booking, $request->input('reason'), $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Booking cancelled successfully.',
            'data' => $booking
        ]);
    }

    public function pay(Request $request, $id)
    {
        $booking = CourtBooking::where('user_id', auth()->guard('api')->id())->findOrFail($id);
        $validated = $request->validate([
            'payment_method' => 'required|in:cash,vnpay,momo,bank_transfer',
            'payment_type' => 'nullable|in:deposit,full,additional',
            'amount' => 'nullable|integer|min:1000',
            'transaction_code' => 'nullable|string|max:120',
            'note' => 'nullable|string|max:255',
        ]);

        $payment = $this->workflowService->recordPayment($booking, $validated, 'user', auth()->guard('api')->id(), $request);

        return response()->json([
            'status' => 'success',
            'message' => $payment->status === 'success' ? 'Payment recorded successfully.' : 'Payment initialized.',
            'data' => $payment,
        ], 201);
    }

    public function qr(Request $request, $id)
    {
        $booking = CourtBooking::where('user_id', auth()->guard('api')->id())->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => [
                'booking_id' => $booking->booking_id,
                'booking_code' => $booking->booking_code,
                'qr_token' => $this->workflowService->qrToken($booking),
            ],
        ]);
    }
}
