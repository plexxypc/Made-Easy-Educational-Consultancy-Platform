<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Actions\Orders\ChangeOrderStatus;
use App\Actions\Orders\SetOrderTotal;
use App\Actions\Pins\AllocatePinsToOrder;
use App\Actions\Pins\DeliverOneOffPin;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\ResultCheckerPin;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/**
 * Header actions for the order screen. Thin UI wrappers: authorisation via
 * OrderPolicy, rules and side effects (history, email) in the Action classes.
 */
class OrderActions
{
    /**
     * "Mark In Progress".
     *
     * @return Action
     */
    public static function mark_in_progress(): Action
    {
        return self::status_action('markInProgress', 'Mark In Progress', OrderStatus::InProgress)
            ->icon(Heroicon::OutlinedPlay)
            ->color('warning');
    }

    /**
     * "Mark Completed".
     *
     * @return Action
     */
    public static function mark_completed(): Action
    {
        return self::status_action('markCompleted', 'Mark Completed', OrderStatus::Completed)
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success');
    }

    /**
     * "Mark Needs Action": requires a note telling the customer what's needed.
     *
     * @return Action
     */
    public static function mark_needs_action(): Action
    {
        return self::status_action('markNeedsAction', 'Mark Needs Action', OrderStatus::NeedsAction)
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->color('danger')
            ->schema([
                Textarea::make('note')
                    ->label('What does the customer need to do?')
                    ->helperText('Sent to the customer by email.')
                    ->required()
                    ->maxLength(2000)
                    ->rows(4),
            ]);
    }

    /**
     * "Cancel": optional reason sent to the customer.
     *
     * @return Action
     */
    public static function cancel(): Action
    {
        return self::status_action('cancel', 'Cancel Order', OrderStatus::Cancelled)
            ->icon(Heroicon::OutlinedXCircle)
            ->color('gray')
            ->schema([
                Textarea::make('note')
                    ->label('Reason (optional, sent to the customer)')
                    ->maxLength(2000)
                    ->rows(3),
            ]);
    }

    /**
     * "Set Total" for quote-priced orders (admin only, while pending payment).
     *
     * @return Action
     */
    public static function set_total(): Action
    {
        return Action::make('setTotal')
            ->label('Set Total')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('primary')
            ->authorize('setTotal')
            ->visible(fn (Order $record): bool => $record->isQuote() && $record->status === OrderStatus::PendingPayment)
            ->modalDescription('Record the price agreed for this request. The customer will pay this amount.')
            ->fillForm(fn (Order $record): array => [
                'total' => $record->quoted_at ? Money::kobo_to_naira($record->total_amount) : null,
            ])
            ->schema([
                TextInput::make('total')
                    ->label('Total')
                    ->prefix('₦')
                    ->inputMode('decimal')
                    ->required()
                    ->regex(Money::NAIRA_INPUT_REGEX)
                    ->validationMessages(['regex' => 'Enter an amount in naira, e.g. 15000 or 15000.50.']),
            ])
            ->action(function (Order $record, array $data, Action $action, SetOrderTotal $set_order_total): void {
                self::run($action, fn () => $set_order_total->handle($record, Money::naira_to_kobo($data['total']), auth()->user()));

                Notification::make()->success()->title('Total set to '.Money::format($record->refresh()->total_amount))->send();
            });
    }

    /**
     * "Deliver PIN manually" for a paid result checker order still owed PINs
     * (stock ran out at payment time). Staff either send from stock once it
     * has been topped up, or enter one PIN they obtained themselves.
     *
     * @return Action
     */
    public static function deliver_pins(): Action
    {
        return Action::make('deliverPins')
            ->label('Deliver PIN manually')
            ->icon(Heroicon::OutlinedKey)
            ->color('danger')
            ->authorize('deliverPins')
            ->visible(fn (Order $record): bool => $record->undeliveredPinCount() > 0)
            ->modalDescription(fn (Order $record): string => trans_choice(
                'This order is paid but still waiting for :count PIN. The customer is emailed as soon as it is delivered.|This order is paid but still waiting for :count PINs. The customer is emailed as soon as they are delivered.',
                $record->undeliveredPinCount(),
            ))
            ->schema([
                Radio::make('method')
                    ->label('How?')
                    ->options(fn (Order $record): array => [
                        'stock' => 'Send from stock ('.ResultCheckerPin::query()->available()->where('service_id', $record->service_id)->count().' available now)',
                        'one_off' => 'Enter one PIN myself',
                    ])
                    ->default('stock')
                    ->required()
                    ->live(),
                TextInput::make('pin')
                    ->label('PIN')
                    ->required()
                    ->maxLength(ResultCheckerPin::MAX_LENGTH + 20)
                    ->autocomplete(false)
                    ->visible(fn (Get $get): bool => $get('method') === 'one_off'),
                TextInput::make('serial_number')
                    ->label('Serial number (optional)')
                    ->maxLength(ResultCheckerPin::MAX_LENGTH + 20)
                    ->autocomplete(false)
                    ->visible(fn (Get $get): bool => $get('method') === 'one_off'),
            ])
            ->action(function (Order $record, array $data, Action $action, AllocatePinsToOrder $allocate_pins_to_order, DeliverOneOffPin $deliver_one_off_pin): void {
                if (($data['method'] ?? 'stock') === 'one_off') {
                    self::run($action, fn () => $deliver_one_off_pin->handle($record, (string) $data['pin'], $data['serial_number'] ?? null, auth()->user()));

                    Notification::make()->success()->title('PIN delivered to the customer')->send();

                    return;
                }

                $result = $allocate_pins_to_order->handle($record);

                $notification = match (true) {
                    $result['allocated'] === 0 => Notification::make()->warning()
                        ->title('No PINs in stock for '.$record->service->name)
                        ->body('Upload more under Catalogue → PIN stock, or enter a PIN yourself.'),
                    $result['undelivered'] > 0 => Notification::make()->warning()
                        ->title(trans_choice(':count PIN delivered|:count PINs delivered', $result['allocated']))
                        ->body(trans_choice(':count is still waiting: stock ran out again.|:count are still waiting: stock ran out again.', $result['undelivered'])),
                    default => Notification::make()->success()
                        ->title(trans_choice(':count PIN delivered; order completed|:count PINs delivered; order completed', $result['allocated'])),
                };

                $notification->send();
            });
    }

    /**
     * Shared configuration for a status transition action.
     *
     * @param  string  $name
     * @param  string  $label
     * @param  OrderStatus  $next_status
     * @return Action
     */
    private static function status_action(string $name, string $label, OrderStatus $next_status): Action
    {
        return Action::make($name)
            ->label($label)
            ->authorize('changeStatus')
            ->visible(fn (Order $record): bool => $record->canTransitionTo($next_status))
            ->requiresConfirmation()
            ->modalHeading("{$label}?")
            ->modalDescription('The customer will be notified by email.')
            ->action(function (Order $record, array $data, Action $action, ChangeOrderStatus $change_order_status) use ($next_status): void {
                self::run($action, fn () => $change_order_status->handle($record, $next_status, auth()->user(), $data['note'] ?? null));

                Notification::make()->success()->title("Order marked \"{$next_status->getLabel()}\"")->send();
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
