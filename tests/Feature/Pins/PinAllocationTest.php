<?php

namespace Tests\Feature\Pins;

use App\Actions\Orders\ChangeOrderStatus;
use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PinStatus;
use App\Enums\PinStockLevel;
use App\Events\OrderPaid;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\DocumentAccessLogs\Pages\ListDocumentAccessLogs;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\RelationManagers\PinsRelationManager;
use App\Listeners\AllocateResultCheckerPins;
use App\Mail\PaymentReceiptMail;
use App\Mail\ResultCheckerDelayedMail;
use App\Mail\ResultCheckerPinsMail;
use App\Models\DocumentAccessLog;
use App\Models\Order;
use App\Models\ResultCheckerPin;
use App\Models\Service;
use App\Models\User;
use App\Notifications\PinsNeedManualFulfilment;
use App\Notifications\PinStockRunningLow;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\SendsPaystackWebhooks;
use Tests\TestCase;

/**
 * Result checker orders: PINs are allocated when the verified webhook marks
 * the order paid (OrderPaid listener), with the manual-fulfilment fallback
 * when stock runs out, and audited staff access to PINs.
 */
class PinAllocationTest extends TestCase
{
    use RefreshDatabase, SendsPaystackWebhooks;

    /**
     * @var Service
     */
    private Service $waec;

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
        $this->waec = Service::factory()->create(['name' => 'WAEC Result Checker', 'fulfilment_type' => FulfilmentType::Instant, 'price' => 650_000]);
    }

    /**
     * The OrderPaid event is wired to the (queued) allocation listener.
     *
     * @return void
     */
    public function test_allocation_listens_to_order_paid(): void
    {
        Event::fake([OrderPaid::class]);

        $order = $this->result_checker_order();
        $this->pay_with_webhook($this->initiated_payment_for($order))->assertOk();

        Event::assertListening(OrderPaid::class, AllocateResultCheckerPins::class);
        Event::assertDispatchedTimes(OrderPaid::class, 1);
    }

    /**
     * Paying for one PIN allocates it, completes the order, and emails the PIN.
     *
     * @return void
     */
    public function test_verified_payment_delivers_pin_and_completes_order(): void
    {
        $pin = $this->stock(1)->first();
        $order = $this->result_checker_order();

        $this->pay_with_webhook($this->initiated_payment_for($order))->assertOk();

        $order->refresh();
        $pin->refresh();
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertSame($order->id, $pin->order_id);
        $this->assertSame(PinStatus::Revealed, $pin->status);
        $this->assertNotNull($pin->allocated_at);
        $this->assertNotNull($pin->revealed_at);
        $this->assertSame('Automatic (PINs delivered)', $order->statusChanges()->first()->actorLabel());

        Mail::assertQueued(PaymentReceiptMail::class);
        Mail::assertQueued(ResultCheckerPinsMail::class, function (ResultCheckerPinsMail $mail) use ($pin): bool {
            $mail->assertSeeInHtml($pin->pin);
            $mail->assertSeeInHtml($pin->serial_number);

            return $mail->hasTo('ada@example.com') && $mail->still_owed === 0;
        });
        Mail::assertNotQueued(ResultCheckerDelayedMail::class);
        Notification::assertNotSentTo($this->staff_user, PinsNeedManualFulfilment::class);
        Notification::assertSentTo($this->staff_user, PinStockRunningLow::class, fn (PinStockRunningLow $notification): bool => $notification->level === PinStockLevel::Out);
    }

    /**
     * No stock: the order stays PAID (money kept, one payment, one receipt),
     * staff are notified, and a duplicate webhook changes nothing.
     *
     * @return void
     */
    public function test_no_stock_leaves_order_paid_and_flags_manual_fulfilment(): void
    {
        $order = $this->result_checker_order();
        $payment = $this->initiated_payment_for($order);

        $this->pay_with_webhook($payment)->assertOk();
        $this->pay_with_webhook($payment)->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(PaymentStatus::Successful, $payment->refresh()->status);
        $this->assertSame(1, $order->payments()->count());
        $this->assertNotNull($order->receipt);
        $this->assertSame(1, $order->undeliveredPinCount());
        $this->assertFalse($order->canBePaid());

        Mail::assertNotQueued(ResultCheckerPinsMail::class);
        Mail::assertQueued(ResultCheckerDelayedMail::class, fn (ResultCheckerDelayedMail $mail): bool => $mail->hasTo('ada@example.com'));
        Mail::assertQueued(ResultCheckerDelayedMail::class, 1);
        Notification::assertSentTo($this->staff_user, PinsNeedManualFulfilment::class, fn (PinsNeedManualFulfilment $notification): bool => $notification->undelivered === 1
            && $notification->reason === PinsNeedManualFulfilment::OUT_OF_STOCK
            && $notification->order->is($order));
        Notification::assertSentTimes(PinsNeedManualFulfilment::class, 1);

        $this->look_up($order);
        $this->get(route('orders.track.show', $order))
            ->assertOk()
            ->assertSee('1 PIN is on the way')
            ->assertDontSee('Amount to pay');

        $this->actingAs($this->staff_user);
        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertSee('PINs still to deliver')
            ->assertActionVisible('deliverPins')
            ->assertActionHidden('markCompleted');
    }

    /**
     * Quantity 3 with 2 in stock: 2 delivered, only the third unit flagged.
     *
     * @return void
     */
    public function test_partial_quantity_flags_only_the_unfulfilled_units(): void
    {
        $stock = $this->stock(2);
        $order = $this->result_checker_order(quantity: 3);
        $this->assertSame(1_950_000, $order->total_amount);

        $this->pay_with_webhook($this->initiated_payment_for($order))->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(2, $order->pins()->count());
        $this->assertSame(1, $order->undeliveredPinCount());
        $this->assertEqualsCanonicalizing($stock->modelKeys(), $order->pins()->pluck('id')->all());
        $this->assertTrue($order->pins->every(fn (ResultCheckerPin $pin): bool => $pin->status === PinStatus::Revealed));

        Mail::assertQueued(ResultCheckerPinsMail::class, fn (ResultCheckerPinsMail $mail): bool => $mail->pins->count() === 2 && $mail->still_owed === 1);
        Notification::assertSentTo($this->staff_user, PinsNeedManualFulfilment::class, fn (PinsNeedManualFulfilment $notification): bool => $notification->undelivered === 1);

        $this->look_up($order);
        $this->get(route('orders.track.show', $order))
            ->assertOk()
            ->assertSee($stock[0]->pin)
            ->assertSee($stock[1]->pin)
            ->assertSee('1 PIN is on the way');
    }

    /**
     * After a top-up, "Deliver PIN manually" (from stock) sends the rest and completes the order.
     *
     * @return void
     */
    public function test_deliver_manually_from_stock_after_top_up(): void
    {
        $this->stock(1);
        $order = $this->result_checker_order(quantity: 2);
        $this->pay_with_webhook($this->initiated_payment_for($order))->assertOk();
        $this->assertSame(1, $order->refresh()->undeliveredPinCount());

        $this->actingAs($this->staff_user);
        $view_order = Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()]);

        $view_order->callAction('deliverPins', ['method' => 'stock'])
            ->assertNotified('No PINs in stock for WAEC Result Checker');
        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);

        $top_up = $this->stock(3);

        $view_order->callAction('deliverPins', ['method' => 'stock'])
            ->assertNotified('1 PIN delivered; order completed');

        $order->refresh();
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertSame(2, $order->pins()->count());
        $this->assertSame($top_up[0]->id, $order->pins()->max('id'));
        $this->assertSame(2, ResultCheckerPin::query()->available()->count());
        Mail::assertQueuedCount(4); // receipt, "delayed", first PIN, second PIN
        Mail::assertQueued(ResultCheckerDelayedMail::class, 1);
    }

    /**
     * "Deliver PIN manually" with a one-off PIN entered by staff; duplicates of stock are refused.
     *
     * @return void
     */
    public function test_deliver_manually_with_one_off_pin(): void
    {
        $existing = $this->stock(1, status: PinStatus::Revealed)->first();
        $order = $this->result_checker_order();
        $this->pay_with_webhook($this->initiated_payment_for($order))->assertOk();

        $this->actingAs($this->staff_user);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('deliverPins', ['method' => 'one_off', 'pin' => $existing->pin])
            ->assertNotified('That PIN is already in stock or has been sold before. Check it and try again.');
        $this->assertSame(0, $order->refresh()->pins()->count());

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('deliverPins', ['method' => 'one_off', 'pin' => '9999 8888 7777', 'serial_number' => 'wrn123456789'])
            ->assertNotified('PIN delivered to the customer');

        $pin = $order->refresh()->pins()->sole();
        $this->assertSame('999988887777', $pin->pin);
        $this->assertSame('WRN123456789', $pin->serial_number);
        $this->assertNull($pin->batch_id);
        $this->assertSame($this->staff_user->id, $pin->added_by);
        $this->assertSame(OrderStatus::Completed, $order->status);
        Mail::assertQueued(ResultCheckerPinsMail::class, fn (ResultCheckerPinsMail $mail): bool => $mail->pins->first()->is($pin));
    }

    /**
     * Staff can't complete a result checker order by hand while PINs are owed.
     *
     * @return void
     */
    public function test_staff_cannot_complete_order_that_is_owed_pins(): void
    {
        $order = $this->result_checker_order();
        $this->pay_with_webhook($this->initiated_payment_for($order))->assertOk();

        $this->expectException(BusinessRuleException::class);

        app(ChangeOrderStatus::class)->handle($order->refresh(), OrderStatus::Completed, $this->staff_user);
    }

    /**
     * Staff/admin revealing a PIN on the order screen writes an audit row
     * (same table as document views); the customer seeing their own PIN does not.
     *
     * @return void
     */
    public function test_staff_viewing_pin_is_audited_but_customer_is_not(): void
    {
        $pin = $this->stock(1)->first();
        $customer = User::factory()->create(['email' => 'ada@example.com']);
        $order = $this->result_checker_order(owner: $customer);
        $this->pay_with_webhook($this->initiated_payment_for($order))->assertOk();

        $this->actingAs($customer)->get(route('orders.track.show', $order))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee($pin->pin);
        $this->actingAs($customer)->get(route('my-requests'))->assertOk()->assertSee($pin->pin);
        $this->assertDatabaseCount('document_access_logs', 0);

        foreach ([$this->staff_user, User::factory()->admin()->create()] as $viewer) {
            $this->actingAs($viewer);

            Livewire::test(PinsRelationManager::class, ['ownerRecord' => $order, 'pageClass' => ViewOrder::class])
                ->assertCanSeeTableRecords([$pin])
                ->assertDontSeeHtml($pin->pin)
                ->mountTableAction('showPin', $pin)
                ->assertMountedActionModalSee($pin->pin);

            $this->assertDatabaseHas('document_access_logs', [
                'result_checker_pin_id' => $pin->id,
                'order_document_id' => null,
                'user_id' => $viewer->id,
                'ip_address' => '127.0.0.1',
            ]);
        }

        $this->assertDatabaseCount('document_access_logs', 2);
    }

    /**
     * A signed-in staff member who looks a guest order up on the public page is audited too.
     *
     * @return void
     */
    public function test_staff_viewing_pin_on_tracking_page_is_audited(): void
    {
        $pin = $this->stock(1)->first();
        $order = $this->result_checker_order();
        $this->pay_with_webhook($this->initiated_payment_for($order))->assertOk();

        $this->actingAs($this->staff_user);
        $this->look_up($order);
        $this->get(route('orders.track.show', $order))->assertOk()->assertSee($pin->pin);

        $this->assertDatabaseHas('document_access_logs', ['result_checker_pin_id' => $pin->id, 'user_id' => $this->staff_user->id]);

        // A guest (not signed in) is the customer: not logged.
        $this->app['auth']->forgetGuards();
        $this->look_up($order);
        $this->get(route('orders.track.show', $order))->assertOk()->assertSee($pin->pin);
        $this->assertSame(1, DocumentAccessLog::query()->count());
    }

    /**
     * PIN views appear in the admin access log and can be found by order reference.
     *
     * @return void
     */
    public function test_access_log_lists_pin_views(): void
    {
        $pin = $this->stock(1)->first();
        $order = $this->result_checker_order();
        $this->pay_with_webhook($this->initiated_payment_for($order))->assertOk();
        $log = DocumentAccessLog::create(['result_checker_pin_id' => $pin->id, 'user_id' => $this->staff_user->id]);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListDocumentAccessLogs::class)
            ->assertCanSeeTableRecords([$log])
            ->assertSee('Result checker PIN (WAEC Result Checker)')
            ->assertDontSeeHtml($pin->pin)
            ->searchTable(strtolower($order->reference))
            ->assertCanSeeTableRecords([$log])
            ->searchTable('ME-ZZZZZZ')
            ->assertCanNotSeeTableRecords([$log]);
    }

    /**
     * Available PINs for the WAEC checker.
     *
     * @param  int  $count
     * @param  PinStatus  $status
     * @return Collection<int, ResultCheckerPin>
     */
    private function stock(int $count, PinStatus $status = PinStatus::Available): Collection
    {
        return ResultCheckerPin::factory()->count($count)->for($this->waec)->create(['status' => $status]);
    }

    /**
     * A pending-payment WAEC checker order for ada@example.com.
     *
     * @param  int  $quantity
     * @param  User|null  $owner  Signed-in customer, or null for a guest order.
     * @return Order
     */
    private function result_checker_order(int $quantity = 1, ?User $owner = null): Order
    {
        return Order::factory()
            ->guest()
            ->for($this->waec)
            ->create([
                'user_id' => $owner?->id,
                'guest_name' => 'Ada Obi',
                'guest_email' => 'ada@example.com',
                'guest_phone' => '08031234567',
                'quantity' => $quantity,
                'total_amount' => $this->waec->price * $quantity,
            ]);
    }

    /**
     * Prove access to a guest order on the tracking page.
     *
     * @param  Order  $order
     * @return void
     */
    private function look_up(Order $order): void
    {
        $this->post(route('orders.track.lookup'), ['reference' => $order->reference, 'contact' => 'ada@example.com'])
            ->assertRedirect(route('orders.track.show', $order));
    }
}
