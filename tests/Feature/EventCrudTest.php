<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Organization;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Reminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventCrudTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $organization;
    protected $calendar;

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

        // Authenticate user
        Sanctum::actingAs($this->user);
    }

    public function test_user_can_list_events()
    {
        // Create some events
        Event::factory()->count(3)->create([
            'calendar_id' => $this->calendar->id,
        ]);

        $response = $this->getJson('/api/events', [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_user_can_create_event()
    {
        $eventData = [
            'calendar_id' => $this->calendar->id,
            'title' => 'Test Event',
            'description_md' => 'This is a test event',
            'location' => 'Test Location',
            'meeting_link' => 'https://meet.example.com/test',
            'start_at' => '2024-01-15 10:00:00',
            'end_at' => '2024-01-15 11:00:00',
            'timezone' => 'Asia/Jakarta',
            'all_day' => false,
            'is_private' => false,
            'participants' => [
                [
                    'email' => 'participant1@example.com',
                    'name' => 'Participant 1',
                    'role' => 'required',
                ],
                [
                    'email' => 'participant2@example.com',
                    'name' => 'Participant 2',
                    'role' => 'optional',
                ]
            ],
            'reminders' => [
                [
                    'method' => 'email',
                    'minutes_before' => 15,
                ],
                [
                    'method' => 'popup',
                    'minutes_before' => 5,
                ]
            ]
        ];

        $response = $this->postJson('/api/events', $eventData, [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('title', 'Test Event');
        $response->assertJsonPath('location', 'Test Location');
        $response->assertJsonPath('calendar_id', $this->calendar->id);

        // Check that event was created in database
        $this->assertDatabaseHas('events', [
            'title' => 'Test Event',
            'calendar_id' => $this->calendar->id,
        ]);

        // Check that participants were created
        $this->assertDatabaseHas('event_participants', [
            'email' => 'participant1@example.com',
            'name' => 'Participant 1',
            'role' => 'required',
        ]);

        // Check that reminders were created
        $this->assertDatabaseHas('reminders', [
            'method' => 'email',
            'minutes_before' => 15,
        ]);
    }

    public function test_user_can_view_event()
    {
        $event = Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Test Event',
            'start_at' => now(),
            'end_at' => now()->addHour(),
        ]);

        $response = $this->getJson("/api/events/{$event->id}", [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('id', $event->id);
        $response->assertJsonPath('title', 'Test Event');
    }

    public function test_user_can_update_event()
    {
        $event = Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Original Title',
            'start_at' => now(),
            'end_at' => now()->addHour(),
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'description_md' => 'Updated description',
            'location' => 'Updated Location',
            'start_at' => '2024-01-16 10:00:00',
            'end_at' => '2024-01-16 11:00:00',
        ];

        $response = $this->putJson("/api/events/{$event->id}", $updateData, [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('title', 'Updated Title');
        $response->assertJsonPath('location', 'Updated Location');

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'title' => 'Updated Title',
            'location' => 'Updated Location',
        ]);
    }

    public function test_user_can_delete_event()
    {
        $event = Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Test Event',
            'start_at' => now(),
            'end_at' => now()->addHour(),
        ]);

        $response = $this->deleteJson("/api/events/{$event->id}", [], [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Event deleted successfully']);

        $this->assertDatabaseMissing('events', [
            'id' => $event->id,
        ]);
    }

    public function test_event_creation_requires_valid_data()
    {
        // Test missing required fields
        $response = $this->postJson('/api/events', [], [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['calendar_id', 'title', 'start_at', 'end_at']);

        // Test invalid date format
        $response = $this->postJson('/api/events', [
            'calendar_id' => $this->calendar->id,
            'title' => 'Test Event',
            'start_at' => 'invalid-date',
            'end_at' => 'invalid-date',
        ], [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['start_at', 'end_at']);

        // Test end_at before start_at
        $response = $this->postJson('/api/events', [
            'calendar_id' => $this->calendar->id,
            'title' => 'Test Event',
            'start_at' => '2024-01-15 11:00:00',
            'end_at' => '2024-01-15 10:00:00',
        ], [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['end_at']);
    }

    public function test_user_can_update_event_participants()
    {
        $event = Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Test Event',
            'start_at' => now(),
            'end_at' => now()->addHour(),
        ]);

        // Create initial participants
        EventParticipant::create([
            'event_id' => $event->id,
            'email' => 'old@example.com',
            'name' => 'Old Participant',
            'role' => 'required',
            'status' => 'pending',
        ]);

        $participantsData = [
            'participants' => [
                [
                    'email' => 'new1@example.com',
                    'name' => 'New Participant 1',
                    'role' => 'required',
                ],
                [
                    'email' => 'new2@example.com',
                    'name' => 'New Participant 2',
                    'role' => 'optional',
                ]
            ]
        ];

        $response = $this->putJson("/api/events/{$event->id}/participants", $participantsData, [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Participants updated successfully']);

        // Check that old participant was removed
        $this->assertDatabaseMissing('event_participants', [
            'event_id' => $event->id,
            'email' => 'old@example.com',
        ]);

        // Check that new participants were added
        $this->assertDatabaseHas('event_participants', [
            'event_id' => $event->id,
            'email' => 'new1@example.com',
        ]);

        $this->assertDatabaseHas('event_participants', [
            'event_id' => $event->id,
            'email' => 'new2@example.com',
        ]);
    }

    public function test_user_cannot_access_event_from_different_organization()
    {
        // Create another organization and calendar
        $otherOrg = Organization::create(['name' => 'Other Organization']);
        $otherUser = User::factory()->create();
        $otherUser->organizations()->attach($otherOrg->id, ['role' => 'Owner']);

        $otherCalendar = Calendar::create([
            'organization_id' => $otherOrg->id,
            'owner_user_id' => $otherUser->id,
            'name' => 'Other Calendar',
            'color' => '#FF0000',
        ]);

        $otherEvent = Event::create([
            'calendar_id' => $otherCalendar->id,
            'title' => 'Other Event',
            'start_at' => now(),
            'end_at' => now()->addHour(),
        ]);

        // Try to access the other organization's event
        $response = $this->getJson("/api/events/{$otherEvent->id}", [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(404); // Should not be found due to tenant isolation
    }

    public function test_all_day_event_validation()
    {
        $eventData = [
            'calendar_id' => $this->calendar->id,
            'title' => 'All Day Event',
            'start_at' => '2024-01-15',
            'end_at' => '2024-01-15',
            'all_day' => true,
        ];

        $response = $this->postJson('/api/events', $eventData, [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('all_day', true);
    }
}
