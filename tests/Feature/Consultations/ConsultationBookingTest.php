<?php

namespace Tests\Feature\Consultations;

use App\Actions\Consultations\BookConsultation;
use App\Enums\ConsultationBookingStatus;
use App\Enums\ConsultationType;
use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Events\OrderPaid;
use App\Exceptions\SlotUnavailable;
use App\Listeners\ConfirmConsultationBookingOnPayment;
use App\Mail\ConsultationBookingMail;
use App\Mail\PaymentReceiptMail;
use App\Mail\ServiceRequestReceivedMail;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSlot;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Notifications\ConsultationNeedsRebooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SendsPaystackWebhooks;
use Tests\TestCase;

/**
 * The customer side of consultations: /book-consultation, the slot hold,
 * the existing Paystack checkout, and confirmation by the OrderPaid listener.
 */
class ConsultationBookingTest extends TestCase
{
    use RefreshDatabase, SendsPaystackWebhooks;

    /**
     * @var Service
     */
    private Service $consultation;

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

        $this->use_paystack_test_key();
        Mail::fake();
        Notification::fake();
        Http::preventStrayRequests();

        $this->staff_user = User::factory()->staff()->create();
        $this->consultation = Service::factory()->create([
            'name' => 'Admission Consultation',
            'slug' => 'admission-consultation',
            'fulfilment_type' => FulfilmentType::Consultation,
            'price' => 1_500_000,
        ]);
    }

    /**
     * Confirmation is an OrderPaid listener, like PIN allocation; the webhook has no consultation logic.
     *
     * @return void
     */
    public function test_confirmation_listens_to_order_paid(): void
    {
        Event::fake([OrderPaid::class]);

        Event::assertListening(OrderPaid::class, ConfirmConsultationBookingOnPayment::class);
    }

    /**
     * Only open, future (beyond the notice period), not-full slots are offered.
     *
     * @return void
     */
    public function test_booking_page_lists_only_open_slots(): void
    {
        $open = ConsultationSlot::factory()->create();
        $whatsapp = ConsultationSlot::factory()->ofType(ConsultationType::WhatsApp)->startingAt(now()->addDays(5))->create();
        $cancelled = ConsultationSlot::factory()->create(['start_time' => '12:00:00', 'cancelled_at' => now()]);
        $full = ConsultationSlot::factory()->create(['start_time' => '14:00:00']);
        ConsultationBooking::factory()->confirmed()->for($full, 'slot')->create();
        $too_soon = ConsultationSlot::factory()->startingAt(now()->addMinutes(20))->create();
        $past = ConsultationSlot::factory()->startingAt(now()->subDay())->create();

        $response = $this->get(route('book-consultation'))
            ->assertOk()
            ->assertSee('Book a Consultation')
            ->assertSee($open->dateLabel())
            ->assertSee('WhatsApp call')
            ->assertSee('Book and pay ₦15,000');

        foreach ([$open, $whatsapp] as $slot) {
            $response->assertSee('name="slot" value="'.$slot->id.'"', false);
        }

        foreach ([$cancelled, $full, $too_soon, $past] as $slot) {
            $response->assertDontSee('name="slot" value="'.$slot->id.'"', false);
        }

        $this->consultation->update(['is_active' => false]);
        $this->get(route('book-consultation'))->assertOk()->assertDontSee('name="slot"', false);
    }

    /**
     * Booking holds the slot, creates a normal pending order, and goes to Paystack.
     *
     * @return void
     */
    public function test_booking_holds_slot_and_redirects_to_paystack(): void
    {
        $this->fake_paystack();
        $slot = ConsultationSlot::factory()->create();

        $this->post(route('book-consultation.book'), $this->form($slot))
            ->assertRedirect('https://checkout.paystack.com/xyz');

        $order = Order::query()->sole();
        $booking = ConsultationBooking::query()->sole();
        $this->assertTrue($order->service->is($this->consultation));
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(1_500_000, $order->total_amount);
        $this->assertSame('ada@example.com', $order->customer_email);
        $this->assertTrue($booking->order->is($order));
        $this->assertTrue($booking->slot->is($slot));
        $this->assertSame(ConsultationBookingStatus::PendingPayment, $booking->status);
        $this->assertTrue($booking->hold_expires_at->between(now()->addMinutes(29), now()->addMinutes(31)));
        $this->assertSame(0, $slot->remainingCapacity());

        Http::assertSent(fn (HttpRequest $request): bool => $request['amount'] === 1_500_000 && $request['email'] === 'ada@example.com');
        Mail::assertNotQueued(ServiceRequestReceivedMail::class);

        $this->get(route('orders.track.show', $order))
            ->assertOk()
            ->assertSee('Your consultation')
            ->assertSee($slot->dateLabel())
            ->assertSee($slot->timeLabel())
            ->assertSee('Google Meet')
            ->assertSee("We're holding this time for you", false);
    }

    /**
     * A held slot can't be booked by someone else until the hold runs out.
     *
     * @return void
     */
    public function test_held_slot_is_unavailable_until_the_hold_expires(): void
    {
        $this->fake_paystack();
        $slot = ConsultationSlot::factory()->create();
        app(BookConsultation::class)->handle($slot->id, ['name' => 'First Customer', 'email' => 'first@example.com', 'phone' => '08030000000']);

        $this->from(route('book-consultation'))
            ->post(route('book-consultation.book'), $this->form($slot))
            ->assertRedirect(route('book-consultation'))
            ->assertSessionHasErrors('slot');
        $this->assertSame(1, Order::query()->count());
        $this->get(route('book-consultation'))->assertDontSee('name="slot" value="'.$slot->id.'"', false);

        $this->travel(31)->minutes();

        $this->get(route('book-consultation'))->assertSee('name="slot" value="'.$slot->id.'"', false);
        $this->post(route('book-consultation.book'), $this->form($slot))
            ->assertRedirect('https://checkout.paystack.com/xyz');
        $this->assertSame(2, ConsultationBooking::query()->where('consultation_slot_id', $slot->id)->count());
        $this->assertSame(1, $slot->bookings()->occupying()->count());
    }

    /**
     * The verified webhook alone confirms the booking (through the OrderPaid
     * listener); the email and order page show date, time and type, with the
     * Google Meet link as a clearly marked placeholder.
     *
     * @return void
     */
    public function test_payment_confirms_booking_and_sends_details(): void
    {
        $slot = ConsultationSlot::factory()->create();
        $order = $this->book($slot);

        $this->pay_with_webhook($this->initiated_payment_for($order))->assertOk();

        $booking = $order->consultationBooking()->sole();
        $this->assertSame(ConsultationBookingStatus::Confirmed, $booking->status);
        $this->assertNotNull($booking->confirmed_at);
        $this->assertNull($booking->hold_expires_at);
        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        $this->assertTrue($booking->isOccupyingSlot());

        Mail::assertQueued(PaymentReceiptMail::class);
        Mail::assertQueued(ConsultationBookingMail::class, function (ConsultationBookingMail $mail) use ($slot): bool {
            $mail->assertSeeInHtml($slot->dateLabel());
            $mail->assertSeeInHtml('Google Meet');
            $mail->assertSeeInHtml('[To be added by our team]');

            return $mail->hasTo('ada@example.com') && $mail->kind === ConsultationBookingMail::CONFIRMED;
        });
        Notification::assertNothingSent();

        $this->look_up($order);
        $this->get(route('orders.track.show', $order))
            ->assertOk()
            ->assertSee('Your consultation is booked')
            ->assertSee($slot->dateLabel())
            ->assertSee('To be added by our team');

        // A repeated webhook changes nothing and sends nothing more.
        $this->pay_with_webhook($order->payments()->sole())->assertOk();
        Mail::assertQueued(ConsultationBookingMail::class, 1);
    }

    /**
     * Paid after the hold lapsed and someone else took the slot: the money is
     * kept, the booking is flagged for a new time, staff are notified and the
     * customer is told.
     *
     * @return void
     */
    public function test_late_payment_for_a_taken_slot_needs_rebooking(): void
    {
        $slot = ConsultationSlot::factory()->create();
        $late_order = $this->book($slot);

        $this->travel(31)->minutes();
        $other_order = $this->book($slot, 'other@example.com');

        $this->pay_with_webhook($this->initiated_payment_for($late_order))->assertOk();

        $late_booking = $late_order->consultationBooking()->sole();
        $this->assertTrue($late_booking->needsRebooking());
        $this->assertSame(ConsultationBookingStatus::PendingPayment, $late_booking->status);
        $this->assertSame(OrderStatus::Paid, $late_order->refresh()->status);
        $this->assertFalse($late_booking->isOccupyingSlot());

        Notification::assertSentTo($this->staff_user, ConsultationNeedsRebooking::class, fn (ConsultationNeedsRebooking $notification): bool => $notification->booking->is($late_booking));
        Mail::assertQueued(ConsultationBookingMail::class, fn (ConsultationBookingMail $mail): bool => $mail->hasTo('ada@example.com') && $mail->kind === ConsultationBookingMail::NEEDS_NEW_TIME);

        $this->look_up($late_order);
        $this->get(route('orders.track.show', $late_order))
            ->assertOk()
            ->assertSee('Paid – needs a new time')
            ->assertSee("You don't need to pay again", false);

        // The customer holding the slot can still pay and is confirmed.
        $this->pay_with_webhook($this->initiated_payment_for($other_order))->assertOk();
        $this->assertSame(ConsultationBookingStatus::Confirmed, $other_order->consultationBooking()->sole()->status);
    }

    /**
     * Customers can't book with an inactive consultation service or a bad slot id.
     *
     * @return void
     */
    public function test_booking_is_refused_when_not_on_sale_or_slot_missing(): void
    {
        $slot = ConsultationSlot::factory()->create();

        $this->from(route('book-consultation'))
            ->post(route('book-consultation.book'), $this->form($slot, ['slot' => 999_999]))
            ->assertSessionHasErrors('slot');

        $this->consultation->update(['is_active' => false]);

        $this->from(route('book-consultation'))
            ->post(route('book-consultation.book'), $this->form($slot))
            ->assertSessionHasErrors('book');

        $this->assertSame(0, Order::query()->count());

        $this->expectException(SlotUnavailable::class);
        $this->consultation->update(['is_active' => true]);
        $slot->forceFill(['cancelled_at' => now()])->save();
        app(BookConsultation::class)->handle($slot->id, ['name' => 'Ada Obi', 'email' => 'ada@example.com', 'phone' => '08031234567']);
    }

    /**
     * Book a slot directly (as the booking form does), returning the order.
     *
     * @param  ConsultationSlot  $slot
     * @param  string  $email
     * @return Order
     */
    private function book(ConsultationSlot $slot, string $email = 'ada@example.com'): Order
    {
        return app(BookConsultation::class)->handle($slot->id, ['name' => 'Ada Obi', 'email' => $email, 'phone' => '08031234567']);
    }

    /**
     * Grant this session access to the order, as "Track a request" does.
     *
     * @param  Order  $order
     * @return void
     */
    private function look_up(Order $order): void
    {
        $this->post(route('orders.track.lookup'), ['reference' => $order->reference, 'contact' => 'ada@example.com'])
            ->assertRedirect(route('orders.track.show', $order));
    }

    /**
     * Booking form fields.
     *
     * @param  ConsultationSlot  $slot
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(ConsultationSlot $slot, array $overrides = []): array
    {
        return [
            'slot' => $slot->id,
            'name' => 'Ada Obi',
            'email' => 'ada@example.com',
            'phone' => '08031234567',
            ...$overrides,
        ];
    }

    /**
     * @return void
     */
    private function fake_paystack(): void
    {
        Http::fake(['api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => ['authorization_url' => 'https://checkout.paystack.com/xyz', 'access_code' => 'xyz'],
        ])]);
    }
}
