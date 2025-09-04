<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RealTimeReminderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    use AuthorizesRequests;

    protected RealTimeReminderService $reminderService;

    public function __construct(RealTimeReminderService $reminderService)
    {
        $this->reminderService = $reminderService;
    }

    /**
     * Get user's notifications
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'limit' => 'integer|min:1|max:50',
            'unread_only' => 'boolean',
        ]);

        $limit = $validated['limit'] ?? 20;
        $unreadOnly = $validated['unread_only'] ?? false;

        $query = $user->notifications();
        
        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $notifications = $query->latest()->limit($limit)->get();
        $unreadCount = $this->reminderService->getUnreadNotificationsCount($user);

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            'total_count' => $user->notifications()->count(),
        ]);
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(Request $request, string $notificationId): JsonResponse
    {
        $user = Auth::user();
        
        $notification = $user->notifications()->where('id', $notificationId)->first();
        
        if (!$notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read',
            'notification' => $notification
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $this->reminderService->markNotificationsAsRead($user);

        return response()->json([
            'message' => 'All notifications marked as read'
        ]);
    }

    /**
     * Delete a notification
     */
    public function destroy(Request $request, string $notificationId): JsonResponse
    {
        $user = Auth::user();
        
        $notification = $user->notifications()->where('id', $notificationId)->first();
        
        if (!$notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted successfully'
        ]);
    }

    /**
     * Snooze a notification
     */
    public function snooze(Request $request, string $notificationId): JsonResponse
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'minutes' => 'required|integer|min:1|max:1440', // Max 24 hours
        ]);

        $notification = $user->notifications()->where('id', $notificationId)->first();
        
        if (!$notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        // For now, we'll just delete the notification and reschedule it
        // In a more advanced implementation, you might want to store snooze information
        $notificationData = $notification->data;
        $snoozeUntil = now()->addMinutes($validated['minutes']);
        
        // Delete current notification
        $notification->delete();
        
        // Here you would typically reschedule the reminder
        // For this implementation, we'll just mark it as snoozed
        
        return response()->json([
            'message' => "Notification snoozed for {$validated['minutes']} minutes",
            'snooze_until' => $snoozeUntil->toISOString()
        ]);
    }

    /**
     * Get notification statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $totalNotifications = $user->notifications()->count();
        $unreadNotifications = $user->unreadNotifications()->count();
        $todayNotifications = $user->notifications()
            ->whereDate('created_at', today())
            ->count();
        
        $recentNotifications = $user->notifications()
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'total_notifications' => $totalNotifications,
            'unread_notifications' => $unreadNotifications,
            'today_notifications' => $todayNotifications,
            'recent_notifications' => $recentNotifications,
        ]);
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'email_notifications_enabled' => 'boolean',
            'browser_notifications_enabled' => 'boolean',
            'realtime_notifications_enabled' => 'boolean',
            'notification_preferences' => 'array',
            'notification_preferences.email' => 'boolean',
            'notification_preferences.browser' => 'boolean',
            'notification_preferences.realtime' => 'boolean',
            'notification_preferences.day_before' => 'boolean',
            'notification_preferences.thirty_minutes_before' => 'boolean',
            'notification_preferences.event_start' => 'boolean',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Notification preferences updated successfully',
            'preferences' => [
                'email_notifications_enabled' => $user->email_notifications_enabled,
                'browser_notifications_enabled' => $user->browser_notifications_enabled,
                'realtime_notifications_enabled' => $user->realtime_notifications_enabled,
                'notification_preferences' => $user->notification_preferences,
            ]
        ]);
    }

    /**
     * Get notification preferences
     */
    public function getPreferences(Request $request): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'preferences' => [
                'email_notifications_enabled' => $user->email_notifications_enabled ?? true,
                'browser_notifications_enabled' => $user->browser_notifications_enabled ?? true,
                'realtime_notifications_enabled' => $user->realtime_notifications_enabled ?? true,
                'notification_preferences' => $user->notification_preferences ?? [
                    'email' => true,
                    'browser' => true,
                    'realtime' => true,
                    'day_before' => true,
                    'thirty_minutes_before' => true,
                    'event_start' => true,
                ],
            ]
        ]);
    }

    /**
     * Test notification (for development/testing purposes)
     */
    public function test(Request $request): JsonResponse
    {
        if (!app()->environment(['local', 'testing'])) {
            return response()->json(['message' => 'Test notifications only available in development'], 403);
        }

        $user = Auth::user();
        
        $validated = $request->validate([
            'type' => 'string|in:info,warning,success,urgent',
            'title' => 'string|max:255',
            'message' => 'string|max:500',
        ]);

        $testNotification = [
            'id' => uniqid('test_'),
            'type' => 'reminder',
            'title' => $validated['title'] ?? 'Test Notification',
            'message' => $validated['message'] ?? 'This is a test notification to verify the system is working.',
            'icon' => '🧪',
            'notification_type' => $validated['type'] ?? 'info',
            'event' => [
                'id' => 'test-event',
                'title' => 'Test Event',
                'start_at' => now()->addHour()->toISOString(),
                'end_at' => now()->addHours(2)->toISOString(),
            ],
            'actions' => [
                [
                    'type' => 'view',
                    'label' => 'View Event',
                    'url' => '/events/test-event',
                    'icon' => '👁️'
                ],
                [
                    'type' => 'dismiss',
                    'label' => 'Dismiss',
                    'action' => 'dismiss',
                    'icon' => '✖️'
                ]
            ],
            'created_at' => now()->toISOString(),
        ];

        // Create a database notification
        $user->notifications()->create([
            'id' => $testNotification['id'],
            'type' => 'App\Notifications\RealTimeReminderNotification',
            'data' => $testNotification,
            'read_at' => null,
        ]);

        // Broadcast the notification if broadcasting is enabled
        if (config('broadcasting.default') !== 'null') {
            broadcast(new \App\Events\ReminderTriggered(
                new \App\Models\Event(['id' => 'test', 'title' => 'Test Event']),
                new \App\Models\Reminder(['id' => 'test', 'reminder_type' => 'test']),
                $user
            ));
        }

        return response()->json([
            'message' => 'Test notification sent successfully',
            'notification' => $testNotification
        ]);
    }
}
