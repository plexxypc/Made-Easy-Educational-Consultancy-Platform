<?php

namespace App\Actions\Consultations;

use App\Enums\ConsultationBookingStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Exceptions\SlotUnavailable;
use App\Mail\ConsultationBookingMail;
use App\Models\ConsultationBooking;
use App\Models\Order;
use App\Models\User;
use App\Notifications\ConsultationNeedsRebooking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * After the verified webhook marks a consultation order paid, confirm its booking.
 *
 * Business rules:
 *  - Called by the OrderPaid listener (ConfirmConsultationBookingOnPayment),
 *    never by the webhook directly. Idempotent: only a pending_payment booking
 *    not already flagged is processed, under a row lock.
 *  - The slot is re-checked with ReserveConsultationSlot in WAIT mode
 *    (a paid customer is never turned away because someone else was booking
 *    at that instant), excluding this booking's own place. If the slot still
 *    has room (normally it does: the customer's hold kept it), the booking
 *    is confirmed and the customer emailed the date, time and type.
 *  - If not (the hold ran out and the slot was taken, or the slot was
 *    cancelled or has started), the money is kept and the order stays paid,
 *    but the booking is flagged "needs a new time": the customer is told
 *    we'll arrange one, and staff are notified to Reschedule (or Cancel and
 *    refund). Two payments for the same last place therefore can't both be
 *    confirmed; the second one is flagged instead.
 */
class ConfirmConsultationBooking
{
    /**
     * @var string
     */
    public const CONFIRMED = 'confirmed';

    /**
     * @var string
     */
    public const NEEDS_REBOOKING = 'needs_rebooking';

    /**
     * @var string
     */
    public const NOTHING_TO_DO = 'nothing_to_do';

    /**
     * @param  ReserveConsultationSlot  $reserve_consultation_slot
     */
    public function __construct(
        private readonly ReserveConsultationSlot $reserve_consultation_slot,
    ) {}

    /**
     * @param  Order  $order  A paid consultation order.
     * @return string One of the outcome constants.
     */
    public function handle(Order $order): string
    {
        [$outcome, $booking] = DB::transaction(function () use ($order): array {
            $locked_order = Order::query()->with('service')->whereKey($order->getKey())->lockForUpdate()->first();
            $booking = ConsultationBooking::query()->where('order_id', $order->getKey())->lockForUpdate()->first();

            if ($locked_order === null
                || $booking === null
                || ! in_array($locked_order->status, [OrderStatus::Paid, OrderStatus::InProgress], true)
                || $booking->status !== ConsultationBookingStatus::PendingPayment
                || $booking->needsRebooking()) {
                return [self::NOTHING_TO_DO, $booking];
            }

            try {
                $this->reserve_consultation_slot->handle($booking->consultation_slot_id, $booking, ReserveConsultationSlot::WAIT, enforce_notice: false);
            } catch (SlotUnavailable $exception) {
                $booking->forceFill([
                    'needs_rebooking_at' => now(),
                    'needs_rebooking_reason' => $exception->getMessage(),
                    'hold_expires_at' => null,
                ])->save();

                return [self::NEEDS_REBOOKING, $booking];
            }

            $booking->forceFill([
                'status' => ConsultationBookingStatus::Confirmed,
                'confirmed_at' => now(),
                'hold_expires_at' => null,
            ])->save();

            return [self::CONFIRMED, $booking];
        });

        if ($outcome === self::NOTHING_TO_DO) {
            return $outcome;
        }

        $order->refresh();

        if ($outcome === self::NEEDS_REBOOKING) {
            Log::warning('Consultation paid but its slot could not be confirmed: rebooking needed.', [
                'order_id' => $order->id,
                'order_reference' => $order->reference,
                'booking_id' => $booking->id,
                'slot_id' => $booking->consultation_slot_id,
                'reason' => $booking->needs_rebooking_reason,
            ]);

            Notification::send(
                User::query()->whereIn('role', [UserRole::Staff->value, UserRole::Admin->value])->get(),
                new ConsultationNeedsRebooking($booking),
            );
        }

        if ($order->customer_email !== null) {
            Mail::to($order->customer_email)->queue(new ConsultationBookingMail(
                $booking,
                $outcome === self::CONFIRMED ? ConsultationBookingMail::CONFIRMED : ConsultationBookingMail::NEEDS_NEW_TIME,
            ));
        }

        return $outcome;
    }
}
