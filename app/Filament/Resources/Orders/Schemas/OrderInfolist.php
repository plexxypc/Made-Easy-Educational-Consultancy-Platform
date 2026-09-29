<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use App\Models\OrderStatusChange;
use App\Support\Money;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    /**
     * Order detail screen. Documents are listed by DocumentsRelationManager below this.
     *
     * @param  Schema  $schema
     * @return Schema
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Callout::make('Awaiting quote')
                    ->description('This is a quote-priced service. An admin must use "Set Total" before the order can be worked on.')
                    ->warning()
                    ->visible(fn (Order $record): bool => $record->awaitsQuote())
                    ->columnSpanFull(),

                Section::make('Order')
                    ->columns(2)
                    ->columnSpan(2)
                    ->schema([
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('total_amount')
                            ->label('Total')
                            ->state(fn (Order $record): string => $record->awaitsQuote() ? 'Awaiting quote' : Money::format($record->total_amount)),
                        TextEntry::make('service.name')
                            ->label('Service'),
                        TextEntry::make('service.fulfilment_type')
                            ->label('Fulfilment')
                            ->badge(),
                        TextEntry::make('created_at')
                            ->label('Placed')
                            ->dateTime('j M Y, H:i'),
                        TextEntry::make('quoted_at')
                            ->label('Total set')
                            ->state(fn (Order $record): ?string => $record->quoted_at
                                ? $record->quoted_at->format('j M Y, H:i').' by '.($record->quotedBy?->name ?? 'a former user')
                                : null)
                            ->visible(fn (Order $record): bool => $record->isQuote() && $record->quoted_at !== null),
                        TextEntry::make('deleted_at')
                            ->label('Deleted')
                            ->dateTime('j M Y, H:i')
                            ->color('danger')
                            ->visible(fn (Order $record): bool => $record->trashed()),
                    ]),

                Section::make('Customer')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('customer_name')
                            ->label('Name'),
                        TextEntry::make('account_type')
                            ->label('Account')
                            ->state(fn (Order $record): string => $record->user_id === null ? 'Guest checkout' : 'Registered customer')
                            ->badge()
                            ->color(fn (Order $record): string => $record->user_id === null ? 'warning' : 'gray'),
                        TextEntry::make('customer_email')
                            ->label('Email')
                            ->placeholder('No email')
                            ->copyable(),
                        TextEntry::make('guest_phone')
                            ->label('Phone')
                            ->placeholder('No phone')
                            ->copyable()
                            ->visible(fn (Order $record): bool => $record->user_id === null),
                    ]),

                Section::make('Customer\'s answers')
                    ->columnSpanFull()
                    ->schema([
                        KeyValueEntry::make('answers')
                            ->hiddenLabel()
                            ->state(fn (Order $record): array => $record->labelledFieldAnswers())
                            ->keyLabel('Question')
                            ->valueLabel('Answer')
                            ->placeholder('No answers were submitted for this order.'),
                    ]),

                Section::make('Status history')
                    ->columnSpanFull()
                    ->collapsible()
                    ->visible(fn (Order $record): bool => $record->statusChanges()->exists())
                    ->schema([
                        RepeatableEntry::make('statusChanges')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('to_status')
                                    ->label('Status')
                                    ->badge(),
                                TextEntry::make('actor')
                                    ->label('By')
                                    ->state(fn (OrderStatusChange $record): string => $record->actorLabel())
                                    ->badge(fn (OrderStatusChange $record): bool => $record->by_customer)
                                    ->color(fn (OrderStatusChange $record): ?string => $record->by_customer ? 'info' : null),
                                TextEntry::make('created_at')
                                    ->label('When')
                                    ->dateTime('j M Y, H:i'),
                                TextEntry::make('note')
                                    ->label('Note')
                                    ->helperText(fn (OrderStatusChange $record): string => $record->by_customer ? "Customer's response" : 'Sent to the customer')
                                    ->placeholder('—'),
                            ]),
                    ]),
            ]);
    }
}
