<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Services\PusherBeamsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PusherBeamsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $pusherBeamsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->pusherBeamsService = new PusherBeamsService();
    }

    /** @test */
    public function it_can_get_pusher_beams_config()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/push-notifications/config');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'instance_id',
                'service_worker_url'
            ]);
    }

    /** @test */
    public function it_can_generate_user_token()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/push-notifications/user-token');

        $response->assertStatus(200);
    }

    /** @test */
    public function it_can_send_test_notification()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/push-notifications/test');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Test notification sent successfully'
            ]);
    }

    /** @test */
    public function it_integrates_with_chat_notifications()
    {
        // Test integration with chat system
        $response = $this->actingAs($this->user)
            ->postJson('/api/push-notifications/send-to-users', [
                'user_ids' => [(string) $this->user->id],
                'title' => 'New Chat Message',
                'body' => 'You have a new message',
                'data' => [
                    'type' => 'chat',
                    'conversation_id' => 1
                ]
            ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_integrates_with_event_reminders()
    {
        // Test integration with event reminder system
        $response = $this->actingAs($this->user)
            ->postJson('/api/push-notifications/send-to-users', [
                'user_ids' => [(string) $this->user->id],
                'title' => 'Event Reminder',
                'body' => 'Your meeting starts in 15 minutes',
                'data' => [
                    'type' => 'reminder',
                    'event_id' => 1
                ]
            ]);

        $response->assertStatus(200);
    }
}
