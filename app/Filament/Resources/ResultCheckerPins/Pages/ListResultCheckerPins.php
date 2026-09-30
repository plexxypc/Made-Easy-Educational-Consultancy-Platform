<?php

namespace App\Filament\Resources\ResultCheckerPins\Pages;

use App\Actions\Pins\ImportPinBatch;
use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\PinImportRejected;
use App\Filament\Resources\ResultCheckerPins\ResultCheckerPinResource;
use App\Filament\Widgets\PinStockOverview;
use App\Models\Order;
use App\Models\Service;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListResultCheckerPins extends ListRecords
{
    protected static string $resource = ResultCheckerPinResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            self::upload_action(),
        ];
    }

    /**
     * Stock levels per service, above the list.
     *
     * @return array<int, class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            PinStockOverview::class,
        ];
    }

    /**
     * "Upload PINs": paste one per line and/or upload a CSV. ImportPinBatch
     * does the parsing and duplicate checks; the whole batch is rejected if
     * any line has a problem.
     *
     * @return Action
     */
    public static function upload_action(): Action
    {
        return Action::make('uploadPins')
            ->label('Upload PINs')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->authorize('create')
            ->modalDescription('One PIN per line, or "PIN,serial number" per line. A header row starting with "PIN" is skipped. Nothing is imported if any line is invalid or already in stock.')
            ->schema([
                Select::make('service_id')
                    ->label('Result checker')
                    ->options(fn (): array => Service::query()
                        ->where('fulfilment_type', FulfilmentType::Instant->value)
                        ->orderByDesc('is_active')
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Service $service): array => [$service->id => $service->name.($service->is_active ? '' : ' (inactive)')])
                        ->all())
                    ->required(),
                Textarea::make('pins')
                    ->label('Paste PINs')
                    ->rows(8)
                    ->requiredWithout('csv')
                    ->extraInputAttributes(['autocomplete' => 'off', 'spellcheck' => 'false']),
                FileUpload::make('csv')
                    ->label('Or upload a CSV file')
                    ->storeFiles(false)
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                    ->maxSize(1024),
            ])
            ->action(function (array $data, Action $action, ImportPinBatch $import_pin_batch): void {
                $input = trim((string) ($data['pins'] ?? ''));
                $csv = $data['csv'] ?? null;

                if ($csv instanceof TemporaryUploadedFile) {
                    $input .= "\n".(string) $csv->get();
                    $csv->delete();
                }

                $service = Service::query()->findOrFail($data['service_id']);

                try {
                    $result = $import_pin_batch->handle($service, $input, auth()->user());
                } catch (PinImportRejected $exception) {
                    Notification::make()
                        ->danger()
                        ->title($exception->getMessage())
                        ->body(self::problem_list($exception->problems))
                        ->persistent()
                        ->send();

                    $action->halt();

                    return;
                } catch (BusinessRuleException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    $action->halt();

                    return;
                }

                $waiting_orders = Order::query()
                    ->where('service_id', $service->id)
                    ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::InProgress->value])
                    ->count();

                Notification::make()
                    ->success()
                    ->title(trans_choice(':count PIN added to :service.|:count PINs added to :service.', $result['imported'], ['service' => $service->name]))
                    ->body($waiting_orders > 0
                        ? trans_choice(':count paid order is waiting for PINs: open it and use "Deliver PIN manually".|:count paid orders are waiting for PINs: open each and use "Deliver PIN manually".', $waiting_orders)
                        : null)
                    ->send();
            });
    }

    /**
     * First problems of a rejected batch, one per line (the rest summarised).
     *
     * @param  array<int, string>  $problems
     * @return HtmlString
     */
    private static function problem_list(array $problems): HtmlString
    {
        $lines = array_map('e', array_slice($problems, 0, 10));
        $more = count($problems) - count($lines);

        if ($more > 0) {
            $lines[] = e('…and '.Str::plural('more line', $more, true).'.');
        }

        return new HtmlString(implode('<br>', $lines));
    }
}
