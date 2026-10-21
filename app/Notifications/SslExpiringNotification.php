<?php

namespace App\Notifications;

use App\Models\MaintenanceContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class SslExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Carbon $checkedAt;

    public function __construct(public MaintenanceContract $contract)
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
        $days = (int) $this->checkedAt->diffInDays($this->contract->ssl_expires_at);

        return (new MailMessage)
            ->error()
            ->subject("🔒 SSL-Zertifikat von {$this->contract->name} läuft in {$days} Tagen ab")
            ->greeting('SSL-Zertifikat läuft ab')
            ->line("Das SSL-Zertifikat von **{$this->contract->name}** ({$this->contract->url}) läuft am {$this->contract->ssl_expires_at->format('d.m.Y')} ab.")
            ->line("Let's Encrypt erneuert normalerweise 30 Tage vorher automatisch. Wahrscheinlich ist die automatische Erneuerung fehlgeschlagen.")
            ->action('Wartungsvertrag öffnen', route('contracts.edit', $this->contract));
    }
}
