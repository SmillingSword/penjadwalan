<?php

namespace App\Events;

use App\Models\User;
use App\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TypingIndicator implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $conversation;
    public $isTyping;
    public $broadcastQueue = 'realtime';
    public $broadcastConnection = 'sync';

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, Conversation $conversation, bool $isTyping = true)
    {
        $this->user = $user;
        $this->conversation = $conversation;
        $this->isTyping = $isTyping;
    }

    /**
     * Get the channels the event should broadcast on.
     * Only broadcast to conversation participants (tenant isolation)
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.conversation.' . $this->conversation->id . '.typing'),
        ];
    }

    /**
     * Get the data to broadcast.
     * Minimal payload for fast transmission
     */
    public function broadcastWith(): array
    {
        return [
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar' => $this->user->avatar,
            ],
            'conversation_id' => $this->conversation->id,
            'is_typing' => $this->isTyping,
            'timestamp' => now()->toISOString(),
            'expires_at' => now()->addSeconds(3)->toISOString(), // Auto-expire after 3 seconds
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'TypingIndicator';
    }

    /**
     * Determine if this event should broadcast.
     * Ensure tenant isolation - only participants can see typing indicators
     */
    public function broadcastWhen(): bool
    {
        return $this->conversation->participants()
            ->where('user_id', $this->user->id)
            ->exists();
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'typing',
            'conversation:' . $this->conversation->id,
            'user:' . $this->user->id,
            'status:' . ($this->isTyping ? 'start' : 'stop'),
        ];
    }
}
