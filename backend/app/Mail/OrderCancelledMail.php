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

    public float $refundAmount;

    public ?string $refundDestination;

    public function __construct(Order $order, string $cancelledBy = 'admin', string $cancelReason = '', float $refundAmount = 0, ?string $refundDestination = null)
    {
        $this->order = $order->loadMissing(['user', 'items']);
        $this->cancelledBy = $cancelledBy;
        $this->cancelReason = $cancelReason;
        $this->refundAmount = $refundAmount;
        $this->refundDestination = $refundDestination;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Ocean Sport] Thông báo hủy đơn hàng #'.$this->order->order_code,
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
                'totalAmount' => number_format((float) ($this->order->grand_total ?? $this->order->total_amount), 0, ',', '.').'đ',
                'refundAmount' => $this->refundAmount > 0 ? number_format($this->refundAmount, 0, ',', '.').'đ' : null,
                'refundDestination' => $this->refundDestination,
                'cancelledBy' => $this->cancelledBy,
                'userName' => $this->order->user?->full_name ?? 'Quý khách',
            ],
        );
    }
}
