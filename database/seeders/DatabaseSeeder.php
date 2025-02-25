<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\EmotionSeeder;
use Database\Seeders\DigestionSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(EmotionSeeder::class);
        $this->call(DigestionSeeder::class);
    }
}
