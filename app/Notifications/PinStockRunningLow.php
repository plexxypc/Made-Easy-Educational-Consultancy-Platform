<?php

namespace App\Notifications;

use App\Enums\PinStockLevel;
use App\Filament\Resources\ResultCheckerPins\ResultCheckerPinResource;
use App\Models\Service;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Pages\Dashboard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells staff/admin a result checker's PIN stock has dropped to its
 * low-stock threshold, or run out. Sent once per crossing (CheckPinStockLevel).
 *
 * Always bell + email: running out means paid customers wait for PINs.
 * Admins are linked to PIN stock (they upload); staff to the dashboard.
 */
class PinStockRunningLow extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Built now (inside the request) rather than in the queue worker.
     *
     * @var string
     */
    public string $stock_url;

    /**
     * @var string
     */
    public string $dashboard_url;

    /**
     * @param  Service  $service
     * @param  PinStockLevel  $level  Low or Out.
     * @param  int  $available  Available PINs when the level changed.
     */
    public function __construct(
        public Service $service,
        public PinStockLevel $level,
        public int $available,
    ) {
        $this->stock_url = ResultCheckerPinResource::getUrl('index');
        $this->dashboard_url = Dashboard::getUrl();
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
        $notification = FilamentNotification::make()
            ->title($this->title())
            ->body($this->summary())
            ->icon('heroicon-o-key')
            ->actions([
                Action::make('view')
                    ->label($notifiable->isAdmin() ? 'Upload PINs' : 'Open dashboard')
                    ->url($this->url_for($notifiable))
                    ->markAsRead(),
            ]);

        $this->level === PinStockLevel::Out ? $notification->danger() : $notification->warning();

        return $notification->getDatabaseMessage();
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
            ->line($notifiable->isAdmin()
                ? 'Upload more under Catalogue → PIN stock. Orders waiting for PINs are delivered automatically after an upload.'
                : 'Please let an admin know so they can upload more PINs.')
            ->action($notifiable->isAdmin() ? 'Upload PINs' : 'Open dashboard', $this->url_for($notifiable));
    }

    /**
     * @return string
     */
    private function title(): string
    {
        return $this->level === PinStockLevel::Out
            ? "Out of stock: {$this->service->name} PINs"
            : "Low stock: {$this->service->name} PINs";
    }

    /**
     * @return string
     */
    private function summary(): string
    {
        return $this->level === PinStockLevel::Out
            ? "No {$this->service->name} PINs are left. New paid orders will wait for manual delivery until stock is uploaded."
            : trans_choice(':count PIN left|:count PINs left', $this->available).' (low-stock level: '.$this->service->lowStockThreshold().').';
    }

    /**
     * @param  User  $notifiable
     * @return string
     */
    private function url_for(User $notifiable): string
    {
        return $notifiable->isAdmin() ? $this->stock_url : $this->dashboard_url;
    }
}
