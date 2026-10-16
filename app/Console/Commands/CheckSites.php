<?php

namespace App\Console\Commands;

use App\Models\MaintenanceContract;
use App\Services\SiteMonitor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sites:check')]
#[Description('Check whether the websites of active maintenance contracts are reachable')]
class CheckSites extends Command
{
    public function handle(SiteMonitor $monitor): int
    {
        $contracts = MaintenanceContract::monitored()->with(['user', 'customer'])->get();

        foreach ($contracts as $contract) {
            $monitor->check($contract);

            $this->line(sprintf('%-40s %s', $contract->url, $contract->monitor_status ?? '-'));
        }

        $this->info("{$contracts->count()} Websites geprüft.");

        return self::SUCCESS;
    }
}
