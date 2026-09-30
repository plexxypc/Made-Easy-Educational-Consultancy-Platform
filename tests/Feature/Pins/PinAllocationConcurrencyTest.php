<?php

namespace Tests\Feature\Pins;

use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ResultCheckerPin;
use App\Models\Service;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Process\Pool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Simultaneous payments for the same result checker never receive the same PIN.
 *
 * This is a real concurrency test, not a logic check: each order is allocated
 * by a separate PHP process with its own PostgreSQL connection, all started
 * at the same instant, each holding its row locks for HOLD_MS so the
 * transactions overlap. It needs PostgreSQL (SQLite has no row locks), so it
 * is skipped on the default SQLite test run. See README "Tests" for the
 * command that runs the suite against PostgreSQL.
 *
 * Data must be committed for the workers to see it, hence DatabaseTruncation
 * instead of RefreshDatabase; tables are emptied again afterwards so later
 * tests start clean.
 */
#[Group('pgsql')]
class PinAllocationConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    /**
     * How long each worker holds its locks (ms).
     *
     * @var int
     */
    private const HOLD_MS = 300;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        if (($_SERVER['DB_CONNECTION'] ?? getenv('DB_CONNECTION')) !== 'pgsql') {
            $this->markTestSkipped('Needs PostgreSQL: run the suite with DB_CONNECTION=pgsql (see README).');
        }

        parent::setUp();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->app !== null) {
            $this->truncateDatabaseTables();
        }

        parent::tearDown();
    }

    /**
     * 8 simultaneous single-PIN payments against exactly 8 PINs: every order
     * gets one, no PIN is shared, and none is wrongly reported short.
     *
     * @return void
     */
    public function test_simultaneous_payments_never_share_a_pin(): void
    {
        $service = $this->result_checker();
        ResultCheckerPin::factory()->count(8)->for($service)->create();
        $orders = $this->paid_orders($service, count: 8, quantity: 1);

        $results = $this->allocate_concurrently($orders);

        $this->assert_workers_overlapped($results);
        $this->assert_no_pin_shared();
        $this->assertSame(8, $results->sum('allocated'));
        $this->assertSame(0, $results->sum('undelivered'));
        $this->assertSame(0, ResultCheckerPin::query()->available()->count());
        $this->assertTrue($orders->every(fn (Order $order): bool => $order->refresh()->status === OrderStatus::Completed && $order->pins()->count() === 1));
    }

    /**
     * 6 simultaneous payments for 2 PINs each (12 wanted) against 9 PINs:
     * all 9 are used, each exactly once, and exactly 3 units are flagged.
     *
     * @return void
     */
    public function test_simultaneous_multi_pin_payments_with_short_stock(): void
    {
        $service = $this->result_checker();
        ResultCheckerPin::factory()->count(9)->for($service)->create();
        $orders = $this->paid_orders($service, count: 6, quantity: 2);

        $results = $this->allocate_concurrently($orders);

        $this->assert_workers_overlapped($results);
        $this->assert_no_pin_shared();
        $this->assertSame(9, $results->sum('allocated'));
        $this->assertSame(3, $results->sum('undelivered'));
        $this->assertSame(3, $orders->sum(fn (Order $order): int => $order->refresh()->undeliveredPinCount()));
        $this->assertTrue($orders->every(fn (Order $order): bool => $order->pins()->count() <= 2));
    }

    /**
     * Run AllocatePinsToOrder for every order at the same moment, one process each.
     *
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, array{order_id: int, allocated: int, undelivered: int, locked_at: float|null, finished_at: float}>
     */
    private function allocate_concurrently(Collection $orders): Collection
    {
        $start_at = sprintf('%.6F', microtime(true) + 3);

        $results = Process::pool(function (Pool $pool) use ($orders, $start_at): void {
            foreach ($orders as $order) {
                $pool->path(base_path())->timeout(60)->command([
                    PHP_BINARY,
                    'tests/Support/allocate_pins_worker.php',
                    (string) $order->id,
                    $start_at,
                    (string) self::HOLD_MS,
                ]);
            }
        })->start()->wait();

        return $results->collect()->map(function (ProcessResult $result): array {
            $this->assertTrue($result->successful(), 'Worker failed: '.$result->errorOutput().$result->output());

            $decoded = json_decode(trim($result->output()), true);
            $this->assertIsArray($decoded, 'Worker printed unexpected output: '.$result->output());

            return $decoded;
        })->values();
    }

    /**
     * At least two workers held their locks at the same time (the race really happened).
     *
     * @param  Collection<int, array{locked_at: float|null, finished_at: float}>  $results
     * @return void
     */
    private function assert_workers_overlapped(Collection $results): void
    {
        $windows = $results->filter(fn (array $result): bool => $result['locked_at'] !== null)->sortBy('locked_at')->values();

        $overlaps = $windows->slice(1)->filter(fn (array $window, int $index): bool => $window['locked_at'] < $windows[$index - 1]['finished_at']);

        $this->assertGreaterThan(0, $overlaps->count(), 'Workers ran one after another; the test did not exercise concurrent allocation.');
    }

    /**
     * No PIN belongs to two orders and none is allocated twice.
     *
     * @return void
     */
    private function assert_no_pin_shared(): void
    {
        $allocated = DB::table('result_checker_pins')->whereNotNull('order_id')->get(['id', 'pin_hash']);

        $this->assertSame($allocated->count(), $allocated->pluck('id')->unique()->count());
        $this->assertSame($allocated->count(), $allocated->pluck('pin_hash')->unique()->count());
    }

    /**
     * @return Service
     */
    private function result_checker(): Service
    {
        return Service::factory()->create(['fulfilment_type' => FulfilmentType::Instant, 'price' => 650_000]);
    }

    /**
     * Paid result checker orders, as the webhook leaves them.
     *
     * @param  Service  $service
     * @param  int  $count
     * @param  int  $quantity
     * @return Collection<int, Order>
     */
    private function paid_orders(Service $service, int $count, int $quantity): Collection
    {
        return Order::factory()->guest()->for($service)->count($count)->create([
            'status' => OrderStatus::Paid,
            'quantity' => $quantity,
            'total_amount' => $service->price * $quantity,
        ])->toBase();
    }
}
