<?php

namespace App\Filament\Resources\Services\Pages;

use App\Actions\Pins\CheckPinStockLevel;
use App\Filament\Resources\Services\ServiceResource;
use App\Models\Service;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditService extends EditRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * A changed low-stock threshold can put a result checker below it: alert if so.
     *
     * @return void
     */
    protected function afterSave(): void
    {
        /** @var Service $service */
        $service = $this->getRecord();

        if ($service->isResultChecker() && $service->wasChanged('pin_low_stock_threshold')) {
            app(CheckPinStockLevel::class)->handle($service);
        }
    }
}
