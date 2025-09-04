<?php

namespace App\Events;

use App\Models\Message;
use App\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $conversation;
    public $broadcastQueue = 'realtime';
    public $broadcastConnection = 'sync';

    /**
     * Create a new event instance.
     */
    public function __construct(Message $message, Conversation $conversation)
    {
        $this->message = $message;
        $this->conversation = $conversation;
        
        // Load necessary relationships to avoid N+1 queries
        $this->message->load(['sender', 'replyTo.sender']);
        $this->conversation->load(['participants.user']);
    }

    /**
     * Get the channels the event should broadcast on.
     * Optimized for tenant isolation and <300ms delivery
     */
    public function broadcastOn(): array
    {
        $channels = [];
        
        // Primary conversation channel for immediate delivery
        $conversationChannel = "private-chat.conversation.{$this->conversation->id}";
        $channels[] = new PrivateChannel($conversationChannel);
        
        // Individual user channels for cross-tab notifications
        foreach ($this->conversation->participants as $participant) {
            // Skip sender to avoid duplicate messages
            if ($participant->user_id !== $this->message->sender_id) {
                $userChannel = "private-user.{$participant->user_id}.chat";
                $channels[] = new PrivateChannel($userChannel);
            }
        }
        
        return $channels;
    }

    /**
     * Get the data to broadcast.
     * Optimized payload for faster transmission
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'sender_id' => $this->message->sender_id,
                'content' => $this->message->content,
                'type' => $this->message->type ?? 'text',
                'sender' => [
                    'id' => $this->message->sender->id,
                    'name' => $this->message->sender->name,
                    'avatar' => $this->message->sender->avatar,
                ],
                'reply_to' => $this->message->replyTo ? [
                    'id' => $this->message->replyTo->id,
                    'content' => $this->message->replyTo->content,
                    'sender' => [
                        'id' => $this->message->replyTo->sender->id,
                        'name' => $this->message->replyTo->sender->name,
                    ],
                ] : null,
                'attachments' => $this->message->attachments ?? [],
                'reactions' => $this->message->reactions ?? [],
                'created_at' => $this->message->created_at->toISOString(),
                'updated_at' => $this->message->updated_at->toISOString(),
                'is_edited' => $this->message->is_edited ?? false,
            ],
            'conversation' => [
                'id' => $this->conversation->id,
                'type' => $this->conversation->type,
                'updated_at' => $this->conversation->updated_at->toISOString(),
            ],
            'timestamp' => now()->toISOString(),
            'delivery_id' => uniqid('msg_', true), // For delivery confirmation
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    /**
     * Determine if this event should broadcast.
     * Add tenant isolation check
     */
    public function broadcastWhen(): bool
    {
        // Ensure conversation has participants (tenant isolation)
        return $this->conversation->participants->count() > 0 
            && $this->conversation->participants->contains('user_id', $this->message->sender_id);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'chat',
            'message:' . $this->message->id,
            'conversation:' . $this->conversation->id,
            'sender:' . $this->message->sender_id,
        ];
    }
}
