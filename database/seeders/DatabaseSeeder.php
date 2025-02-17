<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\EmotionSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(EmotionSeeder::class);
    }
}
