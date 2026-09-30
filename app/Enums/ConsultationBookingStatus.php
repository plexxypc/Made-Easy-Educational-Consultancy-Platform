<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Lifecycle of a consultation booking.
 *
 *   pending_payment -> confirmed   (verified payment; ConfirmConsultationBooking)
 *   pending_payment -> cancelled   (staff, or the slot was cancelled)
 *   confirmed       -> completed | no_show   (staff, once the slot has started)
 *   confirmed       -> cancelled   (staff, or the slot was cancelled)
 *   completed, no_show, cancelled: final
 *
 * Rescheduling moves a confirmed booking to another slot and keeps it confirmed.
 *
 * A booking holds its slot while confirmed, completed or no_show, and while
 * pending_payment only until hold_expires_at (ConsultationBooking::occupying()).
 * So cancelling frees the slot for someone else; completing it does not.
 */
enum ConsultationBookingStatus: string implements HasColor, HasLabel
{
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending payment',
            self::Confirmed => 'Confirmed',
            self::Completed => 'Completed',
            self::NoShow => 'No-show',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * @return string
     */
    public function getColor(): string
    {
        return match ($this) {
            self::PendingPayment => 'gray',
            self::Confirmed => 'info',
            self::Completed => 'success',
            self::NoShow => 'danger',
            self::Cancelled => 'gray',
        };
    }

    /**
     * Statuses that always hold the slot (pending_payment does only while its hold lasts).
     *
     * @return array<int, self>
     */
    public static function holding_slot(): array
    {
        return [self::Confirmed, self::Completed, self::NoShow];
    }

    /**
     * Whether no further change is possible.
     *
     * @return bool
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::NoShow, self::Cancelled], true);
    }
}
