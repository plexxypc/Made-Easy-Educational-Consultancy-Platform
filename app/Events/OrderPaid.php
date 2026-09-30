<?php

namespace App\Events;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An order has just been marked paid by a verified Paystack charge
 * (ConfirmPaystackCharge). Dispatched once per order, after the payment
 * transaction has committed; a repeated webhook never dispatches it again.
 *
 * Listeners do the fulfilment work (e.g. AllocateResultCheckerPins), so the
 * webhook handler stays about payments only.
 */
class OrderPaid
{
    use Dispatchable, SerializesModels;

    /**
     * @param  Order  $order  The order, now "paid".
     * @param  Payment  $payment  The successful payment.
     */
    public function __construct(
        public Order $order,
        public Payment $payment,
    ) {}
}
