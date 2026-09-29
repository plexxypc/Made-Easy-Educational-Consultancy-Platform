<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Actions\Orders\ChangeOrderStatus;
use App\Actions\Orders\SetOrderTotal;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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
