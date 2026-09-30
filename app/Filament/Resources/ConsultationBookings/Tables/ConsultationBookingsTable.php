<?php

namespace App\Filament\Resources\ConsultationBookings\Tables;

use App\Enums\ConsultationBookingStatus;
use App\Enums\ConsultationType;
use App\Filament\Resources\ConsultationBookings\Actions\ConsultationBookingActions;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSlot;
use App\Models\Order;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConsultationBookingsTable
{
    /**
     * Booking list: when, customer, type, status. Upcoming first by default.
     *
     * @param  Table  $table
     * @return Table
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query): Builder => self::order_by_slot_time($query, 'asc'))
            ->columns([
                TextColumn::make('slot.date')
                    ->label('When')
                    ->formatStateUsing(fn (ConsultationBooking $record): string => $record->slot->dateLabel())
                    ->description(fn (ConsultationBooking $record): string => $record->slot->timeLabel())
                    ->sortable(query: fn (Builder $query, string $direction): Builder => self::order_by_slot_time($query, $direction)),
                TextColumn::make('order.customer_name')
                    ->label('Customer')
                    ->state(fn (ConsultationBooking $record): string => $record->order->customer_name)
                    ->description(fn (ConsultationBooking $record): ?string => $record->order->guest_phone ?? $record->order->customer_email)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('order', fn (Builder $order_query) => $order_query
                        ->withTrashed()
                        ->where(fn (Builder $where) => $where
                            ->where('reference', Order::normaliseReference($search))
                            ->orWhereLike('guest_name', "%{$search}%")
                            ->orWhereLike('guest_email', "%{$search}%")
                            ->orWhereLike('guest_phone', "%{$search}%")
                            ->orWhereHas('user', fn (Builder $user_query) => $user_query
                                ->whereLike('name', "%{$search}%")
                                ->orWhereLike('email', "%{$search}%"))))),
                TextColumn::make('slot.consultation_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ConsultationBooking $record): string => $record->statusLabel())
                    ->color(fn (ConsultationBooking $record): string => $record->needsRebooking() ? 'danger' : $record->status->getColor()),
                TextColumn::make('meeting_link')
                    ->label('Meeting link')
                    ->state(fn (ConsultationBooking $record): ?string => match (true) {
                        filled($record->meeting_link) => 'Added',
                        $record->awaitsMeetingLink() => 'To add',
                        default => null,
                    })
                    ->placeholder('—')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'To add' ? 'warning' : 'success'),
                TextColumn::make('order.reference')
                    ->label('Order')
                    ->formatStateUsing(fn (ConsultationBooking $record): string => $record->order->display_reference)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('when')
                    ->label('When')
                    ->placeholder('All dates')
                    ->trueLabel('Upcoming')
                    ->falseLabel('Past')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('slot', fn (Builder $slot_query) => $slot_query->whereDate('date', '>=', now(ConsultationSlot::timezone())->toDateString())),
                        false: fn (Builder $query): Builder => $query->whereHas('slot', fn (Builder $slot_query) => $slot_query->whereDate('date', '<', now(ConsultationSlot::timezone())->toDateString())),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                Filter::make('date')
                    ->label('Date range')
                    ->schema([
                        DatePicker::make('from')->label('From')->native(),
                        DatePicker::make('until')->label('Until')->native(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $from) => $query->whereHas('slot', fn (Builder $slot_query) => $slot_query->whereDate('date', '>=', $from)))
                        ->when($data['until'] ?? null, fn (Builder $query, string $until) => $query->whereHas('slot', fn (Builder $slot_query) => $slot_query->whereDate('date', '<=', $until))))
                    ->indicateUsing(fn (array $data): ?string => match (true) {
                        filled($data['from'] ?? null) && filled($data['until'] ?? null) => "{$data['from']} to {$data['until']}",
                        filled($data['from'] ?? null) => "From {$data['from']}",
                        filled($data['until'] ?? null) => "Until {$data['until']}",
                        default => null,
                    }),
                SelectFilter::make('status')
                    ->options(ConsultationBookingStatus::class)
                    ->multiple(),
                TernaryFilter::make('needs_rebooking')
                    ->label('Needs a new time')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('status', ConsultationBookingStatus::PendingPayment->value)->whereNotNull('needs_rebooking_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('needs_rebooking_at'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                SelectFilter::make('consultation_type')
                    ->label('Type')
                    ->options(ConsultationType::class)
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $type) => $query->whereHas('slot', fn (Builder $slot_query) => $slot_query->where('consultation_type', $type)),
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make(ConsultationBookingActions::all()),
            ]);
    }

    /**
     * Sort by the booked slot's date and start time.
     *
     * @param  Builder<ConsultationBooking>  $query
     * @param  string  $direction
     * @return Builder<ConsultationBooking>
     */
    private static function order_by_slot_time(Builder $query, string $direction): Builder
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderBy(ConsultationSlot::query()->select('date')->whereColumn('consultation_slots.id', 'consultation_bookings.consultation_slot_id'), $direction)
            ->orderBy(ConsultationSlot::query()->select('start_time')->whereColumn('consultation_slots.id', 'consultation_bookings.consultation_slot_id'), $direction);
    }
}
