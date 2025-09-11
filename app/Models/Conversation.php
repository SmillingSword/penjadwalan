<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'description',
        'avatar',
        'created_by',
        'last_message_at',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'last_message_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user who created this conversation
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all messages in this conversation
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    /**
     * Get the latest message in this conversation
     */
    public function latestMessage(): HasMany
    {
        return $this->hasMany(Message::class)->latest();
    }

    /**
     * Get all participants in this conversation
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * Get active participants in this conversation
     */
    public function activeParticipants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class)->where('is_active', true);
    }

    /**
     * Get users who are participants in this conversation
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
                    ->withPivot(['role', 'joined_at', 'last_read_at', 'last_read_message_id', 'is_muted', 'muted_until', 'settings', 'is_active'])
                    ->withTimestamps()
                    ->wherePivot('is_active', true);
    }

    /**
     * Check if user is participant in this conversation
     */
    public function hasParticipant(User $user): bool
    {
        return $this->participants()->where('user_id', $user->id)->where('is_active', true)->exists();
    }

    /**
     * Add a user as participant to this conversation
     */
    public function addParticipant(User $user, string $role = 'member'): ConversationParticipant
    {
        return $this->participants()->create([
            'user_id' => $user->id,
            'role' => $role,
            'joined_at' => now(),
            'is_active' => true,
        ]);
    }

    /**
     * Remove a user from this conversation
     */
    public function removeParticipant(User $user): bool
    {
        return $this->participants()
                    ->where('user_id', $user->id)
                    ->update(['is_active' => false]);
    }

    /**
     * Get unread messages count for a user
     */
    public function getUnreadCountForUser(User $user): int
    {
        $participant = $this->participants()->where('user_id', $user->id)->first();
        
        if (!$participant) {
            return 0;
        }

        $query = $this->messages()->where('sender_id', '!=', $user->id);

        if ($participant->last_read_message_id) {
            $query->where('id', '>', $participant->last_read_message_id);
        }

        return $query->count();
    }

    /**
     * Get unread messages count for a user (alias method)
     */
    public function getUnreadCount(User $user): int
    {
        return $this->getUnreadCountForUser($user);
    }

    /**
     * Mark messages as read for a user
     */
    public function markAsReadForUser(User $user, ?int $messageId = null): bool
    {
        $participant = $this->participants()->where('user_id', $user->id)->first();
        
        if (!$participant) {
            return false;
        }

        $lastMessageId = $messageId ?? $this->messages()->latest()->first()?->id;

        return $participant->update([
            'last_read_at' => now(),
            'last_read_message_id' => $lastMessageId,
        ]);
    }

    /**
     * Get conversation title for display
     */
    public function getDisplayTitle(User $currentUser): string
    {
        if ($this->type === 'group') {
            return $this->title ?? 'Group Chat';
        }

        // For private conversations, show the other participant's name
        $otherParticipant = $this->users()->where('users.id', '!=', $currentUser->id)->first();
        return $otherParticipant ? $otherParticipant->name : 'Private Chat';
    }

    /**
     * Get conversation avatar for display
     */
    public function getDisplayAvatar(User $currentUser): ?string
    {
        if ($this->type === 'group') {
            return $this->avatar;
        }

        // For private conversations, show the other participant's avatar
        $otherParticipant = $this->users()->where('users.id', '!=', $currentUser->id)->first();
        return $otherParticipant?->avatar;
    }

    /**
     * Create a private conversation between two users
     */
    public static function createPrivateConversation(User $user1, User $user2): self
    {
        // Check if conversation already exists
        $existingConversation = self::whereHas('participants', function ($query) use ($user1) {
                $query->where('user_id', $user1->id)->where('is_active', true);
            })
            ->whereHas('participants', function ($query) use ($user2) {
                $query->where('user_id', $user2->id)->where('is_active', true);
            })
            ->where('type', 'private')
            ->first();

        if ($existingConversation) {
            return $existingConversation;
        }

        // Create new conversation
        $conversation = self::create([
            'type' => 'private',
            'created_by' => $user1->id,
            'is_active' => true,
        ]);

        // Add participants
        $conversation->addParticipant($user1);
        $conversation->addParticipant($user2);

        return $conversation;
    }

    /**
     * Scope for active conversations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for conversations where user is participant
     */
    public function scopeForUser($query, User $user)
    {
        return $query->whereHas('participants', function ($q) use ($user) {
            $q->where('user_id', $user->id)->where('is_active', true);
        });
    }
}
