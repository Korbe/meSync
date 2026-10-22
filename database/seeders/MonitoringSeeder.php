<?php

namespace Database\Seeders;

use App\Models\MaintenanceContract;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Monitors for the running demo contracts plus an own app without contract, with 30 days of
 * simulated checks: mostly up, two short outages in the past and one site that is down right
 * now. Nothing is requested for real, the demo checks are simulated by the SiteMonitor as well.
 */
class MonitoringSeeder extends Seeder
{
    private const INTERVAL_MINUTES = 10;

    public function run(User $user): void
    {
        $monitors = $user->maintenanceContracts()->whereNotNull('url')->get()
            ->filter(fn (MaintenanceContract $contract) => $contract->isBillable())
            ->map(fn (MaintenanceContract $contract) => $contract->syncMonitor(['enabled' => true]))
            ->values();

        // An own app, monitored without maintenance contract
        $own = $user->monitors()->make(['name' => 'Eigene App', 'url' => 'https://app.meine-firma.at', 'enabled' => true]);
        $own->forceFill(['user_id' => $user->id])->save();
        $monitors->push($own);

        foreach ($monitors as $index => $monitor) {
            $base = fake()->numberBetween(120, 380);

            // Outages: [minutes ago it started, duration in minutes]
            $outages = match ($index) {
                0 => [[6 * 1440 + 200, 35]],
                1 => [[20 * 1440 + 90, 15], [2 * 1440 + 400, 20]],
                2 => [[25, 999]],   // still down
                default => [],
            };

            $rows = [];
            for ($minutesAgo = 30 * 1440; $minutesAgo >= 0; $minutesAgo -= self::INTERVAL_MINUTES) {
                $down = collect($outages)->contains(fn ($outage) => $minutesAgo <= $outage[0] && $minutesAgo > $outage[0] - $outage[1]);

                $rows[] = [
                    'monitor_id' => $monitor->id,
                    'checked_at' => now()->subMinutes($minutesAgo)->second(0),
                    'up' => ! $down,
                    'response_ms' => $down ? null : max(40, $base + fake()->numberBetween(-60, 140) + ($minutesAgo % 1440 < 120 ? 180 : 0)),
                    'error' => $down ? ($index === 2 ? 'cURL error 28: Connection timed out after 10001 milliseconds' : 'HTTP 503') : null,
                ];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('site_checks')->insert($chunk);
            }

            $last = end($rows);
            $currentlyDown = $index === 2;

            $monitor->forceFill([
                'status' => $currentlyDown ? 'down' : 'up',
                'status_since' => $currentlyDown ? now()->subMinutes(25) : now()->subDays($index === 1 ? 2 : 6),
                'checked_at' => $last['checked_at'],
                'failures' => $currentlyDown ? 3 : 0,
                'response_ms' => $last['response_ms'],
                'error' => $last['error'],
                'ssl_expires_at' => now()->addDays($index === 3 ? 9 : fake()->numberBetween(25, 85)),
            ])->save();
        }
    }
}
