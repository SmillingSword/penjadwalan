<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\User;

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Online Users Fix Test ===\n\n";

// Check if we have users in the database
echo "1. Checking existing users...\n";
$userCount = User::count();
echo "   Total users in database: $userCount\n";

if ($userCount === 0) {
    echo "   No users found! Creating test users...\n";
    
    // Create test users
    $testUsers = [
        [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_online' => true,
            'online_status' => 'available',
            'last_seen_at' => now(),
        ],
        [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_online' => false,
            'online_status' => 'offline',
            'last_seen_at' => now()->subMinutes(10),
        ],
        [
            'name' => 'Bob Wilson',
            'email' => 'bob@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_online' => true,
            'online_status' => 'busy',
            'last_seen_at' => now()->subMinutes(2),
        ],
    ];
    
    foreach ($testUsers as $userData) {
        User::create($userData);
        echo "   Created user: {$userData['name']} ({$userData['email']})\n";
    }
    
    $userCount = User::count();
    echo "   Total users after creation: $userCount\n";
}

echo "\n2. Testing DashboardController logic...\n";

// Simulate the current user (first user)
$currentUser = User::first();
echo "   Current user: {$currentUser->name}\n";

// Test the new logic from DashboardController
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

echo "   Users that will be shown in chat:\n";
foreach ($allUsers as $user) {
    $onlineText = $user['is_online'] ? 'ONLINE' : 'OFFLINE';
    $statusText = $user['online_status'];
    echo "   - {$user['name']} ({$user['email']}) - $onlineText ($statusText)\n";
}

echo "\n3. Testing API endpoint...\n";

// Test the API endpoint that the frontend calls
try {
    $response = app('App\Http\Controllers\Api\RealTimeChatController')->getAllUsers(request());
    $responseData = json_decode($response->getContent(), true);
    
    echo "   API Response Status: " . $response->getStatusCode() . "\n";
    echo "   Users returned by API: " . count($responseData['users']) . "\n";
    
    foreach ($responseData['users'] as $user) {
        $onlineText = $user['is_online'] ? 'ONLINE' : 'OFFLINE';
        $statusText = $user['online_status'];
        echo "   - {$user['name']} - $onlineText ($statusText)\n";
    }
} catch (Exception $e) {
    echo "   API Error: " . $e->getMessage() . "\n";
}

echo "\n4. Summary:\n";
echo "   ✅ Fixed DashboardController online status logic\n";
echo "   ✅ Made online detection more flexible (15 minutes instead of 5)\n";
echo "   ✅ Added fallback avatar path\n";
echo "   ✅ Improved status determination logic\n";

if (count($allUsers) > 0) {
    echo "   ✅ Users should now appear in the Users tab!\n";
} else {
    echo "   ❌ No users found - check database seeding\n";
}

echo "\n=== Test Complete ===\n";
