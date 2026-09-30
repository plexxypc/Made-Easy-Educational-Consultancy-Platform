<?php

namespace App\Actions\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Events\OrderPaid;
use App\Mail\PaymentReceiptMail;
use App\Models\Order;
use App\Models\OrderStatusChange;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use App\Notifications\PaymentNeedsReview;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * Apply a VERIFIED Paystack charge.success webhook. This is the only code that
 * marks an order paid (security rule 2). The caller must already have checked
 * the webhook signature (PaystackWebhookController).
 *
 * Business rules:
 *  - Idempotent (security rule 3): the payment is found by paystack_reference
 *    and locked; if it is already settled (successful, or flagged for review)
 *    nothing happens, so a repeated webhook never issues a second receipt/email.
 *  - Applied automatically only if ALL hold: Paystack status "success",
 *    currency NGN, amount EXACTLY equal to the order's current total_amount,
 *    and the order is still pending_payment (not deleted, cancelled or
 *    already paid by another attempt).
 *  - Otherwise the order is NOT marked paid. The payment keeps status
 *    "initiated" with review_reason set, an error is logged, and staff are
 *    notified: a human must decide (refund, top-up, or apply manually).
 *  - On success: payment "successful", order "paid", a status-history entry
 *    (shown to the customer), a receipt snapshot, and a queued receipt email.
 *    Then OrderPaid is dispatched (once, after commit); fulfilment such as
 *    result checker PIN allocation lives in its listeners, not here.
 */
class ConfirmPaystackCharge
{
    /**
     * Outcomes, for logging and tests.
     *
     * @var string
     */
    public const APPLIED = 'applied';

    /**
     * @var string
     */
    public const ALREADY_PROCESSED = 'already_processed';

    /**
     * @var string
     */
    public const FLAGGED = 'flagged';

    /**
     * @var string
     */
    public const UNKNOWN_REFERENCE = 'unknown_reference';

    /**
     * @param  array<string, mixed>  $charge  The webhook's "data" object.
     * @return string One of the outcome constants.
     */
    public function handle(array $charge): string
    {
        $reference = (string) ($charge['reference'] ?? '');
        $payment = $reference === '' ? null : Payment::query()->where('paystack_reference', $reference)->first();

        if ($payment === null) {
            Log::warning('Paystack charge.success for an unknown payment reference; ignored.', ['paystack_reference' => $reference]);

            return self::UNKNOWN_REFERENCE;
        }

        /** @var array{0: string, 1: Payment, 2: Receipt|null} $result */
        $result = DB::transaction(function () use ($payment, $charge): array {
            $locked_payment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($locked_payment->isSettled()) {
                return [self::ALREADY_PROCESSED, $locked_payment, null];
            }

            $order = Order::query()->withTrashed()->with(['service', 'user'])->whereKey($locked_payment->order_id)->lockForUpdate()->firstOrFail();
            $paid_amount = (int) ($charge['amount'] ?? 0);
            $problems = self::problems($order, $charge, $paid_amount);

            $locked_payment->recordResponse('webhook', $charge);
            $locked_payment->forceFill(['paid_amount' => $paid_amount]);

            if ($problems !== []) {
                $locked_payment->forceFill(['review_reason' => implode(' ', $problems)])->save();

                return [self::FLAGGED, $locked_payment, null];
            }

            $paid_at = self::paid_at($charge);
            $locked_payment->forceFill(['status' => PaymentStatus::Successful, 'paid_at' => $paid_at])->save();
            $order->forceFill(['status' => OrderStatus::Paid])->save();

            OrderStatusChange::create([
                'order_id' => $order->id,
                'changed_by' => null,
                'from_status' => OrderStatus::PendingPayment,
                'to_status' => OrderStatus::Paid,
                'note' => 'Payment of '.Money::format($paid_amount)." received (Paystack reference {$locked_payment->paystack_reference}).",
            ]);

            $receipt = Receipt::create([
                'payment_id' => $locked_payment->id,
                'order_reference' => $order->reference,
                'payment_reference' => $locked_payment->paystack_reference,
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'customer_phone' => $order->guest_phone,
                'service_name' => $order->serviceLabel(),
                'amount' => $paid_amount,
                'currency' => $locked_payment->currency,
                'payment_status' => PaymentStatus::Successful->getLabel(),
                'paid_at' => $paid_at,
            ]);

            return [self::APPLIED, $locked_payment, $receipt];
        });

        [$outcome, $payment, $receipt] = $result;

        if ($outcome === self::FLAGGED) {
            $this->report_for_review($payment);
        }

        if ($receipt !== null && $receipt->customer_email !== null) {
            Mail::to($receipt->customer_email)->queue(new PaymentReceiptMail($receipt));
        }

        if ($outcome === self::APPLIED) {
            OrderPaid::dispatch($payment->order, $payment);
        }

        return $outcome;
    }

    /**
     * Reasons the charge can't be applied automatically (empty if it can).
     *
     * @param  Order  $order
     * @param  array<string, mixed>  $charge
     * @param  int  $paid_amount  KOBO.
     * @return array<int, string>
     */
    private static function problems(Order $order, array $charge, int $paid_amount): array
    {
        $expected_currency = config('madeeasy.payments.currency');
        $currency = strtoupper((string) ($charge['currency'] ?? ''));

        return array_values(array_filter([
            ($charge['status'] ?? null) !== 'success'
                ? 'Paystack reported status "'.($charge['status'] ?? 'none').'", not "success".' : null,
            $currency !== $expected_currency
                ? "Paid in {$currency}, expected {$expected_currency}." : null,
            $paid_amount !== $order->total_amount
                ? 'Amount paid '.Money::format($paid_amount).' does not match the order total '.Money::format($order->total_amount).'.' : null,
            $order->trashed()
                ? 'The order has been deleted.' : null,
            ! $order->trashed() && $order->status !== OrderStatus::PendingPayment
                ? "The order is \"{$order->status->getLabel()}\", not \"Pending payment\" (e.g. already paid by another attempt, or cancelled)." : null,
        ]));
    }

    /**
     * When Paystack says the charge was paid (falls back to now).
     *
     * @param  array<string, mixed>  $charge
     * @return Carbon
     */
    private static function paid_at(array $charge): Carbon
    {
        $paid_at = $charge['paid_at'] ?? $charge['paidAt'] ?? null;

        try {
            return $paid_at ? Carbon::parse($paid_at) : now();
        } catch (\Throwable) {
            return now();
        }
    }

    /**
     * Log loudly and tell staff/admins a verified charge needs a human.
     *
     * @param  Payment  $payment
     * @return void
     */
    private function report_for_review(Payment $payment): void
    {
        $order = $payment->order;

        Log::error('Paystack payment NOT applied: needs manual review.', [
            'payment_id' => $payment->id,
            'paystack_reference' => $payment->paystack_reference,
            'order_id' => $order->id,
            'order_reference' => $order->reference,
            'order_status' => $order->status->value,
            'order_total_kobo' => $order->total_amount,
            'paid_amount_kobo' => $payment->paid_amount,
            'reason' => $payment->review_reason,
        ]);

        Notification::send(
            User::query()->whereIn('role', [UserRole::Staff->value, UserRole::Admin->value])->get(),
            new PaymentNeedsReview($payment),
        );
    }
}
