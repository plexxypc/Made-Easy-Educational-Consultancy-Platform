<?php

namespace Tests\Feature\Pins;

use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Mail\ServiceRequestReceivedMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ResultCheckerPin;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * "Buy Result Checker": the store page and the short path to Paystack,
 * reusing the normal order creation and payment flow.
 */
class ResultCheckerStoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var Service
     */
    private Service $waec;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.paystack.secret_key' => 'sk_test_made_easy_secret', 'services.paystack.base_url' => 'https://api.paystack.co']);
        Mail::fake();
        Http::preventStrayRequests();

        $this->waec = Service::factory()->create(['name' => 'WAEC Result Checker', 'slug' => 'waec-result-checker', 'fulfilment_type' => FulfilmentType::Instant, 'price' => 650_000]);
    }

    /**
     * The page lists active, priced result checkers only, with a quantity selector; it replaces the placeholder.
     *
     * @return void
     */
    public function test_store_lists_active_result_checkers(): void
    {
        Service::factory()->create(['name' => 'NECO Result Checker', 'fulfilment_type' => FulfilmentType::Instant, 'price' => 100_000, 'is_active' => false]);
        Service::factory()->create(['name' => 'Birth Certificate', 'price' => 100_000]);
        ResultCheckerPin::factory()->for($this->waec)->create();

        $this->get(route('result-checker'))
            ->assertOk()
            ->assertSee('Buy Result Checker')
            ->assertSee('WAEC Result Checker')
            ->assertSee('₦6,500')
            ->assertSee('Delivered instantly by email')
            ->assertSee('name="quantity"', false)
            ->assertSee('<option value="10"', false)
            ->assertDontSee('<option value="11"', false)
            ->assertSee('Buy now')
            ->assertDontSee('NECO Result Checker')
            ->assertDontSee('Birth Certificate')
            ->assertDontSee('coming soon');

        $this->get(route('home'))->assertSee('href="'.route('result-checker').'"', false);
    }

    /**
     * Buy now creates one order (price × quantity) through the normal flow and goes straight to Paystack.
     *
     * @return void
     */
    public function test_buy_now_creates_order_and_redirects_to_paystack(): void
    {
        Http::fake(['api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => ['authorization_url' => 'https://checkout.paystack.com/xyz', 'access_code' => 'xyz'],
        ])]);

        $this->post(route('result-checker.buy'), [
            'service' => 'waec-result-checker',
            'quantity' => 3,
            'name' => 'Ada Obi',
            'email' => 'ada@example.com',
            'phone' => '08031234567',
        ])->assertRedirect('https://checkout.paystack.com/xyz');

        $order = Order::query()->sole();
        $this->assertSame($this->waec->id, $order->service_id);
        $this->assertSame(3, $order->quantity);
        $this->assertSame(1_950_000, $order->total_amount);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertNull($order->user_id);
        $this->assertSame(1_950_000, Payment::query()->sole()->amount);

        Http::assertSent(fn (HttpRequest $request): bool => $request['amount'] === 1_950_000 && $request['email'] === 'ada@example.com');
        // Instant fulfilment: the receipt + PIN email confirm receipt, so no "request received" email.
        Mail::assertNotQueued(ServiceRequestReceivedMail::class);

        // The guest can come back from Paystack without looking the order up.
        $this->get(route('orders.track.show', $order))->assertOk()->assertSee('WAEC Result Checker × 3');
    }

    /**
     * A signed-in customer's purchase belongs to their account and shows in My Requests.
     *
     * @return void
     */
    public function test_signed_in_customer_purchase_is_theirs(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/abc']])]);
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->post(route('result-checker.buy'), ['service' => 'waec-result-checker', 'quantity' => 1, 'name' => $customer->name, 'email' => $customer->email, 'phone' => '08031234567'])
            ->assertRedirect('https://checkout.paystack.com/abc');

        $this->assertSame($customer->id, Order::query()->sole()->user_id);
        $this->actingAs($customer)->get(route('my-requests'))->assertOk()->assertSee('WAEC Result Checker');
    }

    /**
     * Invalid quantity, unknown or inactive checkers, and non-checker services are refused.
     *
     * @return void
     */
    public function test_invalid_purchases_are_rejected(): void
    {
        $inactive = Service::factory()->create(['fulfilment_type' => FulfilmentType::Instant, 'price' => 100_000, 'is_active' => false]);
        $manual = Service::factory()->create();
        $contact = ['name' => 'Ada Obi', 'email' => 'ada@example.com', 'phone' => '08031234567'];

        $this->from(route('result-checker'))->post(route('result-checker.buy'), ['service' => 'waec-result-checker', 'quantity' => 0] + $contact)
            ->assertRedirect(route('result-checker'))->assertSessionHasErrors('quantity');
        $this->post(route('result-checker.buy'), ['service' => 'waec-result-checker', 'quantity' => 11] + $contact)->assertSessionHasErrors('quantity');
        $this->post(route('result-checker.buy'), ['service' => $inactive->slug, 'quantity' => 1] + $contact)->assertSessionHasErrors('service');
        $this->post(route('result-checker.buy'), ['service' => $manual->slug, 'quantity' => 1] + $contact)->assertSessionHasErrors('service');
        $this->post(route('result-checker.buy'), ['service' => 'waec-result-checker', 'quantity' => 1, 'name' => 'Ada'])->assertSessionHasErrors(['email', 'phone']);

        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    /**
     * If Paystack can't be reached, the order exists and the customer lands on it with "Pay now".
     *
     * @return void
     */
    public function test_paystack_failure_lands_on_order_page(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Down'], 503)]);

        $response = $this->post(route('result-checker.buy'), ['service' => 'waec-result-checker', 'quantity' => 2, 'name' => 'Ada Obi', 'email' => 'ada@example.com', 'phone' => '08031234567']);

        $order = Order::query()->sole();
        $response->assertRedirect(route('orders.track.show', $order))->assertSessionHasErrors('payment');

        $this->get(route('orders.track.show', $order))
            ->assertOk()
            ->assertSee('Pay ₦13,000.00 now');
    }

    /**
     * Result checkers still can't be ordered through the generic request form, and other services stay at quantity 1.
     *
     * @return void
     */
    public function test_generic_form_is_unchanged(): void
    {
        $this->post(route('services.request', ['category' => $this->waec->category, 'service' => $this->waec]), [
            'name' => 'Ada', 'phone' => '08031234567', 'email' => 'ada@example.com',
        ])->assertForbidden();

        $manual = Service::factory()->create(['price' => 500_000]);
        $this->post(route('services.request', ['category' => $manual->category, 'service' => $manual]), [
            'name' => 'Ada', 'phone' => '08031234567', 'email' => 'ada@example.com', 'quantity' => 5,
        ])->assertRedirect();

        $order = Order::query()->sole();
        $this->assertSame(1, $order->quantity);
        $this->assertSame(500_000, $order->total_amount);
    }
}
