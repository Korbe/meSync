<?php

namespace Database\Seeders;

use App\Enums\CustomerInterest;
use App\Enums\MaintenanceContractType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MaintenanceContractSeeder extends Seeder
{
    /**
     * Contracts start in the past. Their yearly billings are created by the InvoiceSeeder,
     * until then the next invoice date is the start date.
     */
    public function run(User $user, int $count = 12): void
    {
        $customers = $user->customers()->get()
            ->filter(fn ($customer) => array_intersect($customer->interests ?? [], [CustomerInterest::Website->value, CustomerInterest::Webapp->value]))
            ->shuffle()
            ->take($count);

        foreach ($customers as $customer) {
            $type = fake()->randomElement(MaintenanceContractType::cases());
            $start = today()->subDays(fake()->numberBetween(20, 1000))->startOfMonth();
            $name = $customer->company_name ?? $customer->last_name;
            $domain = Str::slug($name).'.at';

            $user->maintenanceContracts()->create([
                'customer_id' => $customer->id,
                'type' => $type,
                'name' => $type === MaintenanceContractType::Webapp ? "{$name} Buchungssystem" : $domain,
                'url' => "https://{$domain}",
                'monthly_price' => $type->defaultMonthlyPrice(),
                'start_date' => $start,
                'next_invoice_date' => $start,
                // Some contracts were cancelled
                'end_date' => fake()->boolean(12) ? today()->subDays(fake()->numberBetween(10, 200)) : null,
                'notes' => fake()->optional(0.3)->randomElement(['Hosting bei World4You.', 'WordPress, Updates monatlich.', 'Laravel, Server bei Hetzner.']),
                // The domains are made up: no uptime checks against them
                'monitor_enabled' => false,
            ]);
        }
    }
}
