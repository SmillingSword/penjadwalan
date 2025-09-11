<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\Reminder;
use App\Models\EventParticipant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Carbon\Carbon;

class EventReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Event $event,
        public Reminder $reminder,
        public EventParticipant $participant
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
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $timeUntilEvent = $this->reminder->minutes_before;
        $timeUnit = $timeUntilEvent >= 60 ? 'hour(s)' : 'minute(s)';
        $timeValue = $timeUntilEvent >= 60 ? round($timeUntilEvent / 60, 1) : $timeUntilEvent;

        $eventStart = $this->event->start_at->setTimezone($this->event->timezone ?? 'UTC');
        $eventEnd = $this->event->end_at?->setTimezone($this->event->timezone ?? 'UTC');

        $mailMessage = (new MailMessage)
            ->subject("Reminder: {$this->event->title}")
            ->greeting("Hello {$this->participant->name}!")
            ->line("This is a reminder that you have an upcoming event:")
            ->line("**{$this->event->title}**")
            ->line("Starting in {$timeValue} {$timeUnit}")
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
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_id' => $this->event->id,
            'event_title' => $this->event->title,
            'reminder_id' => $this->reminder->id,
            'minutes_before' => $this->reminder->minutes_before,
            'participant_email' => $this->participant->email,
            'participant_name' => $this->participant->name,
            'event_start' => $this->event->start_at->toISOString(),
            'event_location' => $this->event->location,
            'meeting_link' => $this->event->meeting_link,
        ];
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

        // Don't send if the participant has declined
        if ($this->participant->status === 'declined') {
            return false;
        }

        // Don't send if the event has already started
        if ($this->event->start_at->isPast()) {
            return false;
        }

        return true;
    }
}
