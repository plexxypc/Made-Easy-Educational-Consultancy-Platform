<?php

namespace App\Filament\Resources\ResultCheckerPins\Tables;

use App\Actions\Pins\CheckPinStockLevel;
use App\Enums\FulfilmentType;
use App\Enums\PinStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\ResultCheckerPin;
use App\Models\Service;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ResultCheckerPinsTable
{
    /**
     * PIN stock list. The PIN column is always masked: plaintext is never
     * rendered in a list, only on the order a PIN was allocated to.
     *
     * @param  Table  $table
     * @return Table
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['service:id,name', 'order:id,reference', 'addedBy:id,name']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('service.name')
                    ->label('Service')
                    ->sortable(),
                TextColumn::make('masked_pin')
                    ->label('PIN')
                    ->state(ResultCheckerPin::MASK)
                    ->color('gray'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('order.reference')
                    ->label('Order')
                    ->placeholder('—')
                    ->url(fn (ResultCheckerPin $record): ?string => $record->order_id
                        ? OrderResource::getUrl('view', ['record' => $record->order_id])
                        : null),
                TextColumn::make('batch_id')
                    ->label('Batch')
                    ->formatStateUsing(fn (?string $state): string => $state ? '…'.substr($state, -6) : 'One-off')
                    ->placeholder('One-off')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('addedBy.name')
                    ->label('Added by')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
                TextColumn::make('allocated_at')
                    ->label('Allocated')
                    ->dateTime('j M Y, H:i')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('service_id')
                    ->label('Service')
                    ->relationship('service', 'name', fn (Builder $query) => $query->where('fulfilment_type', FulfilmentType::Instant->value)),
                SelectFilter::make('status')
                    ->options(PinStatus::class),
                SelectFilter::make('batch_id')
                    ->label('Batch')
                    ->options(fn (): array => ResultCheckerPin::query()
                        ->whereNotNull('batch_id')
                        ->distinct()
                        ->orderByDesc('batch_id')
                        ->limit(50)
                        ->pluck('batch_id')
                        ->mapWithKeys(fn (string $batch_id): array => [$batch_id => '…'.substr($batch_id, -6)])
                        ->all()),
            ])
            ->recordActions([
                // Hidden by the policy for PINs already given to an order.
                DeleteAction::make()
                    ->modalDescription('Remove this unused PIN from stock? It will not be sold.')
                    ->after(fn (ResultCheckerPin $record) => self::recheck_stock([$record->service_id])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Delete unused PINs')
                        ->modalDescription('Only PINs that were never allocated to an order are deleted; any others are skipped.')
                        ->using(function (Collection $records): int {
                            $deleted = ResultCheckerPin::query()
                                ->whereKey($records->modelKeys())
                                ->available()
                                ->delete();

                            self::recheck_stock($records->pluck('service_id')->unique()->all());

                            return $deleted;
                        }),
                ]),
            ]);
    }

    /**
     * Deleting stock can take a service to "low" or "out": alert staff if so.
     *
     * @param  array<int, int>  $service_ids
     * @return void
     */
    private static function recheck_stock(array $service_ids): void
    {
        Service::query()->whereKey($service_ids)->get()
            ->each(fn (Service $service) => app(CheckPinStockLevel::class)->handle($service));
    }
}
