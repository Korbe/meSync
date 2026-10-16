<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CatalogItem>
 */
class CatalogItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['PC-Hilfe vor Ort', 'Windows-Neuinstallation', 'Website Basic', 'Datenrettung']),
            'description' => fake()->optional(0.5)->sentence(),
            'unit' => fake()->randomElement(['Stunde', 'Stück', 'Pauschale']),
            'unit_price' => fake()->randomFloat(2, 20, 1500),
        ];
    }
}
