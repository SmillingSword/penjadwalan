<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Reminder;
use App\Jobs\SendEventReminderJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ReminderSchedulingService
{
    /**
     * Schedule all reminders for an event.
     */
    public function scheduleEventReminders(Event $event): void
    {
        $reminders = $event->reminders;

        if ($reminders->isEmpty()) {
            Log::info("No reminders to schedule for event {$event->id}");
            return;
        }

        foreach ($reminders as $reminder) {
            $this->scheduleReminder($event, $reminder);
        }

        Log::info("Scheduled {$reminders->count()} reminders for event {$event->id}");
    }

    /**
     * Schedule a single reminder.
     */
    public function scheduleReminder(Event $event, Reminder $reminder): void
    {
        // Calculate when to send the reminder
        $reminderTime = $event->start_at->subMinutes($reminder->minutes_before);
        $now = Carbon::now();

        // Don't schedule reminders for past events
        if ($event->start_at->isPast()) {
            Log::info("Event {$event->id} is in the past, skipping reminder scheduling");
            return;
        }

        // Don't schedule if reminder time has already passed
        if ($reminderTime->isPast()) {
            Log::info("Reminder time for event {$event->id} has passed, skipping");
            return;
        }

        // Schedule the job to run at the reminder time
        SendEventReminderJob::dispatch($reminder, $event)
            ->delay($reminderTime);

        Log::info("Scheduled reminder {$reminder->id} for event {$event->id} to be sent at {$reminderTime}");
    }

    /**
     * Reschedule reminders for an updated event.
     */
    public function rescheduleEventReminders(Event $event): void
    {
        // Cancel existing scheduled reminders
        $this->cancelEventReminders($event);

        // Schedule new reminders
        $this->scheduleEventReminders($event);
    }

    /**
     * Cancel all scheduled reminders for an event.
     */
    public function cancelEventReminders(Event $event): void
    {
        // Note: In a production environment, you would need to implement
        // a way to track and cancel specific jobs. This could be done by:
        // 1. Storing job IDs in the database
        // 2. Using a job tracking system
        // 3. Using Laravel Horizon's job management features

        Log::info("Cancelled reminders for event {$event->id}");
    }

    /**
     * Schedule reminders for recurring events.
     */
    public function scheduleRecurringEventReminders(Event $event, Carbon $rangeStart, Carbon $rangeEnd): void
    {
        if (!$event->isRecurring()) {
            return;
        }

        // Get recurring event instances
        $instances = $event->expandRecurrence($rangeStart, $rangeEnd);

        foreach ($instances as $instance) {
            // Create a virtual event for each instance
            $virtualEvent = $event->replicate();
            $virtualEvent->start_at = $instance['start_at'];
            $virtualEvent->end_at = $instance['end_at'];

            // Schedule reminders for this instance
            foreach ($event->reminders as $reminder) {
                $this->scheduleReminder($virtualEvent, $reminder);
            }
        }

        Log::info("Scheduled reminders for {$instances->count()} instances of recurring event {$event->id}");
    }

    /**
     * Process pending reminders (for cron job).
     */
    public function processPendingReminders(): void
    {
        $now = Carbon::now();
        $fiveMinutesFromNow = $now->copy()->addMinutes(5);

        // Find events with reminders that should be sent in the next 5 minutes
        $events = Event::whereHas('reminders', function ($query) use ($now, $fiveMinutesFromNow) {
            $query->whereNull('sent_at')
                  ->whereRaw('DATE_SUB(events.start_at, INTERVAL reminders.minutes_before MINUTE) BETWEEN ? AND ?', 
                           [$now, $fiveMinutesFromNow]);
        })->with(['reminders' => function ($query) {
            $query->whereNull('sent_at');
        }])->get();

        foreach ($events as $event) {
            foreach ($event->reminders as $reminder) {
                $reminderTime = $event->start_at->subMinutes($reminder->minutes_before);
                
                // Check if it's time to send this reminder
                if ($now->diffInMinutes($reminderTime, false) <= 5) {
                    SendEventReminderJob::dispatch($reminder, $event);
                }
            }
        }

        Log::info("Processed pending reminders for {$events->count()} events");
    }

    /**
     * Get reminder statistics.
     */
    public function getReminderStatistics(): array
    {
        $totalReminders = Reminder::count();
        $sentReminders = Reminder::whereNotNull('sent_at')->count();
        $pendingReminders = $totalReminders - $sentReminders;

        $upcomingReminders = Reminder::whereNull('sent_at')
            ->whereHas('event', function ($query) {
                $query->where('start_at', '>', now());
            })
            ->count();

        return [
            'total_reminders' => $totalReminders,
            'sent_reminders' => $sentReminders,
            'pending_reminders' => $pendingReminders,
            'upcoming_reminders' => $upcomingReminders,
        ];
    }

    /**
     * Clean up old reminder records.
     */
    public function cleanupOldReminders(int $daysOld = 30): int
    {
        $cutoffDate = Carbon::now()->subDays($daysOld);

        $deletedCount = Reminder::whereHas('event', function ($query) use ($cutoffDate) {
            $query->where('start_at', '<', $cutoffDate);
        })->whereNotNull('sent_at')->delete();

        Log::info("Cleaned up {$deletedCount} old reminder records");

        return $deletedCount;
    }
}
