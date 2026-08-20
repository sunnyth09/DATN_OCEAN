<?php

namespace App\Services;

use App\Events\CourtBookingRealtimeEvent;
use App\Events\UserNotificationEvent;
use App\Mail\CourtBookingCancelledMail;
use App\Models\Admin;
use App\Models\CourtActivityLog;
use App\Models\CourtBooking;
use App\Models\CourtBookingLock;
use App\Models\CourtBookingPayment;
use App\Models\CourtBookingStatusHistory;
use App\Models\User;
use App\Notifications\Admin\CourtBookingNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
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

            if (! in_array($newStatus, self::ALLOWED_TRANSITIONS[$oldStatus] ?? [], true)) {
                throw new InvalidArgumentException("Invalid booking status transition: {$oldStatus} -> {$newStatus}");
            }

            $oldData = $booking->only(['status', 'payment_status', 'paid_amount', 'cancel_reason']);
            $booking->fill($updates);
            $booking->status = $newStatus;
            $booking->save();

            // Nếu booking bị hủy, lập tức xóa bất kỳ lock nào cho sân & khung giờ này
            if ($newStatus === 'cancelled') {
                CourtBookingLock::where('court_id', $booking->court_id)
                    ->where('booking_date', $booking->booking_date->format('Y-m-d'))
                    ->where('start_time', '<', $booking->end_time)
                    ->where('end_time', '>', $booking->start_time)
                    ->delete();
            }

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

            // Side-effect realtime/notification chỉ chạy sau khi commit thành công.
            DB::afterCommit(function () use ($booking, $newStatus, $oldStatus, $note) {
                $eventName = $newStatus === 'cancelled' ? 'CourtBookingCancelled' : 'CourtBookingStatusChanged';
                $this->broadcast($eventName, $booking, ['old_status' => $oldStatus, 'new_status' => $newStatus]);

                // Phát thêm sự kiện CourtSlotReleased để cả Web và App lập tức mở lại slot cho người khác đặt
                if ($newStatus === 'cancelled') {
                    CourtBookingRealtimeEvent::dispatch('CourtSlotReleased', [
                        'court_id' => $booking->court_id,
                        'booking_date' => $booking->booking_date->format('Y-m-d'),
                        'start_time' => $booking->start_time,
                        'end_time' => $booking->end_time,
                    ]);
                    $this->notifyAdmins($booking, 'cancelled');
                }

                $this->notifyUser($booking, $eventName, [
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'note' => $note,
                ]);
            });

            return $booking;
        });
    }

    public function cancelByUser(CourtBooking $booking, ?string $reason, Request $request): CourtBooking
    {
        $startAt = Carbon::parse($booking->booking_date->format('Y-m-d').' '.$booking->start_time);
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

        // Gói việc đổi trạng thái + tạo bản ghi refund trong 1 transaction để tránh
        // tình trạng booking đã 'refunded' nhưng không có CourtBookingPayment tương ứng
        // (transition() tự mở transaction; ở đây transaction ngoài sẽ bao trọn nó).
        $booking = DB::transaction(function () use ($booking, $updates, $request, $refundAmount, $feeAmount) {
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
                    'booking_id' => $booking->booking_id,
                    'payment_type' => 'refund',
                    'payment_method' => $booking->payment_method ?: 'cash',
                    'transaction_code' => 'RF-'.$booking->booking_code.'-'.Str::upper(Str::random(4)),
                    'amount' => $refundAmount,
                    'status' => 'pending',
                    'note' => "Refund pending. Cancellation fee: {$feeAmount}",
                ]);
            }

            return $booking;
        });

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
                    'error' => $e->getMessage(),
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
            $status = $actorType === 'admin' && in_array($data['payment_method'], ['cash', 'bank_transfer', 'pos_card', 'pos_transfer', 'vnpay', 'momo'], true)
                ? 'success'
                : 'pending';

            $payment = CourtBookingPayment::create([
                'booking_id' => $booking->booking_id,
                'payment_type' => $data['payment_type'] ?? 'full',
                'payment_method' => $data['payment_method'],
                'transaction_code' => $data['transaction_code'] ?? 'CBP-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
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

            // Side-effect realtime/notification chỉ chạy sau khi commit thành công.
            DB::afterCommit(function () use ($booking, $payment) {
                $this->broadcast('CourtBookingPaymentUpdated', $booking, [
                    'payment_status' => $booking->payment_status,
                    'paid_amount' => $booking->paid_amount,
                ]);
                $this->notifyUser($booking, 'CourtBookingPaymentUpdated', [
                    'payment_status' => $booking->payment_status,
                    'paid_amount' => $booking->paid_amount,
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'payment_type' => $payment->payment_type,
                    'payment_record_status' => $payment->status,
                ]);
            });

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
        if (! hash_equals($this->qrToken($booking), $token)) {
            throw new InvalidArgumentException('QR check-in token is invalid.');
        }
    }

    // check in window: từ 30 phút trước đến khi kết thúc thời gian đặt sân
    public function assertCheckInWindow(CourtBooking $booking): void
    {
        $startAt = Carbon::parse($booking->booking_date->format('Y-m-d').' '.$booking->start_time);
        $endAt = Carbon::parse($booking->booking_date->format('Y-m-d').' '.$booking->end_time);

        if (now()->lt($startAt->copy()->subMinutes(30)) || now()->gt($endAt)) {
            throw new InvalidArgumentException('Chỉ được check-in trong khoảng 30 phút trước khi bắt đầu đến khi kết thúc thời gian đặt sân.');
        }
    }

    public function broadcast(string $eventName, CourtBooking $booking, array $extra = []): void
    {
        CourtBookingRealtimeEvent::dispatch($eventName, array_merge([
            'booking_id' => $booking->booking_id,
            'booking_code' => $booking->booking_code,
            'court_id' => $booking->court_id,
            'user_id' => $booking->user_id,   // Để gửi đến user private channel
            'booking_date' => $booking->booking_date->format('Y-m-d'),
            'start_time' => $booking->start_time,
            'end_time' => $booking->end_time,
            'status' => $booking->status,
            'payment_status' => $booking->payment_status,
            'total_amount' => $booking->total_amount,
        ], $extra));
    }

    public function notifyAdmins(CourtBooking $booking, string $eventType): void
    {
        try {
            $admins = Admin::all();
            Notification::send($admins, new CourtBookingNotification($booking, $eventType));
        } catch (\Throwable $e) {
            Log::warning('Failed to notify admins about court booking', [
                'booking_id' => $booking->booking_id,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function notifyUser(CourtBooking $booking, string $eventName, array $extra = []): void
    {
        if (! $booking->user_id) {
            return;
        }

        $booking->loadMissing('court');
        $statusLabels = [
            'pending' => 'chờ xác nhận',
            'confirmed' => 'đã xác nhận',
            'checked_in' => 'đã check-in',
            'playing' => 'đang chơi',
            'extended' => 'đã gia hạn',
            'completed' => 'đã hoàn thành',
            'cancelled' => 'đã hủy',
            'no_show' => 'vắng mặt',
            'expired' => 'đã hết hạn',
        ];

        $paymentLabels = [
            'unpaid' => 'chưa thanh toán',
            'deposit_paid' => 'đã cọc',
            'partially_paid' => 'thanh toán một phần',
            'paid' => 'đã thanh toán',
            'refunded' => 'đã hoàn tiền',
            'partially_refunded' => 'hoàn tiền một phần',
        ];

        $title = match ($eventName) {
            'CourtBookingCreated' => 'Đặt sân thành công',
            'CourtBookingCancelled' => 'Lịch đặt sân đã hủy',
            'CourtBookingPaymentUpdated' => 'Thanh toán đặt sân cập nhật',
            default => 'Lịch đặt sân cập nhật',
        };

        $courtName = $booking->court?->court_name ?? 'sân';
        $time = "{$booking->booking_date->format('d/m/Y')} {$booking->start_time}-{$booking->end_time}";

        $paymentStatus = $paymentLabels[$booking->payment_status] ?? $booking->payment_status;
        $bookingStatus = $statusLabels[$booking->status] ?? $booking->status;

        $message = match ($eventName) {
            'CourtBookingCreated' => "Booking {$booking->booking_code} tại {$courtName} lúc {$time} đang chờ xác nhận.",

            'CourtBookingCancelled' => "Booking {$booking->booking_code} tại {$courtName} đã được hủy.",

            'CourtBookingPaymentUpdated' => "Trạng thái thanh toán của booking {$booking->booking_code} đã được cập nhật thành {$paymentStatus}.",

            default => "Booking {$booking->booking_code} tại {$courtName} lúc {$time} đã được cập nhật sang trạng thái {$bookingStatus}.",
        };

        $notificationData = array_merge([
            'title' => $title,
            'message' => $message,
            'type' => 'court_booking',
            'event' => $eventName,
            'booking_id' => $booking->booking_id,
            'booking_code' => $booking->booking_code,
            'court_id' => $booking->court_id,
            'court_name' => $courtName,
            'booking_date' => $booking->booking_date->format('Y-m-d'),
            'start_time' => $booking->start_time,
            'end_time' => $booking->end_time,
            'status' => $booking->status,
            'payment_status' => $booking->payment_status,
            'total_amount' => $booking->total_amount,
        ], $extra);

        try {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => 'court_booking',
                'notifiable_type' => User::class,
                'notifiable_id' => $booking->user_id,
                'data' => json_encode($notificationData, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            event(new UserNotificationEvent((int) $booking->user_id, $notificationData));
        } catch (\Throwable $e) {
            Log::warning('Failed to create court booking notification', [
                'booking_id' => $booking->booking_id,
                'event' => $eventName,
                'error' => $e->getMessage(),
            ]);
        }
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
