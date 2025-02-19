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
        // Prüfen, ob User mit ID 1 existiert, wenn nicht, erstellen
        $user = User::find(1) ?? User::factory()->create([
            'id' => 1,
            'name' => 'Lukas Korbitsch',
            'email' => 'korbitschl@gmail.com',
            'password' => Hash::make('123456789'),
        ]);

        // 100 Emotionen für diesen User erstellen
        Emotion::factory(365)->create([
            'user_id' => $user->id,
        ]);
    }
}
