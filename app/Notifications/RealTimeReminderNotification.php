<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\Reminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Carbon\Carbon;

class RealTimeReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Event $event,
        public Reminder $reminder
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];
        
        // Add email channel for day-before reminders
        if ($this->reminder->method === 'email' || $this->reminder->minutes_before >= 1440) {
            $channels[] = 'mail';
        }
        
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $reminderMessage = $this->getReminderMessage();
        $eventStart = $this->event->start_at->setTimezone($this->event->timezone ?? 'UTC');
        $eventEnd = $this->event->end_at?->setTimezone($this->event->timezone ?? 'UTC');

        $mailMessage = (new MailMessage)
            ->subject($reminderMessage['title'] . ': ' . $this->event->title)
            ->greeting("Hello {$notifiable->name}!")
            ->line($reminderMessage['message'])
            ->line("**Event:** {$this->event->title}")
            ->line("**When:** {$eventStart->format('l, F j, Y \a\t g:i A T')}");

        if ($eventEnd && !$this->event->all_day) {
            $mailMessage->line("**Until:** {$eventEnd->format('g:i A T')}");
        }

        if ($this->event->location) {
            $mailMessage->line("**Where:** {$this->event->location}");
        }

        if ($this->event->meeting_link) {
            $mailMessage->action('Join Meeting', $this->event->meeting_link);
        }

        if ($this->event->description_md) {
            $mailMessage->line("**Description:**")
                        ->line($this->event->description_md);
        }

        $mailMessage->line('Thank you for using our calendar application!');

        return $mailMessage;
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $reminderMessage = $this->getReminderMessage();
        
        return new BroadcastMessage([
            'id' => $this->id,
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
            ],
            'reminder' => [
                'id' => $this->reminder->id,
                'minutes_before' => $this->reminder->minutes_before,
                'method' => $this->reminder->method,
                'type' => $this->reminder->reminder_type ?? 'default',
            ],
            'created_at' => now()->toISOString(),
            'read_at' => null,
        ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $reminderMessage = $this->getReminderMessage();
        
        return [
            'type' => 'reminder',
            'title' => $reminderMessage['title'],
            'message' => $reminderMessage['message'],
            'icon' => $reminderMessage['icon'],
            'notification_type' => $reminderMessage['type'],
            'event_id' => $this->event->id,
            'event_title' => $this->event->title,
            'event_start' => $this->event->start_at->toISOString(),
            'event_end' => $this->event->end_at?->toISOString(),
            'event_location' => $this->event->location,
            'meeting_link' => $this->event->meeting_link,
            'reminder_id' => $this->reminder->id,
            'reminder_minutes_before' => $this->reminder->minutes_before,
            'reminder_method' => $this->reminder->method,
            'reminder_type' => $this->reminder->reminder_type ?? 'default',
            'can_join' => !empty($this->event->meeting_link),
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
        
        if ($diffInMinutes < 60) {
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
     * Determine if the notification should be sent.
     */
    public function shouldSend(object $notifiable): bool
    {
        // Don't send if the event has been cancelled or deleted
        if (!$this->event->exists) {
            return false;
        }

        // Don't send if the event has already ended
        if ($this->event->end_at && $this->event->end_at->isPast()) {
            return false;
        }

        // Check user notification preferences
        if (method_exists($notifiable, 'realtime_notifications_enabled')) {
            if (!$notifiable->realtime_notifications_enabled) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new \Illuminate\Broadcasting\PrivateChannel('user.' . $this->event->calendar->owner->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'reminder.triggered';
    }
}
