<?php

namespace Tests\Feature\Account;

use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Enums\PinStatus;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\RelationManagers\PinsRelationManager;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSlot;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\OrderStatusChange;
use App\Models\ResultCheckerPin;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\SendsPaystackWebhooks;
use Tests\TestCase;

/**
 * The account dashboard (PDF section 8): each tab shows only the signed-in
 * customer's own data, and owner views of receipts, documents and PINs are
 * not audited while staff views are.
 */
class CustomerDashboardTest extends TestCase
{
    use RefreshDatabase, SendsPaystackWebhooks;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->use_paystack_test_key();
        Mail::fake();
        Http::preventStrayRequests();
        Storage::fake(OrderDocument::DISK);
    }

    /**
     * Every tab lists the customer's own data and nothing of another
     * customer's (or of guest orders), and guessing another customer's ids
     * or references in the existing routes gets nowhere.
     *
     * @return void
     */
    public function test_dashboard_only_ever_shows_own_data(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $mine = $this->account_data($alice, 'Alpha');
        $theirs = $this->account_data($bob, 'Bravo');
        $guest = $this->account_data(null, 'Charlie');

        $this->actingAs($alice);

        $expectations = [
            'requests' => fn (array $data): array => [$data['pending']->serviceLabel(), $data['paid']->display_reference],
            'payments' => fn (array $data): array => [$data['paid']->receipt->number],
            'documents' => fn (array $data): array => [$data['document']->original_filename, route('order-documents.show', $data['document'])],
            'consultations' => fn (array $data): array => [$data['booking']->order->display_reference],
            'pins' => fn (array $data): array => [$data['pin']->pin],
            'messages' => fn (array $data): array => [$data['note']],
        ];

        foreach ($expectations as $tab => $values_for) {
            $response = $this->get(route('my-requests', ['tab' => $tab]))
                ->assertOk()
                ->assertHeader('Cache-Control', 'no-store, private');

            foreach ($values_for($mine) as $value) {
                $response->assertSee($value);
            }

            foreach ([...$values_for($theirs), ...$values_for($guest)] as $value) {
                $response->assertDontSee($value);
            }
        }

        // Guessable ids / references in the existing routes are still refused.
        $this->get(route('order-documents.show', $theirs['document']))->assertForbidden();
        $this->get(route('orders.receipt', $theirs['paid']))->assertRedirect(route('orders.track'));
        $this->get(route('orders.track.show', $theirs['pending']))->assertRedirect(route('orders.track'));
        $this->get(route('orders.track.show', $guest['pending']))->assertRedirect(route('orders.track'));

        // An unknown tab falls back to requests; the page needs a login.
        $this->get(route('my-requests', ['tab' => 'admin']))->assertOk()->assertSee($mine['pending']->serviceLabel());
        $this->app['auth']->forgetGuards();
        $this->get(route('my-requests', ['tab' => 'pins']))->assertRedirect('/login');
    }

    /**
     * Requests are split into Pending and Completed using the existing statuses.
     *
     * @return void
     */
    public function test_requests_are_split_into_pending_and_completed(): void
    {
        $customer = User::factory()->create();
        $this->order_for($customer, 'Waiting Service', OrderStatus::NeedsAction);
        $this->order_for($customer, 'Working Service', OrderStatus::InProgress);
        $this->order_for($customer, 'Finished Service', OrderStatus::Completed);
        $this->order_for($customer, 'Stopped Service', OrderStatus::Cancelled);

        $response = $this->actingAs($customer)->get(route('my-requests'))->assertOk();

        $response->assertSeeInOrder(['Pending', 'Working Service', 'Waiting Service', 'Completed', 'Stopped Service', 'Finished Service']);
    }

    /**
     * Messages aggregate every order's history, newest first; updates from us
     * are "New" until the customer has opened Messages once.
     *
     * @return void
     */
    public function test_messages_are_aggregated_newest_first_with_unread_marker(): void
    {
        $customer = User::factory()->create();
        $first = $this->order_for($customer, 'First Service', OrderStatus::InProgress);
        $second = $this->order_for($customer, 'Second Service', OrderStatus::NeedsAction);

        $this->travel(-3)->hours();
        $this->status_change($first, OrderStatus::PendingPayment, OrderStatus::InProgress, 'We have started on your first request.');
        $this->travelBack();
        $this->travel(-2)->hours();
        $this->status_change($second, OrderStatus::InProgress, OrderStatus::NeedsAction, 'Please upload your birth certificate.');
        $this->travelBack();
        $this->travel(-1)->hours();
        $this->status_change($second, OrderStatus::NeedsAction, OrderStatus::InProgress, 'Here it is.', by_customer: true);
        $this->travelBack();

        $this->actingAs($customer);
        $this->get(route('my-requests'))->assertSee('2 new');

        $html = $this->get(route('my-requests', ['tab' => 'messages']))
            ->assertOk()
            ->assertSeeInOrder(['Here it is.', 'Please upload your birth certificate.', 'We have started on your first request.'])
            ->assertSee('Second Service')
            ->assertSee('First Service')
            ->getContent();

        // Our two updates are new; the customer's own reply is not.
        $this->assertSame(2, substr_count($html, '>New</span>'));

        $this->assertNotNull($customer->refresh()->messages_read_at);

        $this->get(route('my-requests', ['tab' => 'messages']))->assertOk()->assertDontSee('>New<', false);
        $this->get(route('my-requests'))->assertDontSee('new</span>', false);

        // A newer update is unread again.
        $this->travel(1)->minutes();
        $this->status_change($first, OrderStatus::InProgress, OrderStatus::Completed, 'All done.');
        $this->get(route('my-requests'))->assertSee('1 new');
    }

    /**
     * The owner downloading their receipt or document, or revealing their PIN,
     * from the dashboard writes no audit row; staff doing the same through the
     * back office (Show PIN, document download) does.
     *
     * @return void
     */
    public function test_owner_views_are_not_audited_but_staff_views_are(): void
    {
        $customer = User::factory()->create();
        $data = $this->account_data($customer, 'Alpha');
        Storage::disk(OrderDocument::DISK)->put($data['document']->stored_path, '%PDF-1.4 test');

        $this->actingAs($customer);
        $this->get(route('my-requests', ['tab' => 'pins']))->assertOk()->assertSee('Show PIN')->assertSee($data['pin']->pin);
        $this->get(route('my-requests', ['tab' => 'payments']))->assertOk()->assertSee(route('orders.receipt', ['order' => $data['paid'], 'download' => 1]), false);
        $this->get(route('orders.receipt', ['order' => $data['paid'], 'download' => 1]))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="receipt-'.$data['paid']->receipt->number.'.html"');
        $this->get(route('order-documents.show', ['order_document' => $data['document'], 'download' => 1]))->assertOk();

        $this->assertDatabaseCount('document_access_logs', 0);

        $staff_user = User::factory()->staff()->create();
        $this->actingAs($staff_user);

        Livewire::test(PinsRelationManager::class, ['ownerRecord' => $data['pin']->order, 'pageClass' => ViewOrder::class])
            ->mountTableAction('showPin', $data['pin'])
            ->assertMountedActionModalSee($data['pin']->pin);
        $this->get(route('order-documents.show', $data['document']))->assertOk();

        $this->assertDatabaseHas('document_access_logs', ['result_checker_pin_id' => $data['pin']->id, 'user_id' => $staff_user->id]);
        $this->assertDatabaseHas('document_access_logs', ['order_document_id' => $data['document']->id, 'user_id' => $staff_user->id]);
        $this->assertDatabaseCount('document_access_logs', 2);
    }

    /**
     * One of everything the dashboard shows, for a customer (or a guest when null).
     *
     * @param  User|null  $user
     * @param  string  $prefix  Makes each customer's data recognisable.
     * @return array{pending: Order, paid: Order, document: OrderDocument, booking: ConsultationBooking, pin: ResultCheckerPin, note: string}
     */
    private function account_data(?User $user, string $prefix): array
    {
        $pending = $this->order_for($user, "{$prefix} Pending Service", OrderStatus::PendingPayment);

        $paid = $this->order_for($user, "{$prefix} Paid Service", OrderStatus::PendingPayment);
        $this->pay_with_webhook($this->initiated_payment_for($paid))->assertOk();
        $paid->refresh();

        $document = OrderDocument::factory()->for($pending)->create(['original_filename' => strtolower($prefix).'-nin-slip.pdf']);

        $consultation = Service::factory()->create(['name' => "{$prefix} Consultation", 'fulfilment_type' => FulfilmentType::Consultation, 'price' => 1_500_000]);
        $booking_order = $this->order_for($user, null, OrderStatus::Paid, $consultation);
        $booking = ConsultationBooking::factory()->confirmed()->for($booking_order)->for(ConsultationSlot::factory(), 'slot')->create();

        $checker = Service::factory()->create(['name' => "{$prefix} Result Checker", 'fulfilment_type' => FulfilmentType::Instant, 'price' => 650_000]);
        $pin_order = $this->order_for($user, null, OrderStatus::Completed, $checker);
        $pin = ResultCheckerPin::factory()->for($checker)->create([
            'order_id' => $pin_order->id,
            'status' => PinStatus::Revealed,
            'allocated_at' => now(),
            'revealed_at' => now(),
        ]);

        $note = "{$prefix}: please send a clearer photo.";
        $this->status_change($pending, OrderStatus::PendingPayment, OrderStatus::NeedsAction, $note);

        return [
            'pending' => $pending,
            'paid' => $paid,
            'document' => $document,
            'booking' => $booking->load('order'),
            'pin' => $pin->load('order'),
            'note' => $note,
        ];
    }

    /**
     * @param  User|null  $user  Null for a guest order.
     * @param  string|null  $service_name
     * @param  OrderStatus  $status
     * @param  Service|null  $service
     * @return Order
     */
    private function order_for(?User $user, ?string $service_name, OrderStatus $status, ?Service $service = null): Order
    {
        $service ??= Service::factory()->create(['name' => $service_name, 'price' => 1_000_000]);
        $factory = $user === null ? Order::factory()->guest() : Order::factory()->for($user);

        return $factory->for($service)->create(['status' => $status, 'total_amount' => $service->price]);
    }

    /**
     * @param  Order  $order
     * @param  OrderStatus  $from
     * @param  OrderStatus  $to
     * @param  string|null  $note
     * @param  bool  $by_customer
     * @return OrderStatusChange
     */
    private function status_change(Order $order, OrderStatus $from, OrderStatus $to, ?string $note, bool $by_customer = false): OrderStatusChange
    {
        return OrderStatusChange::create([
            'order_id' => $order->id,
            'by_customer' => $by_customer,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
        ]);
    }
}
