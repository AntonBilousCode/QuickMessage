<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserSeeder extends Seeder
{
    /**
     * Fixed test users as required by TZ (will be listed in README).
     *
     * @var array<int, array{name: string, email: string}>
     */
    private array $testUsers = [
        ['name' => 'Anton',   'email' => 'anton@example.com'],
        ['name' => 'Bob',     'email' => 'bob@example.com'],
        ['name' => 'Charlie', 'email' => 'charlie@example.com'],
        ['name' => 'Diana',   'email' => 'diana@example.com'],
        ['name' => 'Elena',   'email' => 'elena@example.com'],
    ];

    public function run(): void
    {
        Log::info('UserSeeder: creating test users', ['count' => count($this->testUsers)]);

        foreach ($this->testUsers as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('Password123!'),
                    'email_verified_at' => now(),
                ],
            );

            Log::debug('UserSeeder: created/updated user', ['email' => $userData['email']]);
        }

        Log::info('UserSeeder: finished', ['count' => count($this->testUsers)]);
    }
}
