<?php

namespace Database\Seeders;

use App\Enums\FinanceCategory as C;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Household book demo data: contracts private and business, plus variable bookings
 * since the start of last year. Made-up example values.
 */
class FinanceSeeder extends Seeder
{
    /**
     * [name, type, scope, category, amount, interval months, share %, start month offset]
     */
    private const CONTRACTS = [
        ['Nettogehalt', 'income', 'private', C::Salary, 2650.00, 1, 100],
        ['Miete (warm)', 'expense', 'private', C::Housing, 780.00, 1, 100],
        ['Strom & Gas', 'expense', 'private', C::Housing, 95.00, 1, 100],
        ['Internet (geteilt)', 'expense', 'private', C::Subscriptions, 39.90, 1, 50],
        ['Handy', 'expense', 'private', C::Subscriptions, 17.90, 1, 100],
        ['Spotify Duo (geteilt)', 'expense', 'private', C::Subscriptions, 16.99, 1, 50],
        ['Fitnessstudio', 'expense', 'private', C::Leisure, 29.90, 1, 100],
        ['Autoversicherung', 'expense', 'private', C::Insurance, 129.99, 1, 100],
        ['Haushaltsversicherung', 'expense', 'private', C::Insurance, 96.00, 12, 100],
        ['ÖAMTC (Jahresabo)', 'expense', 'private', C::Mobility, 92.00, 12, 100],
        ['Vignette', 'expense', 'private', C::Mobility, 103.80, 12, 100],
        ['ETF-Sparplan', 'expense', 'private', C::Investments, 300.00, 1, 100],
        ['Hosting (Server)', 'expense', 'business', C::Hosting, 24.90, 1, 100],
        ['Domains', 'expense', 'business', C::Hosting, 85.00, 12, 100],
        ['JetBrains', 'expense', 'business', C::Software, 249.00, 12, 100],
        ['Adobe Creative Cloud', 'expense', 'business', C::Software, 66.45, 1, 100],
        ['Firmenhandy', 'expense', 'business', C::Subscriptions, 25.00, 1, 100],
        ['SVS Vorauszahlung', 'expense', 'business', C::Taxes, 520.00, 3, 100],
        ['Geschäftskonto', 'expense', 'business', C::Fees, 9.90, 1, 100],
    ];

    /**
     * [description, scope, category, min, max, times per month]
     */
    private const VARIABLE = [
        ['Billa', 'private', C::Groceries, 8, 65, 5],
        ['Hofer', 'private', C::Groceries, 5, 45, 3],
        ['Tanken', 'private', C::Mobility, 45, 75, 2],
        ['Apotheke', 'private', C::Health, 6, 40, 0.4],
        ['Restaurant', 'private', C::Leisure, 18, 70, 1.5],
        ['Kino', 'private', C::Leisure, 12, 25, 0.3],
        ['Amazon', 'private', C::Shopping, 10, 90, 1],
        ['Kleidung', 'private', C::Clothing, 25, 120, 0.4],
        ['Bargeldabhebung', 'private', C::Cash, 50, 100, 0.5],
        ['Parken', 'private', C::Mobility, 2, 8, 1],
        ['Büromaterial', 'business', C::Office, 8, 45, 0.4],
        ['Kabel & Kleinteile', 'business', C::Hardware, 10, 60, 0.6],
        ['Google Ads', 'business', C::Marketing, 30, 120, 0.3],
        ['Fachbuch / Kurs', 'business', C::Education, 25, 90, 0.2],
    ];

    public function run(User $user): void
    {
        $start = today()->subYear()->startOfYear();

        foreach (self::CONTRACTS as [$name, $type, $scope, $category, $amount, $interval, $share]) {
            $user->recurringExpenses()->create([
                'name' => $name, 'type' => $type, 'scope' => $scope, 'category' => $category,
                'amount' => $amount, 'interval_months' => $interval, 'share_percent' => $share,
                // Yearly payments in a random month
                'start_date' => $interval === 12 ? $start->copy()->addMonths(fake()->numberBetween(0, 11)) : $start,
            ]);
        }

        // A price increase: old price until spring, new one after
        $insurance = $user->recurringExpenses()->where('name', 'Autoversicherung')->first();
        $insurance->update(['end_date' => today()->startOfYear()->addMonths(3)->subDay()]);
        $user->recurringExpenses()->create([...$insurance->only(['name', 'type', 'scope', 'category', 'interval_months', 'share_percent']), 'amount' => 134.24, 'start_date' => today()->startOfYear()->addMonths(3)]);

        // Contracts checked against the bank statement: all past months except a few gaps,
        // the current month is still open. Salary with the 13th/14th in June and November.
        foreach ($user->recurringExpenses()->get() as $contract) {
            for ($month = $start->copy(); $month->lt(today()->startOfMonth()); $month->addMonth()) {
                if (! $contract->isDueIn($month->year, $month->month) || ($month->diffInMonths(today()) < 3 && fake()->boolean(10))) {
                    continue;
                }

                $bonus = $contract->category === C::Salary && in_array($month->month, [6, 11], true);
                $contract->payments()->create([
                    'period' => $month->toDateString(),
                    'amount' => $bonus ? round((float) $contract->amount * 2.1, 2) : $contract->amount,
                    'confirmed_at' => $month->copy()->addDays(fake()->numberBetween(3, 25)),
                ]);
            }
        }

        // Variable bookings month by month until today
        for ($month = $start->copy(); $month->lte(today()); $month->addMonth()) {
            foreach (self::VARIABLE as [$description, $scope, $category, $min, $max, $perMonth]) {
                $times = (int) floor($perMonth) + (fake()->randomFloat(2, 0, 1) < fmod($perMonth, 1) ? 1 : 0);

                for ($i = 0; $i < $times; $i++) {
                    $this->entry($user, $this->randomDay($month), $description, 'expense', $scope, $category, fake()->randomFloat(2, $min, $max));
                }
            }

            // Occasional extra income
            if (fake()->boolean(25)) {
                $this->entry($user, $this->randomDay($month), 'Willhaben Verkauf', 'income', 'private', C::Sales, fake()->randomFloat(0, 20, 180));
            }
        }

        // One bigger trip and a grocery booking typed as a sum, like in a spreadsheet
        $this->entry($user, today()->subMonths(3)->startOfMonth()->addDays(9), 'Urlaub Kroatien', 'expense', 'private', C::Travel, 640.00);
        $user->financeEntries()->create([
            'date' => today()->startOfMonth(), 'description' => 'Wocheneinkauf', 'type' => 'expense', 'scope' => 'private',
            'category' => C::Groceries, 'amount' => 41.73, 'amount_input' => '12,49+6,50+22,74',
        ]);
    }

    private function entry(User $user, Carbon $date, string $description, string $type, string $scope, C $category, float $amount): void
    {
        $user->financeEntries()->create(compact('date', 'description', 'type', 'scope', 'category', 'amount'));
    }

    private function randomDay(Carbon $month): Carbon
    {
        $last = $month->isSameMonth(today()) ? today()->day : $month->daysInMonth;

        return $month->copy()->day(fake()->numberBetween(1, $last));
    }
}
