<?php

namespace App\Filament\Resources\ConsultationSlots\Pages;

use App\Actions\Consultations\CreateConsultationSlots;
use App\Enums\ConsultationType;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\ConsultationSlots\ConsultationSlotResource;
use App\Models\ConsultationSlot;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;

class ListConsultationSlots extends ListRecords
{
    protected static string $resource = ConsultationSlotResource::class;

    /**
     * "Add slot" and "Add recurring slots" (admin; ConsultationSlotPolicy::create).
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->add_slot_action(),
            $this->add_recurring_slots_action(),
        ];
    }

    /**
     * One slot on one date.
     *
     * @return Action
     */
    private function add_slot_action(): Action
    {
        return Action::make('addSlot')
            ->label('Add slot')
            ->icon(Heroicon::OutlinedPlus)
            ->authorize('create')
            ->modalDescription('Times are in '.ConsultationSlot::timezone().'.')
            ->schema([
                DatePicker::make('date')
                    ->native()
                    ->minDate(now(ConsultationSlot::timezone())->toDateString())
                    ->required(),
                ...self::slot_detail_fields(start_label: 'Start time'),
            ])
            ->action(function (array $data, Action $action, CreateConsultationSlots $create_consultation_slots): void {
                try {
                    $slot = $create_consultation_slots->create_one(
                        (string) $data['date'],
                        (string) $data['start_time'],
                        (int) $data['duration_minutes'],
                        self::consultation_type($data['consultation_type']),
                        (int) $data['capacity'],
                        auth()->user(),
                    );
                } catch (BusinessRuleException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    $action->halt();

                    return;
                }

                Notification::make()->success()->title('Slot added')->body($slot->label())->send();
            });
    }

    /**
     * Back-to-back slots between two times on chosen weekdays, for N weeks.
     *
     * @return Action
     */
    private function add_recurring_slots_action(): Action
    {
        return Action::make('addRecurringSlots')
            ->label('Add recurring slots')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->authorize('create')
            ->modalDescription('Creates back-to-back slots between the two times on each chosen day. Times are in '.ConsultationSlot::timezone().'. Slots that already exist, or are in the past, are skipped.')
            ->schema([
                Grid::make(2)->schema([
                    DatePicker::make('first_date')
                        ->label('Starting from')
                        ->native()
                        ->minDate(now(ConsultationSlot::timezone())->toDateString())
                        ->default(now(ConsultationSlot::timezone())->toDateString())
                        ->required(),
                    TextInput::make('weeks')
                        ->label('For how many weeks')
                        ->integer()
                        ->minValue(1)
                        ->maxValue(26)
                        ->default(4)
                        ->required(),
                ]),
                CheckboxList::make('weekdays')
                    ->label('On')
                    ->options([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'])
                    ->default([1, 2, 3, 4, 5])
                    ->columns(7)
                    ->required(),
                Grid::make(2)->schema([
                    TimePicker::make('from_time')
                        ->label('From')
                        ->seconds(false)
                        ->default('09:00')
                        ->required(),
                    TimePicker::make('until_time')
                        ->label('Until')
                        ->helperText('The last slot ends by this time.')
                        ->seconds(false)
                        ->default('17:00')
                        ->required(),
                ]),
                ...self::slot_detail_fields(start_label: null),
            ])
            ->action(function (array $data, Action $action, CreateConsultationSlots $create_consultation_slots): void {
                try {
                    $result = $create_consultation_slots->create_recurring(
                        (string) $data['first_date'],
                        (int) $data['weeks'],
                        array_map('intval', (array) $data['weekdays']),
                        (string) $data['from_time'],
                        (string) $data['until_time'],
                        (int) $data['duration_minutes'],
                        self::consultation_type($data['consultation_type']),
                        (int) $data['capacity'],
                        auth()->user(),
                    );
                } catch (BusinessRuleException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    $action->halt();

                    return;
                }

                Notification::make()
                    ->{$result['created'] > 0 ? 'success' : 'warning'}()
                    ->title(trans_choice(':count slot added|:count slots added', $result['created']))
                    ->body($result['skipped'] > 0 ? trans_choice(':count skipped (already existed or in the past).|:count skipped (already existed or in the past).', $result['skipped']) : null)
                    ->send();
            });
    }

    /**
     * The form's type field (an enum, or its value).
     *
     * @param  ConsultationType|string  $value
     * @return ConsultationType
     */
    private static function consultation_type(ConsultationType|string $value): ConsultationType
    {
        return $value instanceof ConsultationType ? $value : ConsultationType::from($value);
    }

    /**
     * Start time (single slot only), duration, type and capacity.
     *
     * @param  string|null  $start_label  Null to leave the start time out.
     * @return array<int, mixed>
     */
    private static function slot_detail_fields(?string $start_label): array
    {
        $fields = [];

        if ($start_label !== null) {
            $fields[] = TimePicker::make('start_time')
                ->label($start_label)
                ->seconds(false)
                ->required();
        }

        return [
            ...$fields,
            Grid::make(2)->schema([
                TextInput::make('duration_minutes')
                    ->label('Length (minutes)')
                    ->integer()
                    ->minValue(CreateConsultationSlots::MIN_DURATION_MINUTES)
                    ->maxValue(CreateConsultationSlots::MAX_DURATION_MINUTES)
                    ->default((int) config('madeeasy.consultations.default_duration_minutes'))
                    ->required(),
                TextInput::make('capacity')
                    ->label('Places')
                    ->helperText('How many customers can book this time.')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(CreateConsultationSlots::MAX_CAPACITY)
                    ->default(1)
                    ->required(),
            ]),
            ToggleButtons::make('consultation_type')
                ->label('Type')
                ->options(ConsultationType::class)
                ->default(ConsultationType::GoogleMeet->value)
                ->inline()
                ->required(),
        ];
    }
}
