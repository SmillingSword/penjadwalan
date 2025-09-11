<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FreeBusyService;
use App\Models\User;
use App\Models\Organization;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\FreeBusyBlock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FreeBusyServiceTest extends TestCase
{
    use RefreshDatabase;

    private FreeBusyService $freeBusyService;
    private User $user;
    private Organization $organization;
    private Calendar $calendar;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->freeBusyService = new FreeBusyService();
        
        // Create test data
        $this->organization = Organization::create(['name' => 'Test Organization']);
        $this->user = User::factory()->create([
            'working_hours_start' => '09:00',
            'working_hours_end' => '17:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'buffer_minutes' => 15,
        ]);
        $this->user->organizations()->attach($this->organization->id, ['role' => 'Owner']);
        
        $this->calendar = Calendar::create([
            'organization_id' => $this->organization->id,
            'owner_user_id' => $this->user->id,
            'name' => 'Test Calendar',
            'color' => '#FF0000',
        ]);
    }

    public function test_can_get_free_busy_for_user_with_no_events()
    {
        $startDate = Carbon::parse('2024-01-15 00:00:00');
        $endDate = Carbon::parse('2024-01-15 23:59:59');
        
        $result = $this->freeBusyService->getFreeBusyForUser(
            $this->user,
            $startDate,
            $endDate,
            'UTC'
        );
        
        $this->assertIsArray($result);
        $this->assertEquals($this->user->id, $result['user_id']);
        $this->assertEquals($this->user->email, $result['email']);
        $this->assertIsArray($result['busy_periods']);
        $this->assertIsArray($result['free_periods']);
        $this->assertIsArray($result['working_hours']);
        $this->assertEmpty($result['busy_periods']);
    }

    public function test_can_get_free_busy_with_events()
    {
        // Create a test event
        $event = Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Test Meeting',
            'start_at' => Carbon::parse('2024-01-15 10:00:00'),
            'end_at' => Carbon::parse('2024-01-15 11:00:00'),
            'timezone' => 'UTC',
        ]);

        $startDate = Carbon::parse('2024-01-15 00:00:00');
        $endDate = Carbon::parse('2024-01-15 23:59:59');
        
        $result = $this->freeBusyService->getFreeBusyForUser(
            $this->user,
            $startDate,
            $endDate,
            'UTC'
        );
        
        $this->assertNotEmpty($result['busy_periods']);
        $this->assertCount(1, $result['busy_periods']);
        
        $busyPeriod = $result['busy_periods'][0];
        $this->assertEquals('2024-01-15T10:00:00.000000Z', $busyPeriod['start']);
        $this->assertEquals('2024-01-15T11:00:00.000000Z', $busyPeriod['end']);
        $this->assertEquals('event', $busyPeriod['type']);
        $this->assertEquals('Test Meeting', $busyPeriod['title']);
    }

    public function test_can_get_free_busy_with_buffer_time()
    {
        // Create a test event
        Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Test Meeting',
            'start_at' => Carbon::parse('2024-01-15 10:00:00'),
            'end_at' => Carbon::parse('2024-01-15 11:00:00'),
            'timezone' => 'UTC',
        ]);

        $startDate = Carbon::parse('2024-01-15 00:00:00');
        $endDate = Carbon::parse('2024-01-15 23:59:59');
        
        $result = $this->freeBusyService->getFreeBusyForUser(
            $this->user,
            $startDate,
            $endDate,
            'UTC',
            true // Include buffer time
        );
        
        $this->assertNotEmpty($result['busy_periods']);
        
        // Should have the main event plus buffer periods
        $busyPeriods = collect($result['busy_periods']);
        $eventPeriod = $busyPeriods->where('type', 'event')->first();
        $bufferPeriods = $busyPeriods->where('type', 'buffer');
        
        $this->assertNotNull($eventPeriod);
        $this->assertGreaterThan(0, $bufferPeriods->count());
    }

    public function test_can_get_free_busy_with_free_busy_blocks()
    {
        // Create a free/busy block
        FreeBusyBlock::create([
            'user_id' => $this->user->id,
            'start_at' => Carbon::parse('2024-01-15 14:00:00'),
            'end_at' => Carbon::parse('2024-01-15 15:00:00'),
            'type' => 'busy',
            'title' => 'External Meeting',
        ]);

        $startDate = Carbon::parse('2024-01-15 00:00:00');
        $endDate = Carbon::parse('2024-01-15 23:59:59');
        
        $result = $this->freeBusyService->getFreeBusyForUser(
            $this->user,
            $startDate,
            $endDate,
            'UTC'
        );
        
        $this->assertNotEmpty($result['busy_periods']);
        $this->assertCount(1, $result['busy_periods']);
        
        $busyPeriod = $result['busy_periods'][0];
        $this->assertEquals('2024-01-15T14:00:00.000000Z', $busyPeriod['start']);
        $this->assertEquals('2024-01-15T15:00:00.000000Z', $busyPeriod['end']);
        $this->assertEquals('external', $busyPeriod['type']);
        $this->assertEquals('External Meeting', $busyPeriod['title']);
    }

    public function test_can_get_multiple_users_free_busy()
    {
        // Create another user
        $user2 = User::factory()->create([
            'working_hours_start' => '08:00',
            'working_hours_end' => '16:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ]);
        $user2->organizations()->attach($this->organization->id, ['role' => 'Member']);

        $calendar2 = Calendar::create([
            'organization_id' => $this->organization->id,
            'owner_user_id' => $user2->id,
            'name' => 'User 2 Calendar',
            'color' => '#00FF00',
        ]);

        // Create events for both users
        Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'User 1 Meeting',
            'start_at' => Carbon::parse('2024-01-15 10:00:00'),
            'end_at' => Carbon::parse('2024-01-15 11:00:00'),
            'timezone' => 'UTC',
        ]);

        Event::create([
            'calendar_id' => $calendar2->id,
            'title' => 'User 2 Meeting',
            'start_at' => Carbon::parse('2024-01-15 14:00:00'),
            'end_at' => Carbon::parse('2024-01-15 15:00:00'),
            'timezone' => 'UTC',
        ]);

        $users = collect([$this->user, $user2]);
        $startDate = Carbon::parse('2024-01-15 00:00:00');
        $endDate = Carbon::parse('2024-01-15 23:59:59');
        
        $result = $this->freeBusyService->getMultipleUsersFreeBusy(
            $users,
            $startDate,
            $endDate,
            'UTC'
        );
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('users', $result);
        $this->assertCount(2, $result['users']);
        
        // Check that each user has their respective busy periods
        $user1Data = collect($result['users'])->where('user_id', $this->user->id)->first();
        $user2Data = collect($result['users'])->where('user_id', $user2->id)->first();
        
        $this->assertNotNull($user1Data);
        $this->assertNotNull($user2Data);
        $this->assertCount(1, $user1Data['busy_periods']);
        $this->assertCount(1, $user2Data['busy_periods']);
    }

    public function test_can_find_common_availability()
    {
        $user2 = User::factory()->create([
            'working_hours_start' => '09:00',
            'working_hours_end' => '17:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ]);
        $user2->organizations()->attach($this->organization->id, ['role' => 'Member']);

        $calendar2 = Calendar::create([
            'organization_id' => $this->organization->id,
            'owner_user_id' => $user2->id,
            'name' => 'User 2 Calendar',
            'color' => '#00FF00',
        ]);

        // Create conflicting events
        Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'User 1 Meeting',
            'start_at' => Carbon::parse('2024-01-15 10:00:00'),
            'end_at' => Carbon::parse('2024-01-15 11:00:00'),
            'timezone' => 'UTC',
        ]);

        Event::create([
            'calendar_id' => $calendar2->id,
            'title' => 'User 2 Meeting',
            'start_at' => Carbon::parse('2024-01-15 14:00:00'),
            'end_at' => Carbon::parse('2024-01-15 15:00:00'),
            'timezone' => 'UTC',
        ]);

        $users = collect([$this->user, $user2]);
        $startDate = Carbon::parse('2024-01-15 09:00:00');
        $endDate = Carbon::parse('2024-01-15 17:00:00');
        
        $result = $this->freeBusyService->findCommonAvailability(
            $users,
            $startDate,
            $endDate,
            60, // 1 hour duration
            'UTC'
        );
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('available_slots', $result);
        $this->assertNotEmpty($result['available_slots']);
        
        // Should find slots between 9-10, 11-14, and 15-17
        $slots = $result['available_slots'];
        $this->assertGreaterThanOrEqual(3, count($slots));
    }

    public function test_respects_working_hours()
    {
        $startDate = Carbon::parse('2024-01-15 00:00:00'); // Monday
        $endDate = Carbon::parse('2024-01-15 23:59:59');
        
        $result = $this->freeBusyService->getFreeBusyForUser(
            $this->user,
            $startDate,
            $endDate,
            'UTC'
        );
        
        $this->assertArrayHasKey('working_hours', $result);
        $workingHours = $result['working_hours'];
        
        $this->assertEquals('09:00', $workingHours['start']);
        $this->assertEquals('17:00', $workingHours['end']);
        $this->assertContains('monday', $workingHours['working_days']);
    }

    public function test_handles_timezone_conversion()
    {
        // Create event in UTC
        Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'UTC Meeting',
            'start_at' => Carbon::parse('2024-01-15 15:00:00', 'UTC'), // 3 PM UTC
            'end_at' => Carbon::parse('2024-01-15 16:00:00', 'UTC'),   // 4 PM UTC
            'timezone' => 'UTC',
        ]);

        $startDate = Carbon::parse('2024-01-15 00:00:00');
        $endDate = Carbon::parse('2024-01-15 23:59:59');
        
        // Get free/busy in New York timezone
        $result = $this->freeBusyService->getFreeBusyForUser(
            $this->user,
            $startDate,
            $endDate,
            'America/New_York'
        );
        
        $this->assertNotEmpty($result['busy_periods']);
        $busyPeriod = $result['busy_periods'][0];
        
        // Should be converted to New York time (10 AM - 11 AM EST, assuming standard time)
        $this->assertStringContains('T10:00:00', $busyPeriod['start']);
        $this->assertStringContains('T11:00:00', $busyPeriod['end']);
    }

    public function test_can_check_availability_for_time_slot()
    {
        // Create a conflicting event
        Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Existing Meeting',
            'start_at' => Carbon::parse('2024-01-15 10:00:00'),
            'end_at' => Carbon::parse('2024-01-15 11:00:00'),
            'timezone' => 'UTC',
        ]);

        $users = collect([$this->user]);
        
        // Check availability for conflicting time
        $conflictResult = $this->freeBusyService->checkAvailability(
            $users,
            Carbon::parse('2024-01-15 10:30:00'),
            Carbon::parse('2024-01-15 11:30:00'),
            'UTC'
        );
        
        $this->assertFalse($conflictResult['available']);
        $this->assertNotEmpty($conflictResult['conflicts']);
        
        // Check availability for free time
        $freeResult = $this->freeBusyService->checkAvailability(
            $users,
            Carbon::parse('2024-01-15 12:00:00'),
            Carbon::parse('2024-01-15 13:00:00'),
            'UTC'
        );
        
        $this->assertTrue($freeResult['available']);
        $this->assertEmpty($freeResult['conflicts']);
    }

    public function test_handles_all_day_events()
    {
        // Create an all-day event
        Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'All Day Event',
            'start_at' => Carbon::parse('2024-01-15 00:00:00'),
            'end_at' => Carbon::parse('2024-01-15 23:59:59'),
            'all_day' => true,
            'timezone' => 'UTC',
        ]);

        $startDate = Carbon::parse('2024-01-15 00:00:00');
        $endDate = Carbon::parse('2024-01-15 23:59:59');
        
        $result = $this->freeBusyService->getFreeBusyForUser(
            $this->user,
            $startDate,
            $endDate,
            'UTC'
        );
        
        $this->assertNotEmpty($result['busy_periods']);
        $busyPeriod = $result['busy_periods'][0];
        
        $this->assertEquals('All Day Event', $busyPeriod['title']);
        $this->assertTrue($busyPeriod['all_day'] ?? false);
    }

    public function test_can_get_availability_summary()
    {
        // Create some events
        Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Morning Meeting',
            'start_at' => Carbon::parse('2024-01-15 09:00:00'),
            'end_at' => Carbon::parse('2024-01-15 10:00:00'),
            'timezone' => 'UTC',
        ]);

        Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Afternoon Meeting',
            'start_at' => Carbon::parse('2024-01-15 14:00:00'),
            'end_at' => Carbon::parse('2024-01-15 15:00:00'),
            'timezone' => 'UTC',
        ]);

        $startDate = Carbon::parse('2024-01-15 00:00:00');
        $endDate = Carbon::parse('2024-01-15 23:59:59');
        
        $result = $this->freeBusyService->getAvailabilitySummary(
            $this->user,
            $startDate,
            $endDate,
            'UTC'
        );
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_busy_minutes', $result);
        $this->assertArrayHasKey('total_free_minutes', $result);
        $this->assertArrayHasKey('busy_percentage', $result);
        $this->assertArrayHasKey('free_percentage', $result);
        
        $this->assertEquals(120, $result['total_busy_minutes']); // 2 hours
        $this->assertGreaterThan(0, $result['total_free_minutes']);
    }
}
