<?php

namespace App\Actions\Consultations;

use App\Exceptions\SlotUnavailable;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSlot;
use App\Support\RowLock;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Lock a consultation slot and check it has a free place. The one place
 * where "is this slot available?" is decided under concurrency; every path
 * that puts a booking into a slot (booking, renewing a hold at payment,
 * confirming after payment, rescheduling) calls this inside its transaction
 * and writes the booking before committing.
 *
 * Business rules:
 *  - Same pattern as PIN allocation (AllocatePinsToOrder): the slot row is
 *    locked, occupancy is counted while it is held, and the booking is
 *    written in the same transaction. Two people can therefore never both
 *    take the last place: the second sees the first's booking once it
 *    commits, or can't get the lock at all.
 *  - SKIP_LOCKED mode (customers booking/paying, staff rescheduling): if the
 *    slot is locked by another transaction this instant, give up at once
 *    with "someone is booking this time right now" instead of queueing
 *    behind them. Assumption: a customer would rather pick again than wait.
 *  - WAIT mode (confirming a booking the customer has already PAID for):
 *    wait for the lock, so a payment is never refused just because someone
 *    else was booking at the same instant.
 *  - Occupancy = ConsultationBooking::occupying(), excluding the booking
 *    being placed (so renewing or confirming your own hold doesn't count it
 *    twice).
 */
class ReserveConsultationSlot
{
    /**
     * Give up if another transaction holds the slot.
     *
     * @var string
     */
    public const SKIP_LOCKED = 'skip_locked';

    /**
     * Wait for another transaction to finish with the slot.
     *
     * @var string
     */
    public const WAIT = 'wait';

    /**
     * @param  int  $slot_id
     * @param  ConsultationBooking|null  $for_booking  The booking being placed, if it already exists.
     * @param  string  $mode  SKIP_LOCKED or WAIT.
     * @param  bool  $enforce_notice  Refuse slots starting within the minimum notice (new bookings);
     *                                otherwise only slots that have already started are refused.
     * @return ConsultationSlot The locked slot (lock held until the caller's transaction ends).
     *
     * @throws SlotUnavailable
     */
    public function handle(int $slot_id, ?ConsultationBooking $for_booking = null, string $mode = self::SKIP_LOCKED, bool $enforce_notice = true): ConsultationSlot
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('ReserveConsultationSlot must run inside the transaction that writes the booking.');
        }

        $query = ConsultationSlot::query()->whereKey($slot_id);
        $slot = $mode === self::WAIT
            ? $query->lockForUpdate()->first()
            : $query->lock(RowLock::skip_locked())->first();

        if ($slot === null) {
            throw new SlotUnavailable(ConsultationSlot::query()->whereKey($slot_id)->exists()
                ? 'Someone is booking this time right now. Please choose another time, or try again in a moment.'
                : 'That consultation time is no longer available. Please choose another.');
        }

        if ($slot->isCancelled()) {
            throw new SlotUnavailable('That consultation time has been cancelled. Please choose another.');
        }

        if ($enforce_notice ? ! $slot->isOpenForBooking() : $slot->hasStarted()) {
            throw new SlotUnavailable('That consultation time is too soon or has passed. Please choose a later time.');
        }

        $occupied = $slot->bookings()
            ->occupying()
            ->when($for_booking !== null, fn ($query) => $query->whereKeyNot($for_booking->getKey()))
            ->count();

        if ($occupied >= $slot->capacity) {
            throw new SlotUnavailable('Sorry, that consultation time has just been booked. Please choose another.');
        }

        return $slot;
    }
}
