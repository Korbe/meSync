<?php

namespace App\Notifications;

use App\Models\MaintenanceContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SiteDownNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public MaintenanceContract $contract)
    {
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
        return (new MailMessage)
            ->error()
            ->subject("⚠️ {$this->contract->name} ist nicht erreichbar")
            ->greeting('Website nicht erreichbar')
            ->line("**{$this->contract->name}** ({$this->contract->url}) ist seit {$this->contract->monitor_status_since->format('d.m.Y H:i')} Uhr nicht erreichbar.")
            ->line('Fehler: '.($this->contract->monitor_error ?: 'unbekannt'))
            ->line("Kunde: {$this->contract->customer->display_name}")
            ->action('Wartungsvertrag öffnen', route('contracts.edit', $this->contract))
            ->line('Sobald die Website wieder erreichbar ist, bekommst du eine Entwarnung.');
    }
}
