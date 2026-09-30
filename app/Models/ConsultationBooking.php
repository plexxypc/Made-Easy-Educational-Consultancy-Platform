<?php

namespace App\Models;

use App\Enums\ConsultationBookingStatus;
use Database\Factories\ConsultationBookingFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A customer's booking of one consultation slot, for one order.
 *
 * Contact details are the order's (Order::$customer_name, customer_email,
 * guest_phone); nothing is duplicated here. Nothing is mass-assignable:
 * every change goes through an App\Actions\Consultations class.
 *
 * @property int $id
 * @property int $order_id
 * @property int $consultation_slot_id
 * @property ConsultationBookingStatus $status
 * @property Carbon|null $hold_expires_at While pending_payment, the slot is held until then.
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $needs_rebooking_at Paid, but the slot couldn't be confirmed: staff must reschedule or cancel.
 * @property string|null $needs_rebooking_reason
 * @property string|null $meeting_link
 * @property int|null $meeting_link_added_by
 * @property Carbon|null $meeting_link_added_at
 * @property Carbon|null $meeting_link_reminder_sent_at Staff reminded the link is missing (once per booking and time).
 * @property int|null $previous_slot_id Slot before the latest reschedule.
 * @property int $reschedule_count
 * @property Carbon|null $rescheduled_at
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property string|null $cancellation_reason
 */
class ConsultationBooking extends Model
{
    /** @use HasFactory<ConsultationBookingFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending_payment',
        'reschedule_count' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ConsultationBookingStatus::class,
            'hold_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'needs_rebooking_at' => 'datetime',
            'meeting_link_added_at' => 'datetime',
            'meeting_link_reminder_sent_at' => 'datetime',
            'reschedule_count' => 'integer',
            'rescheduled_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Bookings that hold a place in their slot: confirmed, completed or
     * no-show, or pending payment with an unexpired hold. Cancelled bookings,
     * lapsed holds and paid bookings waiting for a new time don't.
     *
     * @param  Builder<ConsultationBooking>  $query
     * @return void
     */
    #[Scope]
    protected function occupying(Builder $query): void
    {
        $query->where(fn (Builder $where) => $where
            ->whereIn('status', array_map(fn (ConsultationBookingStatus $status): string => $status->value, ConsultationBookingStatus::holding_slot()))
            ->orWhere(fn (Builder $held) => $held
                ->where('status', ConsultationBookingStatus::PendingPayment->value)
                ->whereNull('needs_rebooking_at')
                ->where('hold_expires_at', '>', now())));
    }

    /**
     * Whether this booking is holding a place in its slot right now.
     *
     * @return bool
     */
    public function isOccupyingSlot(): bool
    {
        return in_array($this->status, ConsultationBookingStatus::holding_slot(), true)
            || ($this->status === ConsultationBookingStatus::PendingPayment
                && $this->needs_rebooking_at === null
                && $this->hold_expires_at?->isFuture() === true);
    }

    /**
     * Paid, but the booked time couldn't be confirmed: staff must pick a new
     * one (Reschedule) or cancel.
     *
     * @return bool
     */
    public function needsRebooking(): bool
    {
        return $this->status === ConsultationBookingStatus::PendingPayment && $this->needs_rebooking_at !== null;
    }

    /**
     * Status for people: "Paid – needs a new time" for needsRebooking().
     *
     * @return string
     */
    public function statusLabel(): string
    {
        return $this->needsRebooking() ? 'Paid – needs a new time' : $this->status->getLabel();
    }

    /**
     * Whether staff still need to add the joining link.
     *
     * @return bool
     */
    public function awaitsMeetingLink(): bool
    {
        return $this->status === ConsultationBookingStatus::Confirmed
            && $this->slot->consultation_type->needsMeetingLink()
            && blank($this->meeting_link);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    /**
     * The slot booked (after a reschedule, the new one).
     *
     * @return BelongsTo<ConsultationSlot, $this>
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(ConsultationSlot::class, 'consultation_slot_id');
    }

    /**
     * @return BelongsTo<ConsultationSlot, $this>
     */
    public function previousSlot(): BelongsTo
    {
        return $this->belongsTo(ConsultationSlot::class, 'previous_slot_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function meetingLinkAddedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'meeting_link_added_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
