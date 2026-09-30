<?php

namespace Tests\Concerns;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Testing\TestResponse;

/**
 * Pay for an order the way production does: an initiated payment, then a
 * signed Paystack charge.success webhook. Call use_paystack_test_key() in setUp.
 */
trait SendsPaystackWebhooks
{
    /**
     * Test-only Paystack secret (never a real key).
     *
     * @var string
     */
    protected string $paystack_test_secret = 'sk_test_made_easy_secret';

    /**
     * @return void
     */
    protected function use_paystack_test_key(): void
    {
        config([
            'services.paystack.secret_key' => $this->paystack_test_secret,
            'services.paystack.base_url' => 'https://api.paystack.co',
        ]);
    }

    /**
     * An initiated payment for the order's current total (as "Pay now" creates).
     *
     * @param  Order  $order
     * @return Payment
     */
    protected function initiated_payment_for(Order $order): Payment
    {
        $payment = new Payment(['order_id' => $order->id, 'paystack_reference' => Payment::newReference()]);
        $payment->forceFill(['amount' => $order->total_amount])->save();

        return $payment;
    }

    /**
     * Send a verified charge.success for the payment (amount defaults to the payment amount).
     *
     * @param  Payment  $payment
     * @param  int|null  $amount  KOBO.
     * @return TestResponse
     */
    protected function pay_with_webhook(Payment $payment, ?int $amount = null): TestResponse
    {
        $body = json_encode([
            'event' => 'charge.success',
            'data' => [
                'status' => 'success',
                'reference' => $payment->paystack_reference,
                'amount' => $amount ?? $payment->amount,
                'currency' => 'NGN',
                'paid_at' => now()->toIso8601String(),
                'channel' => 'card',
            ],
        ]);

        return $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, $this->paystack_test_secret),
        ], $body);
    }
}
