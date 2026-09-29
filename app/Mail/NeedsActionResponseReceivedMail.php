<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\OrderStatusChange;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirms to the customer that their "needs action" response arrived.
 * Lists document filenames only (no attachments: documents stay private).
 */
class NeedsActionResponseReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Order  $order  The order responded to.
     * @param  OrderStatusChange  $response  The customer's recorded response.
     * @param  array<int, string>  $document_names  Original filenames of the uploaded documents.
     */
    public function __construct(
        public Order $order,
        public OrderStatusChange $response,
        public array $document_names,
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
            subject: "We've received your response ({$this->order->display_reference})",
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
            markdown: 'mail.orders.response-received',
            with: [
                'customer_name' => $this->order->customer_name,
                'reference' => $this->order->display_reference,
                'service_name' => $this->order->service->name,
                'note' => $this->response->note,
                'document_names' => $this->document_names,
                'status_label' => $this->response->to_status->getLabel(),
                'track_url' => route('orders.track.show', $this->order),
            ],
        );
    }
}
