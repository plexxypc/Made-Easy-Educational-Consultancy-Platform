<?php

namespace App\Filament\Resources\AdmissionRequirements\Pages;

use App\Actions\Admissions\ImportAdmissionRequirements;
use App\Exceptions\AdmissionImportRejected;
use App\Filament\Resources\AdmissionRequirements\AdmissionRequirementResource;
use App\Filament\Support\ImportProblemList;
use App\Models\AdmissionRequirement;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManageAdmissionRequirements extends ManageRecords
{
    protected static string $resource = AdmissionRequirementResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            self::import_action(),
            self::template_action(),
            CreateAction::make(),
        ];
    }

    /**
     * "Import CSV": the whole file is checked by ImportAdmissionRequirements
     * and rejected, with line numbers, if any line has a problem.
     *
     * @return Action
     */
    public static function import_action(): Action
    {
        return Action::make('importCsv')
            ->label('Import CSV')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->authorize(fn (): bool => Gate::allows('import', AdmissionRequirement::class))
            ->modalDescription('Header row: institution, course, cut_off_mark, olevel_credits_required, and optionally type, ownership, state. '
                .'Institutions and courses are matched by name (capital letters and spaces don\'t matter) and created if new. '
                .'An institution + course already listed gets the new cut-off and credits. Nothing is imported if any line has a problem.')
            ->schema([
                FileUpload::make('csv')
                    ->label('CSV file')
                    ->storeFiles(false)
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                    ->maxSize(2048)
                    ->required(),
            ])
            ->action(function (array $data, Action $action, ImportAdmissionRequirements $import_admission_requirements): void {
                $csv = $data['csv'] ?? null;
                $input = $csv instanceof TemporaryUploadedFile ? (string) $csv->get() : '';

                if ($csv instanceof TemporaryUploadedFile) {
                    $csv->delete();
                }

                try {
                    $result = $import_admission_requirements->handle($input, auth()->user());
                } catch (AdmissionImportRejected $exception) {
                    Notification::make()
                        ->danger()
                        ->title($exception->getMessage())
                        ->body(ImportProblemList::html($exception->problems))
                        ->persistent()
                        ->send();

                    $action->halt();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Import complete')
                    ->body(self::result_summary($result))
                    ->persistent($result['institutions_missing_details'] !== [])
                    ->send();
            });
    }

    /**
     * "Download template": a CSV with the header row and two example lines.
     *
     * @return Action
     */
    public static function template_action(): Action
    {
        return Action::make('downloadTemplate')
            ->label('Download template')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->authorize(fn (): bool => Gate::allows('import', AdmissionRequirement::class))
            ->action(fn (): StreamedResponse => response()->streamDownload(
                function (): void {
                    echo ImportAdmissionRequirements::template();
                },
                'admission-requirements-template.csv',
                ['Content-Type' => 'text/csv; charset=UTF-8'],
            ));
    }

    /**
     * e.g. "12 added, 3 updated, 1 unchanged. New: 2 institutions, 4 courses."
     *
     * @param  array{created: int, updated: int, unchanged: int, institutions_created: int, courses_created: int, institutions_missing_details: array<int, string>}  $result
     * @return string
     */
    private static function result_summary(array $result): string
    {
        $summary = "{$result['created']} added, {$result['updated']} updated, {$result['unchanged']} unchanged.";

        if ($result['institutions_created'] + $result['courses_created'] > 0) {
            $summary .= ' New: '.Str::plural('institution', $result['institutions_created'], true)
                .', '.Str::plural('course', $result['courses_created'], true).'.';
        }

        $missing = $result['institutions_missing_details'];

        if ($missing !== []) {
            $summary .= ' Please add type, ownership and state for: '.implode(', ', array_slice($missing, 0, 10))
                .(count($missing) > 10 ? ' and '.(count($missing) - 10).' more' : '')
                .'. Until then they are left out of searches by state.';
        }

        return $summary;
    }
}
