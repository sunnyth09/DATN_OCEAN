<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
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
        $this->ticket = $ticket->loadMissing(['user', 'order']);

        $this->statusText = match ($ticket->status) {
            'pending'    => 'Chờ xử lý',
            'processing' => 'Đang xử lý',
            'resolved'   => 'Đã giải quyết',
            'closed'     => 'Đã đóng',
            default      => $ticket->status,
        };

        $this->frontendUrl = rtrim((string) config('app.frontend_url', config('app.url', 'http://localhost:3302')), '/');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Ocean Sport] Cập nhật khiếu nại #' . $this->ticket->ticket_id,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.ticket.reply',
            with: [
                'ticket'      => $this->ticket,
                'customerName' => $this->ticket->user?->full_name ?? 'Quý khách',
                'ticketId'    => $this->ticket->ticket_id,
                'reason'      => $this->ticket->reason,
                'statusText'  => $this->statusText,
                'status'      => $this->ticket->status,
                'adminReply'  => $this->ticket->admin_reply,
                'orderCode'   => $this->ticket->order?->order_code,
                'profileUrl'  => $this->frontendUrl . '/profile',
            ],
        );
    }
}
