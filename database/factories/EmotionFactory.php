<?php

namespace Database\Factories;

use App\Models\User;
use App\Enums\PrimaryEmotion;
use App\Enums\SecondaryEmotion;
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
            'user_id'   => User::factory(),
            'score'     => $this->faker->numberBetween(1, 100),
            'primary'   => $this->faker->randomElement(PrimaryEmotion::cases()),
            'secondary' => $this->faker->randomElement(SecondaryEmotion::cases()),
            'description' => $this->faker->sentence(),
        ];
    }
}
