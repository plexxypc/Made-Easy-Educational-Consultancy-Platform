<?php

namespace App\Listeners;

use App\Actions\Consultations\ConfirmConsultationBooking;
use App\Enums\ConsultationBookingStatus;
use App\Enums\UserRole;
use App\Events\OrderPaid;
use App\Models\ConsultationBooking;
use App\Models\User;
use App\Notifications\ConsultationNeedsRebooking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * When a consultation order is paid, confirm its booking
 * (ConfirmConsultationBooking). Same architecture as AllocateResultCheckerPins:
 * queued, retried by the worker, and the webhook handler knows nothing about
 * consultations.
 *
 * Business rule: the payment is never reversed here. If the slot can't be
 * confirmed, or confirmation keeps failing, the order stays paid and staff
 * are notified to reschedule.
 */
class ConfirmConsultationBookingOnPayment implements ShouldQueue
{
    /**
     * @var int
     */
    public int $tries = 3;

    /**
     * Seconds between attempts.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 60];

    /**
     * @param  ConfirmConsultationBooking  $confirm_consultation_booking
     */
    public function __construct(
        private readonly ConfirmConsultationBooking $confirm_consultation_booking,
    ) {}

    /**
     * Only consultation orders need this job.
     *
     * @param  OrderPaid  $event
     * @return bool
     */
    public function shouldQueue(OrderPaid $event): bool
    {
        return $event->order->isConsultation();
    }

    /**
     * @param  OrderPaid  $event
     * @return void
     */
    public function handle(OrderPaid $event): void
    {
        $this->confirm_consultation_booking->handle($event->order);
    }

    /**
     * All attempts failed: the customer has paid, so a human must step in.
     *
     * @param  OrderPaid  $event
     * @param  Throwable  $exception
     * @return void
     */
    public function failed(OrderPaid $event, Throwable $exception): void
    {
        Log::error('Consultation booking confirmation failed after payment: staff must confirm or reschedule.', [
            'order_id' => $event->order->id,
            'order_reference' => $event->order->reference,
            'error' => $exception->getMessage(),
        ]);

        $booking = ConsultationBooking::query()->where('order_id', $event->order->id)->first();

        if ($booking === null || $booking->status !== ConsultationBookingStatus::PendingPayment) {
            return;
        }

        $booking->forceFill([
            'needs_rebooking_at' => $booking->needs_rebooking_at ?? now(),
            'needs_rebooking_reason' => $booking->needs_rebooking_reason ?? 'Automatic confirmation failed (see the logs).',
            'hold_expires_at' => null,
        ])->save();

        Notification::send(
            User::query()->whereIn('role', [UserRole::Staff->value, UserRole::Admin->value])->get(),
            new ConsultationNeedsRebooking($booking),
        );
    }
}
