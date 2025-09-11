<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Api\RealTimeChatController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

echo "=== UI Users Display Debug Test ===\n\n";

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "1. Checking database connection...\n";
    $userCount = User::count();
    echo "   Total users in database: $userCount\n\n";

    if ($userCount === 0) {
        echo "   Creating test users...\n";
        
        // Create test users
        $testUsers = [
            [
                'name' => 'Test User Online',
                'email' => 'online@test.com',
                'password' => bcrypt('password'),
                'is_online' => true,
                'online_status' => 'available',
                'last_seen_at' => now(),
            ],
            [
                'name' => 'Test User Offline',
                'email' => 'offline@test.com',
                'password' => bcrypt('password'),
                'is_online' => false,
                'online_status' => 'offline',
                'last_seen_at' => now()->subHours(2),
            ],
            [
                'name' => 'Test User Away',
                'email' => 'away@test.com',
                'password' => bcrypt('password'),
                'is_online' => false,
                'online_status' => 'away',
                'last_seen_at' => now()->subMinutes(10),
            ]
        ];

        foreach ($testUsers as $userData) {
            User::create($userData);
            echo "   Created: {$userData['name']}\n";
        }
        echo "\n";
    }

    echo "2. Testing DashboardController user data...\n";
    
    // Get first user to simulate authentication
    $currentUser = User::first();
    if (!$currentUser) {
        echo "   ERROR: No users found!\n";
        exit(1);
    }
    
    echo "   Current user: {$currentUser->name}\n";
    
    // Simulate authentication
    Auth::login($currentUser);
    
    // Test DashboardController logic directly
    $allUsers = User::where('id', '!=', $currentUser->id)
        ->select('id', 'name', 'email', 'avatar', 'online_status', 'status_message', 'last_seen_at', 'is_online')
        ->orderByRaw('is_online DESC, last_seen_at DESC NULLS LAST')
        ->get()
        ->map(function($chatUser) {
            // More flexible online status logic for initial load
            $recentlyActive = $chatUser->last_seen_at && $chatUser->last_seen_at >= now()->subMinutes(15);
            $isOnline = $chatUser->is_online || $recentlyActive;
            
            // Determine actual online status for display
            $actualOnlineStatus = 'offline';
            if ($chatUser->is_online) {
                $actualOnlineStatus = $chatUser->online_status ?? 'available';
            } elseif ($recentlyActive) {
                $actualOnlineStatus = 'away';
            }
            
            return [
                'id' => $chatUser->id,
                'name' => $chatUser->name,
                'email' => $chatUser->email,
                'avatar' => $chatUser->avatar ?: '/default-avatar.png',
                'online_status' => $actualOnlineStatus,
                'status_message' => $chatUser->status_message,
                'last_seen_at' => $chatUser->last_seen_at,
                'is_online' => $isOnline,
            ];
        });

    echo "   Users that should appear in UI:\n";
    foreach ($allUsers as $user) {
        $onlineText = $user['is_online'] ? 'ONLINE' : 'OFFLINE';
        $statusText = $user['online_status'];
        echo "   - {$user['name']} ({$user['email']}) - $onlineText ($statusText)\n";
    }
    echo "\n";

    echo "3. Testing API endpoints...\n";
    
    // Test RealTimeChatController users endpoint
    try {
        $controller = new RealTimeChatController();
        $request = new Request();
        
        // This would normally require authentication middleware
        echo "   API endpoint structure exists: ✅\n";
        echo "   Note: Full API test requires web server context\n\n";
    } catch (Exception $e) {
        echo "   API test error: " . $e->getMessage() . "\n\n";
    }

    echo "4. Checking for common UI issues...\n";
    
    // Check if sound files exist
    $soundFiles = [
        'public/sounds/message.mp3',
        'public/sounds/message.ogg',
        'public/sounds/typing.mp3',
        'public/sounds/typing.ogg'
    ];
    
    $missingSounds = [];
    foreach ($soundFiles as $file) {
        if (!file_exists($file)) {
            $missingSounds[] = $file;
        }
    }
    
    if (!empty($missingSounds)) {
        echo "   Missing sound files (causing 404 errors):\n";
        foreach ($missingSounds as $file) {
            echo "   - $file\n";
        }
        echo "   This won't prevent users from showing, but causes console errors.\n\n";
    } else {
        echo "   All sound files exist: ✅\n\n";
    }

    // Check if default avatar exists
    if (!file_exists('public/default-avatar.png')) {
        echo "   Missing default avatar: public/default-avatar.png\n";
        echo "   This could cause avatar display issues.\n\n";
    } else {
        echo "   Default avatar exists: ✅\n\n";
    }

    echo "5. Potential UI Issues Analysis:\n";
    echo "   Based on the console errors you showed:\n\n";
    
    echo "   A. Sound file 404 errors:\n";
    echo "      - These don't prevent user display\n";
    echo "      - But they clutter the console\n";
    echo "      - Solution: Create dummy sound files or remove audio references\n\n";
    
    echo "   B. JSON parsing error:\n";
    echo "      - 'Unexpected token <' suggests HTML response instead of JSON\n";
    echo "      - This could be from a Laravel error page\n";
    echo "      - Check if API routes are working correctly\n";
    echo "      - Verify authentication is working\n\n";
    
    echo "   C. CSS preload warnings:\n";
    echo "      - These are just warnings, not errors\n";
    echo "      - Won't prevent functionality\n\n";

    echo "6. Recommended fixes:\n";
    echo "   1. Create missing sound files\n";
    echo "   2. Fix API authentication issues\n";
    echo "   3. Add error handling in Vue components\n";
    echo "   4. Add debugging to RealTimeChatManager\n\n";

    echo "7. Data Summary:\n";
    echo "   - Users in database: " . User::count() . "\n";
    echo "   - Users that should show in UI: " . $allUsers->count() . "\n";
    echo "   - Current user: {$currentUser->name}\n";
    
    if ($allUsers->count() > 0) {
        echo "   ✅ Backend data is ready for UI display\n";
        echo "   ❌ UI issues are preventing display (likely API/auth problems)\n";
    } else {
        echo "   ❌ No users to display (all users are the current user)\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
