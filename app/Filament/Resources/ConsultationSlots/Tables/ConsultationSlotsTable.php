<?php

namespace App\Filament\Resources\ConsultationSlots\Tables;

use App\Actions\Consultations\CancelConsultationSlot;
use App\Enums\ConsultationType;
use App\Exceptions\BusinessRuleException;
use App\Models\ConsultationSlot;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConsultationSlotsTable
{
    /**
     * Slot list: when, type, places booked, status. Upcoming, live slots by default.
     *
     * @param  Table  $table
     * @return Table
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withOccupiedCount())
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('date')->orderBy('start_time'))
            ->columns([
                TextColumn::make('date')
                    ->label('Date')
                    ->formatStateUsing(fn (ConsultationSlot $record): string => $record->dateLabel())
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('date', $direction)->orderBy('start_time', $direction)),
                TextColumn::make('start_time')
                    ->label('Time')
                    ->formatStateUsing(fn (ConsultationSlot $record): string => $record->timeLabel()),
                TextColumn::make('consultation_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('occupied_count')
                    ->label('Booked')
                    ->formatStateUsing(fn (ConsultationSlot $record): string => "{$record->occupied_count} / {$record->capacity}")
                    ->color(fn (ConsultationSlot $record): ?string => $record->occupied_count >= $record->capacity ? 'success' : null),
                TextColumn::make('state')
                    ->label('Status')
                    ->state(fn (ConsultationSlot $record): string => self::state_label($record))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Cancelled' => 'danger',
                        'Full' => 'success',
                        'Past' => 'gray',
                        default => 'info',
                    })
                    ->description(fn (ConsultationSlot $record): ?string => $record->isCancelled() ? $record->cancellation_reason : null),
            ])
            ->filters([
                TernaryFilter::make('when')
                    ->label('When')
                    ->placeholder('All dates')
                    ->trueLabel('Upcoming')
                    ->falseLabel('Past')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereDate('date', '>=', now(ConsultationSlot::timezone())->toDateString()),
                        false: fn (Builder $query): Builder => $query->whereDate('date', '<', now(ConsultationSlot::timezone())->toDateString()),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('cancelled')
                    ->label('Cancelled slots')
                    ->placeholder('Hide cancelled')
                    ->trueLabel('Only cancelled')
                    ->falseLabel('Include cancelled')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('cancelled_at'),
                        false: fn (Builder $query): Builder => $query,
                        blank: fn (Builder $query): Builder => $query->whereNull('cancelled_at'),
                    ),
                SelectFilter::make('consultation_type')
                    ->label('Type')
                    ->options(ConsultationType::class),
            ])
            ->recordActions([
                self::cancel_action(),
                DeleteAction::make()
                    ->modalDescription('Only slots that have never been booked can be deleted.'),
            ]);
    }

    /**
     * "Cancel slot": stops new bookings and cancels (and emails) anyone booked on it.
     *
     * @return Action
     */
    private static function cancel_action(): Action
    {
        return Action::make('cancelSlot')
            ->label('Cancel')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->authorize('cancel')
            ->visible(fn (ConsultationSlot $record): bool => ! $record->isCancelled() && ! $record->hasStarted())
            ->requiresConfirmation()
            ->modalHeading(fn (ConsultationSlot $record): string => 'Cancel '.$record->label().'?')
            ->modalDescription(fn (ConsultationSlot $record): string => $record->occupiedCount() > 0
                ? trans_choice(
                    ':count customer has this time. Their booking and order will be cancelled and they will be emailed. Refund any payment in Paystack.|:count customers have this time. Their bookings and orders will be cancelled and they will be emailed. Refund any payments in Paystack.',
                    $record->occupiedCount(),
                )
                : 'Nobody has booked this time. It will stop being offered to customers.')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason (optional, sent to affected customers)')
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->action(function (ConsultationSlot $record, array $data, Action $action, CancelConsultationSlot $cancel_consultation_slot): void {
                try {
                    $cancelled = $cancel_consultation_slot->handle($record, auth()->user(), $data['reason'] ?? null);
                } catch (BusinessRuleException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    $action->halt();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Slot cancelled')
                    ->body($cancelled > 0 ? trans_choice(':count booking cancelled; the customer has been emailed.|:count bookings cancelled; the customers have been emailed.', $cancelled) : null)
                    ->send();
            });
    }

    /**
     * Cancelled / Past / Full / Open.
     *
     * @param  ConsultationSlot  $record
     * @return string
     */
    private static function state_label(ConsultationSlot $record): string
    {
        return match (true) {
            $record->isCancelled() => 'Cancelled',
            $record->hasStarted() => 'Past',
            $record->occupied_count >= $record->capacity => 'Full',
            default => 'Open',
        };
    }
}
