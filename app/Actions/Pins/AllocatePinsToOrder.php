<?php

namespace App\Actions\Pins;

use App\Enums\PinStatus;
use App\Models\Order;
use App\Models\ResultCheckerPin;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Give a paid result checker order its PINs from stock, one per unit ordered.
 *
 * Business rules (PIN stock fallback):
 *  - Runs in one transaction. The order row is locked first, so two runs for
 *    the SAME order (listener retry + staff "Deliver PIN manually") can't both
 *    top it up; only quantity minus PINs already allocated is requested.
 *  - Available PINs are selected with FOR UPDATE SKIP LOCKED: concurrent
 *    payments for the same service each lock DIFFERENT rows instead of
 *    waiting for (or re-reading) the same ones. The UPDATE also re-checks
 *    status, and a mismatch rolls everything back, so a PIN can never be
 *    given to two orders.
 *  - If stock can't cover the quantity, what IS available is allocated and
 *    the rest is returned as `undelivered`. The order stays paid; callers
 *    decide how to flag it (AllocateResultCheckerPins notifies staff).
 *  - Anything allocated is delivered straight after commit (DeliverAllocatedPins),
 *    which completes the order once every unit has a PIN.
 */
class AllocatePinsToOrder
{
    /**
     * @param  DeliverAllocatedPins  $deliver_allocated_pins
     */
    public function __construct(
        private readonly DeliverAllocatedPins $deliver_allocated_pins,
    ) {}

    /**
     * @param  Order  $order
     * @return array{allocated: int, undelivered: int} PINs newly allocated now, and units still without a PIN.
     */
    public function handle(Order $order): array
    {
        [$allocated, $undelivered] = DB::transaction(function () use ($order): array {
            $locked_order = Order::query()->with('service')->whereKey($order->getKey())->lockForUpdate()->first();

            if ($locked_order === null || ! $locked_order->canReceivePins()) {
                return [0, 0];
            }

            $needed = $locked_order->quantity - $locked_order->pins()->count();

            if ($needed <= 0) {
                return [0, 0];
            }

            $pin_ids = ResultCheckerPin::query()
                ->available()
                ->where('service_id', $locked_order->service_id)
                ->orderBy('id')
                ->limit($needed)
                ->lock(self::row_lock())
                ->pluck('id')
                ->all();

            if ($pin_ids !== []) {
                $updated = ResultCheckerPin::query()
                    ->whereKey($pin_ids)
                    ->available()
                    ->update([
                        'status' => PinStatus::Allocated->value,
                        'order_id' => $locked_order->id,
                        'allocated_at' => now(),
                        'updated_at' => now(),
                    ]);

                if ($updated !== count($pin_ids)) {
                    throw new RuntimeException("PIN allocation for order {$locked_order->id} lost a race; rolled back.");
                }
            }

            return [count($pin_ids), $needed - count($pin_ids)];
        });

        $this->deliver_allocated_pins->handle($order);

        return ['allocated' => $allocated, 'undelivered' => $undelivered];
    }

    /**
     * Row lock for the stock query. SKIP LOCKED where the database supports it
     * (PostgreSQL in production); plain FOR UPDATE otherwise (SQLite, used by
     * the test suite, has no row locks and serialises writers instead).
     *
     * @return string|bool
     */
    private static function row_lock(): string|bool
    {
        return in_array(DB::getDriverName(), ['pgsql', 'mysql', 'mariadb'], true) ? 'for update skip locked' : true;
    }
}
