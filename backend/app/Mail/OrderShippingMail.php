<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderShippingMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order->loadMissing(['address']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Ocean Sport] Đơn hàng #'.$this->order->order_code.' đang được vận chuyển',
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url', 'http://localhost:3302')), '/');
        $trackingToken = $this->order->tracking_token;

        $trackingUrl = $this->order->user_id
            ? $frontendUrl.'/profile/orders/'.$this->order->order_id
            : ($trackingToken ? $frontendUrl.'/tracking/'.$trackingToken : $frontendUrl.'/tracking');

        $carrierCode = $this->order->tracking_number ?: $this->order->ghn_order_code;

        return new Content(
            markdown: 'emails.orders.shipping',
            with: [
                'order' => $this->order,
                'orderCode' => $this->order->order_code,
                'trackingCode' => $carrierCode,
                'customerName' => $this->order->recipient_name ?: ($this->order->address?->recipient_name ?? $this->order->address?->name ?? 'Quý khách'),
                'trackingUrl' => $trackingUrl,
            ],
        );
    }
}
