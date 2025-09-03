<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Organization;
use App\Models\Calendar;
use App\Models\FreeBusyBlock;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run the role seeder
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    public function test_user_has_uuid_primary_key()
    {
        $user = User::factory()->create();
        
        $this->assertIsString($user->id);
        $this->assertEquals(36, strlen($user->id)); // UUID length
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $user->id);
    }

    public function test_user_has_default_timezone_and_locale()
    {
        $user = User::factory()->create();
        
        $this->assertEquals('Asia/Jakarta', $user->timezone);
        $this->assertEquals('id-ID', $user->locale);
    }

    public function test_user_can_belong_to_multiple_organizations()
    {
        $user = User::factory()->create();
        $org1 = Organization::create(['name' => 'Organization 1']);
        $org2 = Organization::create(['name' => 'Organization 2']);

        $user->organizations()->attach($org1->id, ['role' => 'Owner']);
        $user->organizations()->attach($org2->id, ['role' => 'Member']);

        $this->assertCount(2, $user->organizations);
        $this->assertEquals('Owner', $user->organizations->first()->pivot->role);
        $this->assertEquals('Member', $user->organizations->last()->pivot->role);
    }

    public function test_user_can_have_multiple_calendars()
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'Test Organization']);

        $calendar1 = Calendar::create([
            'organization_id' => $organization->id,
            'owner_user_id' => $user->id,
            'name' => 'Calendar 1',
            'color' => '#FF0000',
        ]);

        $calendar2 = Calendar::create([
            'organization_id' => $organization->id,
            'owner_user_id' => $user->id,
            'name' => 'Calendar 2',
            'color' => '#00FF00',
        ]);

        $this->assertCount(2, $user->calendars);
        $this->assertTrue($user->calendars->contains($calendar1));
        $this->assertTrue($user->calendars->contains($calendar2));
    }

    public function test_user_can_have_free_busy_blocks()
    {
        $user = User::factory()->create();

        $freeBusyBlock = FreeBusyBlock::create([
            'user_id' => $user->id,
            'start_at' => now(),
            'end_at' => now()->addHour(),
            'source' => 'manual',
        ]);

        $this->assertCount(1, $user->freeBusyBlocks);
        $this->assertTrue($user->freeBusyBlocks->contains($freeBusyBlock));
    }

    public function test_user_can_have_audit_logs()
    {
        $user = User::factory()->create();

        $auditLog = AuditLog::create([
            'actor_id' => $user->id,
            'action' => 'create',
            'entity' => 'Calendar',
            'entity_id' => 'some-uuid',
            'meta' => ['name' => 'Test Calendar'],
            'created_at' => now(),
        ]);

        $this->assertCount(1, $user->auditLogs);
        $this->assertTrue($user->auditLogs->contains($auditLog));
    }

    public function test_user_fillable_attributes()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'timezone' => 'America/New_York',
            'locale' => 'en-US',
        ];

        $user = User::create($userData);

        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertEquals('America/New_York', $user->timezone);
        $this->assertEquals('en-US', $user->locale);
    }

    public function test_user_hidden_attributes()
    {
        $user = User::factory()->create();
        $userArray = $user->toArray();

        $this->assertArrayNotHasKey('password', $userArray);
        $this->assertArrayNotHasKey('remember_token', $userArray);
    }

    public function test_user_password_is_hashed()
    {
        $user = User::factory()->create([
            'password' => 'plain-password'
        ]);

        $this->assertNotEquals('plain-password', $user->password);
        $this->assertTrue(password_verify('plain-password', $user->password));
    }

    public function test_user_email_verified_at_is_cast_to_datetime()
    {
        $user = User::factory()->create([
            'email_verified_at' => '2024-01-15 10:00:00'
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->email_verified_at);
    }

    public function test_user_has_roles_trait()
    {
        $user = User::factory()->create();
        
        $this->assertTrue(method_exists($user, 'assignRole'));
        $this->assertTrue(method_exists($user, 'hasRole'));
        $this->assertTrue(method_exists($user, 'getRoleNames'));
    }

    public function test_user_can_be_assigned_roles()
    {
        $user = User::factory()->create();
        
        $user->assignRole('Owner');
        
        $this->assertTrue($user->hasRole('Owner'));
        $this->assertContains('Owner', $user->getRoleNames());
    }
}
