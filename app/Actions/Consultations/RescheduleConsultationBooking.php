<?php

namespace App\Actions\Consultations;

use App\Enums\ConsultationBookingStatus;
use App\Exceptions\BusinessRuleException;
use App\Mail\ConsultationBookingMail;
use App\Models\ConsultationBooking;
use App\Models\Order;
use App\Models\OrderStatusChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Move a booking to another open slot (staff/admin) and tell the customer.
 *
 * Business rules:
 *  - For a confirmed booking, or a paid one waiting for a new time
 *    (needsRebooking(), which becomes confirmed here). Unpaid bookings aren't
 *    rescheduled: the customer simply books again.
 *  - The new slot is claimed with ReserveConsultationSlot (SKIP LOCKED), the
 *    same concurrency check customers go through, so staff can't overbook a
 *    slot. Minimum notice doesn't apply to staff, but the slot must not have
 *    started. The old place is freed in the same transaction.
 *  - Any type may be chosen (e.g. WhatsApp to Google Meet). The meeting link
 *    is cleared whenever the slot changes, because a link belongs to a
 *    specific meeting: staff add a new one for the new time. The 24-hour
 *    missing-link reminder is re-armed for the new time too.
 *  - The order history gets a note and the customer is emailed the new time.
 */
class RescheduleConsultationBooking
{
    /**
     * @param  ReserveConsultationSlot  $reserve_consultation_slot
     */
    public function __construct(
        private readonly ReserveConsultationSlot $reserve_consultation_slot,
    ) {}

    /**
     * @param  ConsultationBooking  $booking
     * @param  int  $new_slot_id
     * @param  User  $actor  Staff/admin.
     * @param  string|null  $note  Optional message to the customer.
     * @return ConsultationBooking
     *
     * @throws BusinessRuleException
     */
    public function handle(ConsultationBooking $booking, int $new_slot_id, User $actor, ?string $note = null): ConsultationBooking
    {
        if (! $actor->isStaffOrAdmin()) {
            throw new BusinessRuleException('Only staff and admins can reschedule consultations.');
        }

        $note = filled($note) ? trim((string) $note) : null;

        [$booking, $previous_label] = DB::transaction(function () use ($booking, $new_slot_id, $actor, $note): array {
            $order = Order::query()->whereKey($booking->order_id)->lockForUpdate()->firstOrFail();
            $locked_booking = ConsultationBooking::query()->with('slot')->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if ($locked_booking->status !== ConsultationBookingStatus::Confirmed && ! $locked_booking->needsRebooking()) {
                throw new BusinessRuleException("Only a confirmed booking, or a paid one waiting for a new time, can be rescheduled; this one is \"{$locked_booking->statusLabel()}\".");
            }

            if ($new_slot_id === $locked_booking->consultation_slot_id && ! $locked_booking->needsRebooking()) {
                throw new BusinessRuleException('Choose a different time: the booking is already in this slot.');
            }

            $new_slot = $this->reserve_consultation_slot->handle($new_slot_id, $locked_booking, ReserveConsultationSlot::SKIP_LOCKED, enforce_notice: false);
            $previous_slot = $locked_booking->slot;
            $slot_changed = $new_slot->id !== $previous_slot->id;

            $locked_booking->forceFill([
                'consultation_slot_id' => $new_slot->id,
                'previous_slot_id' => $slot_changed ? $previous_slot->id : $locked_booking->previous_slot_id,
                'status' => ConsultationBookingStatus::Confirmed,
                'confirmed_at' => $locked_booking->confirmed_at ?? now(),
                'needs_rebooking_at' => null,
                'needs_rebooking_reason' => null,
                'hold_expires_at' => null,
                'reschedule_count' => $locked_booking->reschedule_count + 1,
                'rescheduled_at' => now(),
                'meeting_link' => $slot_changed ? null : $locked_booking->meeting_link,
                'meeting_link_added_by' => $slot_changed ? null : $locked_booking->meeting_link_added_by,
                'meeting_link_added_at' => $slot_changed ? null : $locked_booking->meeting_link_added_at,
                'meeting_link_reminder_sent_at' => $slot_changed ? null : $locked_booking->meeting_link_reminder_sent_at,
            ])->save();

            OrderStatusChange::create([
                'order_id' => $order->id,
                'changed_by' => $actor->id,
                'from_status' => $order->status,
                'to_status' => $order->status,
                'note' => trim("Your consultation is now booked for {$new_slot->label()} ({$new_slot->consultation_type->getLabel()}). ".($note ?? '')),
            ]);

            return [$locked_booking->setRelation('slot', $new_slot), $previous_slot->label()];
        });

        $order = $booking->order;

        if ($order->customer_email !== null) {
            Mail::to($order->customer_email)->queue(new ConsultationBookingMail($booking, ConsultationBookingMail::RESCHEDULED, $previous_label));
        }

        return $booking;
    }
}
