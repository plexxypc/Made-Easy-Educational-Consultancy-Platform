<?php

namespace App\Mail;

use App\Models\Receipt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Payment receipt, sent after a verified payment marks the order paid.
 * The receipt is also viewable/downloadable from the order page.
 */
class PaymentReceiptMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Receipt  $receipt
     */
    public function __construct(
        public Receipt $receipt,
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
            subject: "Payment receipt {$this->receipt->number} ({$this->receipt->displayOrderReference()})",
        );
    }

    /**
     * Get the message content definition.
     *
     * @return Content
     */
    public function content(): Content
    {
        $order = $this->receipt->payment->order;

        return new Content(
            markdown: 'mail.orders.payment-receipt',
            with: [
                'receipt' => $this->receipt,
                'contact' => config('madeeasy.contact'),
                'track_url' => route('orders.track.show', $order),
                'receipt_url' => route('orders.receipt', $order),
                'is_result_checker' => $order->isResultChecker(),
                'is_consultation' => $order->isConsultation(),
                'pin_noun' => $order->quantity > 1 ? 'PINs' : 'PIN',
            ],
        );
    }
}
