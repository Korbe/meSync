<?php

namespace Database\Factories;

use App\Models\User;
use App\Enums\PrimaryEmotion;
use Illuminate\Support\Carbon;
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
            'score'     => $this->faker->numberBetween(33, 92),
            'primary'   => $this->faker->randomElement(PrimaryEmotion::cases()),
            'secondary' => $this->faker->randomElement(SecondaryEmotion::cases()),
            'description' => $this->faker->sentence(),
            'created_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23))->subMinutes(rand(0, 59)),
        ];
    }
}
