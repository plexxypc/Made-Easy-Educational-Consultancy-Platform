<?php

namespace App\Actions\Consultations;

use App\Enums\ConsultationBookingStatus;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Mail\ConsultationBookingMail;
use App\Models\ConsultationBooking;
use App\Models\Order;
use App\Models\OrderStatusChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Cancel a consultation booking (staff/admin) and tell the customer.
 *
 * Business rules:
 *  - Any booking that isn't already completed, no-show or cancelled may be
 *    cancelled, including a paid one waiting for a new time.
 *  - The slot place is freed at once (cancelled bookings don't count in
 *    ConsultationBooking::occupying()), so someone else can book it.
 *  - The order is cancelled too, with a history note the customer can see.
 *  - Money is not refunded here: refunds are made by a person in the Paystack
 *    dashboard, as for any other order. The email tells a paying customer
 *    we'll contact them about a new time or refund.
 */
class CancelConsultationBooking
{
    /**
     * @param  ConsultationBooking  $booking
     * @param  User  $actor  Staff/admin.
     * @param  string|null  $reason  Shown to the customer.
     * @param  string  $mail_kind  ConsultationBookingMail::CANCELLED or ::SLOT_CANCELLED.
     * @return ConsultationBooking
     *
     * @throws BusinessRuleException
     */
    public function handle(ConsultationBooking $booking, User $actor, ?string $reason = null, string $mail_kind = ConsultationBookingMail::CANCELLED): ConsultationBooking
    {
        if (! $actor->isStaffOrAdmin()) {
            throw new BusinessRuleException('Only staff and admins can cancel consultation bookings.');
        }

        $reason = filled($reason) ? trim((string) $reason) : null;

        $booking = DB::transaction(function () use ($booking, $actor, $reason): ConsultationBooking {
            $order = Order::query()->withTrashed()->whereKey($booking->order_id)->lockForUpdate()->firstOrFail();
            $locked_booking = ConsultationBooking::query()->with('slot')->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if ($locked_booking->status->isFinal()) {
                throw new BusinessRuleException("This booking is already \"{$locked_booking->status->getLabel()}\".");
            }

            $was_paid = $order->receipt()->exists();

            $locked_booking->forceFill([
                'status' => ConsultationBookingStatus::Cancelled,
                'hold_expires_at' => null,
                'closed_at' => now(),
                'closed_by' => $actor->id,
                'cancellation_reason' => $reason,
            ])->save();

            if ($order->status->canTransitionTo(OrderStatus::Cancelled)) {
                $previous_status = $order->status;
                $order->forceFill(['status' => OrderStatus::Cancelled])->save();

                OrderStatusChange::create([
                    'order_id' => $order->id,
                    'changed_by' => $actor->id,
                    'from_status' => $previous_status,
                    'to_status' => OrderStatus::Cancelled,
                    'note' => trim("Your consultation on {$locked_booking->slot->label()} has been cancelled. "
                        .($reason !== null ? "Reason: {$reason} " : '')
                        .($was_paid ? "We'll contact you about a new time or a refund." : 'No payment was taken.')),
                ]);
            }

            return $locked_booking;
        });

        $order = $booking->order;

        if ($order->customer_email !== null) {
            Mail::to($order->customer_email)->queue(new ConsultationBookingMail($booking, $mail_kind));
        }

        return $booking;
    }
}
