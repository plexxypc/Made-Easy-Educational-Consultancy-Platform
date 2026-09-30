<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dashboard order counts for staff/admin. PIN stock and orders waiting for
 * PINs are in PinStockOverview.
 */
class OrdersOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 10;

    protected ?string $pollingInterval = '60s';

    /**
     * Staff and admin only (the panel already enforces this; kept explicit).
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
        return [
            Stat::make('Needs action', $this->orders()->where('status', OrderStatus::NeedsAction->value)->count())
                ->description('Waiting on the customer')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('danger'),
            Stat::make('Pending', $this->orders()->where('status', OrderStatus::PendingPayment->value)->count())
                ->description('Pending payment or quote')
                ->icon(Heroicon::OutlinedClock)
                ->color('warning'),
            Stat::make('Last 7 days', $this->orders()->where('created_at', '>=', now()->subDays(7))->count())
                ->description('Orders created')
                ->icon(Heroicon::OutlinedClipboardDocumentList),
        ];
    }

    /**
     * Orders visible to the current user (same scope as the order list).
     *
     * @return Builder<Order>
     */
    private function orders(): Builder
    {
        return Order::query()->visibleTo(auth()->user());
    }
}
