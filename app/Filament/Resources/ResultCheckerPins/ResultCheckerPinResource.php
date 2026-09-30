<?php

namespace App\Filament\Resources\ResultCheckerPins;

use App\Filament\Resources\ResultCheckerPins\Pages\ListResultCheckerPins;
use App\Filament\Resources\ResultCheckerPins\Tables\ResultCheckerPinsTable;
use App\Models\ResultCheckerPin;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Result checker PIN stock: bulk upload and stock levels. Admin only
 * (ResultCheckerPinPolicy). PIN values are never shown here; they are only
 * revealed, with an audit row, on the order a PIN was allocated to.
 */
class ResultCheckerPinResource extends Resource
{
    protected static ?string $model = ResultCheckerPin::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?string $navigationLabel = 'PIN stock';

    protected static ?string $modelLabel = 'result checker PIN';

    protected static ?int $navigationSort = 30;

    /**
     * @param  Table  $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return ResultCheckerPinsTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListResultCheckerPins::route('/'),
        ];
    }
}
