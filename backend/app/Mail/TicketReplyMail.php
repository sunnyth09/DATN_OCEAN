<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public Ticket $ticket;
    public string $statusText;
    public string $frontendUrl;

    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
        $this->statusText = match ($ticket->status) {
            'pending'    => 'Chờ xử lý',
            'processing' => 'Đang xử lý',
            'resolved'   => 'Đã giải quyết',
            'closed'     => 'Đã đóng',
            default      => $ticket->status,
        };
        $this->frontendUrl = rtrim(env('FRONTEND_URL', 'https://oceansport.pro.vn'), '/');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cập nhật khiếu nại #' . $this->ticket->ticket_id . ' - Ocean Sport',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket.reply',
        );
    }
}
