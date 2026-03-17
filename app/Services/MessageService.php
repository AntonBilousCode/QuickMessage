<?php

namespace App\Services;

use App\Contracts\Repositories\MessageRepositoryInterface;
use App\Contracts\Services\MessageServiceInterface;
use App\Events\MessageSent;
use App\Models\Message;
use Illuminate\Database\Eloquent\Collection;

class MessageService implements MessageServiceInterface
{
    public function __construct(
        private readonly MessageRepositoryInterface $messageRepository,
    ) {}

    public function send(int $senderId, int $receiverId, string $body, bool $isEncrypted = false): Message
    {
        $message = $this->messageRepository->create([
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'body' => $body,
            'is_encrypted' => $isEncrypted,
        ]);

        event(new MessageSent($message));

        return $message;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getUnreadMessages(int $userId): Collection
    {
        return $this->messageRepository->findUnreadForUser($userId);
    }

    public function markMessagesAsRead(int $receiverId, ?int $senderId = null): void
    {
        $this->messageRepository->markAsRead($receiverId, $senderId);
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function getUnreadCountsBySender(int $userId): \Illuminate\Support\Collection
    {
        return $this->messageRepository->getUnreadCountsBySender($userId);
    }

    /**
     * @return Collection<int, Message>
     */
    public function getConversation(int $userId1, int $userId2): Collection
    {
        return $this->messageRepository->getConversation($userId1, $userId2);
    }
}
