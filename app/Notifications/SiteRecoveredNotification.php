<?php

namespace App\Notifications;

use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class SiteRecoveredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Carbon $recoveredAt;

    public function __construct(public Monitor $monitor, public Carbon $downSince)
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
            ->subject("✅ {$this->monitor->name} ist wieder erreichbar")
            ->greeting('Entwarnung')
            ->line("**{$this->monitor->name}** ({$this->monitor->url}) ist wieder erreichbar.")
            ->line("Ausfalldauer: ca. {$duration}.")
            ->action($this->monitor->maintenance_contract_id ? 'Wartungsvertrag öffnen' : 'Monitoring öffnen', $this->monitor->link());
    }
}
