<?php

namespace Tests\Feature\Message;

use App\Events\MessageSent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MessageE2EETest extends TestCase
{
    use RefreshDatabase;

    public function test_message_is_marked_encrypted_when_both_users_have_e2ee(): void
    {
        Event::fake();

        $sender = User::factory()->e2eeEnabled()->create();
        $recipient = User::factory()->e2eeEnabled()->create();

        $this->actingAs($sender)->postJson("/messages/{$recipient->id}", [
            'body' => 'encrypted-ciphertext-payload',
        ]);

        $this->assertDatabaseHas('messages', [
            'sender_id' => $sender->id,
            'receiver_id' => $recipient->id,
            'is_encrypted' => true,
        ]);
    }

    public function test_message_is_not_encrypted_when_sender_has_no_e2ee(): void
    {
        Event::fake();

        $sender = User::factory()->create(); // no e2ee
        $recipient = User::factory()->e2eeEnabled()->create();

        $this->actingAs($sender)->postJson("/messages/{$recipient->id}", [
            'body' => 'plaintext message',
        ]);

        $this->assertDatabaseHas('messages', [
            'sender_id' => $sender->id,
            'receiver_id' => $recipient->id,
            'is_encrypted' => false,
        ]);
    }

    public function test_broadcast_payload_includes_is_encrypted_flag(): void
    {
        Event::fake([MessageSent::class]);

        $sender = User::factory()->e2eeEnabled()->create();
        $recipient = User::factory()->e2eeEnabled()->create();

        $this->actingAs($sender)->postJson("/messages/{$recipient->id}", [
            'body' => 'encrypted-ciphertext-payload',
        ]);

        Event::assertDispatched(MessageSent::class, function (MessageSent $event): bool {
            $payload = $event->broadcastWith();

            return isset($payload['message']['is_encrypted'])
                && $payload['message']['is_encrypted'] === true;
        });
    }
}
