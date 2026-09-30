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
 * Tells a customer who has paid for a result checker that some or all of
 * their PINs are delayed (stock ran out), and that nothing more is needed
 * from them. Sent once, when AllocateResultCheckerPins flags the shortfall.
 */
class ResultCheckerDelayedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Order  $order
     * @param  int  $delayed  Units still without a PIN.
     */
    public function __construct(
        public Order $order,
        public int $delayed,
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
            subject: "Your {$this->order->service->name} is delayed ({$this->order->display_reference})",
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
            markdown: 'mail.orders.result-checker-delayed',
            with: [
                'order' => $this->order,
                'delayed' => $this->delayed,
                'is_partial' => $this->delayed < $this->order->quantity,
                'contact' => config('madeeasy.contact'),
                'track_url' => route('orders.track.show', $this->order),
            ],
        );
    }
}
