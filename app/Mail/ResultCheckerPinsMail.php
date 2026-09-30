<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\ResultCheckerPin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The customer's result checker PIN(s), sent by DeliverAllocatedPins.
 *
 * Queued with model identifiers only (SerializesModels), so the jobs table
 * never holds a plaintext PIN; it is decrypted when the worker renders the
 * email.
 */
class ResultCheckerPinsMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Order  $order
     * @param  Collection<int, ResultCheckerPin>  $pins  The PINs delivered in this email.
     * @param  int  $still_owed  Units still waiting for a PIN (sent later by our team).
     */
    public function __construct(
        public Order $order,
        public Collection $pins,
        public int $still_owed = 0,
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
        $noun = $this->pins->count() === 1 ? 'PIN' : 'PINs';

        return new Envelope(
            subject: "Your {$this->order->service->name} {$noun} ({$this->order->display_reference})",
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
            markdown: 'mail.orders.result-checker-pins',
            with: [
                'order' => $this->order,
                'pins' => $this->pins,
                'still_owed' => $this->still_owed,
                'contact' => config('madeeasy.contact'),
                'track_url' => route('orders.track.show', $this->order),
            ],
        );
    }
}
