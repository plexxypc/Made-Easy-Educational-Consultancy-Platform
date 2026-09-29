<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Record the staff-determined price of a quote-type order.
 *
 * Only while the order is still pending payment: once work starts (or the
 * customer pays), the total is fixed. Can be re-run to correct the quote
 * before then.
 */
class SetOrderTotal
{
    /**
     * Set the total.
     *
     * @param  Order  $order  A quote-type order.
     * @param  int  $total_amount  New total in KOBO (> 0).
     * @param  User  $actor  Admin setting the price.
     * @return Order The updated order.
     *
     * @throws BusinessRuleException If the actor, order or amount is not allowed.
     */
    public function handle(Order $order, int $total_amount, User $actor): Order
    {
        if (! $actor->isAdmin()) {
            throw new BusinessRuleException('Only admins can set an order total.');
        }

        if ($total_amount <= 0) {
            throw new BusinessRuleException('The total must be greater than ₦0.');
        }

        DB::transaction(function () use ($order, $total_amount, $actor): void {
            $locked_order = Order::query()->with('service')->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked_order->isQuote()) {
                throw new BusinessRuleException('Only quote-priced orders can have their total set manually.');
            }

            if ($locked_order->status !== OrderStatus::PendingPayment) {
                throw new BusinessRuleException('The total can only be set while the order is pending payment.');
            }

            $locked_order->forceFill([
                'total_amount' => $total_amount,
                'quoted_at' => now(),
                'quoted_by' => $actor->id,
            ])->save();
        });

        return $order->refresh();
    }
}
