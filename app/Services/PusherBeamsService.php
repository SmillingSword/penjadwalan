<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PusherBeamsService
{
    private $instanceId;
    private $secretKey;
    private $baseUrl;

    public function __construct()
    {
        $this->instanceId = config('services.pusher_beams.instance_id');
        $this->secretKey = config('services.pusher_beams.secret_key');
        $this->baseUrl = "https://{$this->instanceId}.pushnotifications.pusher.com";
    }

    /**
     * Send push notification to specific users
     */
    public function sendToUsers(array $userIds, array $notification)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/publishes/users", [
                'users' => $userIds,
                'web' => [
                    'notification' => [
                        'title' => $notification['title'],
                        'body' => $notification['body'],
                        'icon' => $notification['icon'] ?? '/favicon.ico',
                        'badge' => $notification['badge'] ?? '/favicon.ico',
                        'data' => $notification['data'] ?? [],
                    ]
                ]
            ]);

            if ($response->successful()) {
                Log::info('Push notification sent successfully', [
                    'users' => $userIds,
                    'notification' => $notification
                ]);
                return $response->json();
            } else {
                Log::error('Failed to send push notification', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception sending push notification', [
                'error' => $e->getMessage(),
                'users' => $userIds,
                'notification' => $notification
            ]);
            return false;
        }
    }

    /**
     * Send push notification to interests (topics)
     */
    public function sendToInterests(array $interests, array $notification)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/publishes/interests", [
                'interests' => $interests,
                'web' => [
                    'notification' => [
                        'title' => $notification['title'],
                        'body' => $notification['body'],
                        'icon' => $notification['icon'] ?? '/favicon.ico',
                        'badge' => $notification['badge'] ?? '/favicon.ico',
                        'data' => $notification['data'] ?? [],
                    ]
                ]
            ]);

            if ($response->successful()) {
                Log::info('Push notification sent to interests successfully', [
                    'interests' => $interests,
                    'notification' => $notification
                ]);
                return $response->json();
            } else {
                Log::error('Failed to send push notification to interests', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception sending push notification to interests', [
                'error' => $e->getMessage(),
                'interests' => $interests,
                'notification' => $notification
            ]);
            return false;
        }
    }

    /**
     * Generate authentication token for user
     */
    public function generateUserToken(string $userId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/user_auth", [
                'user_id' => $userId
            ]);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('Failed to generate user token', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'user_id' => $userId
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception generating user token', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            return false;
        }
    }

    /**
     * Check if service is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->instanceId) && !empty($this->secretKey);
    }

    /**
     * Get instance ID
     */
    public function getInstanceId(): ?string
    {
        return $this->instanceId;
    }
}
