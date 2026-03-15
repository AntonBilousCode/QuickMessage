<?php

namespace Tests\Feature\Message;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FetchMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_conversation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Message::factory()->create([
            'sender_id' => $alice->id,
            'receiver_id' => $bob->id,
            'body' => 'Hello Bob!',
        ]);

        Message::factory()->create([
            'sender_id' => $bob->id,
            'receiver_id' => $alice->id,
            'body' => 'Hi Alice!',
        ]);

        $response = $this->actingAs($alice)->get("/messages/{$bob->id}");

        $response->assertStatus(200);
        $response->assertSee('Hello Bob!');
        $response->assertSee('Hi Alice!');
    }

    public function test_unread_messages_are_marked_read_on_fetch(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $charlie = User::factory()->create();

        // Bob sends unread messages to Alice
        Message::factory()->count(3)->create([
            'sender_id' => $bob->id,
            'receiver_id' => $alice->id,
            'read_at' => null,
        ]);

        // Charlie also sends unread messages to Alice (should NOT be marked read)
        Message::factory()->count(2)->create([
            'sender_id' => $charlie->id,
            'receiver_id' => $alice->id,
            'read_at' => null,
        ]);

        // Alice opens conversation with Bob
        $this->actingAs($alice)->get("/messages/{$bob->id}");

        // Bob's messages should now be marked as read
        $this->assertDatabaseMissing('messages', [
            'sender_id' => $bob->id,
            'receiver_id' => $alice->id,
            'read_at' => null,
        ]);

        // Charlie's messages should still be unread
        $this->assertDatabaseHas('messages', [
            'sender_id' => $charlie->id,
            'receiver_id' => $alice->id,
            'read_at' => null,
        ]);
    }

    public function test_offline_user_sees_missed_messages_on_login(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        // Bob sends messages while Alice is offline
        Message::factory()->count(5)->unread()->create([
            'sender_id' => $bob->id,
            'receiver_id' => $alice->id,
        ]);

        // Alice logs in and checks unread count
        $response = $this->actingAs($alice)->get('/messages/unread');

        $response->assertStatus(200);
        $response->assertJson(['count' => 5]);
    }

    public function test_conversation_shows_only_messages_between_two_users(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $charlie = User::factory()->create();

        Message::factory()->create([
            'sender_id' => $alice->id,
            'receiver_id' => $bob->id,
            'body' => 'Alice to Bob',
        ]);

        Message::factory()->create([
            'sender_id' => $charlie->id,
            'receiver_id' => $alice->id,
            'body' => 'Charlie to Alice — should not appear',
        ]);

        $response = $this->actingAs($alice)->get("/messages/{$bob->id}");

        $response->assertSee('Alice to Bob');
        $response->assertDontSee('Charlie to Alice — should not appear');
    }
}
