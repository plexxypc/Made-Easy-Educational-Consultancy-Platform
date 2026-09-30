<?php

namespace App\Actions\Pins;

use App\Models\Order;
use App\Models\Service;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * After new stock arrives, give PINs to paid orders that were left short
 * (PIN stock fallback), oldest order first.
 *
 * Business rules:
 *  - Each order goes through AllocatePinsToOrder, the same locked
 *    transaction the OrderPaid listener uses; nothing is re-implemented here.
 *    Delivery emails the PINs and completes the order when it is fully served.
 *  - Oldest first (order id), so the customer who has waited longest is
 *    served before a later one if the new stock doesn't cover everybody.
 *  - Stops as soon as an order gets nothing (stock exhausted again).
 *  - One order failing is logged and skipped; it doesn't stop the others or
 *    undo the upload. Staff were already notified when the shortfall was
 *    flagged, so a still-short order does not re-notify.
 */
class FulfilWaitingPinOrders
{
    /**
     * @param  AllocatePinsToOrder  $allocate_pins_to_order
     */
    public function __construct(
        private readonly AllocatePinsToOrder $allocate_pins_to_order,
    ) {}

    /**
     * @param  Service  $service  A result checker.
     * @return array{orders_completed: int, pins_delivered: int, orders_still_waiting: int}
     */
    public function handle(Service $service): array
    {
        $orders_completed = 0;
        $pins_delivered = 0;

        $waiting_orders = Order::query()
            ->waitingForPins()
            ->where('service_id', $service->id)
            ->orderBy('id')
            ->get();

        foreach ($waiting_orders as $order) {
            try {
                $result = $this->allocate_pins_to_order->handle($order);
            } catch (Throwable $exception) {
                Log::error('Could not serve a waiting result checker order from new stock.', [
                    'order_id' => $order->id,
                    'order_reference' => $order->reference,
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

            if ($result['allocated'] === 0) {
                break;
            }

            $pins_delivered += $result['allocated'];
            $orders_completed += $result['undelivered'] === 0 ? 1 : 0;
        }

        return [
            'orders_completed' => $orders_completed,
            'pins_delivered' => $pins_delivered,
            'orders_still_waiting' => Order::query()->waitingForPins()->where('service_id', $service->id)->count(),
        ];
    }
}
