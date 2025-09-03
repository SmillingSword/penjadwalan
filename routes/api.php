<?php

use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\CalendarController;
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
    
    // Organizations API
    // Route::apiResource('organizations', OrganizationController::class);
    
    // Webhook subscriptions API
    // Route::apiResource('webhook-subscriptions', WebhookSubscriptionController::class);
});

// Public routes (no authentication required)
Route::prefix('public')->group(function () {
    // Public calendar views, etc.
});
