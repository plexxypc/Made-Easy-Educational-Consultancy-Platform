<?php

namespace Tests\Feature\Consultations;

use App\Actions\Consultations\ConfirmConsultationBooking;
use App\Enums\ConsultationBookingStatus;
use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSlot;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Process\Pool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Simultaneous customers can never both get the last place in a consultation slot.
 *
 * Like PinAllocationConcurrencyTest, a real concurrency test: each attempt
 * runs in its own PHP process with its own PostgreSQL connection, all
 * started at the same instant, each holding the slot row lock for HOLD_MS.
 * Skipped on SQLite (no row locks); see README "Tests" for the PostgreSQL run.
 */
#[Group('pgsql')]
class ConsultationBookingConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    /**
     * How long each worker holds the slot lock (ms).
     *
     * @var int
     */
    private const HOLD_MS = 300;

    /**
     * @var int
     */
    private const WORKERS = 6;

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
     * 6 customers submit the booking form for the same 1-place slot at once:
     * exactly one booking is created; the others are told it's taken.
     *
     * @return void
     */
    public function test_simultaneous_bookings_claim_the_slot_once(): void
    {
        $this->consultation_service();
        $slot = ConsultationSlot::factory()->create();

        $results = $this->run_concurrently(collect(range(1, self::WORKERS))->map(fn (int $i): array => [
            'book', (string) $slot->id, "customer{$i}@example.com",
        ]));

        $this->assertSame(1, $results->where('outcome', 'booked')->count());
        $this->assertSame(self::WORKERS - 1, $results->where('outcome', 'unavailable')->count());
        $this->assertSame(1, ConsultationBooking::query()->count());
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, $slot->bookings()->occupying()->count());

        // The race really happened: some losers tried while the winner held the lock.
        $winner = $results->firstWhere('outcome', 'booked');
        $this->assertGreaterThan(
            0,
            $results->where('outcome', 'unavailable')->filter(fn (array $result): bool => $result['lock_query_at'] !== null
                && $result['lock_query_at'] > $winner['lock_query_at']
                && $result['lock_query_at'] < $winner['finished_at'])->count(),
            'Workers ran one after another; the test did not exercise concurrent booking.',
        );
    }

    /**
     * Two (or more) simultaneous payments for the same slot, whose holds had
     * lapsed: exactly one booking is confirmed; the others are flagged for a
     * new time (their money is kept and staff are told).
     *
     * @return void
     */
    public function test_simultaneous_payments_for_one_slot_confirm_only_one(): void
    {
        $service = $this->consultation_service();
        $slot = ConsultationSlot::factory()->create();

        $orders = Order::factory()->guest()->for($service)->count(self::WORKERS)->create([
            'status' => OrderStatus::Paid,
            'total_amount' => $service->price,
        ])->toBase();

        foreach ($orders as $order) {
            ConsultationBooking::factory()->for($order)->for($slot, 'slot')->create(['hold_expires_at' => now()->subMinute()]);
        }

        $results = $this->run_concurrently($orders->map(fn (Order $order): array => ['confirm', (string) $order->id]));

        $this->assertSame(1, $results->where('outcome', ConfirmConsultationBooking::CONFIRMED)->count());
        $this->assertSame(self::WORKERS - 1, $results->where('outcome', ConfirmConsultationBooking::NEEDS_REBOOKING)->count());
        $this->assertSame(1, ConsultationBooking::query()->where('status', ConsultationBookingStatus::Confirmed->value)->count());
        $this->assertSame(self::WORKERS - 1, ConsultationBooking::query()->whereNotNull('needs_rebooking_at')->count());
        $this->assertSame(1, $slot->bookings()->occupying()->count());
        $this->assertTrue($orders->every(fn (Order $order): bool => $order->refresh()->status === OrderStatus::Paid));

        // The race really happened: some worker had to wait for another's lock.
        $this->assertGreaterThan(
            0,
            $results->filter(fn (array $result): bool => self::HOLD_MS * 0.8 / 1000 <= $result['lock_query_at'] - $result['started_at'])->count(),
            'No worker waited for the slot lock; the test did not exercise concurrent confirmation.',
        );
    }

    /**
     * Start one worker per argument list at the same moment and collect their JSON.
     *
     * @param  Collection<int, array<int, string>>  $jobs  [mode, id, ...extra]
     * @return Collection<int, array{mode: string, outcome: string, started_at: float, lock_query_at: float|null, finished_at: float}>
     */
    private function run_concurrently(Collection $jobs): Collection
    {
        $start_at = sprintf('%.6F', microtime(true) + 3);

        $results = Process::pool(function (Pool $pool) use ($jobs, $start_at): void {
            foreach ($jobs as $job) {
                [$mode, $target_id] = $job;

                $pool->path(base_path())->timeout(60)->command([
                    PHP_BINARY,
                    'tests/Support/consultation_booking_worker.php',
                    $mode,
                    $target_id,
                    $start_at,
                    (string) self::HOLD_MS,
                    ...array_slice($job, 2),
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
     * @return Service
     */
    private function consultation_service(): Service
    {
        return Service::factory()->create(['fulfilment_type' => FulfilmentType::Consultation, 'price' => 1_500_000]);
    }
}
