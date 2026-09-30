<?php

/**
 * Test helper for ConsultationBookingConcurrencyTest: claim a consultation
 * slot in a separate PHP process (its own database connection).
 *
 * Usage:
 *   php tests/Support/consultation_booking_worker.php book {slot_id} {start_at} {hold_ms} {email}
 *   php tests/Support/consultation_booking_worker.php confirm {order_id} {start_at} {hold_ms}
 *
 *  - book:    a customer submitting the booking form (BookConsultation,
 *             SKIP LOCKED claim).
 *  - confirm: the OrderPaid listener confirming a paid booking
 *             (ConfirmConsultationBooking, waiting lock).
 *  - start_at: unix time (float) to start at, so all workers begin together.
 *  - hold_ms:  pause once the slot row lock is taken, so transactions overlap.
 *
 * Prints JSON: {"mode", "outcome", "started_at", "lock_query_at", "finished_at"}.
 * outcome: book -> "booked" | "unavailable"; confirm -> ConfirmConsultationBooking constant.
 */

use App\Actions\Consultations\BookConsultation;
use App\Actions\Consultations\ConfirmConsultationBooking;
use App\Exceptions\SlotUnavailable;
use App\Models\Order;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Emails aren't under test here; faking them also stops the workers racing to compile the same mail views.
Mail::fake();
Notification::fake();

[, $mode, $target_id, $start_at, $hold_ms, $email] = $argv + [null, null, null, '0', '0', 'worker@example.com'];
$lock_query_at = null;

DB::listen(function (QueryExecuted $query) use ($hold_ms, &$lock_query_at): void {
    $sql = strtolower($query->sql);

    if (str_contains($sql, 'consultation_slots') && str_contains($sql, 'for update') && $lock_query_at === null) {
        $lock_query_at = microtime(true);
        usleep((int) $hold_ms * 1000);
    }
});

while (microtime(true) < (float) $start_at) {
    usleep(500);
}

$started_at = microtime(true);

if ($mode === 'book') {
    try {
        app(BookConsultation::class)->handle((int) $target_id, ['name' => 'Concurrent Customer', 'email' => (string) $email, 'phone' => '08030000000']);
        $outcome = 'booked';
    } catch (SlotUnavailable) {
        $outcome = 'unavailable';
    }
} else {
    $outcome = app(ConfirmConsultationBooking::class)->handle(Order::query()->findOrFail((int) $target_id));
}

echo json_encode([
    'mode' => $mode,
    'outcome' => $outcome,
    'started_at' => $started_at,
    'lock_query_at' => $lock_query_at,
    'finished_at' => microtime(true),
]);
