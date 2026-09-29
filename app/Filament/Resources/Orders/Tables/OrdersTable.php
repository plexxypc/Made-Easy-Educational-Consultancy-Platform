<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ServiceCategory;
use App\Support\Money;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    /**
     * Order list: customer/guest, service, status, total, date.
     *
     * @param  Table  $table
     * @return Table
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'service']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->description(fn (Order $record): ?string => $record->user_id === null ? 'Guest' : null)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(
                        fn (Builder $query) => $query
                            ->whereLike('guest_name', "%{$search}%")
                            ->orWhereLike('guest_email', "%{$search}%")
                            ->orWhereHas('user', fn (Builder $user_query) => $user_query
                                ->whereLike('name', "%{$search}%")
                                ->orWhereLike('email', "%{$search}%")),
                    )),
                TextColumn::make('service.name')
                    ->label('Service')
                    ->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->formatStateUsing(fn (Order $record): string => $record->awaitsQuote() ? 'Awaiting quote' : Money::format($record->total_amount))
                    ->color(fn (Order $record): ?string => $record->awaitsQuote() ? 'warning' : null)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(OrderStatus::class)
                    ->multiple(),
                SelectFilter::make('service_category')
                    ->label('Service category')
                    ->options(fn (): array => ServiceCategory::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string|int $category_id) => $query->whereHas(
                            'service',
                            fn (Builder $service_query) => $service_query->where('service_category_id', $category_id),
                        ),
                    )),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
