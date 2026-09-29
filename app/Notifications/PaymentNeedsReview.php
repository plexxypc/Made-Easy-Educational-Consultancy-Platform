<?php

namespace App\Notifications;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells staff/admin a verified Paystack charge was NOT applied automatically
 * (e.g. amount mismatch) and needs a human. Always in the Filament bell; also
 * emailed when config('madeeasy.payments.email_staff_on_review') is on.
 */
class PaymentNeedsReview extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Admin URL of the order, built now rather than in the queue worker.
     *
     * @var string
     */
    public string $order_url;

    /**
     * @param  Payment  $payment  The flagged payment (review_reason set).
     */
    public function __construct(
        public Payment $payment,
    ) {
        $this->order_url = OrderResource::getUrl('view', ['record' => $payment->order_id]);
        $this->afterCommit();
    }

    /**
     * @param  User  $notifiable
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return config('madeeasy.payments.email_staff_on_review') ? ['database', 'mail'] : ['database'];
    }

    /**
     * @param  User  $notifiable
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->title())
            ->body($this->summary())
            ->icon('heroicon-o-exclamation-triangle')
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
            ->error()
            ->subject($this->title())
            ->line($this->summary())
            ->line('The order has NOT been marked paid. Please check the payment in the Paystack dashboard and decide whether to refund or follow up with the customer.')
            ->action('Open order', $this->order_url);
    }

    /**
     * @return string
     */
    private function title(): string
    {
        return "Payment needs review: order {$this->payment->order->display_reference}";
    }

    /**
     * @return string
     */
    private function summary(): string
    {
        return 'Paystack '.$this->payment->paystack_reference.' charged '.Money::format($this->payment->paid_amount).'. '
            .$this->payment->review_reason;
    }
}
