<?php

namespace Tests\Feature\Orders;

use App\Actions\Orders\ChangeOrderStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\Service;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Guest "Track your request" lookup, the order page, and "My Requests".
 */
class OrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var Order
     */
    private Order $order;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $service = Service::factory()->create([
            'name' => 'Birth Certificate',
            'customer_fields' => [
                ['key' => 'state_of_origin', 'label' => 'State of origin', 'type' => 'select', 'required' => true, 'options' => ['Osun', 'Oyo']],
                ['key' => 'nin_slip', 'label' => 'NIN slip', 'type' => 'file', 'required' => true],
            ],
        ]);

        $this->order = Order::factory()->guest()->for($service)->create([
            'guest_name' => 'Ada Obi',
            'guest_email' => 'Ada.Obi@Example.com',
            'guest_phone' => '0803 123 4567',
            'field_answers' => ['state_of_origin' => 'Osun', 'nin_slip' => 'my NIN slip.pdf'],
        ]);

        OrderDocument::factory()->for($this->order)->create([
            'field_key' => 'nin_slip',
            'original_filename' => 'my NIN slip.pdf',
        ]);
    }

    /**
     * Correct reference + email (any case, with "#") opens the order with its details.
     *
     * @return void
     */
    public function test_guest_can_look_up_own_order_by_email(): void
    {
        $response = $this->post(route('orders.track.lookup'), [
            'reference' => ' #'.strtolower(str_replace('-', '', $this->order->reference)),
            'contact' => '  ada.obi@example.COM ',
        ]);

        $response->assertRedirect(route('orders.track.show', $this->order));

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee("Reference {$this->order->reference}")
            ->assertSee('Birth Certificate')
            ->assertSee('Pending payment')
            ->assertSee(OrderStatus::PendingPayment->customerDescription())
            ->assertSee('State of origin')
            ->assertSee('Osun')
            ->assertSee('my NIN slip.pdf');
    }

    /**
     * Guests see document filenames, never download links.
     *
     * @return void
     */
    public function test_guest_sees_filenames_but_no_download_links(): void
    {
        $this->look_up('08031234567');

        $document = $this->order->documents()->sole();

        $this->get(route('orders.track.show', $this->order))
            ->assertOk()
            ->assertSee('my NIN slip.pdf')
            ->assertDontSee(route('order-documents.show', $document));
    }

    /**
     * Phone matching ignores formatting and the +234 prefix.
     *
     * @return void
     */
    public function test_guest_can_look_up_own_order_by_phone_in_any_format(): void
    {
        foreach (['08031234567', '+234 803 123 4567', '234-803-123-4567', '(0803) 123 4567'] as $phone) {
            $this->flushSession();

            $this->post(route('orders.track.lookup'), ['reference' => $this->order->reference, 'contact' => $phone])
                ->assertRedirect(route('orders.track.show', $this->order));
        }

        $this->assertSame('08031234567', PhoneNumber::normalise('+234 (803) 123-4567'));
    }

    /**
     * Wrong contact or wrong reference: same generic error, no access granted.
     *
     * @return void
     */
    public function test_mismatched_contact_or_reference_fails(): void
    {
        $other_order = Order::factory()->guest()->create(['guest_email' => 'someone@example.com']);

        $attempts = [
            ['reference' => $this->order->reference, 'contact' => 'someone@example.com'],
            ['reference' => $this->order->reference, 'contact' => '08099999999'],
            ['reference' => $other_order->reference, 'contact' => 'ada.obi@example.com'],
            ['reference' => 'ME-ZZZZZZ', 'contact' => 'ada.obi@example.com'],
            ['reference' => (string) $this->order->id, 'contact' => 'ada.obi@example.com'],
            ['reference' => 'abc', 'contact' => 'ada.obi@example.com'],
            ['reference' => $this->order->reference, 'contact' => '0803'],
        ];

        foreach ($attempts as $index => $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.1.0.{$index}"])
                ->from(route('orders.track'))
                ->post(route('orders.track.lookup'), $attempt)
                ->assertRedirect(route('orders.track'))
                ->assertSessionHasErrors(['reference' => "We couldn't find a request with those details. Check the reference number and use the same email or phone number you gave us."]);
        }

        $this->get(route('orders.track.show', $this->order))->assertRedirect(route('orders.track'));
    }

    /**
     * Soft-deleted orders can't be looked up.
     *
     * @return void
     */
    public function test_deleted_order_cannot_be_looked_up(): void
    {
        $this->order->delete();

        $this->post(route('orders.track.lookup'), ['reference' => $this->order->reference, 'contact' => 'ada.obi@example.com'])
            ->assertSessionHasErrors('reference');
    }

    /**
     * Visiting an order page without proving access goes to the lookup form.
     *
     * @return void
     */
    public function test_order_page_requires_lookup_or_ownership(): void
    {
        $this->get(route('orders.track.show', $this->order))
            ->assertRedirect(route('orders.track'))
            ->assertSessionHas('status');

        $this->actingAs(User::factory()->create())
            ->get(route('orders.track.show', $this->order))
            ->assertRedirect(route('orders.track'));

        // Staff use the back office, not the customer page.
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('orders.track.show', $this->order))
            ->assertRedirect(route('orders.track'));
    }

    /**
     * Guest access ends after 30 idle minutes; activity keeps it alive.
     *
     * @return void
     */
    public function test_guest_access_expires_after_idle_timeout(): void
    {
        $this->look_up('ada.obi@example.com');

        $this->travel(25)->minutes();
        $this->get(route('orders.track.show', $this->order))->assertOk();

        // 25 + 25 = 50 minutes since the lookup, but only 25 since the last visit.
        $this->travel(25)->minutes();
        $this->get(route('orders.track.show', $this->order))->assertOk();

        $this->travel(31)->minutes();
        $this->get(route('orders.track.show', $this->order))
            ->assertRedirect(route('orders.track'))
            ->assertSessionHas('status');

        // Expired for responding too.
        $this->post(route('orders.respond', $this->order), ['note' => 'Hello'])
            ->assertRedirect(route('orders.track'));
    }

    /**
     * Owners aren't affected by the guest idle timeout.
     *
     * @return void
     */
    public function test_owner_access_does_not_expire(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->for($customer)->create();

        $this->travel(3)->hours();

        $this->actingAs($customer)->get(route('orders.track.show', $order))->assertOk();
    }

    /**
     * Looking up one order doesn't open another.
     *
     * @return void
     */
    public function test_lookup_grants_only_that_order(): void
    {
        $other_order = Order::factory()->guest()->create();

        $this->look_up('ada.obi@example.com');

        $this->get(route('orders.track.show', $this->order))->assertOk();
        $this->get(route('orders.track.show', $other_order))->assertRedirect(route('orders.track'));
    }

    /**
     * Same per-IP limit as the request form (5/min), with a friendly message.
     *
     * @return void
     */
    public function test_lookups_are_rate_limited_per_ip(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('orders.track.lookup'), ['reference' => 'ME-00000'.$attempt, 'contact' => 'guess@example.com'])
                ->assertSessionHasErrors('reference');
        }

        $this->from(route('orders.track'))
            ->post(route('orders.track.lookup'), ['reference' => $this->order->reference, 'contact' => 'ada.obi@example.com'])
            ->assertRedirect(route('orders.track'))
            ->assertSessionHasErrors(['reference' => 'Too many attempts. Please wait a few minutes and try again.']);

        $this->get(route('orders.track.show', $this->order))->assertRedirect(route('orders.track'));
    }

    /**
     * One reference can't be guessed from many IPs (10/hour per reference).
     *
     * @return void
     */
    public function test_lookups_are_rate_limited_per_reference(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$attempt}"])
                ->post(route('orders.track.lookup'), ['reference' => $this->order->reference, 'contact' => "guess{$attempt}@example.com"])
                ->assertSessionHasErrors('reference');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->post(route('orders.track.lookup'), ['reference' => $this->order->reference, 'contact' => 'ada.obi@example.com'])
            ->assertSessionHasErrors(['reference' => 'Too many attempts. Please wait a few minutes and try again.']);
    }

    /**
     * The signed-in owner skips the lookup and can download their documents.
     *
     * @return void
     */
    public function test_owner_opens_order_directly_with_download_links(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->for($customer)->create();
        $document = OrderDocument::factory()->for($order)->create(['original_filename' => 'passport.jpg']);

        $this->actingAs($customer)
            ->get(route('orders.track.show', $order))
            ->assertOk()
            ->assertSee('passport.jpg')
            ->assertSee(route('order-documents.show', $document));
    }

    /**
     * Staff notes appear in the Messages timeline, labelled as from the team.
     *
     * @return void
     */
    public function test_staff_notes_are_shown(): void
    {
        $staff_user = User::factory()->staff()->create(['name' => 'Private Staff Name']);
        app(ChangeOrderStatus::class)->handle($this->order, OrderStatus::NeedsAction, $staff_user, 'Started on your certificate.');

        $this->look_up('ada.obi@example.com');

        $this->get(route('orders.track.show', $this->order))
            ->assertOk()
            ->assertSee('Messages')
            ->assertSee('Started on your certificate.')
            ->assertSee('Made Easy team')
            ->assertDontSee('Private Staff Name');
    }

    /**
     * My Requests lists only the customer's own orders.
     *
     * @return void
     */
    public function test_my_requests_lists_only_own_orders(): void
    {
        $customer = User::factory()->create();
        $own_orders = Order::factory()->count(2)->for($customer)
            ->for(Service::factory()->state(['name' => 'Mine Service']))
            ->create();
        $someone_elses = Order::factory()->for(Service::factory()->state(['name' => 'Their Service']))->create();

        $response = $this->actingAs($customer)->get(route('my-requests'))->assertOk();

        foreach ($own_orders as $own_order) {
            $response->assertSee(route('orders.track.show', $own_order));
        }

        $response
            ->assertSee('Mine Service')
            ->assertDontSee('Their Service')
            ->assertDontSee(route('orders.track.show', $someone_elses))
            ->assertDontSee(route('orders.track.show', $this->order));

        $this->actingAs($customer)->get(route('orders.track.show', $someone_elses))->assertRedirect(route('orders.track'));
    }

    /**
     * Staff see only orders they placed themselves on My Requests, not everyone's.
     *
     * @return void
     */
    public function test_my_requests_for_staff_is_not_the_full_order_list(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('my-requests'))
            ->assertOk()
            ->assertSee("You haven't made any requests yet.", false)
            ->assertDontSee(route('orders.track.show', $this->order));
    }

    /**
     * My Requests needs login.
     *
     * @return void
     */
    public function test_my_requests_requires_login(): void
    {
        $this->get(route('my-requests'))->assertRedirect('/login');
    }

    /**
     * @param  string  $contact
     * @return void
     */
    private function look_up(string $contact): void
    {
        $this->post(route('orders.track.lookup'), ['reference' => $this->order->reference, 'contact' => $contact])
            ->assertRedirect(route('orders.track.show', $this->order));
    }
}
