<?php

namespace App\Mail;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusChange;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the customer their order status changed. Sent via the queue worker,
 * only after the status change has been committed.
 */
class OrderStatusChangedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Order  $order  The order that changed.
     * @param  OrderStatusChange  $status_change  The recorded change (status + note).
     */
    public function __construct(
        public Order $order,
        public OrderStatusChange $status_change,
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
            subject: "Order {$this->order->display_reference}: {$this->status_change->to_status->getLabel()}",
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
            markdown: 'mail.orders.status-changed',
            with: [
                'customer_name' => $this->order->customer_name,
                'order_number' => $this->order->display_reference,
                'service_name' => $this->order->service->name,
                'status_label' => $this->status_change->to_status->getLabel(),
                'note' => $this->status_change->note,
                'needs_action' => $this->status_change->to_status === OrderStatus::NeedsAction,
                // Signed-in owners land on the order; guests are asked for their email/phone first.
                'track_url' => route('orders.track.show', $this->order),
            ],
        );
    }
}
