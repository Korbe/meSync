<?php

namespace App\Notifications;

use App\Models\MaintenanceContract;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SslExpiringNotification extends Notification
{
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
        $days = (int) now()->diffInDays($this->contract->ssl_expires_at);

        return (new MailMessage)
            ->error()
            ->subject("🔒 SSL-Zertifikat von {$this->contract->name} läuft in {$days} Tagen ab")
            ->greeting('SSL-Zertifikat läuft ab')
            ->line("Das SSL-Zertifikat von **{$this->contract->name}** ({$this->contract->url}) läuft am {$this->contract->ssl_expires_at->format('d.m.Y')} ab.")
            ->line("Let's Encrypt erneuert normalerweise 30 Tage vorher automatisch. Wahrscheinlich ist die automatische Erneuerung fehlgeschlagen.")
            ->action('Wartungsvertrag öffnen', route('contracts.edit', $this->contract));
    }
}
