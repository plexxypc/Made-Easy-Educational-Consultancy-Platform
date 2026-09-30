<?php

namespace Tests\Feature\Pins;

use App\Actions\Pins\AllocatePinsToOrder;
use App\Actions\Pins\CheckPinStockLevel;
use App\Actions\Pins\ImportPinBatch;
use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Enums\PinStockLevel;
use App\Mail\ResultCheckerPinsMail;
use App\Models\Order;
use App\Models\ResultCheckerPin;
use App\Models\Service;
use App\Models\User;
use App\Notifications\PinStockRunningLow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Per-service low-stock thresholds, the once-per-crossing staff alert, and
 * automatic fulfilment of waiting orders when new PIN stock is uploaded.
 */
class PinStockAlertTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var Service
     */
    private Service $waec;

    /**
     * @var User
     */
    private User $staff_user;

    /**
     * @var User
     */
    private User $admin_user;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();

        $this->staff_user = User::factory()->staff()->create();
        $this->admin_user = User::factory()->admin()->create();
        $this->waec = Service::factory()->create(['name' => 'WAEC Result Checker', 'fulfilment_type' => FulfilmentType::Instant, 'price' => 650_000]);
    }

    /**
     * The service's own threshold wins; unset falls back to the global default.
     *
     * @return void
     */
    public function test_threshold_is_per_service_with_global_default(): void
    {
        config(['madeeasy.pins.low_stock_threshold' => 7]);

        $this->assertSame(7, $this->waec->lowStockThreshold());

        $this->waec->update(['pin_low_stock_threshold' => 2]);
        $this->assertSame(2, $this->waec->refresh()->lowStockThreshold());

        $this->assertSame(PinStockLevel::Ok, PinStockLevel::for_count(3, 2));
        $this->assertSame(PinStockLevel::Low, PinStockLevel::for_count(2, 2));
        $this->assertSame(PinStockLevel::Out, PinStockLevel::for_count(0, 2));
    }

    /**
     * Staff and admins are alerted once when stock reaches the threshold and
     * once when it runs out; later checks at the same level send nothing, and
     * a restock re-arms the alert.
     *
     * @return void
     */
    public function test_low_stock_notification_fires_once_per_crossing(): void
    {
        $this->waec->update(['pin_low_stock_threshold' => 2]);
        ResultCheckerPin::factory()->count(4)->for($this->waec)->create();

        $this->pay_and_allocate(); // 3 left: ok
        Notification::assertNothingSent();

        $this->pay_and_allocate(); // 2 left: low
        $this->assert_alerts_sent(1, PinStockLevel::Low);

        $this->pay_and_allocate(); // 1 left: still low
        app(CheckPinStockLevel::class)->handle($this->waec);
        app(CheckPinStockLevel::class)->handle($this->waec);
        $this->assert_alerts_sent(1);

        $this->pay_and_allocate(); // 0 left: out
        $this->assert_alerts_sent(2, PinStockLevel::Out);

        $this->pay_and_allocate(); // nothing to allocate: still out
        app(CheckPinStockLevel::class)->handle($this->waec);
        $this->assert_alerts_sent(2);

        // Restock (the waiting order takes 1, leaving 4: ok) is silent...
        app(ImportPinBatch::class)->handle($this->waec, "111122223333\n222233334444\n333344445555\n444455556666\n555566667777", $this->admin_user);
        $this->assertSame(PinStockLevel::Ok, $this->waec->refresh()->pin_stock_level);
        $this->assert_alerts_sent(2);

        // ...and re-arms the alert for the next fall.
        $this->pay_and_allocate();
        $this->pay_and_allocate(); // 2 left: low again
        $this->assert_alerts_sent(3, PinStockLevel::Low);
    }

    /**
     * Uploading stock fulfils orders already waiting for PINs, oldest first,
     * and stops at the first one it can't complete.
     *
     * @return void
     */
    public function test_upload_fulfils_waiting_orders_oldest_first(): void
    {
        $oldest = $this->paid_order(quantity: 1);
        $middle = $this->paid_order(quantity: 2);
        $newest = $this->paid_order(quantity: 1);
        $not_paid = $this->paid_order(quantity: 1, status: OrderStatus::PendingPayment);

        $result = app(ImportPinBatch::class)->handle($this->waec, "111122223333\n222233334444\n333344445555", $this->admin_user);

        $this->assertSame(3, $result['imported']);
        $this->assertSame(2, $result['orders_completed']);
        $this->assertSame(3, $result['pins_delivered']);
        $this->assertSame(1, $result['orders_still_waiting']);

        $this->assertSame(OrderStatus::Completed, $oldest->refresh()->status);
        $this->assertSame(OrderStatus::Completed, $middle->refresh()->status);
        $this->assertSame(2, $middle->pins()->count());
        $this->assertSame(OrderStatus::Paid, $newest->refresh()->status);
        $this->assertSame(1, $newest->undeliveredPinCount());
        $this->assertSame(0, $not_paid->pins()->count());
        $this->assertSame(0, ResultCheckerPin::query()->available()->count());

        Mail::assertQueued(ResultCheckerPinsMail::class, 2);
        Mail::assertQueued(ResultCheckerPinsMail::class, fn (ResultCheckerPinsMail $mail): bool => $mail->order->is($oldest));
        Mail::assertQueued(ResultCheckerPinsMail::class, fn (ResultCheckerPinsMail $mail): bool => $mail->order->is($middle));
        $this->assertSame([$newest->id], Order::query()->waitingForPins()->pluck('id')->all());
    }

    /**
     * A paid, single-PIN order allocated as the payment listener would.
     *
     * @return void
     */
    private function pay_and_allocate(): void
    {
        app(AllocatePinsToOrder::class)->handle($this->paid_order(quantity: 1));
    }

    /**
     * @param  int  $quantity
     * @param  OrderStatus  $status
     * @return Order
     */
    private function paid_order(int $quantity, OrderStatus $status = OrderStatus::Paid): Order
    {
        return Order::factory()->guest()->for($this->waec)->create([
            'status' => $status,
            'quantity' => $quantity,
            'total_amount' => $this->waec->price * $quantity,
        ]);
    }

    /**
     * Staff and admin have each had `$times` alerts; the latest at `$level`.
     *
     * @param  int  $times
     * @param  PinStockLevel|null  $level
     * @return void
     */
    private function assert_alerts_sent(int $times, ?PinStockLevel $level = null): void
    {
        Notification::assertSentToTimes($this->staff_user, PinStockRunningLow::class, $times);
        Notification::assertSentToTimes($this->admin_user, PinStockRunningLow::class, $times);

        if ($level !== null) {
            $sent = Notification::sent($this->staff_user, PinStockRunningLow::class);
            $this->assertSame($level, $sent->last()->level);
        }
    }
}
