<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirms a service request was received and gives the reference.
 * Deliberately says nothing has been paid: payment comes later.
 */
class ServiceRequestReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Order  $order  The newly created order.
     */
    public function __construct(
        public Order $order,
    ) {
        $this->afterCommit();
    }

    /**
     * Get the message envelope.
     *
     * @return Envelope
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "We've received your request (Reference #{$this->order->id})",
        );
    }

    /**
     * Get the message content definition.
     *
     * @return Content
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.orders.request-received',
            with: [
                'customer_name' => $this->order->customer_name,
                'reference' => $this->order->id,
                'service_name' => $this->order->service->name,
                'price_label' => $this->order->service->priceLabel(),
                'is_quote' => $this->order->isQuote(),
                'contact' => config('madeeasy.contact'),
                'track_url' => route('orders.track.show', $this->order),
            ],
        );
    }
}
