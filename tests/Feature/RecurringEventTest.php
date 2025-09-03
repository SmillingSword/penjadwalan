<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Calendar;
use App\Models\Event;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

class RecurringEventTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;
    private Calendar $calendar;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run the role seeder
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

        // Create test user, organization, and calendar
        $this->organization = Organization::create(['name' => 'Test Organization']);
        $this->user = User::factory()->create([
            'timezone' => 'America/New_York'
        ]);
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

    public function test_can_create_daily_recurring_event()
    {
        $eventData = [
            'calendar_id' => $this->calendar->id,
            'title' => 'Daily Standup',
            'description_md' => 'Daily team standup meeting',
            'start_at' => '2024-01-01T09:00:00',
            'end_at' => '2024-01-01T09:30:00',
            'timezone' => 'America/New_York',
            'rrule' => 'FREQ=DAILY;COUNT=5',
            'is_private' => false,
        ];

        $response = $this->postJson('/api/events', $eventData, [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(201);
        
        $event = Event::first();
        $this->assertEquals('FREQ=DAILY;COUNT=5', $event->rrule);
        $this->assertTrue($event->isRecurring());
    }

    public function test_can_create_weekly_recurring_event()
    {
        $eventData = [
            'calendar_id' => $this->calendar->id,
            'title' => 'Weekly Team Meeting',
            'start_at' => '2024-01-01T14:00:00',
            'end_at' => '2024-01-01T15:00:00',
            'timezone' => 'America/New_York',
            'rrule' => 'FREQ=WEEKLY;BYDAY=MO,WE,FR',
            'is_private' => false,
        ];

        $response = $this->postJson('/api/events', $eventData, [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(201);
        
        $event = Event::first();
        $this->assertEquals('FREQ=WEEKLY;BYDAY=MO,WE,FR', $event->rrule);
    }

    public function test_can_expand_recurring_events()
    {
        // Test RecurrenceService expansion directly to avoid API race conditions
        $recurrenceService = app(\App\Services\RecurrenceService::class);
        
        $startDate = Carbon::parse('2024-01-01 09:00:00', 'UTC');
        $endDate = Carbon::parse('2024-01-01 10:00:00', 'UTC');
        $rangeStart = Carbon::parse('2024-01-01', 'UTC');
        $rangeEnd = Carbon::parse('2024-01-07', 'UTC');
        
        // Test expansion with COUNT=5
        $instances = $recurrenceService->expandRecurrence(
            'FREQ=DAILY;COUNT=5',
            $startDate,
            $endDate,
            $rangeStart,
            $rangeEnd,
            [],
            'UTC'
        );
        
        $this->assertCount(5, $instances); // Should have 5 instances
        
        // Check that instances have correct dates
        $expectedDates = [
            '2024-01-01T09:00:00.000000Z',
            '2024-01-02T09:00:00.000000Z',
            '2024-01-03T09:00:00.000000Z',
            '2024-01-04T09:00:00.000000Z',
            '2024-01-05T09:00:00.000000Z',
        ];
        
        foreach ($instances as $index => $instance) {
            $this->assertEquals($expectedDates[$index], $instance['start_at']->toISOString());
        }
    }

    public function test_can_add_exception_dates()
    {
        // Test that exdates are properly stored and retrieved from the model
        $event = Event::factory()->create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Daily Meeting',
            'start_at' => Carbon::parse('2024-01-01 09:00:00', 'UTC'),
            'end_at' => Carbon::parse('2024-01-01 10:00:00', 'UTC'),
            'rrule' => 'FREQ=DAILY;COUNT=5',
            'timezone' => 'UTC',
            'exdates' => ['2024-01-02', '2024-01-04'], // Exclude 2nd and 4th instances
        ]);

        // Test that exdates are properly stored
        $this->assertEquals(['2024-01-02', '2024-01-04'], $event->exdates);
        
        // Test that the event is recognized as recurring
        $this->assertTrue($event->isRecurring());
        
        // Test that RecurrenceService can handle exdates
        $recurrenceService = app(\App\Services\RecurrenceService::class);
        $this->assertTrue($recurrenceService->isExcluded($event, '2024-01-02'));
        $this->assertTrue($recurrenceService->isExcluded($event, '2024-01-04'));
        $this->assertFalse($recurrenceService->isExcluded($event, '2024-01-01'));
    }

    public function test_validates_invalid_rrule()
    {
        $eventData = [
            'calendar_id' => $this->calendar->id,
            'title' => 'Invalid Recurring Event',
            'start_at' => '2024-01-01T09:00:00',
            'end_at' => '2024-01-01T10:00:00',
            'timezone' => 'UTC',
            'rrule' => 'INVALID_RRULE_FORMAT',
            'is_private' => false,
        ];

        $response = $this->postJson('/api/events', $eventData, [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(500); // Should fail due to invalid RRULE
    }

    public function test_can_update_recurring_event()
    {
        $event = Event::factory()->create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Original Title',
            'start_at' => Carbon::parse('2024-01-01 09:00:00', 'UTC'),
            'end_at' => Carbon::parse('2024-01-01 10:00:00', 'UTC'),
            'rrule' => 'FREQ=DAILY;COUNT=5',
            'timezone' => 'UTC',
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'rrule' => 'FREQ=WEEKLY;BYDAY=MO,WE,FR',
            'timezone' => 'UTC',
        ];

        $response = $this->putJson("/api/events/{$event->id}", $updateData, [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(200);
        
        $event->refresh();
        $this->assertEquals('Updated Title', $event->title);
        $this->assertEquals('FREQ=WEEKLY;BYDAY=MO,WE,FR', $event->rrule);
    }

    public function test_can_delete_recurring_event()
    {
        $event = Event::factory()->create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Recurring Event to Delete',
            'start_at' => Carbon::parse('2024-01-01 09:00:00', 'UTC'),
            'end_at' => Carbon::parse('2024-01-01 10:00:00', 'UTC'),
            'rrule' => 'FREQ=DAILY;COUNT=5',
            'timezone' => 'UTC',
        ]);

        $response = $this->deleteJson("/api/events/{$event->id}", [], [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_recurring_events_respect_timezone()
    {
        // Create event in New York timezone
        $eventData = [
            'calendar_id' => $this->calendar->id,
            'title' => 'Timezone Test Event',
            'start_at' => '2024-01-01T09:00:00',
            'end_at' => '2024-01-01T10:00:00',
            'timezone' => 'America/New_York',
            'rrule' => 'FREQ=DAILY;COUNT=3',
            'is_private' => false,
        ];

        $response = $this->postJson('/api/events', $eventData, [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(201);
        
        $event = Event::first();
        
        // Event should be stored in UTC
        $this->assertEquals('UTC', $event->start_at->timezone->getName());
        
        // But timezone should be preserved
        $this->assertEquals('America/New_York', $event->timezone);
    }

    public function test_can_get_recurrence_description()
    {
        $event = Event::factory()->create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Weekly Meeting',
            'start_at' => Carbon::parse('2024-01-01 09:00:00', 'UTC'),
            'end_at' => Carbon::parse('2024-01-01 10:00:00', 'UTC'),
            'rrule' => 'FREQ=WEEKLY;BYDAY=MO,WE,FR',
            'timezone' => 'UTC',
        ]);

        $description = $event->getRecurrenceDescription();
        $this->assertIsString($description);
        $this->assertNotEmpty($description);
    }

    public function test_non_recurring_events_work_normally()
    {
        $eventData = [
            'calendar_id' => $this->calendar->id,
            'title' => 'One-time Meeting',
            'start_at' => '2024-01-01T09:00:00',
            'end_at' => '2024-01-01T10:00:00',
            'timezone' => 'UTC',
            'is_private' => false,
        ];

        $response = $this->postJson('/api/events', $eventData, [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(201);
        
        $event = Event::first();
        $this->assertFalse($event->isRecurring());
        $this->assertNull($event->rrule);
    }

    public function test_expansion_respects_date_range()
    {
        // Test that RecurrenceService properly handles date ranges
        $recurrenceService = app(\App\Services\RecurrenceService::class);
        
        $startDate = Carbon::parse('2024-01-01 09:00:00', 'UTC');
        $endDate = Carbon::parse('2024-01-01 10:00:00', 'UTC');
        $rangeStart = Carbon::parse('2024-01-01', 'UTC');
        $rangeEnd = Carbon::parse('2024-01-04 23:59:59', 'UTC'); // 4 day range (inclusive)
        
        // Test expansion with COUNT=10 but limited range
        $instances = $recurrenceService->expandRecurrence(
            'FREQ=DAILY;COUNT=10',
            $startDate,
            $endDate,
            $rangeStart,
            $rangeEnd,
            [],
            'UTC'
        );
        
        // Should return 4 instances (within the 4-day range: Jan 1-4)
        $this->assertCount(4, $instances);
        
        // Test that all instances are within the range
        foreach ($instances as $instance) {
            $this->assertTrue($instance['start_at']->gte($rangeStart));
            $this->assertTrue($instance['start_at']->lte($rangeEnd));
        }
    }
}
