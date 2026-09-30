<?php

namespace App\Actions\Consultations;

use App\Actions\Orders\CreateServiceRequest;
use App\Enums\ConsultationBookingStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\SlotUnavailable;
use App\Models\ConsultationBooking;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Book a consultation": hold the chosen slot and create the order to pay for.
 *
 * Business rules:
 *  - The slot is claimed (ReserveConsultationSlot, SKIP LOCKED), the order
 *    created (CreateServiceRequest, the same code as every other order) and
 *    the booking written in ONE transaction. If the slot is full or being
 *    booked this instant, nothing is created.
 *  - The booking starts pending_payment and holds the slot for
 *    config('madeeasy.consultations.hold_minutes'). The caller then sends
 *    the customer to Paystack (InitiatePayment) as for any order; only the
 *    verified webhook leads to confirmation (ConfirmConsultationBooking).
 *  - Contact details live on the order, not on the booking.
 */
class BookConsultation
{
    /**
     * @param  CreateServiceRequest  $create_service_request
     * @param  ReserveConsultationSlot  $reserve_consultation_slot
     */
    public function __construct(
        private readonly CreateServiceRequest $create_service_request,
        private readonly ReserveConsultationSlot $reserve_consultation_slot,
    ) {}

    /**
     * @param  int  $slot_id
     * @param  array{name: string, email: string, phone: string}  $contact
     * @param  User|null  $submitted_by  Signed-in user, if any.
     * @return Order The new pending_payment order (with its booking).
     *
     * @throws SlotUnavailable
     * @throws BusinessRuleException If consultations aren't on sale.
     */
    public function handle(int $slot_id, array $contact, ?User $submitted_by = null): Order
    {
        $service = Service::bookableConsultation();

        if ($service === null) {
            throw new BusinessRuleException("Consultations can't be booked online at the moment. Please contact us.");
        }

        return DB::transaction(function () use ($slot_id, $contact, $submitted_by, $service): Order {
            $slot = $this->reserve_consultation_slot->handle($slot_id, mode: ReserveConsultationSlot::SKIP_LOCKED, enforce_notice: true);

            $order = $this->create_service_request->handle($service, $contact, [], [], $submitted_by);

            (new ConsultationBooking)->forceFill([
                'order_id' => $order->id,
                'consultation_slot_id' => $slot->id,
                'status' => ConsultationBookingStatus::PendingPayment,
                'hold_expires_at' => self::hold_until(),
            ])->save();

            return $order;
        });
    }

    /**
     * When a hold taken now runs out.
     *
     * @return Carbon
     */
    public static function hold_until(): Carbon
    {
        return now()->addMinutes((int) config('madeeasy.consultations.hold_minutes'));
    }
}
