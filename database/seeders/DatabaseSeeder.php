<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Log::info('DatabaseSeeder: starting');

        $this->call([
            UserSeeder::class,
            MessageSeeder::class,
        ]);

        Log::info('DatabaseSeeder: completed');
    }
}
