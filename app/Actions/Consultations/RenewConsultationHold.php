<?php

namespace App\Actions\Consultations;

use App\Enums\ConsultationBookingStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\SlotUnavailable;
use App\Models\ConsultationBooking;
use App\Models\Order;

/**
 * Before each Paystack checkout for a consultation order ("Pay now"),
 * make sure the slot is still the customer's and restart the hold.
 *
 * Business rules:
 *  - Runs inside InitiatePayment's transaction (order row locked).
 *  - If the hold has lapsed and someone else has taken the slot (or it was
 *    cancelled, or is now too soon), payment is refused with a message
 *    asking the customer to book another time, so nobody pays for a time they
 *    can't have. The old order simply stays unpaid.
 *  - Otherwise the hold restarts from now (hold_minutes).
 *  - This narrows, but can't close, the gap: a customer can still finish a
 *    Paystack page after the hold ends. ConfirmConsultationBooking handles
 *    that case by flagging the paid booking for rebooking.
 */
class RenewConsultationHold
{
    /**
     * @param  ReserveConsultationSlot  $reserve_consultation_slot
     */
    public function __construct(
        private readonly ReserveConsultationSlot $reserve_consultation_slot,
    ) {}

    /**
     * @param  Order  $order  A consultation order, locked by the caller.
     * @return void
     *
     * @throws BusinessRuleException
     */
    public function handle(Order $order): void
    {
        $booking = ConsultationBooking::query()->where('order_id', $order->id)->lockForUpdate()->first();

        if ($booking === null || $booking->status !== ConsultationBookingStatus::PendingPayment || $booking->needsRebooking()) {
            throw new BusinessRuleException('This consultation booking can no longer be paid for. Please book a new time.');
        }

        try {
            $this->reserve_consultation_slot->handle($booking->consultation_slot_id, $booking, ReserveConsultationSlot::SKIP_LOCKED, enforce_notice: true);
        } catch (SlotUnavailable $exception) {
            throw new SlotUnavailable($exception->getMessage().' Your chosen time is no longer being held for you: book a new time on the consultation page.');
        }

        $booking->forceFill(['hold_expires_at' => BookConsultation::hold_until()])->save();
    }
}
