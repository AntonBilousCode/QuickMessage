<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /** User with E2EE enabled and keys already generated */
    public function e2eeEnabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'e2ee_enabled' => true,
            'public_key' => 'test-public-key-'.Str::random(8),
            'encrypted_private_key' => 'test-encrypted-private-key-'.Str::random(8),
        ]);
    }

    /** User with E2EE enabled but no keys uploaded yet (edge case) */
    public function e2eeEnabledNoKeys(): static
    {
        return $this->state(fn (array $attributes) => [
            'e2ee_enabled' => true,
            'public_key' => null,
            'encrypted_private_key' => null,
        ]);
    }
}
