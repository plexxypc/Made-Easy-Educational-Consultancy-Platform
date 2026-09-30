<?php

/**
 * Test helper for PinAllocationConcurrencyTest: allocate PINs to ONE order in
 * a separate PHP process (its own database connection), as a queue worker
 * handling a payment would.
 *
 * Usage: php tests/Support/allocate_pins_worker.php {order_id} {start_at} {hold_ms}
 *  - start_at: unix time (float) to start at, so all workers begin together.
 *  - hold_ms:  pause after the stock rows are locked, before updating them,
 *              so the transactions really overlap.
 *
 * Prints JSON: {"order_id", "allocated", "undelivered", "locked_at", "finished_at"}.
 */

use App\Actions\Pins\AllocatePinsToOrder;
use App\Models\Order;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $order_id, $start_at, $hold_ms] = $argv + [null, null, '0', '0'];
$locked_at = null;

DB::listen(function (QueryExecuted $query) use ($hold_ms, &$locked_at): void {
    if (str_contains(strtolower($query->sql), 'skip locked')) {
        $locked_at = microtime(true);
        usleep((int) $hold_ms * 1000);
    }
});

while (microtime(true) < (float) $start_at) {
    usleep(500);
}

$result = app(AllocatePinsToOrder::class)->handle(Order::query()->findOrFail((int) $order_id));

echo json_encode([
    'order_id' => (int) $order_id,
    'allocated' => $result['allocated'],
    'undelivered' => $result['undelivered'],
    'locked_at' => $locked_at,
    'finished_at' => microtime(true),
]);
