<?php

namespace App\Notifications;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderStatusChange;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells staff/admin that a customer answered a "needs action" request.
 *
 * Always shown in the Filament notification bell (database channel). Also
 * emailed when config('madeeasy.notifications.email_staff_on_customer_response')
 * is on, since emailing every staff member may be noisy for a small team.
 */
class CustomerRespondedToOrder extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Admin URL of the order, built now (inside the web request) rather than in the queue worker.
     *
     * @var string
     */
    public string $order_url;

    /**
     * @param  Order  $order
     * @param  OrderStatusChange  $response  The customer's status change (holds their note).
     * @param  int  $document_count  How many documents they uploaded.
     */
    public function __construct(
        public Order $order,
        public OrderStatusChange $response,
        public int $document_count,
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
        return config('madeeasy.notifications.email_staff_on_customer_response')
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
            ->title("Customer responded to order {$this->order->display_reference}")
            ->body($this->summary())
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->info()
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
            ->subject("Customer responded to order {$this->order->display_reference}")
            ->line($this->summary())
            ->action('Open order', $this->order_url);
    }

    /**
     * One-line summary: who, which service, what they sent, new status.
     *
     * @return string
     */
    private function summary(): string
    {
        $parts = array_filter([
            $this->response->note !== null ? '"'.Str::limit($this->response->note, 120).'"' : null,
            $this->document_count > 0 ? trans_choice(':count document uploaded|:count documents uploaded', $this->document_count) : null,
        ]);

        return "{$this->order->customer_name} ({$this->order->service->name}): "
            .implode(' · ', $parts)
            .". Now {$this->response->to_status->getLabel()}.";
    }
}
