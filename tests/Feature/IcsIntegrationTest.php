<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Organization;
use App\Models\Calendar;
use App\Models\Event;
use App\Services\IcsImportService;
use App\Services\IcsExportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IcsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $organization;
    protected $calendar;
    protected $icsImportService;
    protected $icsExportService;

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

        // Initialize services
        $this->icsImportService = app(IcsImportService::class);
        $this->icsExportService = app(IcsExportService::class);

        // Authenticate user
        Sanctum::actingAs($this->user);
    }

    public function test_can_validate_ics_content()
    {
        $validIcsContent = $this->getValidIcsContent();
        
        $validation = $this->icsImportService->validateIcsContent($validIcsContent);
        
        $this->assertTrue($validation['valid']);
        $this->assertGreaterThan(0, $validation['events_count']);
        $this->assertEmpty($validation['errors']);
    }

    public function test_can_validate_invalid_ics_content()
    {
        $invalidIcsContent = "INVALID ICS CONTENT";
        
        $validation = $this->icsImportService->validateIcsContent($invalidIcsContent);
        
        $this->assertFalse($validation['valid']);
        $this->assertEquals(0, $validation['events_count']);
        $this->assertNotEmpty($validation['errors']);
    }

    public function test_can_preview_ics_content()
    {
        $icsContent = $this->getValidIcsContent();
        
        $preview = $this->icsImportService->previewIcsContent($icsContent, 5);
        
        $this->assertArrayHasKey('total_events', $preview);
        $this->assertArrayHasKey('preview_events', $preview);
        $this->assertArrayHasKey('showing_count', $preview);
        $this->assertLessThanOrEqual(5, $preview['showing_count']);
    }

    public function test_can_import_ics_file()
    {
        $icsContent = $this->getValidIcsContent();
        
        $results = $this->icsImportService->importIcsFile($icsContent, $this->calendar, [
            'skip_duplicates' => true,
            'max_events' => 100,
        ]);
        
        $this->assertArrayHasKey('total_events_found', $results);
        $this->assertArrayHasKey('imported_events', $results);
        $this->assertArrayHasKey('skipped_events', $results);
        $this->assertArrayHasKey('failed_events', $results);
        $this->assertGreaterThan(0, $results['imported_events']);
        
        // Verify events were created in database
        $this->assertDatabaseHas('events', [
            'calendar_id' => $this->calendar->id,
            'external_source' => 'ics_import',
        ]);
    }

    public function test_can_export_calendar_to_ics()
    {
        // Create some events
        Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Test Event 1',
            'start_at' => Carbon::now()->addDays(1),
            'end_at' => Carbon::now()->addDays(1)->addHour(),
        ]);

        Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Test Event 2',
            'start_at' => Carbon::now()->addDays(2),
            'end_at' => Carbon::now()->addDays(2)->addHour(),
            'is_private' => true,
        ]);

        $icsContent = $this->icsExportService->exportCalendar($this->calendar, [
            'include_private' => false,
        ]);
        
        $this->assertStringContainsString('BEGIN:VCALENDAR', $icsContent);
        $this->assertStringContainsString('END:VCALENDAR', $icsContent);
        $this->assertStringContainsString('Test Event 1', $icsContent);
        $this->assertStringNotContainsString('Test Event 2', $icsContent); // Private event excluded
    }

    public function test_can_export_single_event_to_ics()
    {
        $event = Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Single Event Export',
            'description_md' => 'This is a test event',
            'location' => 'Test Location',
            'start_at' => Carbon::now()->addDays(1),
            'end_at' => Carbon::now()->addDays(1)->addHour(),
        ]);

        $icsContent = $this->icsExportService->exportEvent($event);
        
        $this->assertStringContainsString('BEGIN:VCALENDAR', $icsContent);
        $this->assertStringContainsString('BEGIN:VEVENT', $icsContent);
        $this->assertStringContainsString('Single Event Export', $icsContent);
        $this->assertStringContainsString('Test Location', $icsContent);
        $this->assertStringContainsString('END:VEVENT', $icsContent);
        $this->assertStringContainsString('END:VCALENDAR', $icsContent);
    }

    public function test_can_generate_public_feed_url()
    {
        $feedUrl = $this->icsExportService->generatePublicFeedUrl($this->calendar);
        
        $this->assertStringContainsString('/api/public/calendar/feed.ics', $feedUrl);
        $this->assertStringContainsString('token=', $feedUrl);
        $this->assertStringContainsString('calendar_id=' . $this->calendar->id, $feedUrl);
    }

    public function test_can_validate_calendar_token()
    {
        $feedUrl = $this->icsExportService->generatePublicFeedUrl($this->calendar);
        $urlParts = parse_url($feedUrl);
        parse_str($urlParts['query'], $queryParams);
        $token = $queryParams['token'];
        
        $isValid = $this->icsExportService->validateCalendarToken($this->calendar, $token);
        $this->assertTrue($isValid);
        
        $isInvalid = $this->icsExportService->validateCalendarToken($this->calendar, 'invalid-token');
        $this->assertFalse($isInvalid);
    }

    public function test_ics_import_handles_recurring_events()
    {
        $recurringIcsContent = $this->getRecurringEventIcsContent();
        
        $results = $this->icsImportService->importIcsFile($recurringIcsContent, $this->calendar);
        
        $this->assertGreaterThan(0, $results['imported_events']);
        
        // Verify recurring event was imported with RRULE
        $this->assertDatabaseHas('events', [
            'calendar_id' => $this->calendar->id,
            'title' => 'Weekly Meeting',
        ]);
        
        $event = Event::where('calendar_id', $this->calendar->id)
                     ->where('title', 'Weekly Meeting')
                     ->first();
        
        $this->assertNotNull($event->rrule);
        $this->assertStringContainsString('FREQ=WEEKLY', $event->rrule);
    }

    public function test_ics_export_includes_recurring_events()
    {
        Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Weekly Team Meeting',
            'start_at' => Carbon::now()->addDays(1),
            'end_at' => Carbon::now()->addDays(1)->addHour(),
            'rrule' => 'FREQ=WEEKLY;BYDAY=MO;COUNT=10',
        ]);

        $icsContent = $this->icsExportService->exportCalendar($this->calendar);
        
        $this->assertStringContainsString('Weekly Team Meeting', $icsContent);
        $this->assertStringContainsString('RRULE:FREQ=WEEKLY;BYDAY=MO;COUNT=10', $icsContent);
    }

    public function test_ics_import_handles_timezone_conversion()
    {
        $timezoneIcsContent = $this->getTimezoneIcsContent();
        
        $results = $this->icsImportService->importIcsFile($timezoneIcsContent, $this->calendar);
        
        $this->assertGreaterThan(0, $results['imported_events']);
        
        $event = Event::where('calendar_id', $this->calendar->id)
                     ->where('title', 'Timezone Test Event')
                     ->first();
        
        $this->assertNotNull($event);
        $this->assertEquals('America/New_York', $event->timezone);
    }

    public function test_ics_import_skips_duplicates()
    {
        $icsContent = $this->getValidIcsContent();
        
        // Import first time
        $results1 = $this->icsImportService->importIcsFile($icsContent, $this->calendar, [
            'skip_duplicates' => true,
        ]);
        
        // Import second time
        $results2 = $this->icsImportService->importIcsFile($icsContent, $this->calendar, [
            'skip_duplicates' => true,
        ]);
        
        $this->assertGreaterThan(0, $results1['imported_events']);
        $this->assertEquals(0, $results2['imported_events']);
        $this->assertGreaterThan(0, $results2['skipped_events']);
    }

    public function test_ics_import_respects_max_events_limit()
    {
        $largeIcsContent = $this->getLargeIcsContent(50); // 50 events
        
        $results = $this->icsImportService->importIcsFile($largeIcsContent, $this->calendar, [
            'max_events' => 10,
        ]);
        
        $this->assertLessThanOrEqual(10, $results['imported_events']);
        $this->assertNotEmpty($results['errors']); // Should contain limit warning
    }

    public function test_ics_export_respects_date_range()
    {
        // Create events in different date ranges
        Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Past Event',
            'start_at' => Carbon::now()->subDays(10),
            'end_at' => Carbon::now()->subDays(10)->addHour(),
        ]);

        Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Future Event',
            'start_at' => Carbon::now()->addDays(10),
            'end_at' => Carbon::now()->addDays(10)->addHour(),
        ]);

        $icsContent = $this->icsExportService->exportCalendar($this->calendar, [
            'date_range_start' => Carbon::now()->addDays(5)->toDateString(),
            'date_range_end' => Carbon::now()->addDays(15)->toDateString(),
        ]);
        
        $this->assertStringContainsString('Future Event', $icsContent);
        $this->assertStringNotContainsString('Past Event', $icsContent);
    }

    public function test_can_get_import_statistics()
    {
        // Import some events
        $icsContent = $this->getValidIcsContent();
        $this->icsImportService->importIcsFile($icsContent, $this->calendar);
        
        $stats = $this->icsImportService->getImportStatistics($this->calendar);
        
        $this->assertArrayHasKey('total_imported_events', $stats);
        $this->assertArrayHasKey('imported_by_month', $stats);
        $this->assertArrayHasKey('events_with_recurrence', $stats);
        $this->assertArrayHasKey('all_day_events', $stats);
        $this->assertArrayHasKey('private_events', $stats);
        $this->assertGreaterThan(0, $stats['total_imported_events']);
    }

    public function test_can_get_export_statistics()
    {
        // Create various types of events
        Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Regular Event',
            'start_at' => Carbon::now()->addDays(1),
            'end_at' => Carbon::now()->addDays(1)->addHour(),
            'location' => 'Office',
            'description_md' => 'Meeting description',
        ]);

        Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'All Day Event',
            'start_at' => Carbon::now()->addDays(2)->startOfDay(),
            'end_at' => Carbon::now()->addDays(2)->endOfDay(),
            'all_day' => true,
        ]);

        Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Private Event',
            'start_at' => Carbon::now()->addDays(3),
            'end_at' => Carbon::now()->addDays(3)->addHour(),
            'is_private' => true,
        ]);

        Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Recurring Event',
            'start_at' => Carbon::now()->addDays(4),
            'end_at' => Carbon::now()->addDays(4)->addHour(),
            'rrule' => 'FREQ=WEEKLY;COUNT=5',
        ]);

        $stats = $this->icsExportService->getExportStatistics($this->calendar);
        
        $this->assertEquals(4, $stats['total_events']);
        $this->assertEquals(1, $stats['private_events']);
        $this->assertEquals(1, $stats['recurring_events']);
        $this->assertEquals(1, $stats['all_day_events']);
        $this->assertEquals(1, $stats['events_with_location']);
        $this->assertEquals(1, $stats['events_with_description']);
        $this->assertArrayHasKey('date_range', $stats);
    }

    protected function getValidIcsContent(): string
    {
        return "BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test Calendar//EN
BEGIN:VEVENT
UID:test-event-1@example.com
DTSTART:20240115T100000Z
DTEND:20240115T110000Z
SUMMARY:Test Meeting
DESCRIPTION:This is a test meeting
LOCATION:Conference Room A
STATUS:CONFIRMED
END:VEVENT
BEGIN:VEVENT
UID:test-event-2@example.com
DTSTART:20240116T140000Z
DTEND:20240116T150000Z
SUMMARY:Another Meeting
DESCRIPTION:Another test meeting
STATUS:CONFIRMED
END:VEVENT
END:VCALENDAR";
    }

    protected function getRecurringEventIcsContent(): string
    {
        return "BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test Calendar//EN
BEGIN:VEVENT
UID:recurring-event-1@example.com
DTSTART:20240115T100000Z
DTEND:20240115T110000Z
SUMMARY:Weekly Meeting
DESCRIPTION:Weekly team meeting
RRULE:FREQ=WEEKLY;BYDAY=MO;COUNT=10
STATUS:CONFIRMED
END:VEVENT
END:VCALENDAR";
    }

    protected function getTimezoneIcsContent(): string
    {
        return "BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test Calendar//EN
BEGIN:VEVENT
UID:timezone-event-1@example.com
DTSTART;TZID=America/New_York:20240115T100000
DTEND;TZID=America/New_York:20240115T110000
SUMMARY:Timezone Test Event
DESCRIPTION:Event with timezone
STATUS:CONFIRMED
END:VEVENT
END:VCALENDAR";
    }

    protected function getLargeIcsContent(int $eventCount): string
    {
        $ics = "BEGIN:VCALENDAR\nVERSION:2.0\nPRODID:-//Test//Test Calendar//EN\n";
        
        for ($i = 1; $i <= $eventCount; $i++) {
            $date = Carbon::now()->addDays($i);
            $ics .= "BEGIN:VEVENT\n";
            $ics .= "UID:large-event-{$i}@example.com\n";
            $ics .= "DTSTART:" . $date->format('Ymd\THis\Z') . "\n";
            $ics .= "DTEND:" . $date->addHour()->format('Ymd\THis\Z') . "\n";
            $ics .= "SUMMARY:Large Import Event {$i}\n";
            $ics .= "STATUS:CONFIRMED\n";
            $ics .= "END:VEVENT\n";
        }
        
        $ics .= "END:VCALENDAR";
        
        return $ics;
    }
}
