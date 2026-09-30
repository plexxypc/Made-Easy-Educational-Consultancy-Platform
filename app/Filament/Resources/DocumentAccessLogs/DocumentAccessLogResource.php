<?php

namespace App\Filament\Resources\DocumentAccessLogs;

use App\Filament\Resources\DocumentAccessLogs\Pages\ListDocumentAccessLogs;
use App\Filament\Resources\DocumentAccessLogs\Tables\DocumentAccessLogsTable;
use App\Models\DocumentAccessLog;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Read-only audit log of staff/admin views of customer documents and result
 * checker PINs (project security rule 5). Admin only (DocumentAccessLogPolicy).
 */
class DocumentAccessLogResource extends Resource
{
    protected static ?string $model = DocumentAccessLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Access log (documents & PINs)';

    protected static ?string $modelLabel = 'sensitive data access';

    protected static ?int $navigationSort = 20;

    /**
     * @param  Table  $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return DocumentAccessLogsTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListDocumentAccessLogs::route('/'),
        ];
    }
}
