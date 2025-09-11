<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Organization;
use App\Models\Calendar;
use App\Models\Event;
use App\Services\FreeBusyService;
use App\Services\SchedulingAssistantService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SchedulingAssistantTest extends TestCase
{
    use RefreshDatabase;

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
        $this->user = User::factory()->create([
            'timezone' => 'America/New_York',
            'working_hours_start' => '09:00',
            'working_hours_end' => '17:00',
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

    public function test_can_get_user_free_busy_information()
    {
        // Create some events for the user
        $startDate = Carbon::now()->startOfDay();
        $endDate = $startDate->clone()->addDays(7);

        Event::create([
            'calendar_id' => $this->calendar->id,
            'title' => 'Busy Meeting',
            'start_at' => $startDate->clone()->addHours(10),
            'end_at' => $startDate->clone()->addHours(11),
        ]);

        $response = $this->getJson("/api/scheduling/freebusy/{$this->user->id}?" . http_build_query([
            'start_date' => $startDate->toISOString(),
            'end_date' => $endDate->toISOString(),
            'timezone' => 'America/New_York',
        ]), [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'user_id',
            'email',
            'name',
            'timezone',
            'working_hours',
            'busy_periods',
            'free_periods',
            'availability_summary'
        ]);
        
        $this->assertCount(1, $response->json('busy_periods'));
    }

    public function test_can_get_multiple_users_free_busy()
    {
        $user2 = User::factory()->create();
        $user2->organizations()->attach($this->organization->id, ['role' => 'Member']);

        $startDate = Carbon::now()->startOfDay();
        $endDate = $startDate->clone()->addDays(1);

        $response = $this->postJson('/api/scheduling/freebusy/multiple', [
            'user_ids' => [$this->user->id, $user2->id],
            'start_date' => $startDate->toISOString(),
            'end_date' => $endDate->toISOString(),
            'timezone' => 'America/New_York',
        ], [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'users' => [
                '*' => [
                    'user_id',
                    'email',
                    'busy_periods',
                    'free_periods'
                ]
            ],
            'common_availability'
        ]);
        
        $this->assertCount(2, $response->json('users'));
    }

    public function test_can_find_available_slots()
    {
        $startDate = Carbon::now()->startOfDay();
        $endDate = $startDate->clone()->addDays(7);

        $response = $this->postJson('/api/scheduling/available-slots', [
            'participants' => [$this->user->email],
            'duration_minutes' => 60,
            'start_date' => $startDate->toISOString(),
            'end_date' => $endDate->toISOString(),
            'timezone' => 'America/New_York',
            'working_hours_only' => true,
        ], [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'available_slots' => [
                '*' => [
                    'start_time',
                    'end_time',
                    'duration_minutes',
                    'score',
                    'participants_available'
                ]
            ],
            'search_criteria',
            'total_slots_found'
        ]);
    }

    public function test_can_suggest_meeting_times()
    {
        $response = $this->postJson('/api/scheduling/suggest-times', [
            'participants' => [$this->user->email],
            'duration_minutes' => 60,
            'preferred_times' => ['10:00', '14:00'],
            'working_hours_only' => true,
            'buffer_minutes' => 15,
            'max_suggestions' => 5,
        ], [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'suggestions' => [
                '*' => [
                    'start_time',
                    'end_time',
                    'score',
                    'score_breakdown',
                    'participants_available',
                    'conflicts'
                ]
            ],
            'criteria_used',
            'total_suggestions'
        ]);
    }

    public function test_can_validate_meeting_time()
    {
        $meetingTime = Carbon::now()->addDays(1)->setHour(10)->setMinute(0);

        $response = $this->postJson('/api/scheduling/validate-time', [
            'participants' => [$this->user->email],
            'start_time' => $meetingTime->toISOString(),
            'duration_minutes' => 60,
            'timezone' => 'America/New_York',
        ], [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'is_valid',
            'conflicts',
            'participants_availability',
            'recommendations'
        ]);
    }

    public function test_can_create_optimal_meeting()
    {
        $response = $this->postJson('/api/scheduling/create-optimal-meeting', [
            'title' => 'Team Meeting',
            'description' => 'Weekly team sync',
            'participants' => [
                ['email' => $this->user->email, 'role' => 'required']
            ],
            'duration_minutes' => 60,
            'preferred_times' => ['10:00'],
            'working_hours_only' => true,
            'calendar_id' => $this->calendar->id,
        ], [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'event' => [
                'id',
                'title',
                'start_at',
                'end_at',
                'participants'
            ],
            'scheduling_details' => [
                'selected_time_score',
                'alternatives_considered',
                'conflicts_resolved'
            ]
        ]);

        // Verify event was created
        $this->assertDatabaseHas('events', [
            'title' => 'Team Meeting',
            'calendar_id' => $this->calendar->id,
        ]);
    }

    public function test_can_check_slot_availability()
    {
        $slotTime = Carbon::now()->addDays(1)->setHour(10)->setMinute(0);

        $response = $this->postJson('/api/scheduling/check-availability', [
            'participants' => [$this->user->email],
            'start_time' => $slotTime->toISOString(),
            'end_time' => $slotTime->clone()->addHour()->toISOString(),
            'timezone' => 'America/New_York',
        ], [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'available',
            'participants_status',
            'conflicts',
            'score'
        ]);
    }

    public function test_can_get_next_available_slot()
    {
        $response = $this->postJson('/api/scheduling/next-available', [
            'participants' => [$this->user->email],
            'duration_minutes' => 60,
            'after_time' => Carbon::now()->toISOString(),
            'working_hours_only' => true,
        ], [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'next_slot' => [
                'start_time',
                'end_time',
                'score'
            ],
            'search_criteria'
        ]);
    }

    public function test_can_analyze_meeting_patterns()
    {
        // Create some historical events
        for ($i = 0; $i < 5; $i++) {
            Event::create([
                'calendar_id' => $this->calendar->id,
                'title' => "Meeting {$i}",
                'start_at' => Carbon::now()->subDays($i)->setHour(10),
                'end_at' => Carbon::now()->subDays($i)->setHour(11),
            ]);
        }

        $response = $this->getJson("/api/scheduling/patterns/{$this->user->id}?" . http_build_query([
            'period' => 'last_30_days',
        ]), [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'user_id',
            'analysis_period',
            'meeting_frequency',
            'preferred_times',
            'preferred_days',
            'average_meeting_duration',
            'busiest_hours',
            'recommendations'
        ]);
    }

    public function test_scheduling_respects_working_hours()
    {
        // Set user working hours to 9-17
        $this->user->update([
            'working_hours_start' => '09:00',
            'working_hours_end' => '17:00',
        ]);

        $response = $this->postJson('/api/scheduling/suggest-times', [
            'participants' => [$this->user->email],
            'duration_minutes' => 60,
            'working_hours_only' => true,
        ], [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(200);
        
        $suggestions = $response->json('suggestions');
        foreach ($suggestions as $suggestion) {
            $startTime = Carbon::parse($suggestion['start_time']);
            $this->assertGreaterThanOrEqual(9, $startTime->hour);
            $this->assertLessThan(17, $startTime->hour);
        }
    }

    public function test_scheduling_handles_timezone_conversion()
    {
        $response = $this->postJson('/api/scheduling/suggest-times', [
            'participants' => [$this->user->email],
            'duration_minutes' => 60,
            'timezone' => 'Europe/London', // Different from user's timezone
        ], [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'suggestions',
            'criteria_used' => [
                'timezone'
            ]
        ]);
        
        $this->assertEquals('Europe/London', $response->json('criteria_used.timezone'));
    }

    public function test_scheduling_requires_authentication()
    {
        // Remove authentication
        $this->app['auth']->forgetGuards();

        $response = $this->getJson("/api/scheduling/freebusy/{$this->user->id}");
        $response->assertStatus(401);
    }

    public function test_scheduling_respects_tenant_isolation()
    {
        // Create another organization and user
        $otherOrg = Organization::create(['name' => 'Other Organization']);
        $otherUser = User::factory()->create();
        $otherUser->organizations()->attach($otherOrg->id, ['role' => 'Owner']);

        // Try to access other user's free/busy from different organization
        $response = $this->getJson("/api/scheduling/freebusy/{$otherUser->id}", [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(403);
    }
}
