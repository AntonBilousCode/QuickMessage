<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sender_id' => User::factory(),
            'receiver_id' => User::factory(),
            'body' => fake()->sentence(random_int(3, 20)),
            'read_at' => fake()->optional(0.7)->dateTimeThisMonth(),
        ];
    }

    public function unread(): static
    {
        return $this->state(['read_at' => null]);
    }
}
