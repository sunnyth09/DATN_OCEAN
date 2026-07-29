<?php

namespace App\Mail;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderCancelledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Order $order;

    public string $cancelledBy; // 'user' or 'admin'

    public string $cancelReason;

    public function __construct(Order $order, string $cancelledBy = 'admin', string $cancelReason = '')
    {
        $this->order = $order->loadMissing(['user', 'items']);
        $this->cancelledBy = $cancelledBy;
        $this->cancelReason = $cancelReason;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Đơn hàng] Đơn hàng #'.$this->order->order_code.' đã bị hủy',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.order.cancelled',
            with: [
                'order' => $this->order,
                'orderCode' => $this->order->order_code,
                'orderDate' => Carbon::parse($this->order->created_at)->format('d/m/Y H:i'),
                'cancelReason' => $this->cancelReason ?: ($this->order->cancel_reason ?? 'Không có lý do'),
                'totalAmount' => number_format($this->order->total_amount, 0, ',', '.').'đ',
                'cancelledBy' => $this->cancelledBy,
                'userName' => $this->order->user?->full_name ?? 'Quý khách',
            ],
        );
    }
}
