<?php

namespace Database\Seeders;

use App\Enums\MaintenanceContractType;
use App\Models\Invoice;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A few offers in every state: draft, sent, accepted (billed, its contract waiting for the go-live),
 * declined, expired.
 */
class OfferSeeder extends Seeder
{
    // [type, package, days ago, state, project, discount]
    private const OFFERS = [
        ['website', 'starter', 70, 'accepted', 'Website Relaunch', 300],
        ['webapp', 'webapp', 45, 'declined', 'Kundenportal', 0],
        ['website', 'one_pager', 40, 'expired', 'One Pager Eröffnung', 0],
        ['webapp', 'webapp', 6, 'sent', 'Buchungsplattform', 400],
        ['website', 'starter', 1, 'draft', 'Firmenwebsite', 0],
    ];

    public function run(User $user): void
    {
        $customers = $user->customers()->whereNotNull('company_name')->inRandomOrder()->limit(count(self::OFFERS))->get();
        if ($customers->isEmpty()) {
            return;
        }

        $issuer = $user->businessProfile?->toIssuer() ?? [];
        $packages = config('offers.packages');

        foreach (self::OFFERS as $index => [$type, $package, $daysAgo, $state, $project, $discount]) {
            $customer = $customers[$index % $customers->count()];
            $date = today()->subDays($daysAgo);
            $type = MaintenanceContractType::from($type);
            $isWebapp = $type === MaintenanceContractType::Webapp;

            $offer = $user->offers()->create([
                'customer_id' => $customer->id,
                'type' => $type,
                'number' => Offer::nextNumberFor($user, $date->year),
                'title' => "{$project} {$customer->company_name}",
                'url' => 'https://'.str($customer->company_name)->slug().'.at',
                'offer_date' => $date,
                'valid_until' => $date->copy()->addDays(config('offers.valid_days')),
                'tax_rate' => 20,
                'package' => $package,
                'features' => $isWebapp ? fake()->randomElements(array_keys(config('offers.features')), 3) : [],
                'integrations' => $isWebapp ? fake()->randomElements(array_keys(config('offers.integrations')), fake()->numberBetween(0, 2)) : [],
                'content_delivery' => fake()->randomElement(array_keys(config('offers.content_delivery'))),
                'payment_terms' => fake()->randomElement(['prepaid', 'half']),
                'reference_customer' => fake()->boolean(),
                'maintenance_package' => 'Small',
                'maintenance_monthly_price' => $type->defaultMonthlyPrice(),
                'intro' => 'Vielen Dank für das nette Gespräch! Wie besprochen erhalten Sie hier mein Angebot.',
                'recipient' => [
                    ...$customer->toRecipient(),
                    'company' => $customer->company,
                    'contact' => $customer->full_name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                ],
                'issuer' => $issuer,
            ]);

            $price = $packages[$package]['price'];
            $offer->items()->create([
                'position' => 1,
                'description' => implode("\n", [$packages[$package]['name'], ...array_map(fn ($feature) => "- {$feature}", $packages[$package]['features'] ?? [])]),
                'quantity' => 1,
                'unit' => 'Pauschale',
                'unit_price' => $price - $discount,
                'list_price' => $price,
            ]);

            if ($isWebapp) {
                $offer->items()->create(['position' => 2, 'description' => "Individuelle Funktionen\n- Datenbank-Design\n- Benutzerverwaltung", 'quantity' => 12, 'unit' => 'Stunde', 'unit_price' => 85]);
            }

            if ($state !== 'draft') {
                $offer->forceFill(['sent_at' => $date, 'sent_to' => $customer->email])->save();
            }

            // Accepted and billed: the maintenance contract waits for the go-live
            if ($state === 'accepted') {
                $offer->accept();
                $this->bill($offer->load('items'), $user);
            } elseif ($state === 'declined') {
                $offer->forceFill(['declined_at' => $date->copy()->addDays(9)])->save();
            }
        }
    }

    private function bill(Offer $offer, User $user): void
    {
        $invoice = $user->invoices()->create([
            'customer_id' => $offer->customer_id,
            'number' => Invoice::nextNumberFor($user, today()->year),
            'invoice_date' => today(),
            'due_date' => today()->addDays(14),
            'tax_rate' => $offer->tax_rate,
            'paid_amount' => 0,
            'recipient' => $offer->customer->toRecipient(),
            'issuer' => $offer->issuer,
        ]);

        foreach ($offer->toInvoiceItems() as $index => $item) {
            $invoice->items()->create([...$item, 'position' => $index + 1]);
        }

        $offer->billWith($invoice);
    }
}
