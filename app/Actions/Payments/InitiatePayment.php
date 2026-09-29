<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Paystack\PaystackClient;
use App\Services\Paystack\PaystackException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * "Pay now": start a Paystack transaction for the order's total.
 *
 * Business rules:
 *  - Only a payable order (Order::paymentBlocker() is null): pending_payment,
 *    total above zero, and for quote services only after "Set Total".
 *  - The amount is always orders.total_amount, read under a row lock; nothing
 *    from the browser is used.
 *  - Every click creates a new `initiated` payment with a fresh reference
 *    (Paystack references can't be reused). Abandoned attempts simply stay
 *    `initiated`; only a verified webhook ever marks anything paid.
 *  - If Paystack refuses to start the transaction the payment is `failed`.
 */
class InitiatePayment
{
    /**
     * @param  PaystackClient  $paystack
     */
    public function __construct(
        private readonly PaystackClient $paystack,
    ) {}

    /**
     * @param  Order  $order
     * @return string Paystack checkout URL to redirect the customer to.
     *
     * @throws BusinessRuleException
     */
    public function handle(Order $order): string
    {
        if (! $this->paystack->isConfigured()) {
            throw new BusinessRuleException('Online payment is not available at the moment. Please contact us to pay.');
        }

        $payment = DB::transaction(function () use ($order): Payment {
            $locked_order = Order::query()->with('service')->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $blocker = $locked_order->paymentBlocker();

            if ($blocker !== null) {
                throw new BusinessRuleException($blocker);
            }

            $payment = new Payment([
                'order_id' => $locked_order->id,
                'paystack_reference' => Payment::newReference(),
                'currency' => config('madeeasy.payments.currency'),
            ]);
            $payment->forceFill(['amount' => $locked_order->total_amount, 'status' => PaymentStatus::Initiated])->save();

            return $payment;
        });

        $order->refresh();

        try {
            $checkout = $this->paystack->initialize(
                email: (string) $order->customer_email,
                amount: $payment->amount,
                reference: $payment->paystack_reference,
                callback_url: route('orders.payment.return', $order),
                metadata: [
                    'order_id' => $order->id,
                    'order_reference' => $order->reference,
                    'payment_id' => $payment->id,
                    'service' => $order->service->name,
                    'cancel_action' => route('orders.track.show', $order),
                ],
            );
        } catch (PaystackException $exception) {
            $payment->recordResponse('initialize_error', ['message' => $exception->getMessage(), 'response' => $exception->response]);
            $payment->forceFill(['status' => PaymentStatus::Failed])->save();

            Log::warning('Paystack transaction could not be started.', [
                'payment_id' => $payment->id,
                'paystack_reference' => $payment->paystack_reference,
                'order_reference' => $order->reference,
                'error' => $exception->getMessage(),
            ]);

            throw new BusinessRuleException("We couldn't start the payment. Please try again in a moment.");
        }

        $payment->recordResponse('initialize', $checkout);
        $payment->save();

        if (blank($checkout['authorization_url'] ?? null)) {
            throw new BusinessRuleException("We couldn't start the payment. Please try again in a moment.");
        }

        return (string) $checkout['authorization_url'];
    }
}
