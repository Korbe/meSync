<?php

namespace App\Console\Commands;

use App\Models\MaintenanceContract;
use App\Notifications\ContractDueNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('contracts:remind {--days=7 : Remind this many days before the due date}')]
#[Description('Email a reminder for maintenance contracts whose yearly invoice is due soon')]
class RemindDueContracts extends Command
{
    public function handle(): int
    {
        $contracts = MaintenanceContract::billable()
            ->with(['user', 'customer'])
            ->whereDate('next_invoice_date', '<=', today()->addDays((int) $this->option('days')))
            ->get()
            // Only once per due date, never for the demo user
            ->reject(fn (MaintenanceContract $contract) => $contract->reminder_sent_for?->isSameDay($contract->next_invoice_date))
            ->reject(fn (MaintenanceContract $contract) => $contract->user->isDemo());

        foreach ($contracts as $contract) {
            $contract->user->notify(new ContractDueNotification($contract));
            $contract->forceFill(['reminder_sent_for' => $contract->next_invoice_date])->save();
        }

        $this->info("{$contracts->count()} Erinnerungen verschickt.");

        return self::SUCCESS;
    }
}
