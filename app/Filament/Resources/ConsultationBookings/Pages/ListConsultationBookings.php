<?php

namespace App\Filament\Resources\ConsultationBookings\Pages;

use App\Filament\Resources\ConsultationBookings\ConsultationBookingResource;
use Filament\Resources\Pages\ListRecords;

class ListConsultationBookings extends ListRecords
{
    protected static string $resource = ConsultationBookingResource::class;
}
