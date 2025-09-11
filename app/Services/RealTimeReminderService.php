<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Reminder;
use App\Models\User;
use App\Events\ReminderTriggered;
use App\Notifications\RealTimeReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;

class RealTimeReminderService
{
    /**
     * Default reminder intervals in minutes
     */
    const DEFAULT_REMINDERS = [
        [
            'minutes_before' => 1440, // 1 day before
            'method' => 'email',
            'type' => 'day_before'
        ],
        [
            'minutes_before' => 30, // 30 minutes before
            'method' => 'realtime',
            'type' => 'thirty_minutes_before'
        ],
        [
            'minutes_before' => 0, // At event start (H-day)
            'method' => 'realtime',
            'type' => 'event_start'
        ],
        [
            'minutes_before' => 0, // Event start notification
            'method' => 'browser',
            'type' => 'event_starting'
        ]
    ];

    /**
     * Create automatic reminders for an event
     */
    public function createAutomaticReminders(Event $event): void
    {
        Log::info("Creating automatic reminders for event {$event->id}");

        foreach (self::DEFAULT_REMINDERS as $reminderConfig) {
            $reminder = $event->reminders()->create([
                'method' => $reminderConfig['method'],
                'minutes_before' => $reminderConfig['minutes_before'],
                'reminder_type' => $reminderConfig['type'],
                'is_automatic' => true,
            ]);

            Log::info("Created automatic reminder {$reminder->id} for event {$event->id} - Type: {$reminderConfig['type']}");
        }

        // Schedule all reminders
        $reminderSchedulingService = app(ReminderSchedulingService::class);
        $reminderSchedulingService->scheduleEventReminders($event);
    }

    /**
     * Send real-time reminder notification
     */
    public function sendRealTimeReminder(Reminder $reminder, Event $event): void
    {
        try {
            Log::info("Sending real-time reminder {$reminder->id} for event {$event->id}");

            // Get all participants for this event
            $participants = $event->participants;
            
            // Also notify the event owner
            $eventOwner = $event->calendar->owner;
            
            // Combine participants and owner
            $notifiableUsers = collect();
            
            if ($eventOwner) {
                $notifiableUsers->push($eventOwner);
            }

            // Add participants who are also users in the system
            foreach ($participants as $participant) {
                $user = User::where('email', $participant->email)->first();
                if ($user && !$notifiableUsers->contains('id', $user->id)) {
                    $notifiableUsers->push($user);
                }
            }

            // Send notifications to all users
            foreach ($notifiableUsers as $user) {
                if ($this->shouldSendNotification($user, $reminder)) {
                    // Send database notification
                    $user->notify(new RealTimeReminderNotification($event, $reminder));
                    
                    // Broadcast real-time notification
                    broadcast(new ReminderTriggered($event, $reminder, $user));
                    
                    Log::info("Sent real-time reminder to user {$user->id} for event {$event->id}");
                }
            }

            // Mark reminder as sent
            $reminder->update(['sent_at' => now()]);

        } catch (\Exception $e) {
            Log::error("Failed to send real-time reminder {$reminder->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if notification should be sent based on user preferences
     */
    private function shouldSendNotification(User $user, Reminder $reminder): bool
    {
        // Check if user has real-time notifications enabled
        if (!$user->realtime_notifications_enabled) {
            return false;
        }

        // Check specific notification preferences
        $preferences = $user->notification_preferences ?? [];
        
        // If no preferences set, default to enabled
        if (empty($preferences)) {
            return true;
        }

        // Check method-specific preferences
        switch ($reminder->method) {
            case 'realtime':
                return $preferences['realtime'] ?? true;
            case 'browser':
                return $user->browser_notifications_enabled && ($preferences['browser'] ?? true);
            case 'email':
                return $user->email_notifications_enabled && ($preferences['email'] ?? true);
            default:
                return true;
        }
    }

    /**
     * Get reminder message based on type and timing
     */
    public function getReminderMessage(Event $event, Reminder $reminder): array
    {
        $eventTitle = $event->title;
        $eventStart = $event->start_at->setTimezone($event->timezone ?? 'UTC');
        
        switch ($reminder->reminder_type ?? 'default') {
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
                $timeUntilEvent = $reminder->minutes_before;
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
     * Process pending real-time reminders
     */
    public function processPendingRealTimeReminders(): void
    {
        $now = Carbon::now();
        $fiveMinutesFromNow = $now->copy()->addMinutes(5);

        // Find reminders that should be sent in the next 5 minutes
        $reminders = Reminder::whereNull('sent_at')
            ->whereIn('method', ['realtime', 'browser'])
            ->whereHas('event', function ($query) use ($now, $fiveMinutesFromNow) {
                $query->whereRaw('DATE_SUB(start_at, INTERVAL reminders.minutes_before MINUTE) BETWEEN ? AND ?', 
                               [$now, $fiveMinutesFromNow]);
            })
            ->with('event')
            ->get();

        foreach ($reminders as $reminder) {
            $reminderTime = $reminder->event->start_at->subMinutes($reminder->minutes_before);
            
            // Check if it's time to send this reminder
            if ($now->diffInMinutes($reminderTime, false) <= 5) {
                $this->sendRealTimeReminder($reminder, $reminder->event);
            }
        }

        Log::info("Processed {$reminders->count()} pending real-time reminders");
    }

    /**
     * Get user's unread notifications count
     */
    public function getUnreadNotificationsCount(User $user): int
    {
        return $user->unreadNotifications()
            ->where('type', RealTimeReminderNotification::class)
            ->count();
    }

    /**
     * Mark notifications as read
     */
    public function markNotificationsAsRead(User $user, array $notificationIds = []): void
    {
        $query = $user->unreadNotifications()
            ->where('type', RealTimeReminderNotification::class);
            
        if (!empty($notificationIds)) {
            $query->whereIn('id', $notificationIds);
        }
        
        $query->update(['read_at' => now()]);
    }

    /**
     * Get user's recent notifications
     */
    public function getRecentNotifications(User $user, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return $user->notifications()
            ->where('type', RealTimeReminderNotification::class)
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Clean up old notifications
     */
    public function cleanupOldNotifications(int $daysOld = 30): int
    {
        $cutoffDate = Carbon::now()->subDays($daysOld);
        
        $deletedCount = DB::table('notifications')
            ->where('type', RealTimeReminderNotification::class)
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        Log::info("Cleaned up {$deletedCount} old notification records");
        
        return $deletedCount;
    }
}
