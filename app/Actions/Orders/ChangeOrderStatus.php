<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Mail\OrderStatusChangedMail;
use App\Models\Order;
use App\Models\OrderStatusChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Move an order to a new status, record the change and email the customer.
 *
 * The single entry point for staff-driven status changes (Filament actions
 * call this). Transition rules live in OrderStatus / Order::canTransitionTo().
 * Setting "paid" is refused here: only the verified payment webhook may do that.
 */
class ChangeOrderStatus
{
    /**
     * Statuses that must come with an explanation for the customer.
     *
     * @var array<int, OrderStatus>
     */
    public const NOTE_REQUIRED_FOR = [OrderStatus::NeedsAction];

    /**
     * Apply the transition.
     *
     * @param  Order  $order  The order to update.
     * @param  OrderStatus  $next_status  Target status.
     * @param  User  $actor  Staff/admin making the change.
     * @param  string|null  $note  Message for the customer (required for needs_action).
     * @return OrderStatusChange The recorded history row.
     *
     * @throws BusinessRuleException If the actor, transition or note is not allowed.
     */
    public function handle(Order $order, OrderStatus $next_status, User $actor, ?string $note = null): OrderStatusChange
    {
        if (! $actor->isStaffOrAdmin()) {
            throw new BusinessRuleException('Only staff and admins can change order status.');
        }

        if ($next_status === OrderStatus::Paid) {
            throw new BusinessRuleException('Orders are only marked paid by a verified payment.');
        }

        $note = filled($note) ? trim($note) : null;

        if ($note === null && in_array($next_status, self::NOTE_REQUIRED_FOR, true)) {
            throw new BusinessRuleException("A note for the customer is required when marking an order as \"{$next_status->getLabel()}\".");
        }

        $status_change = DB::transaction(function () use ($order, $next_status, $actor, $note): OrderStatusChange {
            $locked_order = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked_order->canTransitionTo($next_status)) {
                $reason = match (true) {
                    $locked_order->awaitsQuote() => 'Set the total for this quote order first.',
                    $locked_order->status->canTransitionTo($next_status) && $locked_order->awaitingPayment() => "This order hasn't been paid yet. It can move on once the customer's payment is confirmed.",
                    default => "An order that is \"{$locked_order->status->getLabel()}\" can't be marked \"{$next_status->getLabel()}\".",
                };

                throw new BusinessRuleException($reason);
            }

            $previous_status = $locked_order->status;

            $locked_order->forceFill(['status' => $next_status])->save();

            return OrderStatusChange::create([
                'order_id' => $locked_order->id,
                'changed_by' => $actor->id,
                'from_status' => $previous_status,
                'to_status' => $next_status,
                'note' => $note,
            ]);
        });

        $order->refresh();

        if ($order->customer_email !== null) {
            Mail::to($order->customer_email)->queue(new OrderStatusChangedMail($order, $status_change));
        }

        return $status_change;
    }
}
