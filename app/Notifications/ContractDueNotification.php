<?php

namespace App\Notifications;

use App\Models\MaintenanceContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class ContractDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    // Fixed now: by the time the queued mail is sent, the contract could already be invoiced
    public Carbon $dueDate;

    public function __construct(public MaintenanceContract $contract)
    {
        $this->dueDate = $contract->next_invoice_date->copy();
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
        $due = $this->dueDate;
        $when = $due->isPast() ? 'war am '.$due->format('d.m.Y').' fällig' : 'wird am '.$due->format('d.m.Y').' fällig';
        $amount = number_format($this->contract->annual_price, 2, ',', '.');

        return (new MailMessage)
            ->subject("Wartungsvertrag {$this->contract->name} {$when}")
            ->greeting('Jahresrechnung steht an')
            ->line("Der Wartungsvertrag **{$this->contract->name}** von {$this->contract->customer->display_name} {$when}.")
            ->line("Betrag: {$amount} € netto für ein Jahr ({$this->contract->type_label}).")
            ->action('Rechnung erstellen', route('invoices.create', ['contract' => $this->contract->id]));
    }
}
