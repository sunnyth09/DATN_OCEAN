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
            subject: '[Ocean Sport] Đơn hàng #'.$this->order->order_code.' đã được tạo vận đơn GHN',
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $trackingToken = $this->order->tracking_token;

        return new Content(
            markdown: 'emails.orders.shipping',
            with: [
                'order' => $this->order,
                'orderCode' => $this->order->order_code,
                'ghnOrderCode' => $this->order->ghn_order_code,
                'customerName' => $this->order->recipient_name ?: ($this->order->address?->recipient_name ?? $this->order->address?->name ?? 'Quý khách'),
                'trackingUrl' => $trackingToken ? $frontendUrl.'/tracking/'.$trackingToken : null,
                'ghnTrackingUrl' => $this->order->ghn_order_code
                    ? rtrim((string) config('ghn.tracking_url', 'https://donhang.ghn.vn'), '/').'/?order_code='.urlencode($this->order->ghn_order_code)
                    : null,
            ],
        );
    }
}
