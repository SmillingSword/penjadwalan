<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\Reminder;
use App\Models\EventParticipant;
use App\Notifications\EventReminderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class SendEventReminderJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Reminder $reminder,
        public Event $event
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Check if the event still exists and hasn't been cancelled
            if (!$this->event->exists) {
                Log::info("Event {$this->event->id} no longer exists, skipping reminder");
                return;
            }

            // Check if the reminder is still valid
            if (!$this->reminder->exists) {
                Log::info("Reminder {$this->reminder->id} no longer exists, skipping");
                return;
            }

            // Calculate reminder time
            $reminderTime = $this->event->start_at->subMinutes($this->reminder->minutes_before);
            $now = Carbon::now();

            // Check if it's time to send the reminder (within 5 minutes tolerance)
            if ($now->diffInMinutes($reminderTime, false) > 5) {
                Log::info("Too early to send reminder for event {$this->event->id}");
                return;
            }

            // Send reminder based on method
            switch ($this->reminder->method) {
                case 'email':
                    $this->sendEmailReminder();
                    break;
                case 'push':
                    $this->sendPushReminder();
                    break;
                case 'sms':
                    $this->sendSmsReminder();
                    break;
                default:
                    Log::warning("Unknown reminder method: {$this->reminder->method}");
            }

            // Mark reminder as sent
            $this->reminder->update(['sent_at' => now()]);

            Log::info("Reminder sent successfully for event {$this->event->id} via {$this->reminder->method}");

        } catch (\Exception $e) {
            Log::error("Failed to send reminder for event {$this->event->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send email reminder to all participants.
     */
    private function sendEmailReminder(): void
    {
        $participants = $this->event->participants;
        
        if ($participants->isEmpty()) {
            Log::info("No participants found for event {$this->event->id}");
            return;
        }

        foreach ($participants as $participant) {
            try {
                Notification::route('mail', $participant->email)
                    ->notify(new EventReminderNotification($this->event, $this->reminder, $participant));
                
                Log::info("Email reminder sent to {$participant->email} for event {$this->event->id}");
            } catch (\Exception $e) {
                Log::error("Failed to send email reminder to {$participant->email}: " . $e->getMessage());
            }
        }
    }

    /**
     * Send push notification reminder.
     */
    private function sendPushReminder(): void
    {
        // This would integrate with a push notification service like FCM, Pusher, etc.
        // For now, we'll log it as a placeholder
        Log::info("Push reminder would be sent for event {$this->event->id}");
        
        // Example implementation with broadcasting:
        // broadcast(new EventReminderBroadcast($this->event, $this->reminder));
    }

    /**
     * Send SMS reminder.
     */
    private function sendSmsReminder(): void
    {
        // This would integrate with an SMS service like Twilio, Nexmo, etc.
        // For now, we'll log it as a placeholder
        Log::info("SMS reminder would be sent for event {$this->event->id}");
        
        // Example implementation:
        // $smsService = app(SmsService::class);
        // foreach ($this->event->participants as $participant) {
        //     if ($participant->phone) {
        //         $smsService->send($participant->phone, $this->getReminderMessage());
        //     }
        // }
    }

    /**
     * Get the reminder message.
     */
    private function getReminderMessage(): string
    {
        $timeUntilEvent = $this->reminder->minutes_before;
        $timeUnit = $timeUntilEvent >= 60 ? 'hour(s)' : 'minute(s)';
        $timeValue = $timeUntilEvent >= 60 ? round($timeUntilEvent / 60, 1) : $timeUntilEvent;

        return "Reminder: '{$this->event->title}' starts in {$timeValue} {$timeUnit} at {$this->event->formatted_start}.";
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SendEventReminderJob failed for event {$this->event->id}: " . $exception->getMessage());
        
        // Optionally, you could send a notification to administrators
        // or mark the reminder as failed in the database
    }
}
