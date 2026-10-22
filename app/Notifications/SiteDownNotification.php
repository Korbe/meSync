<?php

namespace App\Notifications;

use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SiteDownNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Monitor $monitor)
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
            ->subject("⚠️ {$this->monitor->name} ist nicht erreichbar")
            ->greeting('Website nicht erreichbar')
            ->line("**{$this->monitor->name}** ({$this->monitor->url}) ist seit {$this->monitor->status_since->format('d.m.Y H:i')} Uhr nicht erreichbar.")
            ->line('Fehler: '.($this->monitor->error ?: 'unbekannt'))
            ->lineIf($this->monitor->customer !== null, "Kunde: {$this->monitor->customer?->display_name}")
            ->action($this->monitor->maintenance_contract_id ? 'Wartungsvertrag öffnen' : 'Monitoring öffnen', $this->monitor->link())
            ->line('Sobald die Website wieder erreichbar ist, bekommst du eine Entwarnung.');
    }
}
