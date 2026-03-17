<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Message $message,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('messages.'.$this->message->receiver_id),
        ];
    }

    /**
     * The event name to broadcast as.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Payload sent to the browser.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'body' => $this->message->body,
                'created_at' => $this->message->created_at?->toIso8601String(),
                'is_encrypted' => $this->message->is_encrypted,
            ],
            'sender' => [
                'id' => $this->message->sender_id,
                'name' => $this->message->sender?->name,
            ],
        ];
    }
}
