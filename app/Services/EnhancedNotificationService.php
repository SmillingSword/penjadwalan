<?php

namespace App\Services;

use App\Services\PusherBeamsService;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class EnhancedNotificationService
{
    protected $pusherBeamsService;

    public function __construct(PusherBeamsService $pusherBeamsService)
    {
        $this->pusherBeamsService = $pusherBeamsService;
    }

    /**
     * Send chat notification via push
     */
    public function sendChatNotification(User $user, array $messageData)
    {
        $notification = [
            'title' => 'New Message',
            'body' => $messageData['preview'] ?? 'You have a new message',
            'icon' => asset('favicon.ico'),
            'data' => [
                'type' => 'chat',
                'conversation_id' => $messageData['conversation_id'],
                'sender_name' => $messageData['sender_name'],
                'url' => '/chat/' . $messageData['conversation_id']
            ]
        ];

        return $this->pusherBeamsService->sendToUsers([(string) $user->id], $notification);
    }

    /**
     * Send event reminder via push
     */
    public function sendEventReminderNotification(User $user, array $eventData)
    {
        $notification = [
            'title' => 'Event Reminder',
            'body' => $eventData['title'] . ' starts in ' . $eventData['time_until'],
            'icon' => asset('favicon.ico'),
            'data' => [
                'type' => 'reminder',
                'event_id' => $eventData['event_id'],
                'event_title' => $eventData['title'],
                'url' => '/events/' . $eventData['event_id']
            ]
        ];

        return $this->pusherBeamsService->sendToUsers([(string) $user->id], $notification);
    }

    /**
     * Send general notification via push
     */
    public function sendGeneralNotification(User $user, string $title, string $body, array $data = [])
    {
        $notification = [
            'title' => $title,
            'body' => $body,
            'icon' => asset('favicon.ico'),
            'data' => array_merge([
                'type' => 'general',
                'timestamp' => now()->toISOString()
            ], $data)
        ];

        return $this->pusherBeamsService->sendToUsers([(string) $user->id], $notification);
    }

    /**
     * Send notification to multiple users
     */
    public function sendBulkNotification(array $userIds, string $title, string $body, array $data = [])
    {
        $notification = [
            'title' => $title,
            'body' => $body,
            'icon' => asset('favicon.ico'),
            'data' => array_merge([
                'type' => 'bulk',
                'timestamp' => now()->toISOString()
            ], $data)
        ];

        return $this->pusherBeamsService->sendToUsers($userIds, $notification);
    }

    /**
     * Send notification to interest group
     */
    public function sendInterestNotification(array $interests, string $title, string $body, array $data = [])
    {
        $notification = [
            'title' => $title,
            'body' => $body,
            'icon' => asset('favicon.ico'),
            'data' => array_merge([
                'type' => 'interest',
                'timestamp' => now()->toISOString()
            ], $data)
        ];

        return $this->pusherBeamsService->sendToInterests($interests, $notification);
    }
}
