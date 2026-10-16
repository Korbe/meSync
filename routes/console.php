<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Website monitoring of maintenance contracts (mails on outage / recovery / expiring SSL)
Schedule::command('sites:check')->everyFiveMinutes()->withoutOverlapping();

// "Yearly maintenance invoice due in 7 days" reminder
Schedule::command('contracts:remind')->dailyAt('08:00');

// Remove old website check history (see SiteCheck::prunable)
Schedule::command('model:prune', ['--model' => [\App\Models\SiteCheck::class]])->daily();
