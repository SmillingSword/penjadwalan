<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Calendar;
use App\Models\Event;
use App\Services\FreeBusyService;
use App\Services\SchedulingAssistantService;
use App\Services\IcsExportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class PerformanceTest extends TestCase
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

        // Create test data
        $this->organization = Organization::create(['name' => 'Test Organization']);
        $this->user = User::factory()->create([
            'working_hours_start' => '09:00',
            'working_hours_end' => '17:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'buffer_minutes' => 15,
        ]);
        $this->user->organizations()->attach($this->organization->id, ['role' => 'Owner']);
        $this->user->assignRole('Owner');

        $this->calendar = Calendar::create([
            'organization_id' => $this->organization->id,
            'owner_user_id' => $this->user->id,
            'name' => 'Test Calendar',
            'color' => '#FF0000',
        ]);
    }

    public function test_api_response_time_under_threshold()
    {
        Sanctum::actingAs($this->user);

        $startTime = microtime(true);
        
        $response = $this->getJson('/api/calendars', [
            'X-Organization-ID' => $this->organization->id
        ]);
        
        $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        
        $response->assertStatus(200);
        $this->assertLessThan(500, $responseTime, 'API response time should be under 500ms');
    }

    public function test_database_query_performance()
    {
        // Create a large number of events to test query performance
        $events = Event::factory()->count(1000)->create([
            'calendar_id' => $this->calendar->id,
        ]);

        Sanctum::actingAs($this->user);

        $startTime = microtime(true);
        
        $response = $this->getJson('/api/events', [
            'X-Organization-ID' => $this->organization->id
        ]);
        
        $queryTime = (microtime(true) - $startTime) * 1000;
        
        $response->assertStatus(200);
        $this->assertLessThan(1000, $queryTime, 'Database query should complete under 1 second');
    }

    public function test_free_busy_calculation_performance()
    {
        // Create multiple users with many events
        $users = User::factory()->count(10)->create();
        $calendars = [];
        
        foreach ($users as $user) {
            $user->organizations()->attach($this->organization->id, ['role' => 'Member']);
            
            $calendar = Calendar::create([
                'organization_id' => $this->organization->id,
                'owner_user_id' => $user->id,
                'name' => "Calendar for {$user->name}",
                'color' => '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT),
            ]);
            
            $calendars[] = $calendar;
            
            // Create 100 events per user
            Event::factory()->count(100)->create([
                'calendar_id' => $calendar->id,
            ]);
        }

        $freeBusyService = app(FreeBusyService::class);
        
        $startTime = microtime(true);
        
        $result = $freeBusyService->getFreeBusyForUser(
            $users->first(),
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
            'UTC'
        );
        
        $calculationTime = (microtime(true) - $startTime) * 1000;
        
        $this->assertIsArray($result);
        $this->assertLessThan(2000, $calculationTime, 'Free/busy calculation should complete under 2 seconds');
    }

    public function test_scheduling_assistant_performance()
    {
        // Create multiple users for scheduling
        $users = User::factory()->count(5)->create([
            'working_hours_start' => '09:00',
            'working_hours_end' => '17:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ]);
        
        foreach ($users as $user) {
            $user->organizations()->attach($this->organization->id, ['role' => 'Member']);
            
            $calendar = Calendar::create([
                'organization_id' => $this->organization->id,
                'owner_user_id' => $user->id,
                'name' => "Calendar for {$user->name}",
                'color' => '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT),
            ]);
            
            // Create some existing events
            Event::factory()->count(20)->create([
                'calendar_id' => $calendar->id,
            ]);
        }

        $schedulingService = app(SchedulingAssistantService::class);
        
        $startTime = microtime(true);
        
        $suggestions = $schedulingService->suggestMeetingTimes(
            $users->pluck('email')->toArray(),
            60, // 1 hour duration
            Carbon::now()->addDay(),
            Carbon::now()->addWeek(),
            'UTC'
        );
        
        $suggestionTime = (microtime(true) - $startTime) * 1000;
        
        $this->assertIsArray($suggestions);
        $this->assertLessThan(3000, $suggestionTime, 'Meeting time suggestions should complete under 3 seconds');
    }

    public function test_ics_export_performance()
    {
        // Create a large number of events
        Event::factory()->count(500)->create([
            'calendar_id' => $this->calendar->id,
        ]);

        $icsService = app(IcsExportService::class);
        
        $startTime = microtime(true);
        
        $icsContent = $icsService->exportCalendar($this->calendar);
        
        $exportTime = (microtime(true) - $startTime) * 1000;
        
        $this->assertIsString($icsContent);
        $this->assertStringContains('BEGIN:VCALENDAR', $icsContent);
        $this->assertLessThan(5000, $exportTime, 'ICS export should complete under 5 seconds');
    }

    public function test_concurrent_request_handling()
    {
        Sanctum::actingAs($this->user);

        $promises = [];
        $startTime = microtime(true);
        
        // Simulate concurrent requests
        for ($i = 0; $i < 10; $i++) {
            $promises[] = $this->getJson('/api/calendars', [
                'X-Organization-ID' => $this->organization->id
            ]);
        }
        
        $totalTime = (microtime(true) - $startTime) * 1000;
        
        // All requests should complete successfully
        foreach ($promises as $response) {
            $response->assertStatus(200);
        }
        
        // Total time should be reasonable for concurrent requests
        $this->assertLessThan(3000, $totalTime, 'Concurrent requests should complete under 3 seconds');
    }

    public function test_memory_usage_under_limit()
    {
        $initialMemory = memory_get_usage(true);
        
        // Create a large dataset
        Event::factory()->count(1000)->create([
            'calendar_id' => $this->calendar->id,
        ]);

        Sanctum::actingAs($this->user);
        
        $response = $this->getJson('/api/events', [
            'X-Organization-ID' => $this->organization->id
        ]);
        
        $finalMemory = memory_get_usage(true);
        $memoryUsed = $finalMemory - $initialMemory;
        
        $response->assertStatus(200);
        
        // Memory usage should be reasonable (less than 50MB for this test)
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, 'Memory usage should be under 50MB');
    }

    public function test_pagination_performance()
    {
        // Create a large number of events
        Event::factory()->count(2000)->create([
            'calendar_id' => $this->calendar->id,
        ]);

        Sanctum::actingAs($this->user);

        $startTime = microtime(true);
        
        $response = $this->getJson('/api/events?page=1&per_page=50', [
            'X-Organization-ID' => $this->organization->id
        ]);
        
        $paginationTime = (microtime(true) - $startTime) * 1000;
        
        $response->assertStatus(200);
        $this->assertLessThan(1000, $paginationTime, 'Paginated query should complete under 1 second');
        
        // Test deep pagination
        $startTime = microtime(true);
        
        $response = $this->getJson('/api/events?page=20&per_page=50', [
            'X-Organization-ID' => $this->organization->id
        ]);
        
        $deepPaginationTime = (microtime(true) - $startTime) * 1000;
        
        $response->assertStatus(200);
        $this->assertLessThan(1500, $deepPaginationTime, 'Deep pagination should complete under 1.5 seconds');
    }

    public function test_search_performance()
    {
        // Create events with searchable content
        for ($i = 0; $i < 500; $i++) {
            Event::create([
                'calendar_id' => $this->calendar->id,
                'title' => "Meeting {$i} about project planning",
                'description_md' => "This is a detailed description for meeting {$i}",
                'start_at' => Carbon::now()->addDays($i % 30),
                'end_at' => Carbon::now()->addDays($i % 30)->addHour(),
                'timezone' => 'UTC',
            ]);
        }

        Sanctum::actingAs($this->user);

        $startTime = microtime(true);
        
        $response = $this->getJson('/api/search?q=project&type=events', [
            'X-Organization-ID' => $this->organization->id
        ]);
        
        $searchTime = (microtime(true) - $startTime) * 1000;
        
        $response->assertStatus(200);
        $this->assertLessThan(2000, $searchTime, 'Search should complete under 2 seconds');
    }

    public function test_bulk_operations_performance()
    {
        Sanctum::actingAs($this->user);

        // Test bulk event creation
        $eventData = [];
        for ($i = 0; $i < 100; $i++) {
            $eventData[] = [
                'calendar_id' => $this->calendar->id,
                'title' => "Bulk Event {$i}",
                'start_at' => Carbon::now()->addDays($i)->format('Y-m-d H:i:s'),
                'end_at' => Carbon::now()->addDays($i)->addHour()->format('Y-m-d H:i:s'),
                'timezone' => 'UTC',
            ];
        }

        $startTime = microtime(true);
        
        // Note: This assumes a bulk create endpoint exists
        // If not, you can test individual creates in a loop
        foreach (array_slice($eventData, 0, 10) as $data) {
            $response = $this->postJson('/api/events', $data, [
                'X-Organization-ID' => $this->organization->id
            ]);
            $response->assertStatus(201);
        }
        
        $bulkTime = (microtime(true) - $startTime) * 1000;
        
        $this->assertLessThan(5000, $bulkTime, 'Bulk operations should complete under 5 seconds');
    }

    public function test_cache_performance()
    {
        Sanctum::actingAs($this->user);

        // First request (should hit database)
        $startTime = microtime(true);
        $response1 = $this->getJson('/api/calendars', [
            'X-Organization-ID' => $this->organization->id
        ]);
        $firstRequestTime = (microtime(true) - $startTime) * 1000;
        
        // Second request (should hit cache if caching is implemented)
        $startTime = microtime(true);
        $response2 = $this->getJson('/api/calendars', [
            'X-Organization-ID' => $this->organization->id
        ]);
        $secondRequestTime = (microtime(true) - $startTime) * 1000;
        
        $response1->assertStatus(200);
        $response2->assertStatus(200);
        
        // Second request should be faster (if caching is implemented)
        // This test might need adjustment based on your caching strategy
        $this->assertLessThan($firstRequestTime, $secondRequestTime + 50); // Allow 50ms tolerance
    }

    public function test_timezone_conversion_performance()
    {
        // Create events in different timezones
        $timezones = ['UTC', 'America/New_York', 'Europe/London', 'Asia/Tokyo', 'Australia/Sydney'];
        
        foreach ($timezones as $timezone) {
            Event::factory()->count(50)->create([
                'calendar_id' => $this->calendar->id,
                'timezone' => $timezone,
            ]);
        }

        Sanctum::actingAs($this->user);

        $startTime = microtime(true);
        
        $response = $this->getJson('/api/events?timezone=America/New_York', [
            'X-Organization-ID' => $this->organization->id
        ]);
        
        $conversionTime = (microtime(true) - $startTime) * 1000;
        
        $response->assertStatus(200);
        $this->assertLessThan(1500, $conversionTime, 'Timezone conversion should complete under 1.5 seconds');
    }

    public function test_recurring_event_expansion_performance()
    {
        // Create recurring events
        for ($i = 0; $i < 10; $i++) {
            Event::create([
                'calendar_id' => $this->calendar->id,
                'title' => "Daily Standup {$i}",
                'start_at' => Carbon::now()->addDays($i),
                'end_at' => Carbon::now()->addDays($i)->addMinutes(30),
                'rrule' => 'FREQ=DAILY;COUNT=100', // 100 occurrences
                'timezone' => 'UTC',
            ]);
        }

        Sanctum::actingAs($this->user);

        $startTime = microtime(true);
        
        $response = $this->getJson('/api/events?expand=true&start_date=' . Carbon::now()->format('Y-m-d') . '&end_date=' . Carbon::now()->addMonth()->format('Y-m-d'), [
            'X-Organization-ID' => $this->organization->id
        ]);
        
        $expansionTime = (microtime(true) - $startTime) * 1000;
        
        $response->assertStatus(200);
        $this->assertLessThan(3000, $expansionTime, 'Recurring event expansion should complete under 3 seconds');
    }

    public function test_health_check_performance()
    {
        $startTime = microtime(true);
        
        $response = $this->getJson('/api/health');
        
        $healthCheckTime = (microtime(true) - $startTime) * 1000;
        
        $response->assertStatus(200);
        $this->assertLessThan(100, $healthCheckTime, 'Health check should complete under 100ms');
    }

    public function test_detailed_health_check_performance()
    {
        $startTime = microtime(true);
        
        $response = $this->getJson('/api/health/detailed');
        
        $detailedHealthCheckTime = (microtime(true) - $startTime) * 1000;
        
        $response->assertStatus(200);
        $this->assertLessThan(1000, $detailedHealthCheckTime, 'Detailed health check should complete under 1 second');
    }
}
