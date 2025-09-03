<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Organization;
use App\Models\Calendar;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run the role seeder
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    public function test_user_cannot_access_other_organization_calendars()
    {
        // Create two organizations
        $org1 = Organization::create(['name' => 'Organization 1']);
        $org2 = Organization::create(['name' => 'Organization 2']);

        // Create users for each organization
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Attach users to their respective organizations
        $user1->organizations()->attach($org1->id, ['role' => 'Owner']);
        $user2->organizations()->attach($org2->id, ['role' => 'Owner']);

        // Create calendars for each organization
        $calendar1 = Calendar::create([
            'organization_id' => $org1->id,
            'owner_user_id' => $user1->id,
            'name' => 'Calendar 1',
            'color' => '#FF0000',
        ]);

        $calendar2 = Calendar::create([
            'organization_id' => $org2->id,
            'owner_user_id' => $user2->id,
            'name' => 'Calendar 2',
            'color' => '#00FF00',
        ]);

        // Authenticate as user1
        Sanctum::actingAs($user1);

        // User1 should be able to access their own organization's calendars
        $response = $this->getJson('/api/calendars', [
            'X-Organization-ID' => $org1->id
        ]);
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $calendar1->id);

        // User1 should NOT be able to access other organization's calendars
        $response = $this->getJson('/api/calendars', [
            'X-Organization-ID' => $org2->id
        ]);
        $response->assertStatus(403);
        $response->assertJson(['error' => 'Access denied to this organization']);
    }

    public function test_user_cannot_access_events_from_other_organization()
    {
        // Create two organizations
        $org1 = Organization::create(['name' => 'Organization 1']);
        $org2 = Organization::create(['name' => 'Organization 2']);

        // Create users for each organization
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Attach users to their respective organizations
        $user1->organizations()->attach($org1->id, ['role' => 'Owner']);
        $user2->organizations()->attach($org2->id, ['role' => 'Owner']);

        // Create calendars for each organization
        $calendar1 = Calendar::create([
            'organization_id' => $org1->id,
            'owner_user_id' => $user1->id,
            'name' => 'Calendar 1',
            'color' => '#FF0000',
        ]);

        $calendar2 = Calendar::create([
            'organization_id' => $org2->id,
            'owner_user_id' => $user2->id,
            'name' => 'Calendar 2',
            'color' => '#00FF00',
        ]);

        // Create events for each calendar
        $event1 = Event::create([
            'calendar_id' => $calendar1->id,
            'title' => 'Event 1',
            'start_at' => now(),
            'end_at' => now()->addHour(),
        ]);

        $event2 = Event::create([
            'calendar_id' => $calendar2->id,
            'title' => 'Event 2',
            'start_at' => now(),
            'end_at' => now()->addHour(),
        ]);

        // Authenticate as user1
        Sanctum::actingAs($user1);

        // User1 should be able to access events from their organization
        $response = $this->getJson('/api/events', [
            'X-Organization-ID' => $org1->id
        ]);
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $event1->id);

        // User1 should NOT be able to access events from other organization
        $response = $this->getJson('/api/events', [
            'X-Organization-ID' => $org2->id
        ]);
        $response->assertStatus(403);
        $response->assertJson(['error' => 'Access denied to this organization']);
    }

    public function test_request_without_organization_id_is_rejected()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Request without organization ID should be rejected
        $response = $this->getJson('/api/calendars');
        $response->assertStatus(400);
        $response->assertJson(['error' => 'Organization ID is required']);
    }

    public function test_unauthenticated_request_is_rejected()
    {
        // Request without authentication should be rejected
        $response = $this->getJson('/api/calendars', [
            'X-Organization-ID' => 'some-uuid'
        ]);
        $response->assertStatus(401);
        $response->assertJson(['error' => 'Unauthenticated']);
    }

    public function test_user_can_access_multiple_organizations_they_belong_to()
    {
        // Create two organizations
        $org1 = Organization::create(['name' => 'Organization 1']);
        $org2 = Organization::create(['name' => 'Organization 2']);

        // Create a user that belongs to both organizations
        $user = User::factory()->create();
        $user->organizations()->attach($org1->id, ['role' => 'Owner']);
        $user->organizations()->attach($org2->id, ['role' => 'Member']);

        // Create calendars for each organization
        $calendar1 = Calendar::create([
            'organization_id' => $org1->id,
            'owner_user_id' => $user->id,
            'name' => 'Calendar 1',
            'color' => '#FF0000',
        ]);

        $calendar2 = Calendar::create([
            'organization_id' => $org2->id,
            'owner_user_id' => $user->id,
            'name' => 'Calendar 2',
            'color' => '#00FF00',
        ]);

        // Authenticate as the user
        Sanctum::actingAs($user);

        // User should be able to access both organizations
        $response1 = $this->getJson('/api/calendars', [
            'X-Organization-ID' => $org1->id
        ]);
        $response1->assertStatus(200);

        $response2 = $this->getJson('/api/calendars', [
            'X-Organization-ID' => $org2->id
        ]);
        $response2->assertStatus(200);
    }
}
