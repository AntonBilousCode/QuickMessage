<?php

namespace App\Contracts\Repositories;

use App\Models\Message;
use Illuminate\Database\Eloquent\Collection;

interface MessageRepositoryInterface
{
    /**
     * Create and persist a new message
     *
     * @param  array{sender_id: int, receiver_id: int, body: string, is_encrypted?: bool}  $data
     */
    public function create(array $data): Message;

    /**
     * Find all unread messages for a given user (offline missed messages)
     *
     * @return Collection<int, Message>
     */
    public function findUnreadForUser(int $userId): Collection;

    /**
     * Mark unread messages for the given receiver as read.
     * If $senderId is provided, only marks messages FROM that sender.
     * Returns the number of records updated.
     */
    public function markAsRead(int $receiverId, ?int $senderId = null): int;

    /**
     * Get full conversation between two users, ordered by creation time
     *
     * @return Collection<int, Message>
     */
    public function getConversation(int $userId1, int $userId2): Collection;

    /**
     * Get unread message counts grouped by sender_id for a given receiver.
     * Returns a collection keyed by sender_id with count values.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function getUnreadCountsBySender(int $userId): \Illuminate\Support\Collection;
}
