<?php

namespace App\Filament\Resources\ConsultationSlots;

use App\Filament\Resources\ConsultationSlots\Pages\ListConsultationSlots;
use App\Filament\Resources\ConsultationSlots\Tables\ConsultationSlotsTable;
use App\Models\ConsultationSlot;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Admin only (ConsultationSlotPolicy). Slots are added through the header
 * actions on the list page and can't be edited: cancel and re-add instead,
 * so booked customers are always told about a change.
 */
class ConsultationSlotResource extends Resource
{
    protected static ?string $model = ConsultationSlot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Consultations';

    protected static ?string $navigationLabel = 'Availability';

    protected static ?string $modelLabel = 'consultation slot';

    protected static ?int $navigationSort = 20;

    /**
     * @param  Table  $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return ConsultationSlotsTable::configure($table);
    }

    /**
     * @param  Model|null  $record
     * @return string
     */
    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof ConsultationSlot ? $record->label() : 'Consultation slot';
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListConsultationSlots::route('/'),
        ];
    }
}
