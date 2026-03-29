<?php

namespace Tests\Unit\Repositories;

use App\Models\Message;
use App\Models\User;
use App\Repositories\MessageRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private MessageRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new MessageRepository(new Message);
    }

    public function test_create_persists_message(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $message = $this->repository->create([
            'sender_id' => $sender->id,
            'receiver_id' => $recipient->id,
            'body' => 'Test body',
        ]);

        $this->assertInstanceOf(Message::class, $message);
        $this->assertNotNull($message->id);
        $this->assertEquals('Test body', $message->body);
        $this->assertDatabaseHas('messages', ['id' => $message->id]);
    }

    public function test_find_unread_for_user_returns_only_unread(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        // 2 unread for Alice
        Message::factory()->count(2)->unread()->create([
            'sender_id' => $bob->id,
            'receiver_id' => $alice->id,
        ]);

        // 1 read for Alice
        Message::factory()->create([
            'sender_id' => $bob->id,
            'receiver_id' => $alice->id,
            'read_at' => now(),
        ]);

        $unread = $this->repository->findUnreadForUser($alice->id);

        $this->assertCount(2, $unread);
        $unread->each(fn ($m) => $this->assertNull($m->read_at));
    }

    public function test_mark_as_read_updates_all_unread_from_specific_sender(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $charlie = User::factory()->create();

        Message::factory()->count(3)->unread()->create([
            'sender_id' => $bob->id,
            'receiver_id' => $alice->id,
        ]);

        // Charlie's message should NOT be marked as read
        Message::factory()->unread()->create([
            'sender_id' => $charlie->id,
            'receiver_id' => $alice->id,
        ]);

        $count = $this->repository->markAsRead($alice->id, $bob->id);

        $this->assertEquals(3, $count);
        // Bob's messages are now read
        $this->assertDatabaseMissing('messages', [
            'sender_id' => $bob->id,
            'receiver_id' => $alice->id,
            'read_at' => null,
        ]);
        // Charlie's message is still unread
        $this->assertDatabaseHas('messages', [
            'sender_id' => $charlie->id,
            'receiver_id' => $alice->id,
            'read_at' => null,
        ]);
    }

    public function test_mark_as_read_without_sender_marks_all_unread(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Message::factory()->count(3)->unread()->create([
            'sender_id' => $bob->id,
            'receiver_id' => $alice->id,
        ]);

        $count = $this->repository->markAsRead($alice->id);

        $this->assertEquals(3, $count);
        $this->assertDatabaseMissing('messages', ['receiver_id' => $alice->id, 'read_at' => null]);
    }

    public function test_get_conversation_returns_messages_both_directions(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Message::factory()->create(['sender_id' => $alice->id, 'receiver_id' => $bob->id, 'body' => 'A→B']);
        Message::factory()->create(['sender_id' => $bob->id,   'receiver_id' => $alice->id, 'body' => 'B→A']);

        $conversation = $this->repository->getConversation($alice->id, $bob->id);

        $this->assertCount(2, $conversation);
        $bodies = $conversation->pluck('body')->toArray();
        $this->assertContains('A→B', $bodies);
        $this->assertContains('B→A', $bodies);
    }

    public function test_get_conversation_excludes_third_party_messages(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $charlie = User::factory()->create();

        Message::factory()->create(['sender_id' => $alice->id,   'receiver_id' => $bob->id]);
        Message::factory()->create(['sender_id' => $charlie->id, 'receiver_id' => $alice->id]);

        $conversation = $this->repository->getConversation($alice->id, $bob->id);

        $this->assertCount(1, $conversation);
    }

    public function test_get_unread_counts_by_sender_groups_correctly(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $charlie = User::factory()->create();

        Message::factory()->count(3)->unread()->create([
            'sender_id' => $bob->id,
            'receiver_id' => $alice->id,
        ]);

        Message::factory()->count(2)->unread()->create([
            'sender_id' => $charlie->id,
            'receiver_id' => $alice->id,
        ]);

        // Already-read message — must not appear in counts
        Message::factory()->create([
            'sender_id' => $bob->id,
            'receiver_id' => $alice->id,
            'read_at' => now(),
        ]);

        $counts = $this->repository->getUnreadCountsBySender($alice->id);

        $this->assertCount(2, $counts);
        $this->assertEquals(3, $counts[$bob->id]);
        $this->assertEquals(2, $counts[$charlie->id]);
    }

    public function test_get_unread_counts_by_sender_returns_empty_when_none(): void
    {
        $alice = User::factory()->create();

        $counts = $this->repository->getUnreadCountsBySender($alice->id);

        $this->assertEmpty($counts);
    }
}
