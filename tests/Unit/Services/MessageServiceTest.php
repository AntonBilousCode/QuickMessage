<?php

namespace Tests\Unit\Services;

use App\Contracts\Repositories\MessageRepositoryInterface;
use App\Events\MessageSent;
use App\Models\Message;
use App\Services\MessageService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class MessageServiceTest extends TestCase
{
    private MessageRepositoryInterface&MockInterface $repository;

    private MessageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(MessageRepositoryInterface::class);
        $this->service = new MessageService($this->repository);
    }

    public function test_send_creates_message_and_dispatches_event(): void
    {
        Event::fake([MessageSent::class]);

        $message = new Message;
        $message->id = 1;
        $message->sender_id = 1;
        $message->receiver_id = 2;
        $message->body = 'Hello!';

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(['sender_id' => 1, 'receiver_id' => 2, 'body' => 'Hello!', 'is_encrypted' => false])
            ->andReturn($message);

        $result = $this->service->send(1, 2, 'Hello!');

        $this->assertSame($message, $result);
        Event::assertDispatched(MessageSent::class);
    }

    public function test_get_unread_messages_delegates_to_repository(): void
    {
        $collection = new Collection;

        $this->repository
            ->shouldReceive('findUnreadForUser')
            ->once()
            ->with(42)
            ->andReturn($collection);

        $result = $this->service->getUnreadMessages(42);

        $this->assertSame($collection, $result);
    }

    public function test_mark_messages_as_read_delegates_to_repository(): void
    {
        $this->repository
            ->shouldReceive('markAsRead')
            ->once()
            ->with(7, 5)
            ->andReturn(3);

        $this->service->markMessagesAsRead(7, 5);

        $this->assertTrue(true);
    }

    public function test_mark_messages_as_read_without_sender_delegates_to_repository(): void
    {
        $this->repository
            ->shouldReceive('markAsRead')
            ->once()
            ->with(7, null)
            ->andReturn(5);

        $this->service->markMessagesAsRead(7);

        $this->assertTrue(true);
    }

    public function test_get_conversation_delegates_to_repository(): void
    {
        $collection = new Collection;

        $this->repository
            ->shouldReceive('getConversation')
            ->once()
            ->with(1, 2)
            ->andReturn($collection);

        $result = $this->service->getConversation(1, 2);

        $this->assertSame($collection, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
