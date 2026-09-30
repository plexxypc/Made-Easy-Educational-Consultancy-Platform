<?php

namespace App\Actions\Consultations;

use App\Enums\ConsultationBookingStatus;
use App\Exceptions\BusinessRuleException;
use App\Mail\ConsultationBookingMail;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSlot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cancel a consultation slot (admin only).
 *
 * Business rules:
 *  - An unbooked slot is simply withdrawn: it disappears from the booking page.
 *  - A booked slot can be cancelled too. Every booking holding a place in it
 *    (confirmed, or pending payment with a live hold) is cancelled through
 *    CancelConsultationBooking, which cancels the order and EMAILS THE
 *    CUSTOMER. Staff who want to keep a customer should Reschedule them first.
 *  - Paid bookings already waiting for a new time, and lapsed unpaid holds,
 *    don't hold a place and are left alone (the former are rescheduled by
 *    staff; the latter can't be paid for any more).
 *  - The slot is marked cancelled in its own short transaction, BEFORE the
 *    bookings are touched, so a payment being confirmed at the same moment
 *    either finishes first (and is then cancelled here) or sees a cancelled
 *    slot (and is flagged for rebooking); it can never be confirmed into a
 *    cancelled slot. Locks are always taken order -> booking -> slot, as
 *    everywhere else, so this can't deadlock with a confirmation.
 */
class CancelConsultationSlot
{
    /**
     * @param  CancelConsultationBooking  $cancel_consultation_booking
     */
    public function __construct(
        private readonly CancelConsultationBooking $cancel_consultation_booking,
    ) {}

    /**
     * @param  ConsultationSlot  $slot
     * @param  User  $admin
     * @param  string|null  $reason  Shown to affected customers.
     * @return int Bookings cancelled (customers notified).
     *
     * @throws BusinessRuleException
     */
    public function handle(ConsultationSlot $slot, User $admin, ?string $reason = null): int
    {
        if (! $admin->isAdmin()) {
            throw new BusinessRuleException('Only admins can cancel consultation slots.');
        }

        $reason = filled($reason) ? trim((string) $reason) : null;

        DB::transaction(function () use ($slot, $admin, $reason): void {
            $locked_slot = ConsultationSlot::query()->whereKey($slot->getKey())->lockForUpdate()->firstOrFail();

            if ($locked_slot->isCancelled()) {
                throw new BusinessRuleException('This slot is already cancelled.');
            }

            $locked_slot->forceFill([
                'cancelled_at' => now(),
                'cancelled_by' => $admin->id,
                'cancellation_reason' => $reason,
            ])->save();
        });

        $affected = ConsultationBooking::query()
            ->where('consultation_slot_id', $slot->getKey())
            ->where(fn ($query) => $query
                ->where('status', ConsultationBookingStatus::Confirmed->value)
                ->orWhere(fn ($pending) => $pending
                    ->where('status', ConsultationBookingStatus::PendingPayment->value)
                    ->whereNull('needs_rebooking_at')
                    ->where('hold_expires_at', '>', now())))
            ->orderBy('id')
            ->get();

        $cancelled = 0;

        foreach ($affected as $booking) {
            try {
                $this->cancel_consultation_booking->handle($booking, $admin, $reason, ConsultationBookingMail::SLOT_CANCELLED);
                $cancelled++;
            } catch (BusinessRuleException) {
                // Closed by someone else in the meantime: nothing to cancel.
            }
        }

        $slot->refresh();

        return $cancelled;
    }
}
