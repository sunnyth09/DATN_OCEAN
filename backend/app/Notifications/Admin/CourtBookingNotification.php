<?php

namespace App\Notifications\Admin;

use App\Models\CourtBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CourtBookingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $booking;

    public $eventType;

    /**
     * Create a new notification instance.
     */
    public function __construct(CourtBooking $booking, string $eventType)
    {
        $this->booking = $booking;
        $this->eventType = $eventType;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $title = 'Thông báo Đặt sân';
        $message = '';
        $icon = 'bi-info-circle';

        $customerName = $this->booking->customer_name;
        if (empty($customerName) && $this->booking->relationLoaded('user') && $this->booking->user) {
            $customerName = $this->booking->user->full_name ?? $this->booking->user->name;
        }
        $customerName = $customerName ?: 'Khách vãng lai';

        $courtName = '';
        if ($this->booking->relationLoaded('court') && $this->booking->court) {
            $courtName = $this->booking->court->court_name;
        }

        if ($this->eventType === 'created') {
            $title = 'Đơn đặt sân mới';
            $message = "Khách hàng {$customerName} vừa đặt {$courtName} (Mã: {$this->booking->booking_code}).";
            $icon = 'bi-calendar-plus';
        } elseif ($this->eventType === 'cancelled') {
            $title = 'Đơn đặt sân bị hủy';
            $message = "Đơn đặt sân {$this->booking->booking_code} đã bị hủy.";
            $icon = 'bi-calendar-x';
        }

        return [
            'booking_id' => $this->booking->booking_id,
            'booking_code' => $this->booking->booking_code,
            'event_type' => $this->eventType,
            'title' => $title,
            'message' => $message,
            'icon' => $icon,
        ];
    }
}
