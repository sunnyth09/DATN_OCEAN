<?php

namespace App\Services;

use App\Events\CourtBookingRealtimeEvent;
use App\Mail\CourtBookingCancelledMail;
use App\Models\CourtActivityLog;
use App\Models\CourtBooking;
use App\Models\CourtBookingPayment;
use App\Models\CourtBookingStatusHistory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CourtBookingWorkflowService
{
    private const ALLOWED_TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled', 'no_show', 'expired'],
        'confirmed' => ['checked_in', 'cancelled', 'no_show'],
        'checked_in' => ['playing', 'extended', 'completed'],
        'playing' => ['extended', 'completed'],
        'extended' => ['extended', 'completed'],
        'completed' => [],
        'cancelled' => [],
        'no_show' => [],
        'expired' => [],
    ];

    public function transition(
        CourtBooking $booking,
        string $newStatus,
        string $actorType,
        ?int $actorId = null,
        ?string $note = null,
        array $updates = [],
        ?Request $request = null
    ): CourtBooking {
        return DB::transaction(function () use ($booking, $newStatus, $actorType, $actorId, $note, $updates, $request) {
            $booking = CourtBooking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $oldStatus = $booking->status;

            if (!in_array($newStatus, self::ALLOWED_TRANSITIONS[$oldStatus] ?? [], true)) {
                throw new InvalidArgumentException("Invalid booking status transition: {$oldStatus} -> {$newStatus}");
            }

            $oldData = $booking->only(['status', 'payment_status', 'paid_amount', 'cancel_reason']);
            $booking->fill($updates);
            $booking->status = $newStatus;
            $booking->save();

            CourtBookingStatusHistory::create([
                'booking_id' => $booking->booking_id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'note' => $note,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
            ]);

            $this->logActivity(
                "booking.{$newStatus}",
                $booking,
                $oldData,
                $booking->only(['status', 'payment_status', 'paid_amount', 'cancel_reason']),
                $actorType,
                $actorId,
                $request
            );

            $eventName = $newStatus === 'cancelled' ? 'CourtBookingCancelled' : 'CourtBookingStatusChanged';
            $this->broadcast($eventName, $booking, ['old_status' => $oldStatus, 'new_status' => $newStatus]);

            return $booking;
        });
    }

    public function cancelByUser(CourtBooking $booking, ?string $reason, Request $request): CourtBooking
    {
        $startAt = Carbon::parse($booking->booking_date->format('Y-m-d') . ' ' . $booking->start_time);
        $minutesBeforeStart = now()->diffInMinutes($startAt, false);
        $paidAmount = (int) $booking->paid_amount;
        $refundAmount = 0;
        $feeAmount = 0;

        if ($paidAmount > 0) {
            $feeAmount = $minutesBeforeStart >= 120 ? 0 : (int) round($booking->total_amount * 0.2);
            $refundAmount = max(0, $paidAmount - $feeAmount);
        }

        $updates = [
            'cancel_reason_type' => 'customer_request',
            'cancel_reason' => $reason ?: 'User cancelled',
            'cancelled_at' => now(),
        ];

        if ($paidAmount > 0) {
            $updates['payment_status'] = $refundAmount >= $paidAmount ? 'refunded' : 'partially_refunded';
        }

        $booking = $this->transition(
            $booking,
            'cancelled',
            'user',
            auth()->guard('api')->id(),
            $updates['cancel_reason'],
            $updates,
            $request
        );

        if ($refundAmount > 0) {
            CourtBookingPayment::create([
                'booking_id'       => $booking->booking_id,
                'payment_type'     => 'refund',
                'payment_method'   => $booking->payment_method ?: 'cash',
                'transaction_code' => 'RF-' . $booking->booking_code . '-' . Str::upper(Str::random(4)),
                'amount'           => $refundAmount,
                'status'           => 'pending',
                'note'             => "Refund pending. Cancellation fee: {$feeAmount}",
            ]);
        }

        // Gửi email thông báo hủy cho khách hàng
        $booking->loadMissing(['user', 'court']);
        if ($booking->user?->email) {
            try {
                Mail::to($booking->user->email)->queue(
                    new CourtBookingCancelledMail($booking, $refundAmount > 0 ? $refundAmount : null, 'user')
                );
            } catch (\Exception $e) {
                Log::warning('Failed to queue booking cancelled mail (user)', [
                    'booking_id' => $booking->booking_id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $booking;
    }

    public function recordPayment(
        CourtBooking $booking,
        array $data,
        string $actorType,
        ?int $actorId,
        ?Request $request = null
    ): CourtBookingPayment {
        return DB::transaction(function () use ($booking, $data, $actorType, $actorId, $request) {
            $booking = CourtBooking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $amount = (int) ($data['amount'] ?? max(0, $booking->total_amount - $booking->paid_amount));
            $status = $actorType === 'admin' && in_array($data['payment_method'], ['cash', 'bank_transfer', 'pos_card', 'pos_transfer'], true)
                ? 'success'
                : 'pending';

            $payment = CourtBookingPayment::create([
                'booking_id' => $booking->booking_id,
                'payment_type' => $data['payment_type'] ?? 'full',
                'payment_method' => $data['payment_method'],
                'transaction_code' => $data['transaction_code'] ?? 'CBP-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4)),
                'amount' => $amount,
                'status' => $data['status'] ?? $status,
                'paid_at' => ($data['status'] ?? $status) === 'success' ? now() : null,
                'gateway_response' => $data['gateway_response'] ?? null,
                'note' => $data['note'] ?? null,
                'processed_by' => $actorType === 'admin' ? $actorId : null,
            ]);

            if ($payment->status === 'success') {
                $booking->paid_amount = min($booking->total_amount, $booking->paid_amount + $amount);
                $booking->payment_status = $booking->paid_amount >= $booking->total_amount
                    ? 'paid'
                    : ($payment->payment_type === 'deposit' ? 'deposit_paid' : 'partially_paid');
                $booking->payment_method = $data['payment_method'];
                $booking->save();
            }

            $this->logActivity('booking.payment.recorded', $booking, null, $payment->toArray(), $actorType, $actorId, $request);
            $this->broadcast('CourtBookingPaymentUpdated', $booking, [
                'payment_status' => $booking->payment_status,
                'paid_amount' => $booking->paid_amount,
            ]);

            return $payment;
        });
    }

    public function qrToken(CourtBooking $booking): string
    {
        return hash_hmac('sha256', implode('|', [
            $booking->booking_id,
            $booking->booking_code,
            $booking->booking_date->format('Y-m-d'),
            $booking->start_time,
        ]), config('app.key'));
    }

    public function assertValidQrToken(CourtBooking $booking, string $token): void
    {
        if (!hash_equals($this->qrToken($booking), $token)) {
            throw new InvalidArgumentException('QR check-in token is invalid.');
        }
    }

    // check in window: từ 30 phút trước đến khi kết thúc thời gian đặt sân
    public function assertCheckInWindow(CourtBooking $booking): void
    {
        $startAt = Carbon::parse($booking->booking_date->format('Y-m-d') . ' ' . $booking->start_time);
        $endAt = Carbon::parse($booking->booking_date->format('Y-m-d') . ' ' . $booking->end_time);

        if (now()->lt($startAt->copy()->subMinutes(30)) || now()->gt($endAt)) {
            throw new InvalidArgumentException('Chỉ được check-in trong khoảng 30 phút trước khi bắt đầu đến khi kết thúc thời gian đặt sân.');
        }
    }

    public function broadcast(string $eventName, CourtBooking $booking, array $extra = []): void
    {
        CourtBookingRealtimeEvent::dispatch($eventName, array_merge([
            'booking_id'     => $booking->booking_id,
            'booking_code'   => $booking->booking_code,
            'court_id'       => $booking->court_id,
            'user_id'        => $booking->user_id,   // Để gửi đến user private channel
            'booking_date'   => $booking->booking_date->format('Y-m-d'),
            'start_time'     => $booking->start_time,
            'end_time'       => $booking->end_time,
            'status'         => $booking->status,
            'payment_status' => $booking->payment_status,
            'total_amount'   => $booking->total_amount,
        ], $extra));
    }

    public function logActivity(
        string $action,
        ?Model $subject,
        ?array $oldData,
        ?array $newData,
        string $actorType,
        ?int $actorId = null,
        ?Request $request = null
    ): void {
        CourtActivityLog::create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'old_data' => $oldData,
            'new_data' => $newData,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
