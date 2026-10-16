<?php

namespace Database\Factories;

use App\Enums\InventoryCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = fake()->randomElement(InventoryCategory::cases());
        $price = fake()->randomFloat(2, 30, 3000);

        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Laptop', 'Monitor 27"', 'Werkzeugkoffer', 'Router', 'Schreibtisch']),
            'category' => $category,
            'serial_number' => fake()->optional(0.5)->bothify('SN-####-????'),
            'supplier' => fake()->optional(0.7)->randomElement(['Amazon', 'MediaMarkt', 'Hartlauer', 'Conrad']),
            'purchased_at' => fake()->dateTimeBetween('-3 years', 'now'),
            'price' => $price,
            'tax_rate' => 20,
            'useful_life_years' => $price > 1000 ? $category->usefulLifeYears() : null,
            'status' => 'active',
        ];
    }
}
