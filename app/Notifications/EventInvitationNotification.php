<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\EventParticipant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Carbon\Carbon;

class EventInvitationNotification extends Notification implements ShouldQueue
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
        $rsvpLinks = $this->data['rsvp_links'];
        $organizerName = $this->data['organizer_name'];
        $customMessage = $this->data['custom_message'];

        $startTime = $event->start_at->setTimezone($event->timezone ?? 'UTC');
        $endTime = $event->end_at ? $event->end_at->setTimezone($event->timezone ?? 'UTC') : null;

        $mailMessage = (new MailMessage)
            ->subject("Invitation: {$event->title}")
            ->greeting("Hello {$participant->name},")
            ->line("You have been invited to the following event:")
            ->line("**{$event->title}**");

        // Add event details
        if ($event->all_day) {
            $mailMessage->line("📅 **Date:** " . $startTime->format('l, F j, Y'));
            if ($endTime && !$startTime->isSameDay($endTime)) {
                $mailMessage->line("📅 **End Date:** " . $endTime->format('l, F j, Y'));
            }
        } else {
            $mailMessage->line("📅 **Date & Time:** " . $startTime->format('l, F j, Y \a\t g:i A T'));
            if ($endTime) {
                if ($startTime->isSameDay($endTime)) {
                    $mailMessage->line("⏰ **Duration:** " . $startTime->format('g:i A') . ' - ' . $endTime->format('g:i A T'));
                } else {
                    $mailMessage->line("📅 **End:** " . $endTime->format('l, F j, Y \a\t g:i A T'));
                }
            }
        }

        if ($event->location) {
            $mailMessage->line("📍 **Location:** {$event->location}");
        }

        if ($event->meeting_link) {
            $mailMessage->action('Join Meeting', $event->meeting_link);
        }

        if ($event->description_md) {
            $mailMessage->line("**Description:**")
                        ->line(strip_tags($event->description_md));
        }

        if ($customMessage) {
            $mailMessage->line("**Message from organizer:**")
                        ->line($customMessage);
        }

        // Add RSVP buttons
        $mailMessage->line("Please respond to this invitation:")
                    ->action('✅ Accept', $rsvpLinks['accept'])
                    ->line("Or choose another option:")
                    ->line("[Decline]({$rsvpLinks['decline']}) | [Maybe]({$rsvpLinks['tentative']}) | [View Details]({$rsvpLinks['details']})")
                    ->line("Organized by: {$organizerName}")
                    ->line("If you have any questions, please contact the organizer.");

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
            'organizer_name' => $this->data['organizer_name'],
            'type' => 'event_invitation',
        ];
    }
}
