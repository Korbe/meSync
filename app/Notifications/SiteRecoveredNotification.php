<?php

namespace App\Notifications;

use App\Models\MaintenanceContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class SiteRecoveredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Carbon $recoveredAt;

    public function __construct(public MaintenanceContract $contract, public Carbon $downSince)
    {
        $this->recoveredAt = now();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $duration = $this->downSince->diffForHumans($this->recoveredAt, ['syntax' => Carbon::DIFF_ABSOLUTE, 'locale' => 'de']);

        return (new MailMessage)
            ->success()
            ->subject("✅ {$this->contract->name} ist wieder erreichbar")
            ->greeting('Entwarnung')
            ->line("**{$this->contract->name}** ({$this->contract->url}) ist wieder erreichbar.")
            ->line("Ausfalldauer: ca. {$duration}.")
            ->action('Wartungsvertrag öffnen', route('contracts.edit', $this->contract));
    }
}
