<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A few finished stopwatch runs that are not billed yet, to try "in Rechnung übernehmen".
 */
class TimeSessionSeeder extends Seeder
{
    private const RUNS = [
        [2, 75, "Drucker im Netzwerk eingerichtet\nWindows-Updates installiert"],
        [5, 45, 'Neuen Laptop eingerichtet, Daten übertragen'],
        [9, 110, "Virus entfernt\nBrowser zurückgesetzt\nBackup eingerichtet"],
    ];

    public function run(User $user): void
    {
        $rate = $user->catalogItems()->where('pricing', 'time')->orderBy('name')->first();
        $customers = $user->customers()->whereJsonContains('interests', 'pc_help')->inRandomOrder()->limit(count(self::RUNS))->get();

        foreach (self::RUNS as $index => [$daysAgo, $minutes, $notes]) {
            $start = today()->subDays($daysAgo)->setTime(fake()->numberBetween(9, 16), fake()->randomElement([0, 15, 30, 45]));

            $user->timeSessions()->create([
                'customer_id' => $customers[$index]->id ?? null,
                'catalog_item_id' => $rate?->id,
                'started_at' => $start,
                'seconds' => $minutes * 60 - fake()->numberBetween(0, 50),
                'stopped_at' => $start->copy()->addMinutes($minutes),
                'notes' => $notes,
            ]);
        }
    }
}
