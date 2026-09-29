<?php

namespace Tests\Feature\Filament;

use App\Actions\Orders\ChangeOrderStatus;
use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\RelationManagers\DocumentsRelationManager;
use App\Filament\Widgets\OrdersOverview;
use App\Mail\OrderStatusChangedMail;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\Service;
use App\Models\User;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Order status workflow on the Filament order screen.
 */
class OrderStatusActionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Customer who placed the order.
     *
     * @var User
     */
    private User $customer;

    /**
     * A standard (non-quote) pending order.
     *
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

        $this->customer = User::factory()->create(['email' => 'ada@example.com']);
        $this->order = Order::factory()->for($this->customer)->create();
    }

    /**
     * Staff can move an order through the workflow; each change is recorded and emailed.
     *
     * @return void
     */
    public function test_staff_can_change_status(): void
    {
        $staff_user = User::factory()->staff()->create();
        $this->actingAs($staff_user);
        $this->order->forceFill(['status' => OrderStatus::Paid])->save();

        $page = Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
            ->assertActionVisible('markInProgress')
            ->callAction('markInProgress')
            ->assertHasNoActionErrors();

        $this->assertSame(OrderStatus::InProgress, $this->order->refresh()->status);
        $this->assertDatabaseHas('order_status_changes', [
            'order_id' => $this->order->id,
            'changed_by' => $staff_user->id,
            'from_status' => OrderStatus::Paid->value,
            'to_status' => OrderStatus::InProgress->value,
        ]);
        Mail::assertQueued(OrderStatusChangedMail::class, fn (OrderStatusChangedMail $mail): bool => $mail->hasTo('ada@example.com'));

        $page->callAction('markCompleted')->assertHasNoActionErrors();

        $this->assertSame(OrderStatus::Completed, $this->order->refresh()->status);
        $this->assertDatabaseCount('order_status_changes', 2);
        Mail::assertQueuedCount(2);
    }

    /**
     * "Mark Needs Action" requires a note, which is stored and sent to the customer.
     *
     * @return void
     */
    public function test_needs_action_requires_a_note(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
            ->callAction('markNeedsAction', data: ['note' => ''])
            ->assertHasActionErrors(['note' => 'required']);

        $this->assertSame(OrderStatus::PendingPayment, $this->order->refresh()->status);
        Mail::assertNothingQueued();

        Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
            ->callAction('markNeedsAction', data: ['note' => 'Please upload a clearer passport photo.'])
            ->assertHasNoActionErrors();

        $this->assertSame(OrderStatus::NeedsAction, $this->order->refresh()->status);
        $this->assertDatabaseHas('order_status_changes', [
            'order_id' => $this->order->id,
            'to_status' => OrderStatus::NeedsAction->value,
            'note' => 'Please upload a clearer passport photo.',
        ]);
        Mail::assertQueued(OrderStatusChangedMail::class, fn (OrderStatusChangedMail $mail): bool => $mail->status_change->note === 'Please upload a clearer passport photo.');
    }

    /**
     * Guest orders are emailed at the guest address; terminal statuses hide the actions.
     *
     * @return void
     */
    public function test_cancel_emails_guest_and_hides_further_actions(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $guest_order = Order::factory()->guest()->create(['guest_email' => 'guest@example.com']);

        Livewire::test(ViewOrder::class, ['record' => $guest_order->getRouteKey()])
            ->callAction('cancel', data: ['note' => 'Duplicate order.'])
            ->assertHasNoActionErrors()
            ->assertActionHidden('markInProgress')
            ->assertActionHidden('markCompleted')
            ->assertActionHidden('markNeedsAction')
            ->assertActionHidden('cancel');

        $this->assertSame(OrderStatus::Cancelled, $guest_order->refresh()->status);
        Mail::assertQueued(OrderStatusChangedMail::class, fn (OrderStatusChangedMail $mail): bool => $mail->hasTo('guest@example.com'));
    }

    /**
     * Customers cannot reach the order screens or the actions, in the UI or directly.
     *
     * @return void
     */
    public function test_customer_cannot_reach_status_actions(): void
    {
        $this->actingAs($this->customer);

        $this->get(OrderResource::getUrl('index'))->assertForbidden();
        $this->get(OrderResource::getUrl('view', ['record' => $this->order]))->assertForbidden();

        try {
            app(ChangeOrderStatus::class)->handle($this->order, OrderStatus::Completed, $this->customer, null);
            $this->fail('A customer was allowed to change an order status.');
        } catch (BusinessRuleException) {
            // Expected.
        }

        $this->assertSame(OrderStatus::PendingPayment, $this->order->refresh()->status);
        $this->assertDatabaseCount('order_status_changes', 0);
        Mail::assertNothingQueued();
    }

    /**
     * Quote orders stay pending until an admin sets the total; staff cannot set it.
     *
     * @return void
     */
    public function test_quote_order_needs_a_total_before_it_can_progress(): void
    {
        $quote_service = Service::factory()->create(['fulfilment_type' => FulfilmentType::Quote]);
        $quote_order = Order::factory()->for($this->customer)->for($quote_service)->create();

        $this->assertSame(0, $quote_order->total_amount);

        $this->actingAs(User::factory()->staff()->create());

        Livewire::test(ViewOrder::class, ['record' => $quote_order->getRouteKey()])
            ->assertActionHidden('setTotal')
            ->assertActionHidden('markInProgress')
            ->assertActionHidden('markCompleted')
            ->assertActionHidden('markNeedsAction')
            ->assertActionVisible('cancel');

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ViewOrder::class, ['record' => $quote_order->getRouteKey()])
            ->assertActionVisible('setTotal')
            ->assertActionHidden('markInProgress')
            ->callAction('setTotal', data: ['total' => '15000.50'])
            ->assertHasNoActionErrors()
            // Priced now, but still unpaid: only needs action / cancel until Paystack confirms payment.
            ->assertActionHidden('markInProgress')
            ->assertActionVisible('markNeedsAction')
            ->assertActionVisible('cancel');

        $quote_order->refresh();
        $this->assertSame(1_500_050, $quote_order->total_amount);
        $this->assertNotNull($quote_order->quoted_at);
        $this->assertFalse($quote_order->awaitsQuote());
    }

    /**
     * Staff can't progress an unpaid order with a total above zero past pending_payment,
     * including by going through needs_action; cancel is still available.
     *
     * @return void
     */
    public function test_staff_cannot_progress_unpaid_order(): void
    {
        $staff_user = User::factory()->staff()->create();
        $this->actingAs($staff_user);
        $this->assertGreaterThan(0, $this->order->total_amount);

        Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
            ->assertActionHidden('markInProgress')
            ->assertActionHidden('markCompleted')
            ->assertActionVisible('markNeedsAction')
            ->assertActionVisible('cancel');

        foreach ([OrderStatus::InProgress, OrderStatus::Completed] as $next_status) {
            try {
                app(ChangeOrderStatus::class)->handle($this->order, $next_status, $staff_user);
                $this->fail("An unpaid order was moved to {$next_status->value}.");
            } catch (BusinessRuleException $exception) {
                $this->assertContains($exception->getMessage(), [
                    "This order hasn't been paid yet. It can move on once the customer's payment is confirmed.",
                    'An order that is "Pending payment" can\'t be marked "Completed".',
                ]);
            }
        }

        app(ChangeOrderStatus::class)->handle($this->order, OrderStatus::NeedsAction, $staff_user, 'Please confirm your exam year.');

        try {
            app(ChangeOrderStatus::class)->handle($this->order->refresh(), OrderStatus::InProgress, $staff_user);
            $this->fail('An unpaid order skipped payment via needs action.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame("This order hasn't been paid yet. It can move on once the customer's payment is confirmed.", $exception->getMessage());
        }

        $this->assertSame(OrderStatus::NeedsAction, $this->order->refresh()->status);

        app(ChangeOrderStatus::class)->handle($this->order, OrderStatus::Cancelled, $staff_user);
        $this->assertSame(OrderStatus::Cancelled, $this->order->refresh()->status);
    }

    /**
     * A ₦0 order needs no payment, so staff can progress it; a paid order can progress too.
     *
     * @return void
     */
    public function test_staff_can_progress_zero_total_or_paid_order(): void
    {
        $staff_user = User::factory()->staff()->create();
        $this->actingAs($staff_user);

        $free_order = Order::factory()->for(Service::factory()->state(['price' => 0]))->create();
        $this->assertSame(0, $free_order->total_amount);

        Livewire::test(ViewOrder::class, ['record' => $free_order->getRouteKey()])
            ->assertActionVisible('markInProgress')
            ->callAction('markInProgress')
            ->assertHasNoActionErrors();

        $this->assertSame(OrderStatus::InProgress, $free_order->refresh()->status);

        $paid_order = Order::factory()->withStatus(OrderStatus::Paid)->create();
        app(ChangeOrderStatus::class)->handle($paid_order, OrderStatus::InProgress, $staff_user);
        $this->assertSame(OrderStatus::InProgress, $paid_order->refresh()->status);
    }

    /**
     * The order screen shows answers, guest contact, documents and history; the dashboard shows counts.
     *
     * @return void
     */
    public function test_order_screen_and_dashboard_render(): void
    {
        $service = Service::factory()->create([
            'customer_fields' => [
                ['key' => 'exam_year', 'label' => 'Exam year', 'type' => 'number', 'required' => true],
            ],
        ]);
        $guest_order = Order::factory()->guest()->for($service)->create([
            'guest_name' => 'Chidi Guest',
            'guest_phone' => '08012345678',
            'field_answers' => ['exam_year' => 2019],
        ]);
        $guest_order->forceFill(['status' => OrderStatus::Paid])->save();
        OrderDocument::factory()->for($guest_order)->create(['original_filename' => 'result-slip.pdf']);
        Order::factory()->withStatus(OrderStatus::NeedsAction)->create();

        $staff_user = User::factory()->staff()->create();
        $this->actingAs($staff_user);
        app(ChangeOrderStatus::class)->handle($guest_order, OrderStatus::InProgress, $staff_user, 'Started processing.');

        $this->get(OrderResource::getUrl('view', ['record' => $guest_order]))
            ->assertOk()
            ->assertSee('Chidi Guest')
            ->assertSee('08012345678')
            ->assertSee('Exam year')
            ->assertSee('2019')
            ->assertSee('Started processing.');

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $guest_order, 'pageClass' => ViewOrder::class])
            ->assertSee('result-slip.pdf');

        $this->get(Dashboard::getUrl())->assertOk();

        Livewire::test(OrdersOverview::class)
            ->assertSee('Needs action')
            ->assertSee('Last 7 days');
    }

    /**
     * The order list shows orders to staff and filters by status.
     *
     * @return void
     */
    public function test_staff_order_list_filters_by_status(): void
    {
        $needs_action_order = Order::factory()->withStatus(OrderStatus::NeedsAction)->create();

        $this->actingAs(User::factory()->staff()->create());

        Livewire::test(ListOrders::class)
            ->assertCanSeeTableRecords([$this->order, $needs_action_order])
            ->filterTable('status', [OrderStatus::NeedsAction->value])
            ->assertCanSeeTableRecords([$needs_action_order])
            ->assertCanNotSeeTableRecords([$this->order]);
    }
}
