<?php

namespace App\Events;

use App\Models\Event;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class ReminderTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Event $event,
        public Reminder $reminder,
        public User $user
    ) {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->user->id),
            new PrivateChannel('organization.' . $this->event->calendar->organization_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'reminder.triggered';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $reminderMessage = $this->getReminderMessage();
        
        return [
            'id' => uniqid('reminder_'),
            'type' => 'reminder',
            'title' => $reminderMessage['title'],
            'message' => $reminderMessage['message'],
            'icon' => $reminderMessage['icon'],
            'notification_type' => $reminderMessage['type'],
            'event' => [
                'id' => $this->event->id,
                'title' => $this->event->title,
                'start_at' => $this->event->start_at->toISOString(),
                'end_at' => $this->event->end_at?->toISOString(),
                'location' => $this->event->location,
                'meeting_link' => $this->event->meeting_link,
                'description' => $this->event->description_md,
                'all_day' => $this->event->all_day,
                'timezone' => $this->event->timezone,
            ],
            'reminder' => [
                'id' => $this->reminder->id,
                'minutes_before' => $this->reminder->minutes_before,
                'method' => $this->reminder->method,
                'type' => $this->reminder->reminder_type ?? 'default',
            ],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'actions' => $this->getAvailableActions(),
            'created_at' => now()->toISOString(),
            'expires_at' => now()->addHours(24)->toISOString(), // Notification expires after 24 hours
            'priority' => $this->getPriority(),
            'can_snooze' => $this->canSnooze(),
            'time_until_event' => $this->getTimeUntilEvent(),
        ];
    }

    /**
     * Get reminder message based on type and timing
     */
    private function getReminderMessage(): array
    {
        $eventTitle = $this->event->title;
        $eventStart = $this->event->start_at->setTimezone($this->event->timezone ?? 'UTC');
        
        switch ($this->reminder->reminder_type ?? 'default') {
            case 'day_before':
                return [
                    'title' => 'Event Tomorrow',
                    'message' => "Don't forget: \"{$eventTitle}\" is scheduled for tomorrow at {$eventStart->format('g:i A')}",
                    'type' => 'info',
                    'icon' => '📅'
                ];
                
            case 'thirty_minutes_before':
                return [
                    'title' => 'Event Starting Soon',
                    'message' => "\"{$eventTitle}\" starts in 30 minutes at {$eventStart->format('g:i A')}",
                    'type' => 'warning',
                    'icon' => '⏰'
                ];
                
            case 'event_start':
                return [
                    'title' => 'Event Starting Now',
                    'message' => "\"{$eventTitle}\" is starting now!",
                    'type' => 'success',
                    'icon' => '🚀'
                ];
                
            case 'event_starting':
                return [
                    'title' => 'Event Time',
                    'message' => "It's time for \"{$eventTitle}\"",
                    'type' => 'urgent',
                    'icon' => '🔔'
                ];
                
            default:
                $timeUntilEvent = $this->reminder->minutes_before;
                $timeUnit = $timeUntilEvent >= 60 ? 'hour(s)' : 'minute(s)';
                $timeValue = $timeUntilEvent >= 60 ? round($timeUntilEvent / 60, 1) : $timeUntilEvent;
                
                return [
                    'title' => 'Event Reminder',
                    'message' => "\"{$eventTitle}\" starts in {$timeValue} {$timeUnit}",
                    'type' => 'info',
                    'icon' => '📋'
                ];
        }
    }

    /**
     * Get available actions for this reminder
     */
    private function getAvailableActions(): array
    {
        $actions = [
            [
                'type' => 'view',
                'label' => 'View Event',
                'url' => "/events/{$this->event->id}",
                'icon' => '👁️'
            ],
            [
                'type' => 'dismiss',
                'label' => 'Dismiss',
                'action' => 'dismiss',
                'icon' => '✖️'
            ]
        ];

        // Add join meeting action if meeting link exists
        if ($this->event->meeting_link) {
            array_unshift($actions, [
                'type' => 'join',
                'label' => 'Join Meeting',
                'url' => $this->event->meeting_link,
                'icon' => '🎥',
                'primary' => true
            ]);
        }

        // Add snooze action for non-urgent reminders
        if ($this->canSnooze()) {
            $actions[] = [
                'type' => 'snooze',
                'label' => 'Snooze 5 min',
                'action' => 'snooze',
                'duration' => 5,
                'icon' => '⏰'
            ];
        }

        return $actions;
    }

    /**
     * Get notification priority
     */
    private function getPriority(): string
    {
        switch ($this->reminder->reminder_type ?? 'default') {
            case 'event_starting':
            case 'event_start':
                return 'urgent';
            case 'thirty_minutes_before':
                return 'high';
            case 'day_before':
                return 'normal';
            default:
                return 'normal';
        }
    }

    /**
     * Check if reminder can be snoozed
     */
    private function canSnooze(): bool
    {
        $now = Carbon::now();
        $eventStart = $this->event->start_at;
        
        // Can't snooze if event has already started
        if ($eventStart->isPast()) {
            return false;
        }
        
        // Can't snooze if event starts in less than 5 minutes
        if ($now->diffInMinutes($eventStart) < 5) {
            return false;
        }
        
        // Can't snooze urgent notifications
        if (in_array($this->reminder->reminder_type, ['event_starting', 'event_start'])) {
            return false;
        }
        
        return true;
    }

    /**
     * Get time until event in human readable format
     */
    private function getTimeUntilEvent(): string
    {
        $now = Carbon::now();
        $eventStart = $this->event->start_at;
        
        if ($eventStart->isPast()) {
            return 'Event has started';
        }
        
        $diffInMinutes = $now->diffInMinutes($eventStart);
        
        if ($diffInMinutes < 1) {
            return 'Starting now';
        } elseif ($diffInMinutes < 60) {
            return "{$diffInMinutes} minutes";
        } elseif ($diffInMinutes < 1440) {
            $hours = round($diffInMinutes / 60, 1);
            return "{$hours} hours";
        } else {
            $days = round($diffInMinutes / 1440, 1);
            return "{$days} days";
        }
    }

    /**
     * Determine if this event should broadcast.
     */
    public function broadcastWhen(): bool
    {
        // Don't broadcast if the event has been cancelled or deleted
        if (!$this->event->exists) {
            return false;
        }

        // Don't broadcast if the event has already ended
        if ($this->event->end_at && $this->event->end_at->isPast()) {
            return false;
        }

        // Check if user has real-time notifications enabled
        if (!$this->user->realtime_notifications_enabled) {
            return false;
        }

        return true;
    }
}
