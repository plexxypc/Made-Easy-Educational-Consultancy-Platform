<?php

namespace App\Notifications;

use App\Filament\Resources\ConsultationBookings\ConsultationBookingResource;
use App\Models\ConsultationBooking;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells staff/admin a customer has PAID for a consultation whose time could
 * not be confirmed (ConfirmConsultationBooking), so someone must Reschedule
 * it (or Cancel and refund). Same shape as PinsNeedManualFulfilment; always
 * bell + email, because a paying customer is waiting.
 */
class ConsultationNeedsRebooking extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Built now (inside the request) rather than in the queue worker.
     *
     * @var string
     */
    public string $booking_url;

    /**
     * @param  ConsultationBooking  $booking
     */
    public function __construct(
        public ConsultationBooking $booking,
    ) {
        $this->booking_url = ConsultationBookingResource::getUrl('view', ['record' => $booking]);
        $this->afterCommit();
    }

    /**
     * @param  User  $notifiable
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['database', 'mail'];
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
            ->icon('heroicon-o-calendar-days')
            ->danger()
            ->actions([
                Action::make('view')
                    ->label('Open booking')
                    ->url($this->booking_url)
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
            ->line('Open the booking and use "Reschedule" to give them a new time, or "Cancel booking" if they want a refund (refunds are made in the Paystack dashboard).')
            ->action('Open booking', $this->booking_url);
    }

    /**
     * @return string
     */
    private function title(): string
    {
        return "New time needed: paid consultation {$this->booking->order->display_reference}";
    }

    /**
     * @return string
     */
    private function summary(): string
    {
        return "{$this->booking->order->customer_name} paid for {$this->booking->slot->label()}, but that time could not be confirmed: {$this->booking->needs_rebooking_reason}";
    }
}
