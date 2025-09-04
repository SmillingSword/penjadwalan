<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'content',
        'type',
        'attachments',
        'reply_to_id',
        'is_edited',
        'edited_at',
        'reactions',
        'is_system_message',
    ];

    protected $casts = [
        'attachments' => 'array',
        'reactions' => 'array',
        'is_edited' => 'boolean',
        'is_system_message' => 'boolean',
        'edited_at' => 'datetime',
    ];

    /**
     * Get the conversation this message belongs to
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the user who sent this message
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get the message this message is replying to
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    /**
     * Get messages that are replies to this message
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'reply_to_id');
    }

    /**
     * Check if this message has attachments
     */
    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }

    /**
     * Get attachments by type
     */
    public function getAttachmentsByType(string $type): array
    {
        if (!$this->hasAttachments()) {
            return [];
        }

        return array_filter($this->attachments, function ($attachment) use ($type) {
            return ($attachment['type'] ?? '') === $type;
        });
    }

    /**
     * Get image attachments
     */
    public function getImageAttachments(): array
    {
        return $this->getAttachmentsByType('image');
    }

    /**
     * Get file attachments
     */
    public function getFileAttachments(): array
    {
        return $this->getAttachmentsByType('file');
    }

    /**
     * Add reaction to message
     */
    public function addReaction(User $user, string $emoji): bool
    {
        $reactions = $this->reactions ?? [];
        $userId = $user->id;

        // Initialize emoji array if it doesn't exist
        if (!isset($reactions[$emoji])) {
            $reactions[$emoji] = [];
        }

        // Add user to emoji reactions if not already there
        if (!in_array($userId, $reactions[$emoji])) {
            $reactions[$emoji][] = $userId;
        }

        return $this->update(['reactions' => $reactions]);
    }

    /**
     * Remove reaction from message
     */
    public function removeReaction(User $user, string $emoji): bool
    {
        $reactions = $this->reactions ?? [];
        $userId = $user->id;

        if (!isset($reactions[$emoji])) {
            return true; // Already removed
        }

        // Remove user from emoji reactions
        $reactions[$emoji] = array_filter($reactions[$emoji], function ($id) use ($userId) {
            return $id !== $userId;
        });

        // Remove emoji entirely if no users have reacted with it
        if (empty($reactions[$emoji])) {
            unset($reactions[$emoji]);
        }

        return $this->update(['reactions' => $reactions]);
    }

    /**
     * Toggle reaction for user
     */
    public function toggleReaction(User $user, string $emoji): bool
    {
        $reactions = $this->reactions ?? [];
        $userId = $user->id;

        if (isset($reactions[$emoji]) && in_array($userId, $reactions[$emoji])) {
            return $this->removeReaction($user, $emoji);
        } else {
            return $this->addReaction($user, $emoji);
        }
    }

    /**
     * Get reaction count for specific emoji
     */
    public function getReactionCount(string $emoji): int
    {
        $reactions = $this->reactions ?? [];
        return count($reactions[$emoji] ?? []);
    }

    /**
     * Get all reaction counts
     */
    public function getReactionCounts(): array
    {
        $reactions = $this->reactions ?? [];
        $counts = [];

        foreach ($reactions as $emoji => $users) {
            $counts[$emoji] = count($users);
        }

        return $counts;
    }

    /**
     * Check if user has reacted with specific emoji
     */
    public function hasUserReacted(User $user, string $emoji): bool
    {
        $reactions = $this->reactions ?? [];
        return isset($reactions[$emoji]) && in_array($user->id, $reactions[$emoji]);
    }

    /**
     * Mark message as edited
     */
    public function markAsEdited(): bool
    {
        return $this->update([
            'is_edited' => true,
            'edited_at' => now(),
        ]);
    }

    /**
     * Get formatted content for display
     */
    public function getFormattedContent(): string
    {
        if ($this->is_system_message) {
            return $this->content;
        }

        // Basic formatting - can be extended with markdown, mentions, etc.
        $content = $this->content;
        
        // Convert URLs to links (basic implementation)
        $content = preg_replace(
            '/(https?:\/\/[^\s]+)/',
            '<a href="$1" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">$1</a>',
            $content
        );

        return $content;
    }

    /**
     * Create a system message
     */
    public static function createSystemMessage(Conversation $conversation, string $content): self
    {
        return self::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $conversation->created_by, // Use conversation creator as sender
            'content' => $content,
            'type' => 'system',
            'is_system_message' => true,
        ]);
    }

    /**
     * Scope for non-system messages
     */
    public function scopeUserMessages($query)
    {
        return $query->where('is_system_message', false);
    }

    /**
     * Scope for system messages
     */
    public function scopeSystemMessages($query)
    {
        return $query->where('is_system_message', true);
    }

    /**
     * Scope for messages by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for messages with attachments
     */
    public function scopeWithAttachments($query)
    {
        return $query->whereNotNull('attachments');
    }

    /**
     * Boot method to handle model events
     */
    protected static function boot()
    {
        parent::boot();

        // Update conversation's last_message_at when a message is created
        static::created(function ($message) {
            $message->conversation->update([
                'last_message_at' => $message->created_at,
            ]);
        });
    }
}
