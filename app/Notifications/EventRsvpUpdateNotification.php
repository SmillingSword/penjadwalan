<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\EventParticipant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventRsvpUpdateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $data;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
        $this->onQueue('notifications');
    }

    /**
     * Get the notification's delivery channels.
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
        $event = $this->data['event'];
        $participant = $this->data['participant'];
        $oldStatus = $this->data['old_status'];
        $newStatus = $this->data['new_status'];

        $statusEmojis = [
            'accepted' => '✅',
            'declined' => '❌',
            'tentative' => '❓',
            'invited' => '📧',
        ];

        $statusLabels = [
            'accepted' => 'Accepted',
            'declined' => 'Declined',
            'tentative' => 'Tentative',
            'invited' => 'Invited',
        ];

        $participantName = $participant->name ?: $participant->email;
        $oldStatusLabel = $statusLabels[$oldStatus] ?? ucfirst($oldStatus);
        $newStatusLabel = $statusLabels[$newStatus] ?? ucfirst($newStatus);
        $newStatusEmoji = $statusEmojis[$newStatus] ?? '';

        $startTime = $event->start_at->setTimezone($event->timezone ?? 'UTC');

        $mailMessage = (new MailMessage)
            ->subject("RSVP Update: {$event->title}")
            ->greeting("Hello,")
            ->line("**{$participantName}** has updated their RSVP for your event:")
            ->line("**{$event->title}**");

        // Add event details
        if ($event->all_day) {
            $mailMessage->line("📅 **Date:** " . $startTime->format('l, F j, Y'));
        } else {
            $mailMessage->line("📅 **Date & Time:** " . $startTime->format('l, F j, Y \a\t g:i A T'));
        }

        // Show status change
        $mailMessage->line("**RSVP Status Changed:**")
                    ->line("From: {$oldStatusLabel} → To: {$newStatusEmoji} **{$newStatusLabel}**");

        // Add participant note if provided
        if (!empty($participant->rsvp_note)) {
            $mailMessage->line("**Participant's Note:**")
                        ->line($participant->rsvp_note);
        }

        // Add event management link
        $eventUrl = url("/events/{$event->id}");
        $mailMessage->action('View Event Details', $eventUrl)
                    ->line("You can view all participant responses and manage your event from the link above.");

        return $mailMessage;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_id' => $this->data['event']->id,
            'event_title' => $this->data['event']->title,
            'participant_email' => $this->data['participant']->email,
            'participant_name' => $this->data['participant']->name,
            'old_status' => $this->data['old_status'],
            'new_status' => $this->data['new_status'],
            'type' => 'rsvp_update',
        ];
    }
}
