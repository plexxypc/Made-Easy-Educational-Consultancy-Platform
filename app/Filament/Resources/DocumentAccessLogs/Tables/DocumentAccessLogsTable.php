<?php

namespace App\Filament\Resources\DocumentAccessLogs\Tables;

use App\Enums\UserRole;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\DocumentAccessLog;
use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DocumentAccessLogsTable
{
    /**
     * Who viewed which document or result checker PIN, when, from where.
     * Searchable by staff member (name/email) and by order reference or number.
     *
     * @param  Table  $table
     * @return Table
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'user',
                'orderDocument.order',
                'resultCheckerPin.order',
                'resultCheckerPin.service',
            ]))
            ->defaultSort('viewed_at', 'desc')
            ->searchPlaceholder('Search staff name/email or order reference')
            ->columns([
                TextColumn::make('viewed_at')
                    ->label('Viewed')
                    ->dateTime('j M Y, H:i:s')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Staff member')
                    ->description(fn (DocumentAccessLog $record): ?string => $record->user?->email)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'user',
                        fn (Builder $user_query) => $user_query
                            ->whereLike('name', "%{$search}%")
                            ->orWhereLike('email', "%{$search}%"),
                    )),
                TextColumn::make('user.role')
                    ->label('Role')
                    ->badge(),
                TextColumn::make('order')
                    ->label('Order')
                    ->state(fn (DocumentAccessLog $record): string => $record->subjectOrder()?->display_reference ?? '—')
                    ->url(fn (DocumentAccessLog $record): ?string => $record->subjectOrder()
                        ? OrderResource::getUrl('view', ['record' => $record->subjectOrder()])
                        : null)
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::search_order($query, $search)),
                TextColumn::make('subject')
                    ->label('Viewed item')
                    ->state(fn (DocumentAccessLog $record): string => $record->subjectLabel())
                    ->badge(fn (DocumentAccessLog $record): bool => $record->result_checker_pin_id !== null)
                    ->color(fn (DocumentAccessLog $record): ?string => $record->result_checker_pin_id !== null ? 'warning' : null),
                TextColumn::make('ip_address')
                    ->label('IP address')
                    ->placeholder('—')
                    ->copyable(),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Staff member')
                    ->relationship(
                        'user',
                        'name',
                        fn (Builder $query) => $query->whereIn('role', [UserRole::Staff->value, UserRole::Admin->value]),
                    )
                    ->searchable()
                    ->preload(),
                SelectFilter::make('subject_type')
                    ->label('Viewed item')
                    ->options(['document' => 'Documents', 'pin' => 'Result checker PINs'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'document' => $query->whereNotNull('order_document_id'),
                        'pin' => $query->whereNotNull('result_checker_pin_id'),
                        default => $query,
                    }),
            ]);
    }

    /**
     * Match an order reference ("ME-7K3Q9A", any case/spacing) or an order
     * number ("42" or "#42"), for document and PIN views alike.
     *
     * @param  Builder<DocumentAccessLog>  $query
     * @param  string  $search
     * @return Builder<DocumentAccessLog>
     */
    private static function search_order(Builder $query, string $search): Builder
    {
        $order_number = ltrim(trim($search), '#');

        $order_ids = Order::query()
            ->withTrashed()
            ->where('reference', Order::normaliseReference($search))
            ->when(ctype_digit($order_number), fn (Builder $order_query) => $order_query->orWhere('id', (int) $order_number))
            ->pluck('id');

        if ($order_ids->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(fn (Builder $subject_query) => $subject_query
            ->whereHas('orderDocument', fn (Builder $document_query) => $document_query->whereIn('order_id', $order_ids))
            ->orWhereHas('resultCheckerPin', fn (Builder $pin_query) => $pin_query->whereIn('order_id', $order_ids)));
    }
}
