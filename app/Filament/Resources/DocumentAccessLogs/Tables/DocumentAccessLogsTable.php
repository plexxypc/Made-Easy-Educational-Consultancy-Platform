<?php

namespace App\Filament\Resources\DocumentAccessLogs\Tables;

use App\Enums\UserRole;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\DocumentAccessLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DocumentAccessLogsTable
{
    /**
     * Who viewed which document, when, from where. Searchable by staff member
     * (name/email) and by order number.
     *
     * @param  Table  $table
     * @return Table
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'orderDocument']))
            ->defaultSort('viewed_at', 'desc')
            ->searchPlaceholder('Search staff name/email or order #')
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
                TextColumn::make('orderDocument.order_id')
                    ->label('Order')
                    ->formatStateUsing(fn (?int $state): string => $state ? "#{$state}" : '—')
                    ->url(fn (DocumentAccessLog $record): ?string => $record->orderDocument
                        ? OrderResource::getUrl('view', ['record' => $record->orderDocument->order_id])
                        : null)
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::search_order($query, $search)),
                TextColumn::make('orderDocument.original_filename')
                    ->label('Document'),
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
            ]);
    }

    /**
     * Match an order number ("42" or "#42") exactly; non-numeric searches match nothing here.
     *
     * @param  Builder<DocumentAccessLog>  $query
     * @param  string  $search
     * @return Builder<DocumentAccessLog>
     */
    private static function search_order(Builder $query, string $search): Builder
    {
        $order_number = ltrim(trim($search), '#');

        if (! ctype_digit($order_number)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('orderDocument', fn (Builder $document_query) => $document_query->where('order_id', (int) $order_number));
    }
}
