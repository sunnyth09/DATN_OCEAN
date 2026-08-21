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

class CourtBookingConfirmedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public CourtBooking $booking;

    public function __construct(CourtBooking $booking)
    {
        $this->booking = $booking->loadMissing(['court', 'user']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Ocean Sport] Lịch đặt sân #'.$this->booking->booking_code.' đã được xác nhận',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.court.booking_confirmed',
            with: [
                'booking' => $this->booking,
                'bookingCode' => $this->booking->booking_code,
                'courtName' => $this->booking->court?->court_name ?? 'Sân cầu lông',
                'bookingDate' => Carbon::parse($this->booking->booking_date)->format('d/m/Y'),
                'startTime' => substr($this->booking->start_time, 0, 5),
                'endTime' => substr($this->booking->end_time, 0, 5),
                'totalAmount' => number_format($this->booking->total_amount, 0, ',', '.').'đ',
                'userName' => $this->booking->user?->full_name ?? 'Quý khách',
            ],
        );
    }
}
