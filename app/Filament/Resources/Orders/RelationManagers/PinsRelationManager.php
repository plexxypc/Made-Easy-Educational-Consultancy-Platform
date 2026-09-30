<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Actions\Pins\LogPinAccess;
use App\Models\Order;
use App\Models\ResultCheckerPin;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

/**
 * Result checker PINs given to this order (read-only, masked).
 *
 * "Show PIN" is the only place staff/admin see a PIN in plaintext. Opening it
 * writes an audit row through LogPinAccess (same table and rules as document
 * views), before the PIN is rendered.
 */
class PinsRelationManager extends RelationManager
{
    protected static string $relationship = 'pins';

    protected static ?string $title = 'Result checker PINs';

    /**
     * Only on result checker orders. Staff may see this list even though the
     * PIN stock screen is admin-only (ResultCheckerPinPolicy::viewAny).
     *
     * @param  Model  $ownerRecord
     * @param  string  $pageClass
     * @return bool
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Order
            && $ownerRecord->isResultChecker()
            && (auth()->user()?->isStaffOrAdmin() ?? false);
    }

    /**
     * @param  Table  $table
     * @return Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No PINs delivered yet')
            ->emptyStateDescription('Use "Deliver PIN manually" above if this paid order is still waiting.')
            ->columns([
                TextColumn::make('masked_pin')
                    ->label('PIN')
                    ->state(ResultCheckerPin::MASK)
                    ->color('gray'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('source')
                    ->label('From')
                    ->state(fn (ResultCheckerPin $record): string => $record->batch_id === null ? 'Entered by staff' : 'Stock'),
                TextColumn::make('allocated_at')
                    ->label('Allocated')
                    ->dateTime('j M Y, H:i'),
                TextColumn::make('revealed_at')
                    ->label('Delivered')
                    ->dateTime('j M Y, H:i')
                    ->placeholder('—'),
                TextColumn::make('access_logs_count')
                    ->label('Staff views')
                    ->counts('accessLogs')
                    ->badge()
                    ->color('gray'),
            ])
            ->recordActions([
                Action::make('showPin')
                    ->label('Show PIN')
                    ->icon(Heroicon::OutlinedEye)
                    ->authorize('reveal')
                    ->modalHeading('Result checker PIN')
                    ->modalDescription('This view has been recorded in the access log.')
                    ->mountUsing(fn (ResultCheckerPin $record, LogPinAccess $log_pin_access) => $log_pin_access->handle($record, auth()->user(), request()->ip()))
                    ->modalContent(fn (ResultCheckerPin $record): View => view('filament.orders.pin-reveal', ['pin' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ]);
    }
}
