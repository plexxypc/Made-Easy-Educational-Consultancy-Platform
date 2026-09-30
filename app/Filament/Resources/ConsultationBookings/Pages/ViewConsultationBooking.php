<?php

namespace App\Filament\Resources\ConsultationBookings\Pages;

use App\Filament\Resources\ConsultationBookings\Actions\ConsultationBookingActions;
use App\Filament\Resources\ConsultationBookings\ConsultationBookingResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewConsultationBooking extends ViewRecord
{
    protected static string $resource = ConsultationBookingResource::class;

    /**
     * Meeting link, reschedule, complete, no-show, cancel (staff/admin).
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return ConsultationBookingActions::all();
    }
}
