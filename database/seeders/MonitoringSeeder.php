<?php

namespace Database\Seeders;

use App\Models\MaintenanceContract;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 30 days of simulated website checks for the demo contracts: mostly up, two short
 * outages in the past and one site that is down right now. Nothing is requested for
 * real, the demo checks are simulated by the SiteMonitor as well.
 */
class MonitoringSeeder extends Seeder
{
    private const INTERVAL_MINUTES = 10;

    public function run(User $user): void
    {
        $contracts = $user->maintenanceContracts()->whereNotNull('url')->get()
            ->filter(fn (MaintenanceContract $contract) => $contract->isBillable())
            ->values();

        foreach ($contracts as $index => $contract) {
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
                    'maintenance_contract_id' => $contract->id,
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

            $contract->update([
                'monitor_enabled' => true,
            ]);
            $contract->forceFill([
                'monitor_status' => $currentlyDown ? 'down' : 'up',
                'monitor_status_since' => $currentlyDown ? now()->subMinutes(25) : now()->subDays($index === 1 ? 2 : 6),
                'monitor_checked_at' => $last['checked_at'],
                'monitor_failures' => $currentlyDown ? 3 : 0,
                'monitor_response_ms' => $last['response_ms'],
                'monitor_error' => $last['error'],
                'ssl_expires_at' => now()->addDays($index === 3 ? 9 : fake()->numberBetween(25, 85)),
            ])->save();
        }
    }
}
