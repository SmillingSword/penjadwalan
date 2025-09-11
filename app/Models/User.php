<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasUuids, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'location',
        'latitude',
        'longitude',
        'timezone',
        'locale',
        'working_hours_start',
        'working_hours_end',
        'working_days',
        'buffer_minutes',
        'notification_preferences',
        'browser_notifications_enabled',
        'email_notifications_enabled',
        'realtime_notifications_enabled',
        'is_online',
        'last_seen_at',
        'status',
        'status_message',
        'show_online_status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'working_days' => 'array',
            'notification_preferences' => 'array',
            'browser_notifications_enabled' => 'boolean',
            'email_notifications_enabled' => 'boolean',
            'realtime_notifications_enabled' => 'boolean',
            'is_online' => 'boolean',
            'last_seen_at' => 'datetime',
            'show_online_status' => 'boolean',
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class, 'owner_user_id');
    }

    public function freeBusyBlocks(): HasMany
    {
        return $this->hasMany(FreeBusyBlock::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    public function ownedCalendars(): HasMany
    {
        return $this->hasMany(Calendar::class, 'owner_user_id');
    }

    /**
     * Get conversations where user is a participant
     */
    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
                    ->withPivot(['role', 'joined_at', 'last_read_at', 'last_read_message_id', 'is_muted', 'muted_until', 'settings', 'is_active'])
                    ->withTimestamps()
                    ->wherePivot('is_active', true);
    }

    /**
     * Get conversation participants records for this user
     */
    public function conversationParticipants()
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * Get messages sent by this user
     */
    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Get conversations created by this user
     */
    public function createdConversations()
    {
        return $this->hasMany(Conversation::class, 'created_by');
    }

    /**
     * Update user's online status
     */
    public function setOnline(): bool
    {
        return $this->update([
            'is_online' => true,
            'last_seen_at' => now(),
        ]);
    }

    /**
     * Update user's offline status
     */
    public function setOffline(): bool
    {
        return $this->update([
            'is_online' => false,
            'last_seen_at' => now(),
        ]);
    }

    /**
     * Update user's status
     */
    public function updateStatus(string $status, ?string $message = null): bool
    {
        $data = ['status' => $status];
        
        if ($message !== null) {
            $data['status_message'] = $message;
        }

        return $this->update($data);
    }

    /**
     * Check if user is online
     */
    public function isOnline(): bool
    {
        return $this->is_online && $this->show_online_status;
    }

    /**
     * Check if user is available for chat
     */
    public function isAvailable(): bool
    {
        return $this->isOnline() && $this->status === 'available';
    }

    /**
     * Get user's display status
     */
    public function getDisplayStatus(): string
    {
        if (!$this->show_online_status) {
            return 'invisible';
        }

        if (!$this->is_online) {
            return 'offline';
        }

        return $this->status;
    }

    /**
     * Get formatted last seen time
     */
    public function getLastSeenFormatted(): string
    {
        if ($this->isOnline()) {
            return 'Online';
        }

        if (!$this->last_seen_at) {
            return 'Never';
        }

        $diffInMinutes = now()->diffInMinutes($this->last_seen_at);

        if ($diffInMinutes < 1) {
            return 'Just now';
        } elseif ($diffInMinutes < 60) {
            return $diffInMinutes . 'm ago';
        } elseif ($diffInMinutes < 1440) {
            return floor($diffInMinutes / 60) . 'h ago';
        } else {
            return floor($diffInMinutes / 1440) . 'd ago';
        }
    }

    /**
     * Get unread messages count across all conversations
     */
    public function getTotalUnreadMessagesCount(): int
    {
        return $this->conversationParticipants()
                    ->active()
                    ->get()
                    ->sum(function ($participant) {
                        return $participant->getUnreadCount();
                    });
    }

    /**
     * Get conversations with unread messages
     */
    public function getConversationsWithUnreadMessages()
    {
        return $this->conversations()
                    ->whereHas('participants', function ($query) {
                        $query->where('user_id', $this->id)
                              ->where('is_active', true)
                              ->withUnreadMessages();
                    })
                    ->with(['latestMessage', 'users'])
                    ->orderBy('last_message_at', 'desc');
    }

    /**
     * Start a conversation with another user
     */
    public function startConversationWith(User $otherUser): Conversation
    {
        return Conversation::createPrivateConversation($this, $otherUser);
    }

    /**
     * Scope for online users
     */
    public function scopeOnline($query)
    {
        return $query->where('is_online', true)
                    ->where('show_online_status', true);
    }

    /**
     * Scope for users with specific status
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for available users
     */
    public function scopeAvailable($query)
    {
        return $query->online()->where('status', 'available');
    }

    /**
     * Get the avatar URL attribute
     */
    public function getAvatarAttribute($value)
    {
        if (!$value) {
            return null;
        }

        // If it's a Google avatar (full URL), return as is
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        // If it's a local avatar, prepend with storage URL
        return asset('storage/' . $value);
    }

    /**
     * Set the avatar attribute (store only the path, not the full URL)
     */
    public function setAvatarAttribute($value)
    {
        if (!$value) {
            $this->attributes['avatar'] = null;
            return;
        }

        // If it's a full URL (Google avatar), store as is
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $this->attributes['avatar'] = $value;
            return;
        }

        // If it's a local file path that starts with storage/, remove that prefix
        if (str_starts_with($value, 'storage/')) {
            $value = str_replace('storage/', '', $value);
        }

        // Store just the relative path
        $this->attributes['avatar'] = $value;
    }
}
