<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Notifications\EventInvitationNotification;
use App\Services\InvitationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendEventInvitationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Event $event;
    protected EventParticipant $participant;
    protected array $options;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(Event $event, EventParticipant $participant, array $options = [])
    {
        $this->event = $event;
        $this->participant = $participant;
        $this->options = $options;
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(InvitationService $invitationService): void
    {
        try {
            Log::info('Sending event invitation', [
                'event_id' => $this->event->id,
                'participant_email' => $this->participant->email,
                'job_id' => $this->job->getJobId(),
            ]);

            // Generate RSVP links
            $rsvpLinks = $invitationService->generateRsvpLinks($this->event, $this->participant);
            
            // Prepare notification data
            $notificationData = [
                'event' => $this->event->load(['calendar.owner']),
                'participant' => $this->participant,
                'rsvp_links' => $rsvpLinks,
                'custom_message' => $this->options['custom_message'] ?? null,
                'organizer_name' => $this->options['organizer_name'] ?? $this->event->calendar->owner->name ?? 'Event Organizer',
                'organizer_email' => $this->options['organizer_email'] ?? $this->event->calendar->owner->email ?? 'noreply@example.com',
            ];

            // Create and send notification
            $notification = new EventInvitationNotification($notificationData);
            
            // Send to participant email
            Notification::route('mail', $this->participant->email)->notify($notification);

            Log::info('Event invitation sent successfully', [
                'event_id' => $this->event->id,
                'participant_email' => $this->participant->email,
                'job_id' => $this->job->getJobId(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send event invitation', [
                'event_id' => $this->event->id,
                'participant_email' => $this->participant->email,
                'error' => $e->getMessage(),
                'job_id' => $this->job->getJobId(),
            ]);

            // Re-throw the exception to trigger job retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Event invitation job failed permanently', [
            'event_id' => $this->event->id,
            'participant_email' => $this->participant->email,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Could send notification to admin about failed invitation
        // or mark participant as invitation failed
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'event:' . $this->event->id,
            'participant:' . $this->participant->id,
            'invitation',
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [30, 60, 120]; // Wait 30s, then 1m, then 2m between retries
    }
}
