<?php

use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SchedulingController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\RealTimeChatController;
use App\Http\Controllers\Api\PushNotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Protected API routes with authentication and tenant isolation
Route::middleware(['auth:sanctum', 'tenant.isolation'])->group(function () {
    // Calendars API
    Route::apiResource('calendars', CalendarController::class);
    Route::get('calendars/{calendar}/events', [CalendarController::class, 'events']);
    
    // Events API
    Route::apiResource('events', EventController::class);
    Route::put('events/{event}/participants', [EventController::class, 'updateParticipants']);
    
    // Search endpoints
    Route::get('search', [SearchController::class, 'search']);
    Route::get('search/events', [SearchController::class, 'searchEvents']);
    Route::get('search/participants', [SearchController::class, 'searchParticipants']);
    Route::get('search/suggestions', [SearchController::class, 'suggestions']);
    
    // Scheduling Assistant endpoints
    Route::prefix('scheduling')->group(function () {
        // Free/Busy information
        Route::get('freebusy/{user}', [SchedulingController::class, 'getFreeBusy']);
        Route::post('freebusy/multiple', [SchedulingController::class, 'getMultipleFreeBusy']);
        
        // Available slots
        Route::post('available-slots', [SchedulingController::class, 'findAvailableSlots']);
        Route::post('next-available', [SchedulingController::class, 'getNextAvailableSlot']);
        Route::post('check-availability', [SchedulingController::class, 'checkSlotAvailability']);
        
        // Meeting suggestions and creation
        Route::post('suggest-times', [SchedulingController::class, 'suggestMeetingTimes']);
        Route::post('validate-time', [SchedulingController::class, 'validateMeetingTime']);
        Route::post('create-optimal-meeting', [SchedulingController::class, 'createOptimalMeeting']);
        
        // Meeting pattern analysis
        Route::get('patterns/{user}', [SchedulingController::class, 'analyzeMeetingPatterns']);
    });
    
    // Notifications API
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/stats', [NotificationController::class, 'stats']);
        Route::get('/preferences', [NotificationController::class, 'getPreferences']);
        Route::post('/preferences', [NotificationController::class, 'updatePreferences']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::post('/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/{notification}/snooze', [NotificationController::class, 'snooze']);
        Route::delete('/{notification}', [NotificationController::class, 'destroy']);
        
        // Test notification (development only)
        Route::post('/test', [NotificationController::class, 'test']);
    });

    // Push Notifications API (Pusher Beams)
    Route::prefix('push-notifications')->group(function () {
        Route::get('/config', [PushNotificationController::class, 'getConfig']);
        Route::get('/user-token', [PushNotificationController::class, 'getUserToken']);
        Route::post('/send-to-users', [PushNotificationController::class, 'sendToUsers']);
        Route::post('/send-to-interests', [PushNotificationController::class, 'sendToInterests']);
        Route::post('/test', [PushNotificationController::class, 'sendTestNotification']);
    });

    
    
    // Organizations API
    // Route::apiResource('organizations', OrganizationController::class);
    
    // Webhook subscriptions API
    // Route::apiResource('webhook-subscriptions', WebhookSubscriptionController::class);
});

// Chat API - separate from tenant isolation since chat is global
Route::prefix('chat')->middleware(['web', 'auth'])->group(function () {
    Route::get('conversations', [ChatController::class, 'getConversations']);
    Route::get('users', [ChatController::class, 'getAllUsers']);
    Route::get('online-users', [ChatController::class, 'getOnlineUsers']);
    Route::post('conversations/private', [ChatController::class, 'createPrivateConversation']);
    Route::get('conversations/{conversation}/messages', [ChatController::class, 'getMessages']);
    Route::post('conversations/{conversation}/messages', [ChatController::class, 'sendMessage']);
    Route::post('conversations/{conversation}/read', [ChatController::class, 'markAsRead']);
    Route::post('online-status', [ChatController::class, 'updateOnlineStatus']);
    Route::get('search-users', [ChatController::class, 'searchUsers']);
});

// Real-time Chat API - for enhanced real-time features
Route::prefix('realtime-chat')->middleware(['web', 'auth'])->group(function () {
    Route::get('conversations', [RealTimeChatController::class, 'getConversations']);
    Route::get('users', [RealTimeChatController::class, 'getAllUsers']);
    Route::get('online-users', [RealTimeChatController::class, 'getOnlineUsers']);
    Route::post('conversations/private', [RealTimeChatController::class, 'createPrivateConversation']);
    Route::get('conversations/{conversation}/messages', [RealTimeChatController::class, 'getMessages']);
    Route::post('conversations/{conversation}/messages', [RealTimeChatController::class, 'sendMessage']);
    Route::post('conversations/{conversation}/read', [RealTimeChatController::class, 'markAsRead']);
    Route::post('online-status', [RealTimeChatController::class, 'updateOnlineStatus']);
    Route::get('search-users', [RealTimeChatController::class, 'searchUsers']);
    
    // Typing indicator endpoints
    Route::post('conversations/{conversation}/typing/start', [RealTimeChatController::class, 'startTyping']);
    Route::post('conversations/{conversation}/typing/stop', [RealTimeChatController::class, 'stopTyping']);
    Route::get('conversations/{conversation}/typing', [RealTimeChatController::class, 'getTypingUsers']);
    
    // Heartbeat for maintaining online status
    Route::post('heartbeat', [RealTimeChatController::class, 'heartbeat']);
});

// Health check endpoints (no authentication required)
Route::get('/health', [HealthController::class, 'index']);
Route::get('/health/detailed', [HealthController::class, 'detailed']);
Route::get('/health/ready', [HealthController::class, 'ready']);
Route::get('/health/live', [HealthController::class, 'live']);

// Public routes (no authentication required)
Route::prefix('public')->group(function () {
    // Public calendar views, etc.
});
