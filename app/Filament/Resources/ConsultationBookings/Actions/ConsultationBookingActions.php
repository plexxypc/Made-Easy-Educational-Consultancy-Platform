<?php

namespace App\Filament\Resources\ConsultationBookings\Actions;

use App\Actions\Consultations\CancelConsultationBooking;
use App\Actions\Consultations\CloseConsultationBooking;
use App\Actions\Consultations\RescheduleConsultationBooking;
use App\Actions\Consultations\SetConsultationMeetingLink;
use App\Enums\ConsultationBookingStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSlot;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Staff/admin actions on a consultation booking, used on the list and view
 * pages. Thin UI wrappers: authorisation via ConsultationBookingPolicy::manage,
 * rules and customer emails in the App\Actions\Consultations classes.
 */
class ConsultationBookingActions
{
    /**
     * Every action, in display order.
     *
     * @return array<int, Action>
     */
    public static function all(): array
    {
        return [
            self::add_meeting_link(),
            self::reschedule(),
            self::mark_completed(),
            self::mark_no_show(),
            self::cancel(),
        ];
    }

    /**
     * "Mark completed" (after the start time). No customer email.
     *
     * @return Action
     */
    public static function mark_completed(): Action
    {
        return self::close_action('markCompleted', 'Mark completed', ConsultationBookingStatus::Completed)
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success');
    }

    /**
     * "Mark no-show" (after the start time). No refund; the order is completed.
     *
     * @return Action
     */
    public static function mark_no_show(): Action
    {
        return self::close_action('markNoShow', 'Mark no-show', ConsultationBookingStatus::NoShow)
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('warning');
    }

