<?php

namespace App\Actions\Consultations;

use App\Enums\ConsultationBookingStatus;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ConsultationBooking;
use App\Models\Order;
use App\Models\OrderStatusChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Record how a confirmed consultation went: completed, or no-show (staff/admin).
 *
 * Business rules:
 *  - Only a confirmed booking, and only once its start time has passed.
 *  - Either way the order is completed (the time was reserved and paid
 *    for). A no-show is NOT refunded or rebooked automatically, and the
 *    slot stays used: completed and no-show bookings keep their place, so
 *    the time can't be sold twice.
 *  - The order history gets a note the customer sees on their order page.
 *    Assumption: no email for these two outcomes.
 */
class CloseConsultationBooking
{
    /**
     * @param  ConsultationBooking  $booking
     * @param  ConsultationBookingStatus  $outcome  Completed or NoShow.
     * @param  User  $actor  Staff/admin.
     * @return ConsultationBooking
     *
     * @throws BusinessRuleException
     */
    public function handle(ConsultationBooking $booking, ConsultationBookingStatus $outcome, User $actor): ConsultationBooking
    {
        if (! $actor->isStaffOrAdmin()) {
            throw new BusinessRuleException('Only staff and admins can update consultation bookings.');
        }

        if (! in_array($outcome, [ConsultationBookingStatus::Completed, ConsultationBookingStatus::NoShow], true)) {
            throw new BusinessRuleException('A consultation can only be closed as completed or no-show.');
        }

        return DB::transaction(function () use ($booking, $outcome, $actor): ConsultationBooking {
            $order = Order::query()->whereKey($booking->order_id)->lockForUpdate()->firstOrFail();
            $locked_booking = ConsultationBooking::query()->with('slot')->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if ($locked_booking->status !== ConsultationBookingStatus::Confirmed) {
                throw new BusinessRuleException("Only a confirmed booking can be marked \"{$outcome->getLabel()}\"; this one is \"{$locked_booking->statusLabel()}\".");
            }

            if (! $locked_booking->slot->hasStarted()) {
                throw new BusinessRuleException("This consultation hasn't started yet ({$locked_booking->slot->label()}).");
            }

            if (! $order->status->canTransitionTo(OrderStatus::Completed)) {
                throw new BusinessRuleException("The order is \"{$order->status->getLabel()}\". Resolve that on the order first.");
            }

            $locked_booking->forceFill([
                'status' => $outcome,
                'closed_at' => now(),
                'closed_by' => $actor->id,
            ])->save();

            $previous_status = $order->status;
            $order->forceFill(['status' => OrderStatus::Completed])->save();

            OrderStatusChange::create([
                'order_id' => $order->id,
                'changed_by' => $actor->id,
                'from_status' => $previous_status,
                'to_status' => OrderStatus::Completed,
                'note' => $outcome === ConsultationBookingStatus::Completed
                    ? "Your consultation on {$locked_booking->slot->label()} has taken place. Thank you."
                    : "We missed you at your consultation on {$locked_booking->slot->label()}. Contact us if you'd like to book another time.",
            ]);

            return $locked_booking;
        });
    }
}
