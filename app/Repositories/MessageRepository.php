<?php

namespace App\Repositories;

use App\Contracts\Repositories\MessageRepositoryInterface;
use App\Models\Message;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class MessageRepository implements MessageRepositoryInterface
{
    public function __construct(
        private readonly Message $model,
    ) {}

    /**
     * @param  array{sender_id: int, receiver_id: int, body: string, is_encrypted?: bool}  $data
     */
    public function create(array $data): Message
    {
        return $this->model->create($data);
    }

    /**
     * @return Collection<int, Message>
     */
    public function findUnreadForUser(int $userId): Collection
    {
        return $this->model
            ->with('sender')
            ->forUser($userId)
            ->unread()
            ->orderBy('created_at')
            ->get();
    }

    public function markAsRead(int $receiverId, ?int $senderId = null): int
    {
        $query = $this->model
            ->forUser($receiverId)
            ->unread();

        if ($senderId !== null) {
            $query->where('sender_id', $senderId);
        }

        return $query->update(['read_at' => Carbon::now()]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function getUnreadCountsBySender(int $userId): \Illuminate\Support\Collection
    {
        return $this->model
            ->selectRaw('sender_id, COUNT(*) as count')
            ->where('receiver_id', $userId)
            ->whereNull('read_at')
            ->groupBy('sender_id')
            ->pluck('count', 'sender_id');
    }

    /**
     * @return Collection<int, Message>
     */
    public function getConversation(int $userId1, int $userId2): Collection
    {
        return $this->model
            ->with(['sender', 'receiver'])
            ->where(function ($query) use ($userId1, $userId2): void {
                $query->where('sender_id', $userId1)
                    ->where('receiver_id', $userId2);
            })
            ->orWhere(function ($query) use ($userId1, $userId2): void {
                $query->where('sender_id', $userId2)
                    ->where('receiver_id', $userId1);
            })
            ->orderBy('created_at')
            ->get();
    }
}
