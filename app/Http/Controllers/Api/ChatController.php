<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Events\MessageSent;
use App\Events\UserStatusUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
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
            ->get()
            ->map(function($conversation) use ($user) {
                $lastMessage = $conversation->messages->first();
                $otherParticipant = $conversation->participants
                    ->where('user_id', '!=', $user->id)
                    ->first();
                
                return [
                    'id' => $conversation->id,
                    'type' => $conversation->type,
                    'title' => $conversation->getDisplayTitle($user),
                    'avatar' => $otherParticipant ? $otherParticipant->user->avatar : null,
                    'last_message' => $lastMessage ? [
                        'content' => $lastMessage->content,
                        'created_at' => $lastMessage->created_at,
                        'sender_name' => $lastMessage->sender->name,
                    ] : null,
                    'unread_count' => $conversation->getUnreadCount($user),
                    'updated_at' => $conversation->updated_at,
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
            ->select('id', 'name', 'email', 'avatar', 'online_status', 'status_message', 'last_seen_at', 'is_online')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar ?: '/default-avatar.png',
                    'online_status' => $user->online_status ?? 'available',
                    'status_message' => $user->status_message,
                    'last_seen_at' => $user->last_seen_at,
                    'is_online' => $user->is_online ?? false,
                ];
            });

        return response()->json([
            'users' => $users
        ]);
    }

    /**
     * Get online users
     */
    public function getOnlineUsers()
    {
        $currentUser = Auth::user();
        
        $onlineUsers = User::where('id', '!=', $currentUser->id)
            ->where('is_online', true)
            ->where('last_seen_at', '>=', now()->subMinutes(5))
            ->select('id', 'name', 'email', 'avatar', 'online_status', 'status_message', 'last_seen_at')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar ?: '/default-avatar.png',
                    'online_status' => $user->online_status ?? 'available',
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
     * Get conversation messages
     */
    public function getMessages(Request $request, $conversationId)
    {
        $user = Auth::user();
        
        // Verify user is participant
        $conversation = Conversation::whereHas('participants', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->findOrFail($conversationId);

        $messages = $conversation->messages()
            ->with(['sender', 'replyTo.sender'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->reverse()
            ->values()
            ->map(function($message) {
                return [
                    'id' => $message->id,
                    'content' => $message->content,
                    'sender' => [
                        'id' => $message->sender->id,
                        'name' => $message->sender->name,
                        'avatar' => $message->sender->avatar ?: '/default-avatar.png',
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

        // Mark messages as read
        $conversation->participants()
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);

        return response()->json([
            'messages' => $messages
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

        // Broadcast message to other participants
        broadcast(new MessageSent($message, $conversation))->toOthers();

        return response()->json([
            'message' => [
                'id' => $message->id,
                'content' => $message->content,
                'sender' => [
                    'id' => $message->sender->id,
                    'name' => $message->sender->name,
                    'avatar' => $message->sender->avatar ?: '/default-avatar.png',
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
     * Update user online status
     */
    public function updateOnlineStatus(Request $request)
    {
        $request->validate([
            'status' => 'required|in:available,busy,away,invisible',
            'status_message' => 'nullable|string|max:100',
        ]);

        $user = Auth::user();
        $user->update([
            'online_status' => $request->status,
            'status_message' => $request->status_message,
            'is_online' => $request->status !== 'invisible',
            'last_seen_at' => now(),
        ]);

        // Broadcast status update
        broadcast(new UserStatusUpdated($user))->toOthers();

        return response()->json([
            'status' => 'success',
            'user' => [
                'id' => $user->id,
                'online_status' => $user->online_status,
                'status_message' => $user->status_message,
                'is_online' => $user->is_online,
            ]
        ]);
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
     * Mark conversation as read
     */
    public function markAsRead(Request $request, $conversationId)
    {
        $user = Auth::user();
        
        // Verify user is participant
        $conversation = Conversation::whereHas('participants', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->findOrFail($conversationId);

        // Mark as read
        $conversation->markAsReadForUser($user);

        return response()->json([
            'status' => 'success',
            'message' => 'Conversation marked as read'
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
            'avatar' => $otherParticipant ? ($otherParticipant->user->avatar ?: '/default-avatar.png') : null,
            'participants' => $conversation->participants->map(function($participant) {
                return [
                    'id' => $participant->user->id,
                    'name' => $participant->user->name,
                    'avatar' => $participant->user->avatar ?: '/default-avatar.png',
                    'online_status' => $participant->user->online_status,
                    'is_online' => $participant->user->is_online,
                ];
            }),
            'unread_count' => $conversation->getUnreadCount($user),
            'created_at' => $conversation->created_at,
            'updated_at' => $conversation->updated_at,
        ];
    }
}
