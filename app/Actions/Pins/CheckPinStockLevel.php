<?php

namespace App\Actions\Pins;

use App\Enums\PinStockLevel;
use App\Enums\UserRole;
use App\Models\ResultCheckerPin;
use App\Models\Service;
use App\Models\User;
use App\Notifications\PinStockRunningLow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Re-read a result checker's available PIN count and alert staff when it
 * crosses the service's low-stock threshold or reaches zero.
 *
 * Business rules (low-stock alerts):
 *  - The level (ok / low / out) is compared with the one stored on the
 *    service the last time this ran. Staff are notified (bell + email) only
 *    when it gets WORSE: ok -> low, ok -> out, low -> out. The same level on
 *    later checks sends nothing, so an alert fires once per crossing.
 *  - A restock that brings the level back up is recorded silently, which
 *    re-arms the alert for the next time stock falls.
 *  - The service row is locked while comparing, so two allocations finishing
 *    at the same moment can't both send the same alert.
 *  - Threshold: Service::lowStockThreshold() (per service, else the default).
 *
 * Called after PIN allocation, uploads, deletions and threshold edits.
 */
class CheckPinStockLevel
{
    /**
     * @param  Service  $service
     * @return PinStockLevel The current level.
     */
    public function handle(Service $service): PinStockLevel
    {
        if (! $service->isResultChecker()) {
            return PinStockLevel::Ok;
        }

        [$level, $alert, $available, $locked_service] = DB::transaction(function () use ($service): array {
            $locked_service = Service::query()->whereKey($service->getKey())->lockForUpdate()->firstOrFail();
            $available = ResultCheckerPin::query()->available()->where('service_id', $locked_service->id)->count();
            $level = PinStockLevel::for_count($available, $locked_service->lowStockThreshold());
            $previous_level = $locked_service->pin_stock_level ?? PinStockLevel::Ok;

            if ($level === $previous_level && $locked_service->pin_stock_level !== null) {
                return [$level, false, $available, $locked_service];
            }

            $locked_service->forceFill([
                'pin_stock_level' => $level,
                'pin_stock_level_changed_at' => now(),
            ])->save();

            return [$level, $level->severity() > $previous_level->severity(), $available, $locked_service];
        });

        $service->setRawAttributes($locked_service->getAttributes(), true);

        if ($alert) {
            Notification::send(
                User::query()->whereIn('role', [UserRole::Staff->value, UserRole::Admin->value])->get(),
                new PinStockRunningLow($locked_service, $level, $available),
            );
        }

        return $level;
    }
}
