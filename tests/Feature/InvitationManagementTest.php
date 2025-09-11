<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Organization;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Services\InvitationService;
use App\Jobs\SendEventInvitationJob;
use App\Notifications\EventInvitationNotification;
use App\Notifications\EventRsvpUpdateNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvitationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $organization;
    protected $calendar;
    protected $event;
    protected $invitationService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run the role seeder
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

        // Create test user, organization, and calendar
        $this->organization = Organization::create(['name' => 'Test Organization']);
        $this->user = User::factory()->create();
        $this->user->organizations()->attach($this->organization->id, ['role' => 'Owner']);
        $this->user->assignRole('Owner');

        $this->calendar = Calendar::create([
            'organization_id' => $this->organization->id,
            'owner_user_id' => $this->user->id,
            'name' => 'Test Calendar',
            'color' => '#FF0000',
        ]);

        $this->event = Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Test Meeting',
            'description_md' => 'This is a test meeting',
            'location' => 'Conference Room A',
            'start_at' => Carbon::now()->addDays(1)->setHour(10),
            'end_at' => Carbon::now()->addDays(1)->setHour(11),
        ]);

        // Initialize service
        $this->invitationService = app(InvitationService::class);

        // Authenticate user
        Sanctum::actingAs($this->user);
    }

    public function test_can_send_event_invitations_immediately()
    {
        Notification::fake();

        // Add participants to the event
        $participant1 = EventParticipant::create([
            'event_id' => $this->event->id,
            'email' => 'participant1@example.com',
            'name' => 'Participant One',
            'role' => 'required',
            'status' => 'invited',
        ]);

        $participant2 = EventParticipant::create([
            'event_id' => $this->event->id,
            'email' => 'participant2@example.com',
            'name' => 'Participant Two',
            'role' => 'optional',
            'status' => 'invited',
        ]);

        $results = $this->invitationService->sendEventInvitations($this->event, [
            'send_immediately' => true,
            'include_ics_attachment' => true,
        ]);

        $this->assertEquals(2, $results['total_participants']);
        $this->assertEquals(2, $results['invitations_sent']);
        $this->assertEquals(0, $results['invitations_queued']);
        $this->assertEquals(0, $results['failed_invitations']);

        // Verify notifications were sent
        Notification::assertSentTimes(EventInvitationNotification::class, 2);
    }

    public function test_can_queue_event_invitations()
    {
        Queue::fake();

        // Add participants to the event
        EventParticipant::create([
            'event_id' => $this->event->id,
            'email' => 'participant1@example.com',
            'name' => 'Participant One',
            'role' => 'required',
            'status' => 'invited',
        ]);

        $results = $this->invitationService->sendEventInvitations($this->event, [
            'send_immediately' => false,
        ]);

        $this->assertEquals(1, $results['total_participants']);
        $this->assertEquals(0, $results['invitations_sent']);
        $this->assertEquals(1, $results['invitations_queued']);

        // Verify job was queued
        Queue::assertPushed(SendEventInvitationJob::class);
    }

    public function test_can_handle_rsvp_response()
    {
        Notification::fake();

        $participant = EventParticipant::create([
            'event_id' => $this->event->id,
            'email' => 'participant@example.com',
            'name' => 'Test Participant',
            'role' => 'required',
            'status' => 'invited',
        ]);

        $result = $this->invitationService->handleRsvpResponse(
            $this->event,
            'participant@example.com',
            'accepted',
            ['note' => 'Looking forward to the meeting']
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('invited', $result['old_status']);
        $this->assertEquals('accepted', $result['new_status']);
        $this->assertEquals('accepted', $result['participant']['status']);
        $this->assertEquals('Looking forward to the meeting', $result['participant']['rsvp_note']);
        $this->assertNotNull($result['participant']['rsvp_at']);

        // Verify organizer was notified
        Notification::assertSentTo(
            $this->user,
            EventRsvpUpdateNotification::class
        );
    }

    public function test_rsvp_response_validates_status()
    {
        $participant = EventParticipant::create([
            'event_id' => $this->event->id,
            'email' => 'participant@example.com',
            'name' => 'Test Participant',
            'role' => 'required',
            'status' => 'invited',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid RSVP response');

        $this->invitationService->handleRsvpResponse(
            $this->event,
            'participant@example.com',
            'invalid_status'
        );
    }

    public function test_rsvp_response_requires_existing_participant()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Participant not found');

        $this->invitationService->handleRsvpResponse(
            $this->event,
            'nonexistent@example.com',
            'accepted'
        );
    }

    public function test_can_add_external_participants()
    {
        $participants = [
            [
                'email' => 'external1@company.com',
                'name' => 'External User 1',
                'role' => 'required',
            ],
            [
                'email' => 'external2@company.com',
                'name' => 'External User 2',
                'role' => 'optional',
            ],
        ];

        $results = $this->invitationService->addExternalParticipants($this->event, $participants, [
            'send_invitations' => false,
        ]);

        $this->assertEquals(2, $results['added_participants']);
        $this->assertEquals(0, $results['skipped_participants']);
        $this->assertEquals(0, $results['failed_participants']);
        $this->assertCount(2, $results['participant_ids']);

        // Verify participants were added to database
        $this->assertDatabaseHas('event_participants', [
            'event_id' => $this->event->id,
            'email' => 'external1@company.com',
            'name' => 'External User 1',
            'role' => 'required',
            'status' => 'invited',
        ]);

        $this->assertDatabaseHas('event_participants', [
            'event_id' => $this->event->id,
            'email' => 'external2@company.com',
            'name' => 'External User 2',
            'role' => 'optional',
            'status' => 'invited',
        ]);
    }

    public function test_add_external_participants_skips_duplicates()
    {
        // Add existing participant
        EventParticipant::create([
            'event_id' => $this->event->id,
            'email' => 'existing@example.com',
            'name' => 'Existing User',
            'role' => 'required',
            'status' => 'invited',
        ]);

        $participants = [
            [
                'email' => 'existing@example.com',
                'name' => 'Existing User',
                'role' => 'required',
            ],
            [
                'email' => 'new@example.com',
                'name' => 'New User',
                'role' => 'optional',
            ],
        ];

        $results = $this->invitationService->addExternalParticipants($this->event, $participants, [
            'allow_duplicates' => false,
        ]);

        $this->assertEquals(1, $results['added_participants']);
        $this->assertEquals(1, $results['skipped_participants']);
        $this->assertEquals(0, $results['failed_participants']);
    }

    public function test_add_external_participants_validates_email()
    {
        $participants = [
            [
                'email' => 'invalid-email',
                'name' => 'Invalid User',
                'role' => 'required',
            ],
            [
                'email' => 'valid@example.com',
                'name' => 'Valid User',
                'role' => 'required',
            ],
        ];

        $results = $this->invitationService->addExternalParticipants($this->event, $participants);

        $this->assertEquals(1, $results['added_participants']);
        $this->assertEquals(0, $results['skipped_participants']);
        $this->assertEquals(1, $results['failed_participants']);
        $this->assertNotEmpty($results['errors']);
    }

    public function test_can_remove_participants()
    {
        // Add participants
        EventParticipant::create([
            'event_id' => $this->event->id,
            'email' => 'remove1@example.com',
            'name' => 'Remove User 1',
            'role' => 'required',
            'status' => 'accepted',
        ]);

        EventParticipant::create([
            'event_id' => $this->event->id,
            'email' => 'remove2@example.com',
            'name' => 'Remove User 2',
            'role' => 'optional',
            'status' => 'invited',
        ]);

        $results = $this->invitationService->removeParticipants($this->event, [
            'remove1@example.com',
            'remove2@example.com',
            'nonexistent@example.com',
        ], [
            'send_cancellation_notice' => false,
        ]);

        $this->assertEquals(2, $results['removed_participants']);
        $this->assertEquals(1, $results['not_found_participants']);

        // Verify participants were removed
        $this->assertDatabaseMissing('event_participants', [
            'event_id' => $this->event->id,
            'email' => 'remove1@example.com',
        ]);

        $this->assertDatabaseMissing('event_participants', [
            'event_id' => $this->event->id,
            'email' => 'remove2@example.com',
        ]);
    }

    public function test_can_get_rsvp_statistics()
    {
        // Add participants with different statuses
        EventParticipant::create([
            'event_id' => $this->event->id,
            'email' => 'accepted@example.com',
            'role' => 'required',
            'status' => 'accepted',
        ]);

        EventParticipant::create([
            'event_id' => $this->event->id,
            'email' => 'declined@example.com',
            'role' => 'optional',
            'status' => 'declined',
        ]);

        EventParticipant::create([
            'event_id' => $this->event->id,
            'email' => 'tentative@example.com',
            'role' => 'required',
            'status' => 'tentative',
        ]);

        EventParticipant::create([
            'event_id' => $this->event->id,
            'email' => 'invited@example.com',
            'role' => 'optional',
            'status' => 'invited',
        ]);

        $stats = $this->invitationService->getRsvpStatistics($this->event);

        $this->assertEquals(4, $stats['total_participants']);
        $this->assertEquals(1, $stats['by_status']['accepted']);
        $this->assertEquals(1, $stats['by_status']['declined']);
        $this->assertEquals(1, $stats['by_status']['tentative']);
        $this->assertEquals(1, $stats['by_status']['invited']);
        $this->assertEquals(2, $stats['by_role']['required']);
        $this->assertEquals(2, $stats['by_role']['optional']);
        $this->assertEquals(75.0, $stats['response_rate']); // 3 responses out of 4
        $this->assertEquals(50.0, $stats['attendance_rate']); // 2 attending out of 4
    }

    public function test_can_generate_rsvp_links()
    {
        $participant = EventParticipant::create([
            'event_id' => $this->event->id,
            'email' => 'participant@example.com',
            'name' => 'Test Participant',
            'role' => 'required',
            'status' => 'invited',
        ]);

        $links = $this->invitationService->generateRsvpLinks($this->event, $participant);

        $this->assertArrayHasKey('accept', $links);
        $this->assertArrayHasKey('decline', $links);
        $this->assertArrayHasKey('tentative', $links);
        $this->assertArrayHasKey('details', $links);

        $this->assertStringContainsString('/rsvp/', $links['accept']);
        $this->assertStringContainsString('/accept', $links['accept']);
        $this->assertStringContainsString('token=', $links['accept']);
        $this->assertStringContainsString($this->event->id, $links['accept']);
        $this->assertStringContainsString($participant->id, $links['accept']);
    }

    public function test_can_validate_rsvp_token()
    {
        $participant = EventParticipant::create([
            'event_id' => $this->event->id,
            'email' => 'participant@example.com',
            'name' => 'Test Participant',
            'role' => 'required',
            'status' => 'invited',
        ]);

        $links = $this->invitationService->generateRsvpLinks($this->event, $participant);
        $urlParts = parse_url($links['accept']);
        parse_str($urlParts['query'], $queryParams);
        $token = $queryParams['token'];

        $isValid = $this->invitationService->validateRsvpToken($this->event, $participant, $token);
        $this->assertTrue($isValid);

        $isInvalid = $this->invitationService->validateRsvpToken($this->event, $participant, 'invalid-token');
        $this->assertFalse($isInvalid);
    }

    public function test_can_get_participant_availability()
    {
        // Create a user participant
        $userParticipant = EventParticipant::create([
            'event_id' => $this->event->id,
            'email' => $this->user->email,
            'name' => $this->user->name,
            'role' => 'required',
            'status' => 'invited',
        ]);

        // Create an external participant
        $externalParticipant = EventParticipant::create([
            'event_id' => $this->event->id,
            'email' => 'external@company.com',
            'name' => 'External User',
            'role' => 'optional',
            'status' => 'invited',
        ]);

        $participants = collect([$userParticipant, $externalParticipant]);
        $startDate = Carbon::now()->startOfDay();
        $endDate = $startDate->clone()->addDays(7);

        $availability = $this->invitationService->getParticipantAvailability($participants, $startDate, $endDate);

        $this->assertArrayHasKey($this->user->email, $availability);
        $this->assertArrayHasKey('external@company.com', $availability);

        // User should have detailed availability
        $userAvailability = $availability[$this->user->email];
        $this->assertArrayHasKey('user_id', $userAvailability);
        $this->assertArrayHasKey('busy_periods', $userAvailability);

        // External user should be marked as unknown availability
        $externalAvailability = $availability['external@company.com'];
        $this->assertTrue($externalAvailability['is_external']);
        $this->assertTrue($externalAvailability['availability_unknown']);
    }

    public function test_can_create_meeting_poll()
    {
        $proposedTimes = [
            [
                'start_time' => Carbon::now()->addDays(1)->setHour(10)->toISOString(),
                'end_time' => Carbon::now()->addDays(1)->setHour(11)->toISOString(),
            ],
            [
                'start_time' => Carbon::now()->addDays(1)->setHour(14)->toISOString(),
                'end_time' => Carbon::now()->addDays(1)->setHour(15)->toISOString(),
            ],
        ];

        $poll = $this->invitationService->createMeetingPoll($this->event, $proposedTimes, [
            'poll_deadline' => Carbon::now()->addDays(3),
            'allow_new_suggestions' => true,
        ]);

        $this->assertArrayHasKey('poll_id', $poll);
        $this->assertArrayHasKey('event_id', $poll);
        $this->assertArrayHasKey('proposed_times', $poll);
        $this->assertArrayHasKey('participants', $poll);
        $this->assertArrayHasKey('deadline', $poll);
        $this->assertEquals('active', $poll['status']);
        $this->assertEquals($this->event->id, $poll['event_id']);
        $this->assertCount(2, $poll['proposed_times']);
    }

    public function test_invitation_service_handles_errors_gracefully()
    {
        // Test with invalid event
        $invalidEvent = new Event();
        $invalidEvent->id = 999999;

        $participant = EventParticipant::create([
            'event_id' => $this->event->id,
            'email' => 'test@example.com',
            'name' => 'Test User',
            'role' => 'required',
            'status' => 'invited',
        ]);

        $result = $this->invitationService->sendInvitationToParticipant($invalidEvent, $participant);
        $this->assertFalse($result);
    }

    public function test_rsvp_response_updates_only_change_status()
    {
        Notification::fake();

        $participant = EventParticipant::create([
            'event_id' => $this->event->id,
            'email' => 'participant@example.com',
            'name' => 'Test Participant',
            'role' => 'required',
            'status' => 'accepted',
        ]);

        // Try to set same status
        $result = $this->invitationService->handleRsvpResponse(
            $this->event,
            'participant@example.com',
            'accepted'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('accepted', $result['old_status']);
        $this->assertEquals('accepted', $result['new_status']);

        // Organizer should not be notified for same status
        Notification::assertNotSentTo($this->user, EventRsvpUpdateNotification::class);
    }
}
