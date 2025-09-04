<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\User;
use App\Notifications\EventInvitationNotification;
use App\Notifications\EventRsvpUpdateNotification;
use App\Jobs\SendEventInvitationJob;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InvitationService
{
    /**
     * Send invitations for an event to all participants.
     */
    public function sendEventInvitations(Event $event, array $options = []): array
    {
        $options = array_merge([
            'send_immediately' => false,
            'include_ics_attachment' => true,
            'custom_message' => null,
            'organizer_name' => null,
            'organizer_email' => null,
        ], $options);

        $results = [
            'total_participants' => 0,
            'invitations_sent' => 0,
            'invitations_queued' => 0,
            'failed_invitations' => 0,
            'errors' => [],
        ];

        $participants = $event->participants()->where('status', 'invited')->get();
        $results['total_participants'] = $participants->count();

        foreach ($participants as $participant) {
            try {
                if ($options['send_immediately']) {
                    $this->sendInvitationImmediately($event, $participant, $options);
                    $results['invitations_sent']++;
                } else {
                    $this->queueInvitation($event, $participant, $options);
                    $results['invitations_queued']++;
                }
            } catch (\Exception $e) {
                $results['failed_invitations']++;
                $results['errors'][] = "Failed to send invitation to {$participant->email}: " . $e->getMessage();
                
                Log::error('Invitation sending failed', [
                    'event_id' => $event->id,
                    'participant_email' => $participant->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Send invitation to a specific participant.
     */
    public function sendInvitationToParticipant(Event $event, EventParticipant $participant, array $options = []): bool
    {
        try {
            $options = array_merge([
                'send_immediately' => false,
                'include_ics_attachment' => true,
                'custom_message' => null,
            ], $options);

            if ($options['send_immediately']) {
                $this->sendInvitationImmediately($event, $participant, $options);
            } else {
                $this->queueInvitation($event, $participant, $options);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send invitation', [
                'event_id' => $event->id,
                'participant_id' => $participant->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle RSVP response from a participant.
     */
    public function handleRsvpResponse(Event $event, string $participantEmail, string $response, array $options = []): array
    {
        $validResponses = ['accepted', 'declined', 'tentative'];
        
        if (!in_array($response, $validResponses)) {
            throw new \InvalidArgumentException('Invalid RSVP response. Must be one of: ' . implode(', ', $validResponses));
        }

        $participant = $event->participants()->where('email', $participantEmail)->first();
        
        if (!$participant) {
            throw new \Exception('Participant not found for this event.');
        }

        $oldStatus = $participant->status;
        
        DB::beginTransaction();
        try {
            // Update participant status
            $participant->update([
                'status' => $response,
                'rsvp_at' => now(),
                'rsvp_note' => $options['note'] ?? null,
            ]);

            // Log the RSVP change
            $this->logRsvpChange($event, $participant, $oldStatus, $response);

            // Notify organizer if status changed
            if ($oldStatus !== $response) {
                $this->notifyOrganizerOfRsvpChange($event, $participant, $oldStatus, $response);
            }

            DB::commit();

            return [
                'success' => true,
                'participant' => $participant->fresh(),
                'old_status' => $oldStatus,
                'new_status' => $response,
                'event' => $event->load(['participants', 'calendar']),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Add external participants to an event.
     */
    public function addExternalParticipants(Event $event, array $participants, array $options = []): array
    {
        $options = array_merge([
            'send_invitations' => true,
            'default_role' => 'required',
            'allow_duplicates' => false,
        ], $options);

        $results = [
            'added_participants' => 0,
            'skipped_participants' => 0,
            'failed_participants' => 0,
            'participant_ids' => [],
            'errors' => [],
        ];

        DB::beginTransaction();
        try {
            foreach ($participants as $participantData) {
                $email = $participantData['email'] ?? null;
                $name = $participantData['name'] ?? null;
                $role = $participantData['role'] ?? $options['default_role'];

                if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $results['failed_participants']++;
                    $results['errors'][] = "Invalid email address: " . ($email ?? 'empty');
                    continue;
                }

                // Check for duplicates
                if (!$options['allow_duplicates']) {
                    $existingParticipant = $event->participants()->where('email', $email)->first();
                    if ($existingParticipant) {
                        $results['skipped_participants']++;
                        continue;
                    }
                }

                // Create participant
                $participant = $event->participants()->create([
                    'email' => $email,
                    'name' => $name,
                    'role' => $role,
                    'status' => 'invited',
                ]);

                $results['added_participants']++;
                $results['participant_ids'][] = $participant->id;

                // Send invitation if requested
                if ($options['send_invitations']) {
                    $this->queueInvitation($event, $participant, $options);
                }
            }

            DB::commit();
            return $results;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Remove participants from an event.
     */
    public function removeParticipants(Event $event, array $participantEmails, array $options = []): array
    {
        $options = array_merge([
            'send_cancellation_notice' => true,
            'cancellation_message' => null,
        ], $options);

        $results = [
            'removed_participants' => 0,
            'not_found_participants' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();
        try {
            foreach ($participantEmails as $email) {
                $participant = $event->participants()->where('email', $email)->first();
                
                if (!$participant) {
                    $results['not_found_participants']++;
                    continue;
                }

                // Send cancellation notice if requested
                if ($options['send_cancellation_notice']) {
                    $this->sendCancellationNotice($event, $participant, $options);
                }

                // Remove participant
                $participant->delete();
                $results['removed_participants']++;
            }

            DB::commit();
            return $results;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get RSVP statistics for an event.
     */
    public function getRsvpStatistics(Event $event): array
    {
        $participants = $event->participants;
        
        $stats = [
            'total_participants' => $participants->count(),
            'by_status' => [
                'invited' => $participants->where('status', 'invited')->count(),
                'accepted' => $participants->where('status', 'accepted')->count(),
                'declined' => $participants->where('status', 'declined')->count(),
                'tentative' => $participants->where('status', 'tentative')->count(),
            ],
            'by_role' => [
                'required' => $participants->where('role', 'required')->count(),
                'optional' => $participants->where('role', 'optional')->count(),
                'resource' => $participants->where('role', 'resource')->count(),
            ],
            'response_rate' => 0,
            'attendance_rate' => 0,
        ];

        $totalResponses = $stats['by_status']['accepted'] + $stats['by_status']['declined'] + $stats['by_status']['tentative'];
        
        if ($stats['total_participants'] > 0) {
            $stats['response_rate'] = round(($totalResponses / $stats['total_participants']) * 100, 1);
            $stats['attendance_rate'] = round((($stats['by_status']['accepted'] + $stats['by_status']['tentative']) / $stats['total_participants']) * 100, 1);
        }

        return $stats;
    }

    /**
     * Generate RSVP links for participants.
     */
    public function generateRsvpLinks(Event $event, EventParticipant $participant): array
    {
        $baseUrl = config('app.url');
        $token = $this->generateRsvpToken($event, $participant);

        return [
            'accept' => "{$baseUrl}/rsvp/{$event->id}/{$participant->id}/accept?token={$token}",
            'decline' => "{$baseUrl}/rsvp/{$event->id}/{$participant->id}/decline?token={$token}",
            'tentative' => "{$baseUrl}/rsvp/{$event->id}/{$participant->id}/tentative?token={$token}",
            'details' => "{$baseUrl}/rsvp/{$event->id}/{$participant->id}?token={$token}",
        ];
    }

    /**
     * Validate RSVP token.
     */
    public function validateRsvpToken(Event $event, EventParticipant $participant, string $token): bool
    {
        $expectedToken = $this->generateRsvpToken($event, $participant);
        return hash_equals($expectedToken, $token);
    }

    /**
     * Send invitation immediately.
     */
    private function sendInvitationImmediately(Event $event, EventParticipant $participant, array $options): void
    {
        // Generate RSVP links
        $rsvpLinks = $this->generateRsvpLinks($event, $participant);
        
        // Create notification data
        $notificationData = [
            'event' => $event,
            'participant' => $participant,
            'rsvp_links' => $rsvpLinks,
            'custom_message' => $options['custom_message'] ?? null,
            'organizer_name' => $options['organizer_name'] ?? $event->calendar->owner->name ?? 'Event Organizer',
            'organizer_email' => $options['organizer_email'] ?? $event->calendar->owner->email ?? 'noreply@example.com',
        ];

        // Send notification
        $notification = new EventInvitationNotification($notificationData);
        
        // Send to participant email
        \Notification::route('mail', $participant->email)->notify($notification);
    }

    /**
     * Queue invitation for later sending.
     */
    private function queueInvitation(Event $event, EventParticipant $participant, array $options): void
    {
        SendEventInvitationJob::dispatch($event, $participant, $options)
            ->delay(now()->addMinutes(1)); // Small delay to batch invitations
    }

    /**
     * Send cancellation notice to participant.
     */
    private function sendCancellationNotice(Event $event, EventParticipant $participant, array $options): void
    {
        // Implementation would send cancellation email
        Log::info('Cancellation notice sent', [
            'event_id' => $event->id,
            'participant_email' => $participant->email,
        ]);
    }

    /**
     * Log RSVP status change.
     */
    private function logRsvpChange(Event $event, EventParticipant $participant, string $oldStatus, string $newStatus): void
    {
        Log::info('RSVP status changed', [
            'event_id' => $event->id,
            'participant_email' => $participant->email,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'timestamp' => now(),
        ]);
    }

    /**
     * Notify organizer of RSVP change.
     */
    private function notifyOrganizerOfRsvpChange(Event $event, EventParticipant $participant, string $oldStatus, string $newStatus): void
    {
        $organizer = $event->calendar->owner;
        
        if ($organizer && $organizer->email) {
            $notification = new EventRsvpUpdateNotification([
                'event' => $event,
                'participant' => $participant,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);
            
            $organizer->notify($notification);
        }
    }

    /**
     * Generate secure RSVP token.
     */
    private function generateRsvpToken(Event $event, EventParticipant $participant): string
    {
        $data = [
            'event_id' => $event->id,
            'participant_id' => $participant->id,
            'participant_email' => $participant->email,
            'created_at' => $participant->created_at->timestamp,
            'secret' => config('app.key'),
        ];
        
        return hash('sha256', implode('|', $data));
    }

    /**
     * Get participant availability for meeting times.
     */
    public function getParticipantAvailability(Collection $participants, Carbon $startDate, Carbon $endDate): array
    {
        $freeBusyService = app(FreeBusyService::class);
        $availability = [];

        foreach ($participants as $participant) {
            // Try to find user by email
            $user = User::where('email', $participant->email)->first();
            
            if ($user) {
                $userAvailability = $freeBusyService->getFreeBusyForUser($user, $startDate, $endDate);
                $availability[$participant->email] = $userAvailability;
            } else {
                // External participant - assume available
                $availability[$participant->email] = [
                    'user_id' => null,
                    'email' => $participant->email,
                    'name' => $participant->name,
                    'is_external' => true,
                    'availability_unknown' => true,
                ];
            }
        }

        return $availability;
    }

    /**
     * Create meeting poll for participants.
     */
    public function createMeetingPoll(Event $event, array $proposedTimes, array $options = []): array
    {
        $options = array_merge([
            'poll_deadline' => now()->addDays(3),
            'allow_new_suggestions' => false,
            'require_all_participants' => false,
        ], $options);

        // This would create a meeting poll system
        // For now, return a basic structure
        return [
            'poll_id' => Str::uuid(),
            'event_id' => $event->id,
            'proposed_times' => $proposedTimes,
            'participants' => $event->participants->pluck('email'),
            'deadline' => $options['poll_deadline'],
            'status' => 'active',
            'responses' => [],
        ];
    }
}
