<?php

namespace Tests\Feature\Website;

use App\Enums\OrderStatus;
use App\Enums\TestimonialStatus;
use App\Filament\Resources\Testimonials\Pages\ManageTestimonials;
use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Models\Order;
use App\Models\Testimonial;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Testimonials: only approved ones are public; customers submit only from a
 * completed order they can access; staff moderate.
 */
class TestimonialTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A valid submission.
     *
     * @var array<string, mixed>
     */
    private const SUBMISSION = [
        'customer_name' => 'Adaeze O.',
        'body' => 'They handled my WAEC registration quickly and kept me updated the whole way.',
        'rating' => 5,
        'consent' => '1',
    ];

    /**
     * Pending and rejected testimonials never appear on the homepage or the
     * testimonials page; approved ones appear on both.
     *
     * @return void
     */
    public function test_only_approved_testimonials_are_public(): void
    {
        Testimonial::factory()->approved()->create(['body' => 'Approved words from a happy parent.']);
        Testimonial::factory()->create(['body' => 'Pending words awaiting review.']);
        Testimonial::factory()->rejected()->create(['body' => 'Rejected words never to be shown.']);

        foreach ([route('home'), route('testimonials')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('Approved words from a happy parent.')
                ->assertDontSee('Pending words awaiting review.')
                ->assertDontSee('Rejected words never to be shown.');
        }

        $this->assertSame(['Approved words from a happy parent.'], Testimonial::query()->approved()->pluck('body')->all());
    }

    /**
     * The form needs access to the order: guests without a lookup and other
     * customers are sent to the lookup form, and their POSTs save nothing.
     *
     * @return void
     */
    public function test_form_needs_access_to_the_order(): void
    {
        $order = Order::factory()->withStatus(OrderStatus::Completed)->create();

        $this->get(route('orders.testimonial', $order))->assertRedirect(route('orders.track'));
        $this->post(route('orders.testimonial.store', $order), self::SUBMISSION)->assertRedirect(route('orders.track'));

        $this->actingAs(User::factory()->create());
        $this->get(route('orders.testimonial', $order))->assertRedirect(route('orders.track'));
        $this->post(route('orders.testimonial.store', $order), self::SUBMISSION)->assertRedirect(route('orders.track'));

        $this->assertDatabaseCount('testimonials', 0);
    }

    /**
     * Only a completed order offers the form, and only once.
     *
     * @return void
     */
    public function test_only_a_completed_order_takes_one_testimonial(): void
    {
        $customer = User::factory()->create(['name' => 'Adaeze Okafor']);
        $this->actingAs($customer);

        foreach ([OrderStatus::PendingPayment, OrderStatus::Paid, OrderStatus::InProgress, OrderStatus::NeedsAction, OrderStatus::Cancelled] as $status) {
            $order = Order::factory()->for($customer)->withStatus($status)->create();

            $this->get(route('orders.track.show', $order))->assertOk()->assertDontSee('Share your experience');
            $this->get(route('orders.testimonial', $order))->assertRedirect(route('orders.track.show', $order));
        }
        // POSTs are rate limited (5 a minute per IP), so only the last one is tried here.
        $this->post(route('orders.testimonial.store', $order), self::SUBMISSION)->assertRedirect(route('orders.track.show', $order));
        $this->assertDatabaseCount('testimonials', 0);

        $completed = Order::factory()->for($customer)->withStatus(OrderStatus::Completed)->create();

        $this->get(route('my-requests'))->assertSee(route('orders.testimonial', $completed), false);
        $this->get(route('orders.track.show', $completed))->assertSee('Share your experience');
        $this->get(route('orders.testimonial', $completed))->assertOk()->assertSee('value="Adaeze O."', false);

        $this->post(route('orders.testimonial.store', $completed), self::SUBMISSION)
            ->assertRedirect(route('orders.track.show', $completed))
            ->assertSessionHas('status');

        $testimonial = Testimonial::query()->sole();
        $this->assertSame($completed->id, $testimonial->order_id);
        $this->assertSame(TestimonialStatus::Pending, $testimonial->status);
        $this->assertSame(5, $testimonial->rating);
        $this->get(route('home'))->assertDontSee(self::SUBMISSION['body']);

        // Once per order: the form is gone and a second POST adds nothing.
        $this->get(route('orders.track.show', $completed))->assertDontSee('Share your experience');
        $this->get(route('my-requests'))->assertDontSee(route('orders.testimonial', $completed), false);
        $this->get(route('orders.testimonial', $completed))->assertRedirect(route('orders.track.show', $completed));
        $this->post(route('orders.testimonial.store', $completed), self::SUBMISSION);
        $this->assertDatabaseCount('testimonials', 1);
    }

    /**
     * A guest who looked the order up may submit; consent is required and a
     * filled honeypot is dropped without saving.
     *
     * @return void
     */
    public function test_guest_after_lookup_with_consent_and_honeypot(): void
    {
        $order = Order::factory()->guest()->withStatus(OrderStatus::Completed)->create(['guest_email' => 'parent@example.com']);

        $this->post(route('orders.track.lookup'), ['reference' => $order->reference, 'contact' => 'parent@example.com'])
            ->assertRedirect(route('orders.track.show', $order));

        $this->get(route('orders.testimonial', $order))->assertOk();

        $this->post(route('orders.testimonial.store', $order), ['consent' => ''] + self::SUBMISSION)
            ->assertSessionHasErrors(['consent']);

        $this->post(route('orders.testimonial.store', $order), ['website' => 'https://spam.example'] + self::SUBMISSION)
            ->assertRedirect(route('orders.track.show', $order));
        $this->assertDatabaseCount('testimonials', 0);

        $this->post(route('orders.testimonial.store', $order), self::SUBMISSION)->assertRedirect(route('orders.track.show', $order));
        $this->assertDatabaseCount('testimonials', 1);
    }

    /**
     * Staff approve from the back office and it shows at once; customers
     * can't reach moderation.
     *
     * @return void
     */
    public function test_staff_moderate_and_customers_cannot(): void
    {
        $testimonial = Testimonial::factory()->create(['body' => 'Excellent help with my JAMB registration.']);

        $this->actingAs(User::factory()->create());
        $this->get(TestimonialResource::getUrl('index'))->assertForbidden();

        $staff_user = User::factory()->staff()->create();
        $this->actingAs($staff_user);

        Livewire::test(ManageTestimonials::class)
            ->assertCanSeeTableRecords([$testimonial])
            ->callAction(TestAction::make('approve')->table($testimonial))
            ->assertNotified();

        $testimonial->refresh();
        $this->assertSame(TestimonialStatus::Approved, $testimonial->status);
        $this->assertSame($staff_user->id, $testimonial->moderated_by);
        $this->get(route('home'))->assertSee('Excellent help with my JAMB registration.');

        Livewire::test(ManageTestimonials::class)
            ->filterTable('status', TestimonialStatus::Approved->value)
            ->callAction(TestAction::make('reject')->table($testimonial));

        $this->assertSame(TestimonialStatus::Rejected, $testimonial->refresh()->status);
        $this->get(route('home'))->assertDontSee('Excellent help with my JAMB registration.');

        Livewire::test(ManageTestimonials::class)
            ->filterTable('status', TestimonialStatus::Rejected->value)
            ->assertActionHidden(TestAction::make('delete')->table($testimonial));
    }
}
