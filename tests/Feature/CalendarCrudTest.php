<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Organization;
use App\Models\Calendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CalendarCrudTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $organization;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run the role seeder
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

        // Create test user and organization
        $this->organization = Organization::create(['name' => 'Test Organization']);
        $this->user = User::factory()->create();
        $this->user->organizations()->attach($this->organization->id, ['role' => 'Owner']);
        $this->user->assignRole('Owner');

        // Authenticate user
        Sanctum::actingAs($this->user);
    }

    public function test_user_can_list_calendars()
    {
        // Create some calendars
        Calendar::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'owner_user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/calendars', [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_user_can_create_calendar()
    {
        $calendarData = [
            'name' => 'Test Calendar',
            'description' => 'A test calendar',
            'color' => '#FF0000',
            'is_default' => false,
            'is_public' => true,
        ];

        $response = $this->postJson('/api/calendars', $calendarData, [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Test Calendar');
        $response->assertJsonPath('data.color', '#FF0000');
        $response->assertJsonPath('data.organization_id', $this->organization->id);
        $response->assertJsonPath('data.owner_user_id', $this->user->id);

        $this->assertDatabaseHas('calendars', [
            'name' => 'Test Calendar',
            'organization_id' => $this->organization->id,
            'owner_user_id' => $this->user->id,
        ]);
    }

    public function test_user_can_view_calendar()
    {
        $calendar = Calendar::create([
            'organization_id' => $this->organization->id,
            'owner_user_id' => $this->user->id,
            'name' => 'Test Calendar',
            'color' => '#FF0000',
        ]);

        $response = $this->getJson("/api/calendars/{$calendar->id}", [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $calendar->id);
        $response->assertJsonPath('data.name', 'Test Calendar');
    }

    public function test_user_can_update_calendar()
    {
        $calendar = Calendar::create([
            'organization_id' => $this->organization->id,
            'owner_user_id' => $this->user->id,
            'name' => 'Original Name',
            'color' => '#FF0000',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'color' => '#00FF00',
            'description' => 'Updated description',
        ];

        $response = $this->putJson("/api/calendars/{$calendar->id}", $updateData, [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Updated Name');
        $response->assertJsonPath('data.color', '#00FF00');

        $this->assertDatabaseHas('calendars', [
            'id' => $calendar->id,
            'name' => 'Updated Name',
            'color' => '#00FF00',
        ]);
    }

    public function test_user_can_delete_calendar()
    {
        $calendar = Calendar::create([
            'organization_id' => $this->organization->id,
            'owner_user_id' => $this->user->id,
            'name' => 'Test Calendar',
            'color' => '#FF0000',
        ]);

        $response = $this->deleteJson("/api/calendars/{$calendar->id}", [], [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Calendar deleted successfully']);

        $this->assertDatabaseMissing('calendars', [
            'id' => $calendar->id,
        ]);
    }

    public function test_calendar_creation_requires_valid_data()
    {
        // Test missing required fields
        $response = $this->postJson('/api/calendars', [], [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);

        // Test invalid color format
        $response = $this->postJson('/api/calendars', [
            'name' => 'Test Calendar',
            'color' => 'invalid-color',
        ], [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['color']);
    }

    public function test_user_cannot_access_calendar_from_different_organization()
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

        // Try to access the other organization's calendar
        $response = $this->getJson("/api/calendars/{$otherCalendar->id}", [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(403); // Should be forbidden due to tenant isolation
    }

    public function test_member_cannot_create_calendar()
    {
        // Create a member user
        $member = User::factory()->create();
        $member->organizations()->attach($this->organization->id, ['role' => 'Member']);
        $member->assignRole('Member');

        Sanctum::actingAs($member);

        $calendarData = [
            'name' => 'Test Calendar',
            'color' => '#FF0000',
        ];

        $response = $this->postJson('/api/calendars', $calendarData, [
            'X-Organization-ID' => $this->organization->id
        ]);

        $response->assertStatus(403); // Forbidden
    }
}
