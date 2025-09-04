<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'joined_at',
        'last_read_at',
        'last_read_message_id',
        'is_muted',
        'muted_until',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'last_read_at' => 'datetime',
        'muted_until' => 'datetime',
        'is_muted' => 'boolean',
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Get the conversation this participant belongs to
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the user who is the participant
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the last read message
     */
    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_read_message_id');
    }

    /**
     * Check if participant is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if participant is muted
     */
    public function isMuted(): bool
    {
        if (!$this->is_muted) {
            return false;
        }

        // Check if mute has expired
        if ($this->muted_until && $this->muted_until->isPast()) {
            $this->update(['is_muted' => false, 'muted_until' => null]);
            return false;
        }

        return true;
    }

    /**
     * Mute participant for specified duration
     */
    public function mute(?int $minutes = null): bool
    {
        $data = ['is_muted' => true];
        
        if ($minutes) {
            $data['muted_until'] = now()->addMinutes($minutes);
        }

        return $this->update($data);
    }

    /**
     * Unmute participant
     */
    public function unmute(): bool
    {
        return $this->update([
            'is_muted' => false,
            'muted_until' => null,
        ]);
    }

    /**
     * Update last read message
     */
    public function updateLastRead(?int $messageId = null): bool
    {
        $data = ['last_read_at' => now()];
        
        if ($messageId) {
            $data['last_read_message_id'] = $messageId;
        }

        return $this->update($data);
    }

    /**
     * Get unread messages count
     */
    public function getUnreadCount(): int
    {
        $query = $this->conversation->messages()
                      ->where('sender_id', '!=', $this->user_id);

        if ($this->last_read_message_id) {
            $query->where('id', '>', $this->last_read_message_id);
        }

        return $query->count();
    }

    /**
     * Check if participant has unread messages
     */
    public function hasUnreadMessages(): bool
    {
        return $this->getUnreadCount() > 0;
    }

    /**
     * Get participant setting
     */
    public function getSetting(string $key, $default = null)
    {
        $settings = $this->settings ?? [];
        return $settings[$key] ?? $default;
    }

    /**
     * Set participant setting
     */
    public function setSetting(string $key, $value): bool
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        
        return $this->update(['settings' => $settings]);
    }

    /**
     * Promote participant to admin
     */
    public function promoteToAdmin(): bool
    {
        return $this->update(['role' => 'admin']);
    }

    /**
     * Demote participant to member
     */
    public function demoteToMember(): bool
    {
        return $this->update(['role' => 'member']);
    }

    /**
     * Leave conversation (mark as inactive)
     */
    public function leave(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * Rejoin conversation (mark as active)
     */
    public function rejoin(): bool
    {
        return $this->update(['is_active' => true]);
    }

    /**
     * Scope for active participants
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for admin participants
     */
    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    /**
     * Scope for member participants
     */
    public function scopeMembers($query)
    {
        return $query->where('role', 'member');
    }

    /**
     * Scope for muted participants
     */
    public function scopeMuted($query)
    {
        return $query->where('is_muted', true)
                    ->where(function ($q) {
                        $q->whereNull('muted_until')
                          ->orWhere('muted_until', '>', now());
                    });
    }

    /**
     * Scope for participants with unread messages
     */
    public function scopeWithUnreadMessages($query)
    {
        return $query->whereHas('conversation.messages', function ($q) {
            $q->whereColumn('messages.id', '>', 'conversation_participants.last_read_message_id')
              ->whereColumn('messages.sender_id', '!=', 'conversation_participants.user_id');
        });
    }
}
