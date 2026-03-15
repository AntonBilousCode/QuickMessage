<?php

namespace Database\Seeders;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class MessageSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        if ($users->count() < 2) {
            Log::warning('MessageSeeder: not enough users to seed messages');

            return;
        }

        Log::info('MessageSeeder: seeding messages', ['users_count' => $users->count()]);

        $messages = [
            ['from' => 'anton@example.com',   'to' => 'bob@example.com',     'body' => 'Hey Bob! How are you?'],
            ['from' => 'bob@example.com',     'to' => 'anton@example.com',   'body' => 'Hi Anton! Doing great, thanks!'],
            ['from' => 'anton@example.com',   'to' => 'bob@example.com',     'body' => 'Nice!'],
            ['from' => 'charlie@example.com', 'to' => 'diana@example.com',   'body' => 'Diana, did you see the latest updates?'],
            ['from' => 'diana@example.com',   'to' => 'charlie@example.com', 'body' => 'Not yet, what happened?'],
            ['from' => 'charlie@example.com', 'to' => 'diana@example.com',   'body' => 'Is live now!'],
            ['from' => 'elena@example.com',   'to' => 'anton@example.com',   'body' => 'Anton, this app is amazing!'],
            ['from' => 'anton@example.com',   'to' => 'elena@example.com',   'body' => 'Thanks Elena!'],
            ['from' => 'bob@example.com',     'to' => 'charlie@example.com', 'body' => 'Hey, want to test the WebSocket?'],
            ['from' => 'charlie@example.com', 'to' => 'bob@example.com',     'body' => 'Sure! Opening the app now...'],
        ];

        foreach ($messages as $data) {
            $sender = $users->firstWhere('email', $data['from']);
            $receiver = $users->firstWhere('email', $data['to']);

            if ($sender && $receiver) {
                Message::create([
                    'sender_id' => $sender->id,
                    'receiver_id' => $receiver->id,
                    'body' => $data['body'],
                    'read_at' => now(), // pre-seeded messages are all "read"
                ]);
            }
        }

        Log::info('MessageSeeder: finished', ['messages_created' => count($messages)]);
    }
}
