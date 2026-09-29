<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Orders are placed by customers, never created from the back office.
 */
class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;
}
