<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Emotion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EmotionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::find(1) ?? User::factory()->create([
            'id' => 1,
            'name' => 'Lukas Korbitsch',
            'email' => 'korbitschl@gmail.com',
            'password' => Hash::make('123456789'),
        ]);

        $this->createEmotions($user);
    }

    public function createEmotions($user)
    {
        $startDate = now()->subMonths(3)->startOfDay();
        $endDate = now()->endOfDay();

        while ($startDate->lessThanOrEqualTo($endDate)) {
            // Anzahl der Emotionen pro Tag (mindestens 1, zufällig bis zu 3)
            $emotionsPerDay = rand(1, 3);

            Emotion::factory($emotionsPerDay)->create([
                'user_id' => $user->id,
                'created_at' => $startDate->copy()->addHours(rand(6, 23))->addMinutes(rand(0, 59)), // Zufällige Uhrzeit am Tag
            ]);

            // Zum nächsten Tag gehen
            $startDate->addDay();
        }
    }
}
