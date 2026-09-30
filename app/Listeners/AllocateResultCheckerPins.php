<?php

namespace App\Listeners;

use App\Actions\Pins\AllocatePinsToOrder;
use App\Enums\UserRole;
use App\Events\OrderPaid;
use App\Mail\ResultCheckerDelayedMail;
use App\Models\Order;
use App\Models\User;
use App\Notifications\PinsNeedManualFulfilment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * When a result checker order is paid, allocate and deliver its PINs.
 *
 * Queued, so the Paystack webhook answers quickly and a transient failure is
 * retried by the worker instead of being lost (the webhook itself is
 * idempotent and won't fire OrderPaid twice).
 *
 * Business rule (PIN stock fallback): running out of stock never blocks or
 * reverses the payment. The order stays paid, whatever stock exists is
 * delivered, staff are notified, and the customer is emailed that the rest
 * is delayed. The same happens if allocation keeps failing for another
 * reason. Waiting orders are then served by the next stock upload
 * (FulfilWaitingPinOrders) or by staff ("Deliver PIN manually").
 */
class AllocateResultCheckerPins implements ShouldQueue
{
    /**
     * @var int
     */
    public int $tries = 3;

    /**
     * Seconds between attempts.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 60];

    /**
     * @param  AllocatePinsToOrder  $allocate_pins_to_order
     */
    public function __construct(
        private readonly AllocatePinsToOrder $allocate_pins_to_order,
    ) {}

    /**
     * Only result checker orders need this job.
     *
     * @param  OrderPaid  $event
     * @return bool
     */
    public function shouldQueue(OrderPaid $event): bool
    {
        return $event->order->isResultChecker();
    }

    /**
     * @param  OrderPaid  $event
     * @return void
     */
    public function handle(OrderPaid $event): void
    {
        $result = $this->allocate_pins_to_order->handle($event->order);

        if ($result['undelivered'] > 0) {
            Log::warning('Result checker order paid but PIN stock ran out: manual fulfilment needed.', [
                'order_id' => $event->order->id,
                'order_reference' => $event->order->reference,
                'service_id' => $event->order->service_id,
                'quantity' => $event->order->quantity,
                'allocated_now' => $result['allocated'],
                'undelivered' => $result['undelivered'],
            ]);

            $order = $event->order->refresh();

            self::notify_staff(new PinsNeedManualFulfilment($order, $result['undelivered'], PinsNeedManualFulfilment::OUT_OF_STOCK));
            self::tell_customer_about_delay($order, $result['undelivered']);
        }
    }

    /**
     * All attempts failed: the customer has paid, so a human must step in.
     *
     * @param  OrderPaid  $event
     * @param  Throwable  $exception
     * @return void
     */
    public function failed(OrderPaid $event, Throwable $exception): void
    {
        Log::error('Result checker PIN allocation failed: manual fulfilment needed.', [
            'order_id' => $event->order->id,
            'order_reference' => $event->order->reference,
            'error' => $exception->getMessage(),
        ]);

        $order = $event->order->refresh();

        self::notify_staff(new PinsNeedManualFulfilment($order, max(1, $order->undeliveredPinCount()), PinsNeedManualFulfilment::ALLOCATION_FAILED));

        if ($order->undeliveredPinCount() > 0) {
            self::tell_customer_about_delay($order, $order->undeliveredPinCount());
        }
    }

    /**
     * "Your result checker is delayed", sent when the shortfall is flagged.
     *
     * @param  Order  $order
     * @param  int  $delayed
     * @return void
     */
    private static function tell_customer_about_delay(Order $order, int $delayed): void
    {
        if ($order->customer_email !== null) {
            Mail::to($order->customer_email)->queue(new ResultCheckerDelayedMail($order, $delayed));
        }
    }

    /**
     * @param  PinsNeedManualFulfilment  $notification
     * @return void
     */
    private static function notify_staff(PinsNeedManualFulfilment $notification): void
    {
        Notification::send(
            User::query()->whereIn('role', [UserRole::Staff->value, UserRole::Admin->value])->get(),
            $notification,
        );
    }
}
