<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\Actions\OrderActions;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * Status actions (staff/admin), Set Total (admin, quote orders), delete/restore (admin).
     *
     * @return array<int, Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [
            OrderActions::set_total(),
            OrderActions::mark_in_progress(),
            OrderActions::mark_completed(),
            OrderActions::mark_needs_action(),
            OrderActions::cancel(),
            ActionGroup::make([
                DeleteAction::make(),
                RestoreAction::make(),
            ]),
        ];
    }
}
