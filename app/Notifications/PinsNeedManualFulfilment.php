<?php

namespace App\Notifications;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells staff/admin that a PAID result checker order is still owed PINs and
 * needs "Deliver PIN manually" on the order screen.
 *
 * Same shape as CustomerRespondedToOrder: always in the Filament bell
 * (database channel), and emailed when
 * config('madeeasy.pins.email_staff_on_shortfall') is on (the default).
 */
class PinsNeedManualFulfilment extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Stock ran out at payment time.
     *
     * @var string
     */
    public const OUT_OF_STOCK = 'out_of_stock';

    /**
     * Allocation kept failing for another reason (see the logs).
     *
     * @var string
     */
    public const ALLOCATION_FAILED = 'allocation_failed';

    /**
     * Admin URL of the order, built now (inside the request) rather than in the queue worker.
     *
     * @var string
     */
    public string $order_url;

    /**
     * @param  Order  $order
     * @param  int  $undelivered  Units still without a PIN.
     * @param  string  $reason  OUT_OF_STOCK or ALLOCATION_FAILED.
     */
    public function __construct(
        public Order $order,
        public int $undelivered,
        public string $reason = self::OUT_OF_STOCK,
    ) {
        $this->order_url = OrderResource::getUrl('view', ['record' => $order]);
        $this->afterCommit();
    }

    /**
     * @param  User  $notifiable
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return config('madeeasy.pins.email_staff_on_shortfall')
            ? ['database', 'mail']
            : ['database'];
    }

    /**
     * Filament-formatted database notification.
     *
     * @param  User  $notifiable
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->title())
            ->body($this->summary())
            ->icon('heroicon-o-key')
            ->danger()
            ->actions([
                Action::make('view')
                    ->label('Open order')
                    ->url($this->order_url)
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    /**
     * @param  User  $notifiable
     * @return MailMessage
     */
    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line($this->summary())
            ->line('Use "Deliver PIN manually" on the order to send a PIN once stock is topped up, or enter one yourself.')
            ->action('Open order', $this->order_url);
    }

    /**
     * @return string
     */
    private function title(): string
    {
        return "PIN needed: paid order {$this->order->display_reference}";
    }

    /**
     * Who, what, how many are missing and why.
     *
     * @return string
     */
    private function summary(): string
    {
        $why = $this->reason === self::OUT_OF_STOCK
            ? 'Not enough PINs in stock.'
            : 'Automatic PIN allocation failed (see the logs).';

        return "{$this->order->customer_name} paid for {$this->order->serviceLabel()}; "
            .trans_choice(':count PIN still to deliver.|:count PINs still to deliver.', $this->undelivered)
            ." {$why}";
    }
}
