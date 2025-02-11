<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Emotion>
 */
class EmotionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'   => User::factory(), // Create a user if not provided
            'score'     => $this->faker->numberBetween(0, 10),
            'primary'   => $this->faker->randomElement(PrimaryEmotion::cases()),
            'secondary' => $this->faker->randomElement(SecondaryEmotion::cases()),
            'description' => $this->faker->sentence(),
        ];
    }
}
