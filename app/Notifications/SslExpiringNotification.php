<?php

namespace App\Notifications;

use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class SslExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Carbon $checkedAt;

    public function __construct(public Monitor $monitor)
    {
        $this->checkedAt = now();
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
        $days = (int) $this->checkedAt->diffInDays($this->monitor->ssl_expires_at);

        return (new MailMessage)
            ->error()
            ->subject("🔒 SSL-Zertifikat von {$this->monitor->name} läuft in {$days} Tagen ab")
            ->greeting('SSL-Zertifikat läuft ab')
            ->line("Das SSL-Zertifikat von **{$this->monitor->name}** ({$this->monitor->url}) läuft am {$this->monitor->ssl_expires_at->format('d.m.Y')} ab.")
            ->line("Let's Encrypt erneuert normalerweise 30 Tage vorher automatisch. Wahrscheinlich ist die automatische Erneuerung fehlgeschlagen.")
            ->action($this->monitor->maintenance_contract_id ? 'Wartungsvertrag öffnen' : 'Monitoring öffnen', $this->monitor->link());
    }
}
