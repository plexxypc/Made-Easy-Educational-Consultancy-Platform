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
 * Tells staff/admin a confirmed video consultation starts within 24 hours and
 * still has no meeting link (RemindStaffOfMissingMeetingLinks). Sent once per
 * booking; always bell + email.
 */
class ConsultationMeetingLinkMissing extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Built now (in the scheduler) rather than in the queue worker.
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
            ->icon('heroicon-o-link')
            ->warning()
            ->actions([
                Action::make('view')
                    ->label('Add meeting link')
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
            ->line('Create the meeting and use "Add meeting link" on the booking. The customer is emailed the link as soon as it is saved.')
            ->action('Open booking', $this->booking_url);
    }

    /**
     * @return string
     */
    private function title(): string
    {
        return "Meeting link missing: consultation {$this->booking->order->display_reference}";
    }

    /**
     * @return string
     */
    private function summary(): string
    {
        return "{$this->booking->order->customer_name}'s {$this->booking->slot->consultation_type->getLabel()} consultation is at {$this->booking->slot->label()} and has no meeting link yet.";
    }
}