    /**
     * "Cancel booking": frees the slot, cancels the order, emails the customer.
     *
     * @return Action
     */
    public static function cancel(): Action
    {
        return Action::make('cancelBooking')
            ->label('Cancel booking')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->authorize('manage')
            ->requiresConfirmation()
            ->modalHeading('Cancel this consultation?')
            ->modalDescription(fn (ConsultationBooking $record): string => 'The time is released for other customers, the order is cancelled and the customer is emailed.'
                .($record->order->receipt()->exists() ? ' Refund the payment in Paystack if one is due.' : ''))
            ->schema([
                Textarea::make('reason')
                    ->label('Reason (optional, sent to the customer)')
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->action(function (ConsultationBooking $record, array $data, Action $action, CancelConsultationBooking $cancel_consultation_booking): void {
                self::run($action, fn () => $cancel_consultation_booking->handle($record, auth()->user(), $data['reason'] ?? null));

                Notification::make()->success()->title('Booking cancelled; the customer has been emailed')->send();
            });
    }

    /**
     * "Reschedule": move to another open slot (any type) and email the customer.
     * Also how staff resolve a paid booking whose time was lost.
     *
     * @return Action
     */
    public static function reschedule(): Action
    {
        return Action::make('reschedule')
            ->label(fn (ConsultationBooking $record): string => $record->needsRebooking() ? 'Choose new time' : 'Reschedule')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color(fn (ConsultationBooking $record): string => $record->needsRebooking() ? 'danger' : 'gray')
            ->authorize('manage')
            ->visible(fn (ConsultationBooking $record): bool => $record->status === ConsultationBookingStatus::Confirmed || $record->needsRebooking())
            ->modalDescription(fn (ConsultationBooking $record): string => 'Currently: '.$record->slot->label().' ('.$record->slot->consultation_type->getLabel().'). The customer is emailed the new time. Agree the new time with them first where you can.')
            ->schema([
                Select::make('slot_id')
                    ->label('New time')
                    ->options(fn (ConsultationBooking $record): array => self::open_slot_options($record))
                    ->searchable()
                    ->required()
                    ->helperText('Open future slots with a free place. Add more under Consultations → Availability (admins).'),
                Textarea::make('note')
                    ->label('Message to the customer (optional)')
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->action(function (ConsultationBooking $record, array $data, Action $action, RescheduleConsultationBooking $reschedule_consultation_booking): void {
                self::run($action, fn () => $reschedule_consultation_booking->handle($record, (int) $data['slot_id'], auth()->user(), $data['note'] ?? null));

                Notification::make()->success()->title('Rescheduled to '.$record->refresh()->slot->label())->body('The customer has been emailed.')->send();
            });
    }

    /**
     * "Add meeting link" / "Change meeting link" for online and Google Meet
     * bookings. Staff create the meeting themselves; nothing is generated.
     *
     * @return Action
     */
    public static function add_meeting_link(): Action
    {
        return Action::make('meetingLink')
            ->label(fn (ConsultationBooking $record): string => filled($record->meeting_link) ? 'Change meeting link' : 'Add meeting link')
            ->icon(Heroicon::OutlinedLink)
            ->color(fn (ConsultationBooking $record): string => filled($record->meeting_link) ? 'gray' : 'primary')
            ->authorize('manage')
            ->visible(fn (ConsultationBooking $record): bool => $record->status === ConsultationBookingStatus::Confirmed && $record->slot->consultation_type->needsMeetingLink())
            ->modalDescription('Create the meeting (e.g. in Google Calendar) and paste its link here. The customer is emailed the link and it appears on their booking page.')
            ->fillForm(fn (ConsultationBooking $record): array => ['meeting_link' => $record->meeting_link])
            ->schema([
                TextInput::make('meeting_link')
                    ->label('Meeting link')
                    ->placeholder('https://meet.google.com/abc-defg-hij')
                    ->required()
                    ->maxLength(SetConsultationMeetingLink::MAX_LENGTH)
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (! SetConsultationMeetingLink::is_valid_link(trim((string) $value))) {
                            $fail('Enter the full meeting link, starting with https://');
                        }
                    }),
            ])
            ->action(function (ConsultationBooking $record, array $data, Action $action, SetConsultationMeetingLink $set_consultation_meeting_link): void {
                self::run($action, fn () => $set_consultation_meeting_link->handle($record, (string) $data['meeting_link'], auth()->user()));

                Notification::make()->success()->title('Meeting link saved; the customer has been emailed')->send();
            });
    }

    /**
     * Future, live slots with a free place, other than the booking's current one.
     *
     * @param  ConsultationBooking  $booking
     * @return array<int, string>
     */
    private static function open_slot_options(ConsultationBooking $booking): array
    {
        return ConsultationSlot::query()
            ->withOccupiedCount()
            ->whereNull('cancelled_at')
            ->whereDate('date', '>=', now(ConsultationSlot::timezone())->toDateString())
            ->orderBy('date')
            ->orderBy('start_time')
            ->limit(500)
            ->get()
            ->reject(fn (ConsultationSlot $slot): bool => $slot->hasStarted()
                || $slot->remainingCapacity() === 0
                || ($slot->is($booking->slot) && ! $booking->needsRebooking()))
            ->mapWithKeys(fn (ConsultationSlot $slot): array => [
                $slot->id => $slot->label().' · '.$slot->consultation_type->getLabel().($slot->capacity > 1 ? " ({$slot->remainingCapacity()} left)" : ''),
            ])
            ->all();
    }

    /**
     * Shared configuration for completed / no-show.
     *
     * @param  string  $name
     * @param  string  $label
     * @param  ConsultationBookingStatus  $outcome
     * @return Action
     */
    private static function close_action(string $name, string $label, ConsultationBookingStatus $outcome): Action
    {
        return Action::make($name)
            ->label($label)
            ->authorize('manage')
            ->visible(fn (ConsultationBooking $record): bool => $record->status === ConsultationBookingStatus::Confirmed && $record->slot->hasStarted())
            ->requiresConfirmation()
            ->modalHeading("{$label}?")
            ->modalDescription('The order is marked completed. The customer is not emailed.')
            ->action(function (ConsultationBooking $record, Action $action, CloseConsultationBooking $close_consultation_booking) use ($outcome): void {
                self::run($action, fn () => $close_consultation_booking->handle($record, $outcome, auth()->user()));

                Notification::make()->success()->title("Marked \"{$outcome->getLabel()}\"")->send();
            });
    }

    /**
     * Run an Action-class call, turning business-rule failures into a UI notification.
     *
     * @param  Action  $action
     * @param  callable(): mixed  $callback
     * @return void
     */
    private static function run(Action $action, callable $callback): void
    {
        try {
            $callback();
        } catch (BusinessRuleException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            $action->halt();
        }
    }
}
