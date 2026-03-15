<?php

namespace App\Contracts\Services;

use App\Models\Message;
use Illuminate\Database\Eloquent\Collection;

interface MessageServiceInterface
{
    /**
     * Send a message from one user to another.
     * Persists to DB and dispatches the MessageSent broadcast event.
     */
    public function send(int $senderId, int $receiverId, string $body): Message;

    /**
     * Get all unread messages for the given user (missed while offline).
     *
     * @return Collection<int, Message>
     */
    public function getUnreadMessages(int $userId): Collection;

    /**
     * Mark unread messages as read.
     * If $senderId is provided, only marks messages from that specific sender.
     */
    public function markMessagesAsRead(int $receiverId, ?int $senderId = null): void;

    /**
     * Get the full conversation history between two users.
     *
     * @return Collection<int, Message>
     */
    public function getConversation(int $userId1, int $userId2): Collection;

    /**
     * Get unread message counts grouped by sender_id (SQL-level aggregation).
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function getUnreadCountsBySender(int $userId): \Illuminate\Support\Collection;
}
