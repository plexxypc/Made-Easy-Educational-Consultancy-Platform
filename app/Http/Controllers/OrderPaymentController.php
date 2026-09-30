<?php

namespace App\Http\Controllers;

use App\Actions\Payments\InitiatePayment;
use App\Actions\Receipts\RenderReceiptPdf;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Requests\PayForOrderRequest;
use App\Models\Order;
use App\Services\Paystack\PaystackClient;
use App\Services\Paystack\PaystackException;
use App\Support\TrackedOrders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Customer side of payments: "Pay now", the page Paystack returns to, and the receipt.
 *
 * Access to each: TrackedOrders::allows() (owner, or a recent guest lookup).
 */
class OrderPaymentController extends Controller
{
    /**
     * Start a Paystack transaction and send the customer to Paystack's checkout.
     *
     * @param  PayForOrderRequest  $request
     * @param  Order  $order
     * @param  InitiatePayment  $initiate_payment
     * @return RedirectResponse
     */
    public function pay(PayForOrderRequest $request, Order $order, InitiatePayment $initiate_payment): RedirectResponse
    {
        try {
            $checkout_url = $initiate_payment->handle($order);
        } catch (BusinessRuleException $exception) {
            return redirect()
                ->route('orders.track.show', $order)
                ->withErrors(['payment' => $exception->getMessage()]);
        }

        return redirect()->away($checkout_url);
    }

    /**
     * Where Paystack sends the browser after checkout (?reference=...).
     *
     * DISPLAY ONLY: this asks Paystack's verify API how the transaction went,
     * purely to tell the customer what is happening. It never writes anything;
     * the order is marked paid only by the verified webhook (ConfirmPaystackCharge).
     * While Paystack says "success" but the webhook hasn't landed yet, the page
     * refreshes itself every 5 seconds, up to config('madeeasy.payments.return_page_max_checks') times.
     *
     * @param  Request  $request
     * @param  Order  $order
     * @param  PaystackClient  $paystack
     * @return View|RedirectResponse
     */
    public function payment_return(Request $request, Order $order, PaystackClient $paystack): View|RedirectResponse
    {
        if (! TrackedOrders::allows($request, $order)) {
            return redirect()
                ->route('orders.track')
                ->withInput(['reference' => $order->reference])
                ->with('status', 'Look up your request to see your payment status.');
        }

        $reference = (string) ($request->query('reference') ?? $request->query('trxref') ?? '');
        $payment = $reference === '' ? null : $order->payments()->where('paystack_reference', $reference)->first();

        if ($payment === null) {
            return redirect()->route('orders.track.show', $order);
        }

        if ($payment->status === PaymentStatus::Successful) {
            return redirect()
                ->route('orders.track.show', $order)
                ->with('status', "Payment confirmed. Thank you! We've emailed your receipt.");
        }

        if ($payment->needsReview()) {
            return redirect()
                ->route('orders.track.show', $order)
                ->with('status', "We've received a payment notice for this request and our team is checking it. We'll contact you shortly.");
        }

        try {
            $paystack_status = (string) ($paystack->verify($reference)['status'] ?? 'unknown');
        } catch (PaystackException) {
            $paystack_status = 'unknown';
        }

        if (in_array($paystack_status, ['failed', 'abandoned', 'reversed'], true)) {
            return redirect()
                ->route('orders.track.show', $order)
                ->withErrors(['payment' => "Your payment wasn't completed, so you haven't been charged. You can try again below."]);
        }

        $check = max(1, (int) $request->query('check', 1));
        $keep_checking = $check < (int) config('madeeasy.payments.return_page_max_checks');

        return view('orders.payment-return', [
            'order' => $order,
            'paystack_says_paid' => $paystack_status === 'success',
            'refresh_url' => $keep_checking
                ? route('orders.payment.return', ['order' => $order, 'reference' => $reference, 'check' => $check + 1])
                : null,
        ]);
    }

    /**
     * The receipt for a paid order as a PDF: opened in the browser, or saved
     * as a file with ?download=1. Same PDF as the one attached to the email.
     *
     * @param  Request  $request
     * @param  Order  $order
     * @param  RenderReceiptPdf  $render_receipt_pdf
     * @return Response|RedirectResponse
     */
    public function receipt(Request $request, Order $order, RenderReceiptPdf $render_receipt_pdf): Response|RedirectResponse
    {
        if (! TrackedOrders::allows($request, $order)) {
            return redirect()
                ->route('orders.track')
                ->withInput(['reference' => $order->reference])
                ->with('status', 'Look up your request to view its receipt.');
        }

        $receipt = $order->receipt;

        if ($receipt === null) {
            return redirect()->route('orders.track.show', $order);
        }

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($render_receipt_pdf->handle($receipt), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$render_receipt_pdf->file_name($receipt).'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
