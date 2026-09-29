<?php

namespace App\Filament\Resources\DocumentAccessLogs\Pages;

use App\Filament\Resources\DocumentAccessLogs\DocumentAccessLogResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Read-only: no create/edit/delete actions.
 */
class ListDocumentAccessLogs extends ListRecords
{
    protected static string $resource = DocumentAccessLogResource::class;
}
