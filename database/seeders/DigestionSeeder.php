<?php

namespace Database\Seeders;

use App\Models\Digestion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DigestionSeeder extends Seeder
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

        Digestion::factory(365)->create([
            'user_id' => $user->id,
        ]);
    }
}
