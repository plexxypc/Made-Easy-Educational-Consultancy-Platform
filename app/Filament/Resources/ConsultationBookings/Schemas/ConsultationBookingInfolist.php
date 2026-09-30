<?php

namespace App\Filament\Resources\ConsultationBookings\Schemas;

use App\Enums\ConsultationBookingStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\ConsultationBooking;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ConsultationBookingInfolist
{
    /**
     * Booking detail: when and how, customer contact (from the order), history.
     *
     * @param  Schema  $schema
     * @return Schema
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Callout::make('Paid – needs a new time')
                    ->description(fn (ConsultationBooking $record): string => 'The customer paid but this time could not be confirmed: '
                        .($record->needs_rebooking_reason ?? 'the slot was no longer available')
                        .'. Contact them, then use "Choose new time" (or "Cancel booking" and refund in Paystack).')
                    ->danger()
                    ->visible(fn (ConsultationBooking $record): bool => $record->needsRebooking())
                    ->columnSpanFull(),

                Callout::make('Meeting link needed')
                    ->description('Create the meeting yourself (e.g. Google Calendar → Add Google Meet) and use "Add meeting link". Until then the customer sees "To be added by our team".')
                    ->warning()
                    ->visible(fn (ConsultationBooking $record): bool => $record->awaitsMeetingLink())
                    ->columnSpanFull(),

                Section::make('Consultation')
                    ->columns(2)
                    ->columnSpan(2)
                    ->schema([
                        TextEntry::make('slot_date')
                            ->label('Date')
                            ->state(fn (ConsultationBooking $record): string => $record->slot->dateLabel()),
                        TextEntry::make('slot_time')
                            ->label('Time')
                            ->state(fn (ConsultationBooking $record): string => $record->slot->timeLabel()),
                        TextEntry::make('slot.consultation_type')
                            ->label('Type')
                            ->badge(),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (ConsultationBooking $record): string => $record->statusLabel())
                            ->color(fn (ConsultationBooking $record): string => $record->needsRebooking() ? 'danger' : $record->status->getColor()),
                        TextEntry::make('meeting_link')
                            ->label('Meeting link')
                            ->url(fn (ConsultationBooking $record): ?string => $record->meeting_link, shouldOpenInNewTab: true)
                            ->copyable()
                            ->placeholder('Not added yet')
                            ->helperText(fn (ConsultationBooking $record): ?string => $record->meeting_link_added_at
                                ? 'Added '.$record->meeting_link_added_at->format('j M Y, H:i').' by '.($record->meetingLinkAddedBy?->name ?? 'a former user')
                                : null)
                            ->visible(fn (ConsultationBooking $record): bool => $record->slot->consultation_type->needsMeetingLink()),
                        TextEntry::make('hold_expires_at')
                            ->label('Held until')
                            ->dateTime('j M Y, H:i')
                            ->visible(fn (ConsultationBooking $record): bool => $record->status === ConsultationBookingStatus::PendingPayment && ! $record->needsRebooking()),
                        TextEntry::make('confirmed_at')
                            ->label('Confirmed')
                            ->dateTime('j M Y, H:i')
                            ->placeholder('—'),
                        TextEntry::make('rescheduled')
                            ->label('Rescheduled')
                            ->state(fn (ConsultationBooking $record): ?string => $record->reschedule_count > 0
                                ? trans_choice(':count time|:count times', $record->reschedule_count)
                                    .($record->previousSlot ? ' (previously '.$record->previousSlot->label().')' : '')
                                : null)
                            ->visible(fn (ConsultationBooking $record): bool => $record->reschedule_count > 0),
                        TextEntry::make('closed')
                            ->label('Closed')
                            ->state(fn (ConsultationBooking $record): ?string => $record->closed_at
                                ? $record->closed_at->format('j M Y, H:i').' by '.($record->closedBy?->name ?? 'a former user')
                                : null)
                            ->visible(fn (ConsultationBooking $record): bool => $record->closed_at !== null),
                        TextEntry::make('cancellation_reason')
                            ->label('Cancellation reason')
                            ->visible(fn (ConsultationBooking $record): bool => $record->status === ConsultationBookingStatus::Cancelled)
                            ->placeholder('None given'),
                    ]),

                Section::make('Customer')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('order.customer_name')
                            ->label('Name')
                            ->state(fn (ConsultationBooking $record): string => $record->order->customer_name),
                        TextEntry::make('order.customer_email')
                            ->label('Email')
                            ->state(fn (ConsultationBooking $record): ?string => $record->order->customer_email)
                            ->placeholder('No email')
                            ->copyable(),
                        TextEntry::make('order.guest_phone')
                            ->label('Phone')
                            ->placeholder('No phone')
                            ->copyable(),
                        TextEntry::make('order.reference')
                            ->label('Order')
                            ->state(fn (ConsultationBooking $record): string => $record->order->display_reference.' · '.$record->order->status->getLabel())
                            ->url(fn (ConsultationBooking $record): string => OrderResource::getUrl('view', ['record' => $record->order])),
                    ]),
            ]);
    }
}
