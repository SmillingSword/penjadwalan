<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $broadcastQueue = 'realtime';
    public $broadcastConnection = 'sync';

    /**
     * Create a new event instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Get the channels the event should broadcast on.
     * Optimized for presence and real-time status updates
     */
    public function broadcastOn(): array
    {
        $channels = [];
        
        // Global presence channel for all online users
        $channels[] = new PresenceChannel('presence-chat.online-users');
        
        // Public channel for general status updates (non-sensitive data)
        $channels[] = new Channel('user-status-updates');
        
        // Private user channel for personal status updates
        $channels[] = new PrivateChannel('user.' . $this->user->id . '.status');
        
        // Broadcast to all active conversations this user is part of
        if ($this->user->is_online) {
            $activeConversations = $this->user->conversations()
                ->whereHas('participants', function($query) {
                    $query->where('user_id', '!=', $this->user->id)
                          ->whereHas('user', function($userQuery) {
                              $userQuery->where('is_online', true)
                                       ->where('last_seen_at', '>=', now()->subMinutes(5));
                          });
                })
                ->pluck('id');
                
            foreach ($activeConversations as $conversationId) {
                $channels[] = new PrivateChannel('chat.conversation.' . $conversationId . '.presence');
            }
        }
        
        return $channels;
    }

    /**
     * Get the data to broadcast.
     * Optimized payload for presence updates
     */
    public function broadcastWith(): array
    {
        return [
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar' => $this->user->avatar,
                'online_status' => $this->user->online_status ?? 'available',
                'status_message' => $this->user->status_message,
                'is_online' => $this->user->is_online ?? false,
                'last_seen_at' => $this->user->last_seen_at?->toISOString(),
                'updated_at' => $this->user->updated_at->toISOString(),
            ],
            'timestamp' => now()->toISOString(),
            'event_type' => $this->getEventType(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'UserStatusUpdated';
    }

    /**
     * Determine if this event should broadcast.
     */
    public function broadcastWhen(): bool
    {
        // Only broadcast if user status actually changed
        return $this->user->wasChanged(['online_status', 'is_online', 'status_message', 'last_seen_at']);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'presence',
            'user:' . $this->user->id,
            'status:' . ($this->user->is_online ? 'online' : 'offline'),
        ];
    }

    /**
     * Get the authentication data for presence channels.
     */
    public function broadcastAuth($request)
    {
        return [
            'id' => $this->user->id,
            'name' => $this->user->name,
            'avatar' => $this->user->avatar,
            'online_status' => $this->user->online_status,
            'is_online' => $this->user->is_online,
        ];
    }

    /**
     * Determine the type of status event.
     */
    private function getEventType(): string
    {
        if ($this->user->wasChanged('is_online')) {
            return $this->user->is_online ? 'user_online' : 'user_offline';
        }
        
        if ($this->user->wasChanged('online_status')) {
            return 'status_changed';
        }
        
        if ($this->user->wasChanged('status_message')) {
            return 'message_changed';
        }
        
        return 'heartbeat';
    }
}
