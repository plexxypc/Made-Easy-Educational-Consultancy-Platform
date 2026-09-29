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
use Filament\Pages\Dashboard;
use App\Models\Service;
use App\Models\User;
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

        $page = Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
            ->assertActionVisible('markInProgress')
            ->callAction('markInProgress')
            ->assertHasNoActionErrors();

        $this->assertSame(OrderStatus::InProgress, $this->order->refresh()->status);
        $this->assertDatabaseHas('order_status_changes', [
            'order_id' => $this->order->id,
            'changed_by' => $staff_user->id,
            'from_status' => OrderStatus::PendingPayment->value,
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
            ->assertActionVisible('markInProgress');

        $quote_order->refresh();
        $this->assertSame(1_500_050, $quote_order->total_amount);
        $this->assertNotNull($quote_order->quoted_at);
        $this->assertFalse($quote_order->awaitsQuote());
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
