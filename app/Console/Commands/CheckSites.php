<?php

namespace App\Console\Commands;

use App\Models\Monitor;
use App\Services\SiteMonitor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sites:check')]
#[Description('Check whether the monitored websites are reachable')]
class CheckSites extends Command
{
    public function handle(SiteMonitor $monitor): int
    {
        $monitors = Monitor::active()->with(['user', 'customer'])->get();

        foreach ($monitors as $site) {
            $monitor->check($site);

            $this->line(sprintf('%-40s %s', $site->url, $site->status ?? '-'));
        }

        $this->info("{$monitors->count()} Websites geprüft.");

        return self::SUCCESS;
    }
}
