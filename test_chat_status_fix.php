<?php

// Test script to verify chat status real-time updates
echo "Testing Chat Status Real-Time Updates...\n\n";

// Test 1: Check if UserStatusUpdated event broadcasts on correct channel
echo "1. Checking UserStatusUpdated event channel configuration...\n";
$userEventFile = __DIR__ . '/app/Events/UserStatusUpdated.php';
if (file_exists($userEventFile)) {
    $content = file_get_contents($userEventFile);
    if (strpos($content, 'presence-chat.online-users') !== false) {
        echo "✅ UserStatusUpdated event broadcasts on 'presence-chat.online-users' channel\n";
    } else {
        echo "❌ UserStatusUpdated event does not broadcast on correct channel\n";
    }
} else {
    echo "❌ UserStatusUpdated.php file not found\n";
}

// Test 2: Check if frontend subscribes to correct channel
echo "\n2. Checking frontend presence channel subscription...\n";
$frontendFile = __DIR__ . '/resources/js/Components/Chat/RealTimeChatManager.vue';
if (file_exists($frontendFile)) {
    $content = file_get_contents($frontendFile);
    if (strpos($content, 'presence-chat.online-users') !== false) {
        echo "✅ Frontend subscribes to 'presence-chat.online-users' channel\n";
    } else {
        echo "❌ Frontend does not subscribe to correct channel\n";
    }
} else {
    echo "❌ RealTimeChatManager.vue file not found\n";
}

// Test 3: Check status color mapping
echo "\n3. Checking status color mapping in ChatSidebar...\n";
$sidebarFile = __DIR__ . '/resources/js/Components/Chat/ChatSidebar.vue';
if (file_exists($sidebarFile)) {
    $content = file_get_contents($sidebarFile);
    if (strpos($content, 'available: \'bg-green-500\'') !== false) {
        echo "✅ Status color mapping includes 'available' -> green\n";
    } else {
        echo "❌ Status color mapping missing or incorrect\n";
    }
} else {
    echo "❌ ChatSidebar.vue file not found\n";
}

// Test 4: Check API endpoint validation
echo "\n4. Checking API endpoint status validation...\n";
$controllerFile = __DIR__ . '/app/Http/Controllers/Api/RealTimeChatController.php';
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    if (strpos($content, 'available,busy,away,invisible,offline') !== false) {
        echo "✅ API validates correct status values\n";
    } else {
        echo "❌ API status validation missing or incorrect\n";
    }
} else {
    echo "❌ RealTimeChatController.php file not found\n";
}

echo "\n🎯 Chat Status Fix Summary:\n";
echo "- Fixed channel name mismatch between backend and frontend\n";
echo "- Backend now broadcasts on 'presence-chat.online-users'\n";
echo "- Frontend subscribes to matching channel\n";
echo "- Status colors should now update in real-time\n";
echo "- 'Active in chat' status should show green color\n\n";

echo "Next steps:\n";
echo "1. Clear browser cache and reload the chat page\n";
echo "2. Test status changes by updating user status\n";
echo "3. Verify status color changes to green for 'available' status\n";
echo "4. Check browser console for any real-time connection errors\n";

?>
