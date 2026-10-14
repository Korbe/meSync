<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
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
            'title_prefix' => fake()->optional(0.3)->randomElement(['Dr.', 'Mag.', 'DI', 'Ing.']),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'title_suffix' => fake()->optional(0.2)->randomElement(['BSc', 'MSc', 'MBA']),
            'company_name' => fake()->optional(0.5)->company(),
            'company_suffix' => fake()->optional(0.5)->randomElement(['GmbH', 'e.U.', 'OG', 'KG']),
            'street' => fake()->streetAddress(),
            'zip' => fake()->numerify('####'),
            'city' => fake()->city(),
            'country' => 'Österreich',
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('+43 664 #######'),
            'uid_number' => fake()->optional(0.5)->numerify('ATU########'),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }
}
