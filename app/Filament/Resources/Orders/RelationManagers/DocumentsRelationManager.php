<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Actions\Documents\OpenOrderDocument;
use App\Models\OrderDocument;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Uploaded documents on the order screen (read-only).
 *
 * Both actions go through OpenOrderDocument, which writes the same
 * document_access_logs row as GET /documents/{id}:
 *  - "View" opens that route in a new tab (inline preview);
 *  - "Download" streams the file from this Livewire component.
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Uploaded documents';

    /**
     * @param  Table  $table
     * @return Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_filename')
            ->defaultSort('uploaded_at', 'desc')
            ->emptyStateHeading('No documents uploaded')
            ->columns([
                TextColumn::make('original_filename')
                    ->label('File')
                    ->searchable(),
                TextColumn::make('field_key')
                    ->label('For question')
                    ->formatStateUsing(fn (OrderDocument $record, ?string $state): string => self::field_label($record, $state))
                    ->placeholder('—'),
                TextColumn::make('mime_type')
                    ->label('Type')
                    ->color('gray'),
                TextColumn::make('size')
                    ->formatStateUsing(fn (int $state): string => Number::fileSize($state, precision: 1)),
                TextColumn::make('uploaded_at')
                    ->label('Uploaded')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
                TextColumn::make('access_logs_count')
                    ->label('Staff views')
                    ->counts('accessLogs')
                    ->badge()
                    ->color('gray'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::OutlinedEye)
                    ->authorize('view')
                    ->url(fn (OrderDocument $record): string => route('order-documents.show', $record), shouldOpenInNewTab: true),
                Action::make('download')
                    ->label('Download')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->authorize('view')
                    ->action(fn (OrderDocument $record, OpenOrderDocument $open_order_document): StreamedResponse => $open_order_document->handle(
                        $record,
                        auth()->user(),
                        request()->ip(),
                        as_download: true,
                    )),
            ]);
    }

    /**
     * Label of the service field a document was uploaded for (falls back to the key).
     *
     * @param  OrderDocument  $record
     * @param  string|null  $field_key
     * @return string
     */
    private static function field_label(OrderDocument $record, ?string $field_key): string
    {
        $field = collect($record->order?->service?->customer_fields ?? [])->firstWhere('key', $field_key);

        return $field['label'] ?? (string) str($field_key)->headline();
    }
}
