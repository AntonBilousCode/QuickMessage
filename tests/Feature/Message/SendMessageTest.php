<?php

namespace Tests\Feature\Message;

use App\Events\MessageSent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SendMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_send_message(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $response = $this->actingAs($sender)->post("/messages/{$recipient->id}", [
            'body' => 'Hello there!',
        ]);

        $response->assertRedirect("/messages/{$recipient->id}");
    }

    public function test_message_is_stored_in_database(): void
    {
        Event::fake();

        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $this->actingAs($sender)->post("/messages/{$recipient->id}", [
            'body' => 'Test message body',
        ]);

        $this->assertDatabaseHas('messages', [
            'sender_id' => $sender->id,
            'receiver_id' => $recipient->id,
            'body' => 'Test message body',
        ]);
    }

    public function test_message_triggers_broadcast_event(): void
    {
        Event::fake([MessageSent::class]);

        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $this->actingAs($sender)->post("/messages/{$recipient->id}", [
            'body' => 'Broadcast this!',
        ]);

        Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($sender, $recipient): bool {
            return $event->message->sender_id === $sender->id
                && $event->message->receiver_id === $recipient->id;
        });
    }

    public function test_unauthenticated_user_cannot_send_message(): void
    {
        $recipient = User::factory()->create();

        $response = $this->post("/messages/{$recipient->id}", [
            'body' => 'Should not be sent',
        ]);

        $response->assertRedirect('/login');
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_empty_message_body_is_rejected(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $response = $this->actingAs($sender)->post("/messages/{$recipient->id}", [
            'body' => '',
        ]);

        $response->assertSessionHasErrors(['body']);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_message_body_too_long_is_rejected(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $response = $this->actingAs($sender)->post("/messages/{$recipient->id}", [
            'body' => str_repeat('a', 5001),
        ]);

        $response->assertSessionHasErrors(['body']);
    }
}
