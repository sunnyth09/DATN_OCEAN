<?php

namespace App\Mail;

use App\Models\CourtBooking;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CourtBookingCancelledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public CourtBooking $booking;

    public ?int $refundAmount;

    public string $cancelledBy; // 'user' or 'admin'

    public function __construct(CourtBooking $booking, ?int $refundAmount = null, string $cancelledBy = 'user')
    {
        $this->booking = $booking->loadMissing(['court', 'user']);
        $this->refundAmount = $refundAmount;
        $this->cancelledBy = $cancelledBy;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Đặt Sân] Lịch đặt sân #'.$this->booking->booking_code.' đã bị hủy',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.court.booking_cancelled',
            with: [
                'booking' => $this->booking,
                'bookingCode' => $this->booking->booking_code,
                'courtName' => $this->booking->court?->court_name ?? 'Sân cầu lông',
                'bookingDate' => Carbon::parse($this->booking->booking_date)->format('d/m/Y'),
                'startTime' => substr($this->booking->start_time, 0, 5),
                'endTime' => substr($this->booking->end_time, 0, 5),
                'cancelReason' => $this->booking->cancel_reason ?? 'Không có lý do',
                'refundAmount' => $this->refundAmount
                    ? number_format($this->refundAmount, 0, ',', '.').'đ'
                    : null,
                'cancelledBy' => $this->cancelledBy,
                'userName' => $this->booking->user?->full_name ?? 'Quý khách',
            ],
        );
    }
}
