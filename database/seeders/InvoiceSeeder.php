<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MaintenanceContract;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class InvoiceSeeder extends Seeder
{
    private User $user;

    private Collection $catalog;

    /**
     * Yearly contract billings plus one-off jobs, created in date order so the
     * invoice numbers ascend with the date. Then payments and a few cancellations.
     */
    public function run(User $user, int $jobs = 45): void
    {
        $this->user = $user;
        $this->catalog = $user->catalogItems()->get();

        $events = collect();

        foreach ($user->maintenanceContracts()->get() as $contract) {
            // Some contracts keep their latest billing open, so the dashboard has something to remind of
            $leaveDue = fake()->boolean(25);
            $date = $contract->next_invoice_date->copy();

            while ($date->lte(today()) && ($contract->end_date === null || $date->lte($contract->end_date))) {
                if ($leaveDue && $date->copy()->addYear()->gt(today())) {
                    break;
                }

                $events->push(['date' => $date->copy(), 'contract' => $contract]);
                $date->addYear();
            }
        }

        for ($i = 0; $i < $jobs; $i++) {
            $events->push(['date' => today()->subDays(fake()->numberBetween(0, 600)), 'contract' => null]);
        }

        foreach ($events->sortBy(fn ($event) => $event['date']->timestamp) as $event) {
            $invoice = $event['contract']
                ? $this->contractInvoice($event['contract'], $event['date'])
                : $this->jobInvoice($event['date']);

            $this->settle($invoice);
        }

        $this->cancelSome(2);
        $this->keepSomeOpenFromLastYear(3);
    }

    /**
     * A few invoices of last year still unpaid (one of them partly), so the turn of the
     * year can be tried out: they stay visible under "Offen" in the current year.
     */
    private function keepSomeOpenFromLastYear(int $count): void
    {
        $lastYear = today()->year - 1;
        $missing = $count - $this->user->invoices()->whereYear('invoice_date', $lastYear)->whereStatus('unpaid')->count();

        if ($missing <= 0) {
            return;
        }

        $this->user->invoices()
            ->whereYear('invoice_date', $lastYear)
            ->whereNull('maintenance_contract_id')
            ->where('paid_in_cash', false)
            ->whereStatus('paid')
            ->latest('invoice_date')
            ->take($missing)
            ->get()
            ->each(function (Invoice $invoice, int $index) {
                $invoice->payments()->delete();

                if ($index === 0) {
                    $invoice->load('items', 'payments');
                    $invoice->payments()->create([
                        'paid_at' => $invoice->invoice_date->copy()->addDays(10),
                        'amount' => round($invoice->outstanding / 2, 2),
                        'method' => PaymentMethod::Transfer,
                        'note' => 'Anzahlung',
                    ]);
                }
            });
    }

    private function contractInvoice(MaintenanceContract $contract, Carbon $date): Invoice
    {
        $contract->refresh();
        $periodEnd = $contract->next_invoice_date->copy()->addYear()->subDay();

        $invoice = $this->createInvoice($contract->customer, $date, [[
            'description' => "{$contract->type_label} Wartung & Betreuung – {$contract->name}\n"
                .'Zeitraum: '.$contract->next_invoice_date->format('d.m.Y').' – '.$periodEnd->format('d.m.Y'),
            'quantity' => 1,
            'unit' => 'Stück',
            'unit_price' => $contract->annual_price,
        ]], $contract);

        $contract->markInvoiced($date, $invoice->number)->update(['invoice_id' => $invoice->id]);

        return $invoice;
    }

    private function jobInvoice(Carbon $date): Invoice
    {
        $items = $this->catalog->random(min($this->catalog->count(), fake()->numberBetween(1, 3)))
            ->map(fn (CatalogItem $item) => $this->itemFor($item))
            ->values()
            ->all();

        return $this->createInvoice($this->user->customers()->inRandomOrder()->first(), $date, $items);
    }

    /**
     * @return array<string, mixed>
     */
    private function itemFor(CatalogItem $item): array
    {
        if ($item->isTimeBased()) {
            $minutes = fake()->randomElement([30, 45, 60, 75, 90, 120, 150]);

            return [
                'description' => "{$item->name}\nDauer: ".intdiv($minutes, 60).':'.str_pad($minutes % 60, 2, '0', STR_PAD_LEFT).' h',
                'quantity' => 1,
                'unit' => 'Stück',
                'unit_price' => $item->priceForMinutes($minutes),
            ];
        }

        return [
            'description' => trim($item->name."\n".$item->description),
            'quantity' => $item->unit === 'Stunde' ? fake()->numberBetween(2, 30) : 1,
            'unit' => $item->unit,
            'unit_price' => $item->unit_price,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function createInvoice(Customer $customer, Carbon $date, array $items, ?MaintenanceContract $contract = null): Invoice
    {
        $profile = $this->user->businessProfile;

        $invoice = $this->user->invoices()->create([
            'customer_id' => $customer->id,
            'maintenance_contract_id' => $contract?->id,
            'number' => Invoice::nextNumberFor($this->user, $date->year),
            'invoice_date' => $date,
            'due_date' => $date->copy()->addDays($profile->payment_terms_days ?? 14),
            'tax_rate' => $profile->default_tax_rate ?? 20,
            'paid_amount' => 0,
            'notes' => fake()->optional(0.15)->randomElement(['Vielen Dank für Ihren Auftrag!', 'Wie telefonisch besprochen.']),
            'recipient' => $customer->toRecipient(),
            'issuer' => $profile?->toIssuer() ?? [],
        ]);

        foreach ($items as $index => $item) {
            $invoice->items()->create(['position' => $index + 1, ...$item]);
        }

        return $invoice->fresh();
    }

    /**
     * Payments: older invoices are mostly paid, recent ones often still open, some overdue.
     */
    private function settle(Invoice $invoice): void
    {
        $total = $invoice->amount_due;
        $age = (int) $invoice->invoice_date->diffInDays(today());

        // PC help on site is often paid in cash right away
        if (! $invoice->maintenance_contract_id && fake()->boolean(25)) {
            $invoice->payments()->create(['paid_at' => $invoice->invoice_date, 'amount' => $total, 'method' => PaymentMethod::Cash, 'note' => 'Bar bezahlt']);
            $invoice->update(['paid_in_cash' => true]);

            return;
        }

        $chance = match (true) {
            $age > 60 => 92,
            $age > 20 => 60,
            default => 20,
        };

        if (! fake()->boolean($chance)) {
            return;
        }

        $paidAt = $invoice->invoice_date->copy()->addDays(fake()->numberBetween(1, min(40, max(1, $age))))->min(today());
        $method = fake()->randomElement([PaymentMethod::Transfer, PaymentMethod::Transfer, PaymentMethod::Transfer, PaymentMethod::Card]);

        // Occasionally paid in two parts, sometimes the second part is still missing
        if ($total > 300 && fake()->boolean(15)) {
            $first = round($total / 2, 2);
            $invoice->payments()->create(['paid_at' => $paidAt, 'amount' => $first, 'method' => $method, 'note' => 'Anzahlung']);

            if (fake()->boolean(60) && $paidAt->copy()->addDays(20)->lte(today())) {
                $invoice->payments()->create(['paid_at' => $paidAt->copy()->addDays(20), 'amount' => round($total - $first, 2), 'method' => $method, 'note' => 'Restzahlung']);
            }

            return;
        }

        $invoice->payments()->create(['paid_at' => $paidAt, 'amount' => $total, 'method' => $method]);
    }

    /**
     * Cancel a few older unpaid job invoices. The cancellation is dated today, like in the app,
     * so the numbers stay in date order.
     */
    private function cancelSome(int $count): void
    {
        $this->user->invoices()
            ->whereNull('cancels_invoice_id')
            ->whereNull('maintenance_contract_id')
            ->doesntHave('payments')
            ->doesntHave('cancellation')
            ->where('invoice_date', '<', today()->subDays(30))
            ->inRandomOrder()
            ->take($count)
            ->get()
            ->each(fn (Invoice $invoice) => $invoice->load('items')->cancel());
    }
}
