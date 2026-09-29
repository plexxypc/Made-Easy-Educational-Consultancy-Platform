<?php

namespace App\Filament\Resources\Services\Tables;

use App\Enums\FulfilmentType;
use App\Models\Service;
use App\Support\Money;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServicesTable
{
    /**
     * Service list. "Availability" and "Price" make inactive placeholders and
     * quote-priced services stand out at a glance.
     *
     * @param  Table  $table
     * @return Table
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('category'))
            ->groups([
                Group::make('category.name')->label('Category')->collapsible(),
            ])
            ->defaultGroup('category.name')
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Service $record): string => $record->possibleDuplicateSlug()
                        ? "{$record->slug} · ⚠ possible duplicate of {$record->possibleDuplicateSlug()}"
                        : $record->slug),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('availability')
                    ->state(fn (Service $record): string => match (true) {
                        $record->is_active => 'Active',
                        $record->isInactivePlaceholder() => 'Inactive · placeholder price',
                        default => 'Inactive',
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Inactive' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('price')
                    ->formatStateUsing(fn (Service $record): string => $record->fulfilment_type === FulfilmentType::Quote
                        ? 'Quote (₦0)'
                        : Money::format($record->price))
                    ->color(fn (Service $record): ?string => $record->fulfilment_type === FulfilmentType::Quote ? 'info' : null)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('fulfilment_type')
                    ->label('Fulfilment')
                    ->badge(),
                TextColumn::make('customer_fields')
                    ->label('Customer fields')
                    ->state(fn (Service $record): int => count($record->customer_fields ?? []))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('inactive_placeholders')
                    ->label('Inactive placeholders only')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->inactivePlaceholder()),
                Filter::make('possible_duplicates')
                    ->label('Possible duplicates only')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereIn('slug', array_keys(Service::POSSIBLE_DUPLICATES))),
                TernaryFilter::make('is_active')
                    ->label('Active'),
                SelectFilter::make('fulfilment_type')
                    ->label('Fulfilment')
                    ->options(FulfilmentType::class),
                SelectFilter::make('service_category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                // Hidden by the policy once a service has orders; deactivate instead.
                DeleteAction::make(),
            ]);
    }
}
