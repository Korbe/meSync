<?php

namespace App\Notifications;

use App\Models\MaintenanceContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Sent on the due date itself, in addition to the reminder 7 days ahead.
 */
class ContractDueTodayNotification extends Notification implements ShouldQueue
{
    use Queueable;

    // Fixed now: by the time the queued mail is sent, the contract could already be invoiced
    public Carbon $dueDate;

    public float $amount;

    public function __construct(public MaintenanceContract $contract)
    {
        $this->dueDate = $contract->next_invoice_date->copy();
        $this->amount = $contract->annual_price;
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
        $amount = number_format($this->amount, 2, ',', '.');

        return (new MailMessage)
            ->subject("📅 Heute fällig: Jahresrechnung für {$this->contract->name}")
            ->greeting('Heute fällig')
            ->line("Die Jahresrechnung für den Wartungsvertrag **{$this->contract->name}** von {$this->contract->customer->display_name} ist **heute, am {$this->dueDate->format('d.m.Y')}**, fällig.")
            ->line("Betrag: {$amount} € netto für ein Jahr ({$this->contract->type_label}).")
            ->action('Rechnung jetzt erstellen', route('invoices.create', ['contract' => $this->contract->id]))
            ->line('Schon erledigt? Dann markiere den Vertrag als verrechnet.');
    }
}
