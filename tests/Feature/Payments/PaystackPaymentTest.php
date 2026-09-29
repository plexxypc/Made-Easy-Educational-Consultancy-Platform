<?php

namespace Tests\Feature\Payments;

use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Mail\PaymentReceiptMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Service;
use App\Models\User;
use App\Notifications\PaymentNeedsReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Paystack: starting a payment, the webhook (the only thing that marks an
 * order paid), the display-only return page, and receipts.
 */
class PaystackPaymentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var string
     */
    private const SECRET_KEY = 'sk_test_made_easy_secret';

    /**
     * Guest order for ₦15,000.
     *
     * @var Order
     */
    private Order $order;

    /**
     * @var User
     */
    private User $staff_user;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.paystack.secret_key' => self::SECRET_KEY, 'services.paystack.base_url' => 'https://api.paystack.co']);
        Mail::fake();
        Http::preventStrayRequests();

        $this->staff_user = User::factory()->staff()->create();
        $service = Service::factory()->create(['name' => 'Birth Certificate', 'price' => 1_500_000]);
        $this->order = Order::factory()->guest()->for($service)->create([
            'guest_name' => 'Ada Obi',
            'guest_email' => 'ada@example.com',
            'guest_phone' => '08031234567',
        ]);
    }

    /**
     * New orders get a random "ME-" + 6 Crockford base32 characters, all different.
     *
     * @return void
     */
    public function test_new_orders_get_random_unique_references(): void
    {
        $references = Order::factory()->count(30)->create()->pluck('reference')->push($this->order->reference);

        foreach ($references as $reference) {
            $this->assertMatchesRegularExpression('/^ME-[0-9ABCDEFGHJKMNPQRSTVWXYZ]{6}$/', $reference);
        }

        $this->assertCount($references->count(), $references->unique());
        $this->assertSame($this->order->reference, $this->order->display_reference);
    }

    /**
     * Typed references are normalised; old numeric references keep working.
     *
     * @return void
     */
    public function test_references_are_normalised_and_legacy_numbers_still_work(): void
    {
        $this->assertSame('ME-7K3Q9A', Order::normaliseReference(' me 7k3q9a '));
        $this->assertSame('ME-7K3Q9A', Order::normaliseReference('#ME7K3Q9A'));
        $this->assertSame('ME-0K1Q1A', Order::normaliseReference('ME-OKIQLA'));
        $this->assertSame('12', Order::normaliseReference('#12'));

        $legacy_order = Order::factory()->guest()->create(['guest_email' => 'old@example.com']);
        $legacy_order->forceFill(['reference' => '12'])->save();
        $this->assertSame('#12', $legacy_order->display_reference);

        $this->post(route('orders.track.lookup'), ['reference' => '#12', 'contact' => 'old@example.com'])
            ->assertRedirect(route('orders.track.show', $legacy_order));
        $this->get('/track/12')->assertOk()->assertSee('Reference #12');
    }

    /**
     * Order URLs use the reference; the numeric id doesn't open anything.
     *
     * @return void
     */
    public function test_order_urls_use_the_reference(): void
    {
        $this->assertStringEndsWith("/track/{$this->order->reference}", route('orders.track.show', $this->order));
        $this->get('/track/'.$this->order->id)->assertNotFound();
    }

    /**
     * "Pay now" starts a Paystack transaction for the order total and redirects to checkout.
     *
     * @return void
     */
    public function test_pay_now_initiates_paystack_transaction(): void
    {
        Http::fake(['api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'message' => 'Authorization URL created',
            'data' => ['authorization_url' => 'https://checkout.paystack.com/abc123', 'access_code' => 'abc123', 'reference' => 'ignored'],
        ])]);

        $this->look_up();

        $this->get(route('orders.track.show', $this->order))
            ->assertOk()
            ->assertSee('Amount to pay: ₦15,000.00')
            ->assertSee('action="'.route('orders.pay', $this->order).'"', false);

        $this->post(route('orders.pay', $this->order))->assertRedirect('https://checkout.paystack.com/abc123');

        $payment = Payment::query()->sole();
        $this->assertSame($this->order->id, $payment->order_id);
        $this->assertSame(PaymentStatus::Initiated, $payment->status);
        $this->assertSame(1_500_000, $payment->amount);
        $this->assertStringStartsWith(Payment::REFERENCE_PREFIX, $payment->paystack_reference);
        $this->assertSame('abc123', $payment->raw_response['initialize']['access_code']);
        $this->assertSame(OrderStatus::PendingPayment, $this->order->refresh()->status);

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.paystack.co/transaction/initialize'
            && $request->hasHeader('Authorization', 'Bearer '.self::SECRET_KEY)
            && $request['amount'] === 1_500_000
            && $request['email'] === 'ada@example.com'
            && $request['currency'] === 'NGN'
            && $request['reference'] === $payment->paystack_reference
            && $request['callback_url'] === route('orders.payment.return', $this->order));
    }

    /**
     * A quote order with no total set can't be paid: clear message, no payment, no Paystack call.
     *
     * @return void
     */
    public function test_quote_order_without_total_cannot_be_paid(): void
    {
        $quote_order = Order::factory()->guest()
            ->for(Service::factory()->state(['fulfilment_type' => FulfilmentType::Quote, 'price' => 0]))
            ->create(['guest_email' => 'ada@example.com']);
        $this->assertTrue($quote_order->awaitsQuote());

        $this->look_up($quote_order);

        $this->get(route('orders.track.show', $quote_order))
            ->assertOk()
            ->assertSee('Price not set yet')
            ->assertDontSee(route('orders.pay', $quote_order));

        $this->post(route('orders.pay', $quote_order))
            ->assertRedirect(route('orders.track.show', $quote_order))
            ->assertSessionHasErrors(['payment' => "We're still preparing the price for this request. You'll be able to pay once we've confirmed it; we'll email you."]);

        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }

    /**
     * Once an admin sets the total, the quote order becomes payable.
     *
     * @return void
     */
    public function test_quote_order_is_payable_after_set_total(): void
    {
        $quote_order = Order::factory()->guest()
            ->for(Service::factory()->state(['fulfilment_type' => FulfilmentType::Quote, 'price' => 0]))
            ->create();
        $quote_order->forceFill(['total_amount' => 2_000_000, 'quoted_at' => now()])->save();

        $this->assertTrue($quote_order->refresh()->canBePaid());
    }

    /**
     * Zero-total, paid, cancelled and needs-action orders can't be paid.
     *
     * @return void
     */
    public function test_only_pending_orders_with_a_total_can_be_paid(): void
    {
        $free_order = Order::factory()->for(Service::factory()->state(['price' => 0]))->create();
        $this->assertSame('There is nothing to pay for this request.', $free_order->paymentBlocker());

        foreach ([OrderStatus::Paid, OrderStatus::Cancelled, OrderStatus::NeedsAction, OrderStatus::InProgress] as $status) {
            $this->order->forceFill(['status' => $status])->save();
            $this->assertFalse($this->order->canBePaid(), "{$status->value} order was payable.");
        }
    }

    /**
     * Paystack refusing to start a transaction marks the attempt failed and tells the customer.
     *
     * @return void
     */
    public function test_paystack_initialize_failure_marks_payment_failed(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Invalid key'], 401)]);
        $this->look_up();

        $this->post(route('orders.pay', $this->order))
            ->assertRedirect(route('orders.track.show', $this->order))
            ->assertSessionHasErrors(['payment' => "We couldn't start the payment. Please try again in a moment."]);

        $payment = Payment::query()->sole();
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('Invalid key', $payment->raw_response['initialize_error']['message']);
    }

    /**
     * Paying requires the same access as the order page.
     *
     * @return void
     */
    public function test_pay_requires_access(): void
    {
        $this->post(route('orders.pay', $this->order))->assertRedirect(route('orders.track'));

        $this->assertDatabaseCount('payments', 0);
    }

    /**
     * A verified webhook with the exact amount marks the order paid, creates the receipt and emails it.
     *
     * @return void
     */
    public function test_verified_webhook_marks_order_paid_and_issues_receipt(): void
    {
        $payment = $this->initiated_payment();

        $this->webhook($this->charge_success($payment))->assertOk();

        $payment->refresh();
        $this->order->refresh();
        $this->assertSame(PaymentStatus::Successful, $payment->status);
        $this->assertSame(1_500_000, $payment->paid_amount);
        $this->assertSame('2026-09-29 10:15:00', $payment->paid_at->format('Y-m-d H:i:s'));
        $this->assertNull($payment->review_reason);
        $this->assertSame(OrderStatus::Paid, $this->order->status);

        $receipt = Receipt::query()->sole();
        $this->assertSame($payment->id, $receipt->payment_id);
        $this->assertSame($this->order->reference, $receipt->order_reference);
        $this->assertSame($payment->paystack_reference, $receipt->payment_reference);
        $this->assertSame('Ada Obi', $receipt->customer_name);
        $this->assertSame('ada@example.com', $receipt->customer_email);
        $this->assertSame('08031234567', $receipt->customer_phone);
        $this->assertSame('Birth Certificate', $receipt->service_name);
        $this->assertSame(1_500_000, $receipt->amount);
        $this->assertSame('Successful', $receipt->payment_status);
        $this->assertMatchesRegularExpression('/^MER-\d{6}$/', $receipt->number);

        $history = $this->order->statusChanges()->first();
        $this->assertSame(OrderStatus::PendingPayment, $history->from_status);
        $this->assertSame(OrderStatus::Paid, $history->to_status);
        $this->assertSame('Paystack (verified payment)', $history->actorLabel());

        Mail::assertQueued(PaymentReceiptMail::class, function (PaymentReceiptMail $mail) use ($receipt, $payment): bool {
            $mail->assertSeeInHtml($receipt->number);
            $mail->assertSeeInHtml($payment->paystack_reference);
            $mail->assertSeeInHtml('Birth Certificate');
            $mail->assertSeeInHtml('₦15,000.00');
            $mail->assertSeeInHtml('Successful');

            return $mail->hasTo('ada@example.com');
        });
    }

    /**
     * The receipt is viewable and downloadable from the order page (with access only).
     *
     * @return void
     */
    public function test_receipt_is_downloadable_from_order_page(): void
    {
        $payment = $this->initiated_payment();
        $this->webhook($this->charge_success($payment))->assertOk();
        $receipt = Receipt::query()->sole();

        $this->get(route('orders.receipt', $this->order))->assertRedirect(route('orders.track'));

        $this->look_up();

        $this->get(route('orders.track.show', $this->order))
            ->assertOk()
            ->assertSee('Paid: ₦15,000.00')
            ->assertSee(route('orders.receipt', $this->order))
            ->assertDontSee(route('orders.pay', $this->order));

        $this->get(route('orders.receipt', $this->order))
            ->assertOk()
            ->assertSee('Payment receipt')
            ->assertSee($receipt->number)
            ->assertSee($payment->paystack_reference)
            ->assertSee('Ada Obi')
            ->assertSee('Birth Certificate')
            ->assertSee('₦15,000.00')
            ->assertSee('Successful');

        $this->get(route('orders.receipt', ['order' => $this->order, 'download' => 1]))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="receipt-'.$receipt->number.'.html"');
    }

    /**
     * The same event delivered twice is processed once: one receipt, one email, one history entry.
     *
     * @return void
     */
    public function test_duplicate_webhook_is_processed_once(): void
    {
        $payment = $this->initiated_payment();
        $event = $this->charge_success($payment);

        $this->webhook($event)->assertOk();
        $this->webhook($event)->assertOk();
        $this->webhook($event)->assertOk();

        $this->assertSame(1, Receipt::query()->count());
        $this->assertSame(1, $this->order->statusChanges()->where('to_status', OrderStatus::Paid->value)->count());
        $this->assertSame(OrderStatus::Paid, $this->order->refresh()->status);
        Mail::assertQueuedCount(1);
    }

    /**
     * A bad or missing signature is rejected and changes nothing.
     *
     * @return void
     */
    public function test_webhook_with_bad_signature_is_rejected(): void
    {
        $payment = $this->initiated_payment();
        $event = $this->charge_success($payment);

        $this->webhook($event, signature: hash_hmac('sha512', json_encode($event), 'sk_test_wrong_key'))->assertUnauthorized();
        $this->webhook($event, signature: '')->assertUnauthorized();

        // Signed body, but the body was tampered with afterwards.
        $signature = hash_hmac('sha512', json_encode($event), self::SECRET_KEY);
        $event['data']['amount'] = 100;
        $this->webhook($event, signature: $signature)->assertUnauthorized();

        $this->assertSame(PaymentStatus::Initiated, $payment->refresh()->status);
        $this->assertNull($payment->paid_amount);
        $this->assertSame(OrderStatus::PendingPayment, $this->order->refresh()->status);
        $this->assertDatabaseCount('receipts', 0);
        Mail::assertNothingQueued();
    }

    /**
     * Without a configured secret key every webhook is rejected.
     *
     * @return void
     */
    public function test_webhook_rejected_when_secret_not_configured(): void
    {
        $payment = $this->initiated_payment();
        $event = $this->charge_success($payment);
        $signature = hash_hmac('sha512', json_encode($event), '');
        config(['services.paystack.secret_key' => null]);

        $this->webhook($event, signature: $signature)->assertUnauthorized();

        $this->assertSame(OrderStatus::PendingPayment, $this->order->refresh()->status);
    }

    /**
     * An amount that doesn't match the order total is NOT applied: logged, flagged, staff told.
     *
     * @return void
     */
    public function test_amount_mismatch_does_not_mark_order_paid(): void
    {
        Notification::fake();
        Log::spy();
        $payment = $this->initiated_payment();

        $this->webhook($this->charge_success($payment, amount: 1_000_000))->assertOk();

        $payment->refresh();
        $this->assertSame(OrderStatus::PendingPayment, $this->order->refresh()->status);
        $this->assertSame(PaymentStatus::Initiated, $payment->status);
        $this->assertSame(1_000_000, $payment->paid_amount);
        $this->assertSame('Amount paid ₦10,000.00 does not match the order total ₦15,000.00.', $payment->review_reason);
        $this->assertTrue($payment->needsReview());
        $this->assertDatabaseCount('receipts', 0);
        Mail::assertNothingQueued();

        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context): bool => str_contains($message, 'NOT applied')
            && $context['paid_amount_kobo'] === 1_000_000
            && $context['order_total_kobo'] === 1_500_000);
        Notification::assertSentTo($this->staff_user, PaymentNeedsReview::class);

        // A repeat delivery doesn't notify again or change anything.
        $this->webhook($this->charge_success($payment, amount: 1_000_000))->assertOk();
        Notification::assertSentToTimes($this->staff_user, PaymentNeedsReview::class, 1);
        $this->assertSame(OrderStatus::PendingPayment, $this->order->refresh()->status);
    }

    /**
     * Wrong currency is treated like a mismatch.
     *
     * @return void
     */
    public function test_wrong_currency_is_not_applied(): void
    {
        Notification::fake();
        $payment = $this->initiated_payment();

        $this->webhook($this->charge_success($payment, currency: 'USD'))->assertOk();

        $this->assertSame(OrderStatus::PendingPayment, $this->order->refresh()->status);
        $this->assertStringContainsString('Paid in USD, expected NGN.', $payment->refresh()->review_reason);
    }

    /**
     * A second successful charge for an order that is already paid is flagged, not applied twice.
     *
     * @return void
     */
    public function test_second_payment_for_paid_order_is_flagged(): void
    {
        Notification::fake();
        $first_payment = $this->initiated_payment();
        $second_payment = $this->initiated_payment();

        $this->webhook($this->charge_success($first_payment))->assertOk();
        $this->webhook($this->charge_success($second_payment))->assertOk();

        $this->assertSame(PaymentStatus::Successful, $first_payment->refresh()->status);
        $this->assertSame(PaymentStatus::Initiated, $second_payment->refresh()->status);
        $this->assertStringContainsString('The order is "Paid"', $second_payment->review_reason);
        $this->assertSame(1, Receipt::query()->count());
        Notification::assertSentTo($this->staff_user, PaymentNeedsReview::class);

        $this->actingAs($this->staff_user)
            ->get(OrderResource::getUrl('view', ['record' => $this->order]))
            ->assertOk()
            ->assertSee('Payment needs review')
            ->assertSee($second_payment->paystack_reference);
    }

    /**
     * Unknown references and other events are acknowledged and ignored.
     *
     * @return void
     */
    public function test_unknown_reference_and_other_events_are_ignored(): void
    {
        $payment = $this->initiated_payment();

        $this->webhook(['event' => 'charge.success', 'data' => ['reference' => 'MEPAY-NOT-OURS', 'amount' => 1_500_000, 'currency' => 'NGN', 'status' => 'success']])->assertOk();
        $this->webhook(['event' => 'transfer.success', 'data' => ['reference' => $payment->paystack_reference]])->assertOk();

        $this->assertSame(PaymentStatus::Initiated, $payment->refresh()->status);
        $this->assertSame(OrderStatus::PendingPayment, $this->order->refresh()->status);
    }

    /**
     * The return page asks Paystack but NEVER marks anything paid, even when Paystack says "success".
     *
     * @return void
     */
    public function test_return_page_is_display_only(): void
    {
        $payment = $this->initiated_payment();
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => ['status' => 'success', 'reference' => $payment->paystack_reference, 'amount' => 1_500_000, 'currency' => 'NGN'],
        ])]);
        $this->look_up();

        $this->get(route('orders.payment.return', ['order' => $this->order, 'reference' => $payment->paystack_reference, 'trxref' => $payment->paystack_reference]))
            ->assertOk()
            ->assertSee('Payment received, confirming')
            ->assertSee('http-equiv="refresh"', false);

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.paystack.co/transaction/verify/'.$payment->paystack_reference);
        $this->assertSame(PaymentStatus::Initiated, $payment->refresh()->status);
        $this->assertSame(OrderStatus::PendingPayment, $this->order->refresh()->status);
        $this->assertDatabaseCount('receipts', 0);
        Mail::assertNothingQueued();

        // Once the verified webhook has landed, the page says so without asking Paystack again.
        $this->webhook($this->charge_success($payment))->assertOk();

        $this->get(route('orders.payment.return', ['order' => $this->order, 'reference' => $payment->paystack_reference]))
            ->assertRedirect(route('orders.track.show', $this->order))
            ->assertSessionHas('status', "Payment confirmed. Thank you! We've emailed your receipt.");
    }

    /**
     * A failed or abandoned checkout sends the customer back with a message; nothing changes.
     *
     * @return void
     */
    public function test_return_page_for_abandoned_payment(): void
    {
        $payment = $this->initiated_payment();
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => 'abandoned']])]);
        $this->look_up();

        $this->get(route('orders.payment.return', ['order' => $this->order, 'reference' => $payment->paystack_reference]))
            ->assertRedirect(route('orders.track.show', $this->order))
            ->assertSessionHasErrors('payment');

        $this->assertSame(PaymentStatus::Initiated, $payment->refresh()->status);
    }

    /**
     * After the last automatic check the page stops refreshing and explains.
     *
     * @return void
     */
    public function test_return_page_stops_refreshing(): void
    {
        $payment = $this->initiated_payment();
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => 'success']])]);
        $this->look_up();

        $this->get(route('orders.payment.return', ['order' => $this->order, 'reference' => $payment->paystack_reference, 'check' => config('madeeasy.payments.return_page_max_checks')]))
            ->assertOk()
            ->assertSee('Your payment is still being confirmed')
            ->assertDontSee('http-equiv="refresh"', false);
    }

    /**
     * An initiated payment for $this->order (as "Pay now" would create).
     *
     * @return Payment
     */
    private function initiated_payment(): Payment
    {
        $payment = new Payment(['order_id' => $this->order->id, 'paystack_reference' => Payment::newReference()]);
        $payment->forceFill(['amount' => $this->order->total_amount])->save();

        return $payment;
    }

    /**
     * A charge.success event as Paystack sends it.
     *
     * @param  Payment  $payment
     * @param  int|null  $amount  KOBO; defaults to the payment amount.
     * @param  string  $currency
     * @return array<string, mixed>
     */
    private function charge_success(Payment $payment, ?int $amount = null, string $currency = 'NGN'): array
    {
        return [
            'event' => 'charge.success',
            'data' => [
                'id' => 302961,
                'domain' => 'test',
                'status' => 'success',
                'reference' => $payment->paystack_reference,
                'amount' => $amount ?? $payment->amount,
                'currency' => $currency,
                'paid_at' => '2026-09-29T10:15:00.000Z',
                'channel' => 'card',
                'customer' => ['email' => 'ada@example.com'],
            ],
        ];
    }

    /**
     * POST the event to the webhook with a signature (valid by default).
     *
     * @param  array<string, mixed>  $event
     * @param  string|null  $signature  Defaults to a valid HMAC-SHA512 of the body.
     * @return TestResponse
     */
    private function webhook(array $event, ?string $signature = null): TestResponse
    {
        $body = json_encode($event);

        return $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature ?? hash_hmac('sha512', $body, self::SECRET_KEY),
        ], $body);
    }

    /**
     * Look up an order as its guest customer (ada@example.com).
     *
     * @param  Order|null  $order
     * @return void
     */
    private function look_up(?Order $order = null): void
    {
        $order ??= $this->order;

        $this->post(route('orders.track.lookup'), ['reference' => $order->reference, 'contact' => 'ada@example.com'])
            ->assertRedirect(route('orders.track.show', $order));
    }
}
