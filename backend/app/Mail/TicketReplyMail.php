<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketReplyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Ticket $ticket;

    public string $statusText;

    public string $frontendUrl;

    public string $profileUrl;

    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket->loadMissing(['user', 'order']);
        $this->statusText = match ($ticket->status) {
            'pending' => 'Chờ xử lý',
            'processing' => 'Đang xử lý',
            'resolved' => 'Đã giải quyết',
            'closed' => 'Đã đóng',
            default => $ticket->status,
        };

        $base = env('FRONTEND_URL') ?? config('app.frontend_url') ?? (env('APP_ENV') === 'local' ? 'http://localhost:3302' : 'https://oceansport.pro.vn');
        $this->frontendUrl = rtrim((string) $base, '/');
        $this->profileUrl = $this->frontendUrl.'/profile/tickets';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Ocean Sport] Cập nhật khiếu nại #'.$this->ticket->ticket_id,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.ticket.reply',
            with: [
                'ticket' => $this->ticket,
                'customerName' => $this->ticket->user?->full_name ?? 'Quý khách',
                'ticketId' => $this->ticket->ticket_id,
                'reason' => $this->ticket->reason,
                'statusText' => $this->statusText,
                'status' => $this->ticket->status,
                'adminReply' => $this->ticket->admin_reply,
                'orderCode' => $this->ticket->order?->order_code,
                'profileUrl' => $this->profileUrl,
            ],
        );
    }
}
