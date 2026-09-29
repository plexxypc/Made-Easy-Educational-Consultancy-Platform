<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Lifecycle of an order.
 *
 * Allowed transitions (see allowedNextStatuses()):
 *
 *   pending_payment -> in_progress | needs_action | cancelled
 *   paid            -> in_progress | completed | needs_action | cancelled
 *   in_progress     -> completed | needs_action | cancelled
 *   needs_action    -> in_progress | cancelled
 *   completed, cancelled: terminal
 *
 * Customer side: responding to needs_action returns the order to the status it
 * had before (Order::statusBeforeNeedsAction(), RespondToNeedsAction).
 *
 * "paid" is never set by staff: only a verified Paystack webhook whose amount
 * matches orders.total_amount may do that (project security rule 2, payments prompt).
 * Until payments exist, staff manage orders manually, which is why
 * pending_payment -> in_progress is allowed for now.
 *
 * Quote-type orders have an extra guard in Order::canTransitionTo().
 */
enum OrderStatus: string implements HasColor, HasLabel
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case NeedsAction = 'needs_action';
    case Cancelled = 'cancelled';

    /**
     * Human-readable label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending payment',
            self::Paid => 'Paid',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::NeedsAction => 'Needs action',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Plain-language explanation for customers on the tracking page.
     *
     * @return string
     */
    public function customerDescription(): string
    {
        return match ($this) {
            self::PendingPayment => "We've received your request. We'll contact you about the next steps, including payment.",
            self::Paid => "Payment received. We'll start work on your request shortly.",
            self::InProgress => 'Our team is working on your request.',
            self::Completed => 'Your request has been completed.',
            self::NeedsAction => 'We need something from you before we can continue. See the message below.',
            self::Cancelled => 'This request has been cancelled. Contact us if you think this is a mistake.',
        };
    }

    /**
     * Badge colour in Filament.
     *
     * @return string
     */
    public function getColor(): string
    {
        return match ($this) {
            self::PendingPayment => 'gray',
            self::Paid => 'info',
            self::InProgress => 'warning',
            self::Completed => 'success',
            self::NeedsAction => 'danger',
            self::Cancelled => 'gray',
        };
    }

    /**
     * Statuses this status may move to.
     *
     * @return array<int, self>
     */
    public function allowedNextStatuses(): array
    {
        return match ($this) {
            self::PendingPayment => [self::InProgress, self::NeedsAction, self::Cancelled],
            self::Paid => [self::InProgress, self::Completed, self::NeedsAction, self::Cancelled],
            self::InProgress => [self::Completed, self::NeedsAction, self::Cancelled],
            self::NeedsAction => [self::InProgress, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    /**
     * Whether moving from this status to the given one is allowed.
     *
     * @param  self  $next_status
     * @return bool
     */
    public function canTransitionTo(self $next_status): bool
    {
        return in_array($next_status, $this->allowedNextStatuses(), true);
    }
}
