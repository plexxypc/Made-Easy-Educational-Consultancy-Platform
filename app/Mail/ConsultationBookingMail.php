<?php

namespace App\Mail;

use App\Models\ConsultationBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Every email a customer gets about their consultation booking: one template,
 * one variant per event (the KIND constants). Each shows the date, time and
 * consultation type as they are when the email is sent, plus the meeting
 * link, or a clearly marked "to be added" placeholder, for online and
 * Google Meet slots.
 */
class ConsultationBookingMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Payment verified and the slot confirmed.
     *
     * @var string
     */
    public const CONFIRMED = 'confirmed';

    /**
     * Payment verified, but the slot couldn't be confirmed; we'll arrange a new time.
     *
     * @var string
     */
    public const NEEDS_NEW_TIME = 'needs_new_time';

    /**
     * Staff moved the booking to another slot.
     *
     * @var string
     */
    public const RESCHEDULED = 'rescheduled';

    /**
     * Staff cancelled the booking.
     *
     * @var string
     */
    public const CANCELLED = 'cancelled';

    /**
     * The slot itself was cancelled, which cancelled the booking.
     *
     * @var string
     */
    public const SLOT_CANCELLED = 'slot_cancelled';

    /**
     * Staff added (or changed) the meeting link.
     *
     * @var string
     */
    public const MEETING_LINK = 'meeting_link';

    /**
     * @param  ConsultationBooking  $booking
     * @param  string  $kind  One of the KIND constants.
     * @param  string|null  $previous_slot_label  RESCHEDULED: the old date and time.
     */
    public function __construct(
        public ConsultationBooking $booking,
        public string $kind,
        public ?string $previous_slot_label = null,
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
        $reference = $this->booking->order->display_reference;
        $when = $this->booking->slot->startsAt()->format('D j M, g:ia');

        return new Envelope(subject: match ($this->kind) {
            self::CONFIRMED => "Consultation booked for {$when} ({$reference})",
            self::NEEDS_NEW_TIME => "Payment received: we need to arrange a new consultation time ({$reference})",
            self::RESCHEDULED => "Your consultation has moved to {$when} ({$reference})",
            self::CANCELLED, self::SLOT_CANCELLED => "Your consultation on {$when} has been cancelled ({$reference})",
            self::MEETING_LINK => "Your meeting link for {$when} ({$reference})",
        });
    }

    /**
     * Get the message content definition.
     *
     * @return Content
     */
    public function content(): Content
    {
        $order = $this->booking->order;

        return new Content(
            markdown: 'mail.orders.consultation-booking',
            with: [
                'kind' => $this->kind,
                'booking' => $this->booking,
                'slot' => $this->booking->slot,
                'order' => $order,
                'previous_slot_label' => $this->previous_slot_label,
                'was_paid' => $order->receipt()->exists(),
                'contact' => config('madeeasy.contact'),
                'track_url' => route('orders.track.show', $order),
                'book_url' => route('book-consultation'),
            ],
        );
    }
}
