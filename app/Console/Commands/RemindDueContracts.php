<?php

namespace App\Console\Commands;

use App\Models\MaintenanceContract;
use App\Notifications\ContractDueNotification;
use App\Notifications\ContractDueTodayNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('contracts:remind {--days=7 : Remind this many days before the due date}')]
#[Description('Email a reminder 7 days before a yearly maintenance invoice is due, and again on the due date')]
class RemindDueContracts extends Command
{
    public function handle(): int
    {
        // Due today: its own mail, so no early reminder on the same day
        $dueToday = MaintenanceContract::billable()
            ->with(['user', 'customer'])
            ->whereDate('next_invoice_date', today())
            ->get()
            ->reject(fn (MaintenanceContract $contract) => $contract->due_notice_sent_for?->isSameDay($contract->next_invoice_date))
            ->reject(fn (MaintenanceContract $contract) => $contract->user->isDemo());

        foreach ($dueToday as $contract) {
            $contract->user->notify(new ContractDueTodayNotification($contract));
            $contract->forceFill([
                'due_notice_sent_for' => $contract->next_invoice_date,
                'reminder_sent_for' => $contract->next_invoice_date,
            ])->save();
        }

        $contracts = MaintenanceContract::billable()
            ->with(['user', 'customer'])
            ->whereDate('next_invoice_date', '<=', today()->addDays((int) $this->option('days')))
            ->whereDate('next_invoice_date', '!=', today())
            ->get()
            // Only once per due date, never for the demo user
            ->reject(fn (MaintenanceContract $contract) => $contract->reminder_sent_for?->isSameDay($contract->next_invoice_date))
            ->reject(fn (MaintenanceContract $contract) => $contract->user->isDemo());

        foreach ($contracts as $contract) {
            $contract->user->notify(new ContractDueNotification($contract));
            $contract->forceFill(['reminder_sent_for' => $contract->next_invoice_date])->save();
        }

        $this->info("{$contracts->count()} Erinnerungen und {$dueToday->count()} Mails „heute fällig“ verschickt.");

        return self::SUCCESS;
    }
}
