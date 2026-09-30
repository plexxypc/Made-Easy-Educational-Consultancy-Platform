<?php

namespace App\Filament\Widgets;

use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Service;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Available result checker PINs per service, with a low-stock warning, plus
 * the number of paid orders still waiting for PINs. Counts only; no PIN
 * values. Shown on the dashboard and above the PIN stock list.
 *
 * Business rule: a service is "low" at or below
 * config('madeeasy.pins.low_stock_threshold') available PINs (one threshold
 * for all services). Services that are inactive and have no stock are left out.
 */
class PinStockOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 20;

    protected ?string $pollingInterval = '60s';

    protected ?string $heading = 'Result checker PIN stock';

    /**
     * Staff see stock levels too (they deliver PINs manually); uploads stay admin only.
     *
     * @return bool
     */
    public static function canView(): bool
    {
        return auth()->user()?->isStaffOrAdmin() ?? false;
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $threshold = (int) config('madeeasy.pins.low_stock_threshold');

        $services = Service::query()
            ->where('fulfilment_type', FulfilmentType::Instant->value)
            ->withCount(['pins as available_pins_count' => fn (Builder $query) => $query->available()])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->filter(fn (Service $service): bool => $service->is_active || $service->available_pins_count > 0);

        $stats = $services->map(function (Service $service) use ($threshold): Stat {
            $available = (int) $service->available_pins_count;
            $is_low = $available <= $threshold;

            return Stat::make($service->name, number_format($available))
                ->description(match (true) {
                    $available === 0 => 'Out of stock: new orders will need manual delivery',
                    $is_low => "Low stock (≤ {$threshold})",
                    default => 'Available',
                }.($service->is_active ? '' : ' · service inactive'))
                ->descriptionIcon($is_low ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                ->color($available === 0 ? 'danger' : ($is_low ? 'warning' : 'success'));
        })->values()->all();

        $waiting = Order::query()
            ->whereHas('service', fn (Builder $query) => $query->where('fulfilment_type', FulfilmentType::Instant->value))
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::InProgress->value])
            ->count();

        $stats[] = Stat::make('Paid orders waiting for PINs', $waiting)
            ->description($waiting > 0 ? 'Open each order and use "Deliver PIN manually"' : 'None')
            ->icon(Heroicon::OutlinedKey)
            ->color($waiting > 0 ? 'danger' : 'gray');

        return $stats;
    }
}
