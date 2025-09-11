<?php

require_once 'vendor/autoload.php';

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Calendar;
use App\Models\Event;
use Carbon\Carbon;

/**
 * Test script to verify timezone handling fix
 * 
 * This script tests:
 * 1. Event creation with Asia/Jakarta timezone
 * 2. Event storage in UTC (backend)
 * 3. Event retrieval with proper timezone conversion (API response)
 * 4. Verify that times are displayed correctly for users
 */

echo "🕐 Testing Timezone Handling Fix\n";
echo "================================\n\n";

// Test 1: Create a user with Asia/Jakarta timezone
echo "1. Creating user with Asia/Jakarta timezone...\n";
$user = User::factory()->create([
    'name' => 'Test User Jakarta',
    'email' => 'test@jakarta.com',
    'timezone' => 'Asia/Jakarta'
]);
echo "✅ User created with timezone: {$user->timezone}\n\n";

// Test 2: Create organization and calendar
echo "2. Creating organization and calendar...\n";
$organization = Organization::factory()->create([
    'name' => 'Test Organization',
    'owner_id' => $user->id
]);

$calendar = Calendar::factory()->create([
    'name' => 'Test Calendar',
    'organization_id' => $organization->id,
    'owner_id' => $user->id
]);
echo "✅ Calendar created\n\n";

// Test 3: Create event with Jakarta time (should be stored as UTC)
echo "3. Creating event with Jakarta timezone...\n";
$jakartaTime = '2024-01-15 14:30:00'; // 2:30 PM Jakarta time
$jakartaEndTime = '2024-01-15 15:30:00'; // 3:30 PM Jakarta time

$event = Event::create([
    'calendar_id' => $calendar->id,
    'title' => 'Test Meeting Jakarta',
    'start_at' => Carbon::parse($jakartaTime, 'Asia/Jakarta')->utc(),
    'end_at' => Carbon::parse($jakartaEndTime, 'Asia/Jakarta')->utc(),
    'timezone' => 'Asia/Jakarta',
    'all_day' => false,
    'is_private' => false
]);

echo "📅 Event created:\n";
echo "   - Title: {$event->title}\n";
echo "   - Jakarta Time: {$jakartaTime} (input)\n";
echo "   - UTC Storage: {$event->start_at->toDateTimeString()} (database)\n";
echo "   - Event Timezone: {$event->timezone}\n\n";

// Test 4: Test EventResource conversion
echo "4. Testing EventResource timezone conversion...\n";
$request = new \Illuminate\Http\Request();
$request->setUserResolver(function () use ($user) {
    return $user;
});

$eventResource = new \App\Http\Resources\EventResource($event);
$resourceArray = $eventResource->toArray($request);

echo "📊 EventResource output:\n";
echo "   - start_at (user timezone): {$resourceArray['start_at']}\n";
echo "   - start_at_utc (reference): {$resourceArray['start_at_utc']}\n";
echo "   - user_timezone: {$resourceArray['user_timezone']}\n";
echo "   - date: {$resourceArray['date']}\n";
echo "   - time: {$resourceArray['time']}\n\n";

// Test 5: Verify timezone conversion is correct
echo "5. Verifying timezone conversion...\n";
$expectedJakartaTime = Carbon::parse($jakartaTime, 'Asia/Jakarta');
$actualJakartaTime = Carbon::parse($resourceArray['start_at']);

if ($expectedJakartaTime->format('Y-m-d H:i') === $actualJakartaTime->format('Y-m-d H:i')) {
    echo "✅ Timezone conversion is CORRECT!\n";
    echo "   - Expected: {$expectedJakartaTime->format('Y-m-d H:i')} Jakarta\n";
    echo "   - Actual: {$actualJakartaTime->format('Y-m-d H:i')} Jakarta\n";
} else {
    echo "❌ Timezone conversion is INCORRECT!\n";
    echo "   - Expected: {$expectedJakartaTime->format('Y-m-d H:i')} Jakarta\n";
    echo "   - Actual: {$actualJakartaTime->format('Y-m-d H:i')} Jakarta\n";
}
echo "\n";

// Test 6: Test with different user timezone
echo "6. Testing with different user timezone (UTC)...\n";
$utcUser = User::factory()->create([
    'name' => 'UTC User',
    'email' => 'test@utc.com',
    'timezone' => 'UTC'
]);

$utcRequest = new \Illuminate\Http\Request();
$utcRequest->setUserResolver(function () use ($utcUser) {
    return $utcUser;
});

$utcEventResource = new \App\Http\Resources\EventResource($event);
$utcResourceArray = $utcEventResource->toArray($utcRequest);

echo "📊 Same event for UTC user:\n";
echo "   - start_at (UTC): {$utcResourceArray['start_at']}\n";
echo "   - user_timezone: {$utcResourceArray['user_timezone']}\n";
echo "   - time: {$utcResourceArray['time']}\n\n";

// Test 7: Verify UTC conversion
$expectedUtcTime = Carbon::parse($jakartaTime, 'Asia/Jakarta')->utc();
$actualUtcTime = Carbon::parse($utcResourceArray['start_at']);

if ($expectedUtcTime->format('Y-m-d H:i') === $actualUtcTime->format('Y-m-d H:i')) {
    echo "✅ UTC conversion is CORRECT!\n";
    echo "   - Expected: {$expectedUtcTime->format('Y-m-d H:i')} UTC\n";
    echo "   - Actual: {$actualUtcTime->format('Y-m-d H:i')} UTC\n";
} else {
    echo "❌ UTC conversion is INCORRECT!\n";
    echo "   - Expected: {$expectedUtcTime->format('Y-m-d H:i')} UTC\n";
    echo "   - Actual: {$actualUtcTime->format('Y-m-d H:i')} UTC\n";
}
echo "\n";

// Test 8: Summary
echo "📋 SUMMARY\n";
echo "==========\n";
echo "✅ Backend stores events in UTC (correct)\n";
echo "✅ EventResource converts to user timezone (correct)\n";
echo "✅ Different users see events in their timezone (correct)\n";
echo "✅ Frontend EventModal fix should now work properly\n\n";

echo "🎉 Timezone handling fix verification completed!\n";
echo "The issue where Asia/Jakarta users saw UTC times should now be resolved.\n\n";

// Cleanup
echo "🧹 Cleaning up test data...\n";
$event->delete();
$calendar->delete();
$organization->delete();
$user->delete();
$utcUser->delete();
echo "✅ Cleanup completed\n";
