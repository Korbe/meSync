<?php

namespace Database\Factories;

use App\Enums\MaintenanceContractType;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MaintenanceContract>
 */
class MaintenanceContractFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(MaintenanceContractType::cases());
        $start = fake()->dateTimeBetween('-2 years', 'now');

        return [
            'user_id' => User::factory(),
            'customer_id' => fn (array $attributes) => Customer::factory()->create(['user_id' => $attributes['user_id']]),
            'type' => $type,
            'name' => fake()->domainName(),
            'url' => fake()->url(),
            'monthly_price' => $type->defaultMonthlyPrice(),
            'start_date' => $start,
            'next_invoice_date' => $start,
            'end_date' => null,
            'notes' => null,
        ];
    }
}
