<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\Calendar;
use App\Models\Organization;
use App\Models\User;
use App\Models\EventParticipant;
use App\Models\Reminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventModelTest extends TestCase
{
    use RefreshDatabase;

    protected $calendar;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $organization = Organization::create(['name' => 'Test Organization']);
        $user = User::factory()->create();
        
        $this->calendar = Calendar::create([
            'organization_id' => $organization->id,
            'owner_user_id' => $user->id,
            'name' => 'Test Calendar',
            'color' => '#FF0000',
        ]);
    }

    public function test_event_has_uuid_primary_key()
    {
        $event = Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Test Event',
            'start_at' => now(),
            'end_at' => now()->addHour(),
        ]);
        
        $this->assertIsString($event->id);
        $this->assertEquals(36, strlen($event->id)); // UUID length
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $event->id);
    }

    public function test_event_belongs_to_calendar()
    {
        $event = Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Test Event',
            'start_at' => now(),
            'end_at' => now()->addHour(),
        ]);

        $this->assertInstanceOf(Calendar::class, $event->calendar);
        $this->assertEquals($this->calendar->id, $event->calendar->id);
    }

    public function test_event_can_have_multiple_participants()
    {
        $event = Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Test Event',
            'start_at' => now(),
            'end_at' => now()->addHour(),
        ]);

        $participant1 = EventParticipant::create([
            'event_id' => $event->id,
            'email' => 'participant1@example.com',
            'name' => 'Participant 1',
            'role' => 'required',
            'status' => 'invited',
        ]);

        $participant2 = EventParticipant::create([
            'event_id' => $event->id,
            'email' => 'participant2@example.com',
            'name' => 'Participant 2',
            'role' => 'optional',
            'status' => 'accepted',
        ]);

        $this->assertCount(2, $event->participants);
        $this->assertTrue($event->participants->contains($participant1));
        $this->assertTrue($event->participants->contains($participant2));
    }

    public function test_event_can_have_multiple_reminders()
    {
        $event = Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Test Event',
            'start_at' => now(),
            'end_at' => now()->addHour(),
        ]);

        $reminder1 = Reminder::create([
            'event_id' => $event->id,
            'method' => 'email',
            'minutes_before' => 15,
        ]);

        $reminder2 = Reminder::create([
            'event_id' => $event->id,
            'method' => 'push',
            'minutes_before' => 5,
        ]);

        $this->assertCount(2, $event->reminders);
        $this->assertTrue($event->reminders->contains($reminder1));
        $this->assertTrue($event->reminders->contains($reminder2));
    }

    public function test_event_fillable_attributes()
    {
        $eventData = [
            'calendar_id' => $this->calendar->id,
            'title' => 'Test Event',
            'description_md' => 'This is a test event',
            'location' => 'Test Location',
            'meeting_link' => 'https://meet.example.com/test',
            'start_at' => '2024-01-15 10:00:00',
            'end_at' => '2024-01-15 11:00:00',
            'all_day' => false,
            'timezone' => 'Asia/Jakarta',
            'rrule' => 'FREQ=WEEKLY;BYDAY=MO',
            'exdates' => ['2024-01-22'],
            'is_private' => true,
        ];

        $event = Event::create($eventData);

        $this->assertEquals('Test Event', $event->title);
        $this->assertEquals('This is a test event', $event->description_md);
        $this->assertEquals('Test Location', $event->location);
        $this->assertEquals('https://meet.example.com/test', $event->meeting_link);
        $this->assertEquals('Asia/Jakarta', $event->timezone);
        $this->assertEquals('FREQ=WEEKLY;BYDAY=MO', $event->rrule);
        $this->assertEquals(['2024-01-22'], $event->exdates);
        $this->assertTrue($event->is_private);
        $this->assertFalse($event->all_day);
    }

    public function test_event_date_attributes_are_cast_to_datetime()
    {
        $event = Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Test Event',
            'start_at' => '2024-01-15 10:00:00',
            'end_at' => '2024-01-15 11:00:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $event->start_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $event->end_at);
    }

    public function test_event_boolean_attributes_are_cast_to_boolean()
    {
        $event = Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Test Event',
            'start_at' => now(),
            'end_at' => now()->addHour(),
            'all_day' => 1,
            'is_private' => 0,
        ]);

        $this->assertIsBool($event->all_day);
        $this->assertIsBool($event->is_private);
        $this->assertTrue($event->all_day);
        $this->assertFalse($event->is_private);
    }

    public function test_event_exdates_are_cast_to_array()
    {
        $event = Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Test Event',
            'start_at' => now(),
            'end_at' => now()->addHour(),
            'exdates' => ['2024-01-22', '2024-01-29'],
        ]);

        $this->assertIsArray($event->exdates);
        $this->assertEquals(['2024-01-22', '2024-01-29'], $event->exdates);
    }

    public function test_event_can_be_all_day()
    {
        $event = Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'All Day Event',
            'start_at' => '2024-01-15',
            'end_at' => '2024-01-15',
            'all_day' => true,
        ]);

        $this->assertTrue($event->all_day);
        $this->assertEquals('2024-01-15 00:00:00', $event->start_at->format('Y-m-d H:i:s'));
        $this->assertEquals('2024-01-15 00:00:00', $event->end_at->format('Y-m-d H:i:s'));
    }

    public function test_event_can_have_recurrence_rule()
    {
        $event = Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Recurring Event',
            'start_at' => now(),
            'end_at' => now()->addHour(),
            'rrule' => 'FREQ=DAILY;COUNT=10',
        ]);

        $this->assertEquals('FREQ=DAILY;COUNT=10', $event->rrule);
    }

    public function test_event_can_have_exception_dates()
    {
        $event = Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Event with Exceptions',
            'start_at' => now(),
            'end_at' => now()->addHour(),
            'rrule' => 'FREQ=WEEKLY;BYDAY=MO',
            'exdates' => ['2024-01-22', '2024-01-29'],
        ]);

        $this->assertIsArray($event->exdates);
        $this->assertContains('2024-01-22', $event->exdates);
        $this->assertContains('2024-01-29', $event->exdates);
    }

    public function test_event_can_be_private()
    {
        $event = Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Private Event',
            'start_at' => now(),
            'end_at' => now()->addHour(),
            'is_private' => true,
        ]);

        $this->assertTrue($event->is_private);
    }

    public function test_event_can_have_meeting_link()
    {
        $event = Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Online Meeting',
            'start_at' => now(),
            'end_at' => now()->addHour(),
            'meeting_link' => 'https://zoom.us/j/123456789',
        ]);

        $this->assertEquals('https://zoom.us/j/123456789', $event->meeting_link);
    }

    public function test_event_can_have_markdown_description()
    {
        $markdownDescription = "# Meeting Agenda\n\n- Item 1\n- Item 2\n\n**Important:** Please review the documents.";
        
        $event = Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Meeting with Agenda',
            'start_at' => now(),
            'end_at' => now()->addHour(),
            'description_md' => $markdownDescription,
        ]);

        $this->assertEquals($markdownDescription, $event->description_md);
    }
}
