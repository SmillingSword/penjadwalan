<?php

require_once 'vendor/autoload.php';

use App\Models\User;

// Test the status indicators
echo "=== Chat Status Indicators Test ===\n\n";

// Get Reynaldi's user data
$reynaldi = User::where('name', 'like', '%Reynaldi%')->first();

if ($reynaldi) {
    echo "User: {$reynaldi->name}\n";
    echo "is_online: " . ($reynaldi->is_online ? 'true' : 'false') . "\n";
    echo "status: " . ($reynaldi->status ?? 'null') . "\n";
    echo "online_status: " . ($reynaldi->online_status ?? 'null') . "\n";
    echo "\n";
    
    echo "Expected Result:\n";
    echo "- Since is_online = true, status indicator should be GREEN\n";
    echo "- Logic: user.is_online === true ? 'bg-green-500' : 'bg-gray-400'\n";
    echo "\n";
    
    // Test other users
    echo "Other users:\n";
    $users = User::where('id', '!=', $reynaldi->id)->limit(3)->get();
    foreach ($users as $user) {
        echo "- {$user->name}: is_online=" . ($user->is_online ? 'true' : 'false') . 
             ", status=" . ($user->status ?? 'null') . "\n";
    }
} else {
    echo "Reynaldi user not found\n";
}

echo "\n=== Status Logic Test ===\n";
echo "Frontend Logic (simplified):\n";
echo "const isUserTrulyOnline = (user) => {\n";
echo "  return user.is_online === true\n";
echo "}\n\n";

echo "Color Logic:\n";
echo "isUserTrulyOnline(user) ? 'bg-green-500' : 'bg-gray-400'\n\n";

echo "If Reynaldi still shows gray, the issue is:\n";
echo "1. Frontend cache - need to rebuild assets\n";
echo "2. Data not being passed correctly from backend\n";
echo "3. Vue reactivity not updating\n";
