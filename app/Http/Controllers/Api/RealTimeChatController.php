<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Events\MessageSent;
use App\Events\UserStatusUpdated;
use App\Events\TypingIndicator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class RealTimeChatController extends Controller
{
    /**
     * Get user's conversations
     */
    public function getConversations()
    {
        $user = Auth::user();
        
        $conversations = $user->conversations()
            ->with(['participants.user', 'messages' => function($query) {
                $query->latest()->limit(1);
            }])
            ->orderBy('last_message_at', 'desc')
            ->get()
            ->map(function($conversation) use ($user) {
                $lastMessage = $conversation->messages->first();
                $otherParticipant = $conversation->participants
                    ->where('user_id', '!=', $user->id)
                    ->first();
                
                // Get other user data for frontend
                $otherUser = $otherParticipant ? $otherParticipant->user : null;
                
                return [
                    'id' => $conversation->id,
                    'type' => $conversation->type,
                    'title' => $conversation->getDisplayTitle($user),
                    'avatar' => $otherUser ? $otherUser->avatar : null,
                    'other_user' => $otherUser ? [
                        'id' => $otherUser->id,
                        'name' => $otherUser->name,
                        'avatar' => $otherUser->avatar,
                        'is_online' => $otherUser->is_online,
                        'status' => $otherUser->status,
                        'last_seen_at' => $otherUser->last_seen_at,
                    ] : null,
                    'last_message' => $lastMessage ? [
                        'content' => $lastMessage->content,
                        'created_at' => $lastMessage->created_at,
                        'sender_name' => $lastMessage->sender->name,
                        'sender_id' => $lastMessage->sender_id,
                    ] : null,
                    'unread_count' => $conversation->getUnreadCount($user),
                    'updated_at' => $conversation->updated_at,
                    'last_message_at' => $conversation->last_message_at,
                ];
            });

        return response()->json([
            'conversations' => $conversations
        ]);
    }

    /**
     * Get all users (online and offline)
     */
    public function getAllUsers()
    {
        $currentUser = Auth::user();
        
        $users = User::where('id', '!=', $currentUser->id)
            ->select('id', 'name', 'email', 'avatar', 'status', 'status_message', 'last_seen_at', 'is_online', 'show_online_status')
            ->get()
            ->map(function($user) {
                // Determine actual online status considering show_online_status setting
                $actualIsOnline = ($user->is_online === true) && ($user->show_online_status !== false);
                
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'status' => $user->status ?? 'available',
                    'status_message' => $user->status_message,
                    'last_seen_at' => $user->last_seen_at,
                    'is_online' => $actualIsOnline,
                ];
            });

        return response()->json([
            'users' => $users
        ]);
    }

    /**
     * Get online users only
     */
    public function getOnlineUsers()
    {
        $currentUser = Auth::user();
        
        $onlineUsers = User::where('id', '!=', $currentUser->id)
            ->where('is_online', true)
            ->where('last_seen_at', '>=', now()->subMinutes(5))
            ->select('id', 'name', 'email', 'avatar', 'status', 'status_message', 'last_seen_at')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'status' => $user->status ?? 'available',
                    'status_message' => $user->status_message,
                    'last_seen_at' => $user->last_seen_at,
                    'is_online' => true,
                ];
            });

        return response()->json([
            'online_users' => $onlineUsers
        ]);
    }

    /**
     * Create or get private conversation
     */
    public function createPrivateConversation(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $currentUser = Auth::user();
        $otherUserId = $request->user_id;

        // Check if conversation already exists
        $existingConversation = Conversation::where('type', 'private')
            ->whereHas('participants', function($query) use ($currentUser) {
                $query->where('user_id', $currentUser->id);
            })
            ->whereHas('participants', function($query) use ($otherUserId) {
                $query->where('user_id', $otherUserId);
            })
            ->first();

        if ($existingConversation) {
            return response()->json([
                'conversation' => $this->formatConversation($existingConversation, $currentUser)
            ]);
        }

        // Create new conversation
        $conversation = Conversation::create([
            'type' => 'private',
            'created_by' => $currentUser->id,
        ]);

        // Add participants
        $conversation->participants()->create([
            'user_id' => $currentUser->id,
            'role' => 'admin',
            'joined_at' => now(),
        ]);

        $conversation->participants()->create([
            'user_id' => $otherUserId,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $conversation->load(['participants.user']);

        return response()->json([
            'conversation' => $this->formatConversation($conversation, $currentUser)
        ]);
    }

    /**
     * Get conversation messages with polling support
     */
    public function getMessages(Request $request, $conversationId)
    {
        $user = Auth::user();
        
        // Verify user is participant
        $conversation = Conversation::whereHas('participants', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->findOrFail($conversationId);

        $query = $conversation->messages()
            ->with(['sender', 'replyTo.sender'])
            ->orderBy('created_at', 'desc');

        // Check if this is a polling request for new messages
        if ($request->has('after')) {
            $afterMessageId = $request->get('after');
            $query->where('id', '>', $afterMessageId)->orderBy('created_at', 'asc');
        } else {
            $query->limit(50);
        }

        $messages = $query->get();

        if (!$request->has('after')) {
            $messages = $messages->reverse()->values();
        }

        $formattedMessages = $messages->map(function($message) {
            return [
                'id' => $message->id,
                'content' => $message->content,
                'sender_id' => $message->sender_id,
                'sender' => [
                    'id' => $message->sender->id,
                    'name' => $message->sender->name,
                    'avatar' => $message->sender->avatar,
                ],
                'reply_to' => $message->replyTo ? [
                    'id' => $message->replyTo->id,
                    'content' => $message->replyTo->content,
                    'sender_name' => $message->replyTo->sender->name,
                ] : null,
                'attachments' => $message->attachments,
                'reactions' => $message->reactions,
                'created_at' => $message->created_at,
                'updated_at' => $message->updated_at,
            ];
        });

        // Mark messages as read (only for initial load, not polling)
        if (!$request->has('after')) {
            $conversation->participants()
                ->where('user_id', $user->id)
                ->update(['last_read_at' => now()]);
        }

        return response()->json([
            'messages' => $formattedMessages
        ]);
    }

    /**
     * Get new messages since a specific message ID (for polling fallback)
     */
    public function getMessagesSince(Request $request, $conversationId, $lastMessageId = 0)
    {
        $user = Auth::user();
        
        // Verify user is participant
        $conversation = Conversation::whereHas('participants', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->findOrFail($conversationId);

        $messages = $conversation->messages()
            ->with(['sender', 'replyTo.sender'])
            ->where('id', '>', $lastMessageId)
            ->orderBy('created_at', 'asc')
            ->get();

        $formattedMessages = $messages->map(function($message) {
            return [
                'id' => $message->id,
                'content' => $message->content,
                'sender_id' => $message->sender_id,
                'sender' => [
                    'id' => $message->sender->id,
                    'name' => $message->sender->name,
                    'avatar' => $message->sender->avatar,
                ],
                'reply_to' => $message->replyTo ? [
                    'id' => $message->replyTo->id,
                    'content' => $message->replyTo->content,
                    'sender_name' => $message->replyTo->sender->name,
                ] : null,
                'attachments' => $message->attachments,
                'reactions' => $message->reactions,
                'created_at' => $message->created_at,
                'updated_at' => $message->updated_at,
            ];
        });

        return response()->json([
            'messages' => $formattedMessages,
            'count' => $formattedMessages->count()
        ]);
    }

    /**
     * Send message
     */
    public function sendMessage(Request $request, $conversationId)
    {
        $request->validate([
            'content' => 'required|string|max:1000',
            'reply_to_id' => 'nullable|exists:messages,id',
            'attachments' => 'nullable|array',
        ]);

        $user = Auth::user();
        
        // Verify user is participant
        $conversation = Conversation::whereHas('participants', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->findOrFail($conversationId);

        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'content' => $request->content,
            'reply_to_id' => $request->reply_to_id,
            'attachments' => $request->attachments ?? [],
        ]);

        $message->load(['sender', 'replyTo.sender']);

        // Update conversation timestamp
        $conversation->touch();

        // Broadcast message (for future WebSocket implementation)
        try {
            broadcast(new MessageSent($message, $conversation))->toOthers();
        } catch (\Exception $e) {
            // Ignore broadcasting errors for now
        }

        // Add avatar URL to sender for frontend
        $message->sender->avatar_url = $message->sender->avatar ? (
            str_starts_with($message->sender->avatar, 'http') ? 
            $message->sender->avatar : 
            asset('storage/' . $message->sender->avatar)
        ) : null;

        return response()->json([
            'message' => [
                'id' => $message->id,
                'content' => $message->content,
                'sender_id' => $message->sender_id,
                'sender' => [
                    'id' => $message->sender->id,
                    'name' => $message->sender->name,
                    'avatar' => $message->sender->avatar_url,
                ],
                'reply_to' => $message->replyTo ? [
                    'id' => $message->replyTo->id,
                    'content' => $message->replyTo->content,
                    'sender_name' => $message->replyTo->sender->name,
                ] : null,
                'attachments' => $message->attachments,
                'reactions' => $message->reactions,
                'created_at' => $message->created_at,
                'updated_at' => $message->updated_at,
            ]
        ]);
    }

    /**
     * Mark conversation as read
     */
    public function markAsRead(Request $request, $conversationId)
    {
        $user = Auth::user();
        
        $conversation = Conversation::whereHas('participants', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->findOrFail($conversationId);

        $conversation->participants()
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Conversation marked as read'
        ]);
    }

    /**
     * Update user online status with optimized broadcasting
     */
    public function updateOnlineStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:available,busy,away,invisible,offline',
            'status_message' => 'nullable|string|max:100',
            'is_online' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $isOnline = $request->is_online ?? ($request->status !== 'offline');
        
        // Only update if status actually changed
        $hasChanges = $user->status !== $request->status ||
                     $user->status_message !== $request->status_message ||
                     $user->is_online !== $isOnline;

        if ($hasChanges) {
            $user->update([
                'status' => $request->status,
                'status_message' => $request->status_message,
                'is_online' => $isOnline,
                'last_seen_at' => now(),
            ]);

            // Broadcast status update with optimized event
            try {
                broadcast(new UserStatusUpdated($user))->toOthers();
            } catch (\Exception $e) {
                Log::warning('Failed to broadcast user status update', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'user' => [
                'id' => $user->id,
                'status' => $user->status,
                'status_message' => $user->status_message,
                'is_online' => $user->is_online,
                'last_seen_at' => $user->last_seen_at?->toISOString(),
            ]
        ]);
    }

    /**
     * Handle typing indicator start
     */
    public function startTyping(Request $request, $conversationId)
    {
        $validator = Validator::make(['conversation_id' => $conversationId], [
            'conversation_id' => 'required|exists:conversations,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid conversation'
            ], 422);
        }

        $user = Auth::user();
        
        // Verify user is participant (tenant isolation)
        $conversation = Conversation::whereHas('participants', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->findOrFail($conversationId);

        // Cache typing status with auto-expiry (3 seconds)
        $cacheKey = "typing:{$conversationId}:{$user->id}";
        Cache::put($cacheKey, true, 3);

        // Broadcast typing indicator
        try {
            broadcast(new TypingIndicator($user, $conversation, true))->toOthers();
        } catch (\Exception $e) {
            Log::warning('Failed to broadcast typing indicator', [
                'user_id' => $user->id,
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Typing indicator started'
        ]);
    }

    /**
     * Handle typing indicator stop
     */
    public function stopTyping(Request $request, $conversationId)
    {
        $validator = Validator::make(['conversation_id' => $conversationId], [
            'conversation_id' => 'required|exists:conversations,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid conversation'
            ], 422);
        }

        $user = Auth::user();
        
        // Verify user is participant (tenant isolation)
        $conversation = Conversation::whereHas('participants', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->findOrFail($conversationId);

        // Remove typing status from cache
        $cacheKey = "typing:{$conversationId}:{$user->id}";
        Cache::forget($cacheKey);

        // Broadcast typing stop
        try {
            broadcast(new TypingIndicator($user, $conversation, false))->toOthers();
        } catch (\Exception $e) {
            Log::warning('Failed to broadcast typing stop', [
                'user_id' => $user->id,
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Typing indicator stopped'
        ]);
    }

    /**
     * Get typing users for a conversation
     */
    public function getTypingUsers($conversationId)
    {
        $user = Auth::user();
        
        // Verify user is participant (tenant isolation)
        $conversation = Conversation::whereHas('participants', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->findOrFail($conversationId);

        $typingUsers = [];
        $participants = $conversation->participants()->with('user')->get();

        foreach ($participants as $participant) {
            if ($participant->user_id !== $user->id) {
                $cacheKey = "typing:{$conversationId}:{$participant->user_id}";
                if (Cache::has($cacheKey)) {
                    $typingUsers[] = [
                        'id' => $participant->user->id,
                        'name' => $participant->user->name,
                        'avatar' => $participant->user->avatar,
                    ];
                }
            }
        }

        return response()->json([
            'typing_users' => $typingUsers
        ]);
    }

    /**
     * Heartbeat endpoint for maintaining online status
     */
    public function heartbeat(Request $request)
    {
        $user = Auth::user();
        
        // Update last seen timestamp
        $user->update([
            'last_seen_at' => now(),
            'is_online' => true,
        ]);

        // Clean up expired typing indicators
        $this->cleanupExpiredTypingIndicators();

        return response()->json([
            'status' => 'success',
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Clean up expired typing indicators
     */
    private function cleanupExpiredTypingIndicators()
    {
        // This would be better handled by a scheduled job
        // For now, we rely on cache expiry
    }

    /**
     * Search users for chat
     */
    public function searchUsers(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2',
        ]);

        $currentUser = Auth::user();
        $query = $request->query;

        $users = User::where('id', '!=', $currentUser->id)
            ->where(function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('email', 'LIKE', "%{$query}%");
            })
            ->select('id', 'name', 'email', 'avatar', 'online_status', 'is_online')
            ->limit(10)
            ->get();

        return response()->json([
            'users' => $users
        ]);
    }

    /**
     * Format conversation for response
     */
    private function formatConversation($conversation, $user)
    {
        $otherParticipant = $conversation->participants
            ->where('user_id', '!=', $user->id)
            ->first();

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'title' => $conversation->getDisplayTitle($user),
            'avatar' => $otherParticipant ? $otherParticipant->user->avatar : null,
            'participants' => $conversation->participants->map(function($participant) {
                return [
                    'id' => $participant->user->id,
                    'name' => $participant->user->name,
                    'avatar' => $participant->user->avatar,
                    'status' => $participant->user->status,
                    'is_online' => $participant->user->is_online,
                ];
            }),
            'unread_count' => $conversation->getUnreadCount($user),
            'created_at' => $conversation->created_at,
            'updated_at' => $conversation->updated_at,
        ];
    }
}
