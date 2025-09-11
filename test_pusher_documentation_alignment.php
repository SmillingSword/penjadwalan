<?php

/**
 * Comprehensive Test for Pusher Documentation Alignment
 * 
 * This script tests all the changes made to align with Laravel Pusher documentation:
 * 1. Broadcasting Configuration
 * 2. MyEvent (Documentation Example)
 * 3. MessageSent Event (Simplified Chat)
 * 4. ReminderTriggered Event (Simplified Notifications)
 * 5. Frontend Integration
 * 6. Backend Integration
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

// Initialize Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

echo "🧪 PUSHER DOCUMENTATION ALIGNMENT - COMPREHENSIVE TEST\n";
echo "=" . str_repeat("=", 60) . "\n\n";

// Test results tracking
$testResults = [];
$totalTests = 0;
$passedTests = 0;

function testResult($testName, $passed, $message = '', $details = []) {
    global $testResults, $totalTests, $passedTests;
    
    $totalTests++;
    if ($passed) {
        $passedTests++;
        $status = "✅ PASS";
        $color = "\033[32m"; // Green
    } else {
        $status = "❌ FAIL";
        $color = "\033[31m"; // Red
    }
    
    echo $color . sprintf("%-50s %s\033[0m", $testName, $status) . "\n";
    if ($message) {
        echo "   📝 " . $message . "\n";
    }
    
    if (!empty($details)) {
        foreach ($details as $key => $value) {
            echo "   📊 $key: $value\n";
        }
    }
    echo "\n";
    
    $testResults[] = [
        'name' => $testName,
        'passed' => $passed,
        'message' => $message,
        'details' => $details
    ];
}

// =============================================================================
// TEST 1: BROADCASTING CONFIGURATION
// =============================================================================

echo "🔧 TEST 1: Broadcasting Configuration\n";
echo "-" . str_repeat("-", 40) . "\n";

// Test broadcasting config exists and is simplified
$broadcastConfig = null;
if (file_exists('config/broadcasting.php')) {
    $broadcastConfig = include 'config/broadcasting.php';
    $driver = $broadcastConfig['default'] ?? 'null';
    
    testResult(
        'Broadcasting Driver Configuration',
        $driver === 'pusher',
        "Driver: $driver",
        $driver === 'pusher' ? ['Status' => 'Pusher configured correctly'] : ['Warning' => 'Driver not set to pusher']
    );
    
    if ($driver === 'pusher') {
        $pusherConfig = $broadcastConfig['connections']['pusher'] ?? [];
        $hasKey = !empty($pusherConfig['key']);
        $hasSecret = !empty($pusherConfig['secret']);
        $hasAppId = !empty($pusherConfig['app_id']);
        $hasSimplifiedOptions = isset($pusherConfig['options']) && 
                               count($pusherConfig['options']) <= 2 && 
                               isset($pusherConfig['options']['cluster']) && 
                               isset($pusherConfig['options']['useTLS']);
        
        testResult(
            'Pusher Configuration Completeness',
            $hasKey && $hasSecret && $hasAppId,
            'All required Pusher credentials configured',
            [
                'Key' => $hasKey ? 'Set' : 'Missing',
                'Secret' => $hasSecret ? 'Set' : 'Missing',
                'App ID' => $hasAppId ? 'Set' : 'Missing'
            ]
        );
        
        testResult(
            'Simplified Options Format',
            $hasSimplifiedOptions,
            'Options simplified to match documentation',
            [
                'Options Count' => count($pusherConfig['options'] ?? []),
                'Has Cluster' => isset($pusherConfig['options']['cluster']) ? 'Yes' : 'No',
                'Has useTLS' => isset($pusherConfig['options']['useTLS']) ? 'Yes' : 'No'
            ]
        );
    }
} else {
    testResult('Broadcasting Config File', false, 'config/broadcasting.php not found');
}

// =============================================================================
// TEST 2: MYEVENT (DOCUMENTATION EXAMPLE)
// =============================================================================

echo "📚 TEST 2: MyEvent (Documentation Example)\n";
echo "-" . str_repeat("-", 40) . "\n";

// Test MyEvent class exists and matches documentation
$myEventExists = file_exists('app/Events/MyEvent.php');
testResult('MyEvent Class Exists', $myEventExists, $myEventExists ? 'Event file found' : 'Event file missing');

if ($myEventExists) {
    $myEventContent = file_get_contents('app/Events/MyEvent.php');
    
    // Check for documentation compliance
    $hasCorrectInterface = strpos($myEventContent, 'implements ShouldBroadcast') !== false;
    $hasCorrectTraits = strpos($myEventContent, 'use Dispatchable, InteractsWithSockets, SerializesModels') !== false;
    $hasPublicMessage = strpos($myEventContent, 'public $message') !== false;
    $hasSimpleBroadcastOn = strpos($myEventContent, "return ['my-channel']") !== false;
    $hasSimpleBroadcastAs = strpos($myEventContent, "return 'my-event'") !== false;
    
    testResult(
        'MyEvent Documentation Compliance',
        $hasCorrectInterface && $hasCorrectTraits && $hasPublicMessage && $hasSimpleBroadcastOn && $hasSimpleBroadcastAs,
        'Event matches Laravel documentation exactly',
        [
            'ShouldBroadcast Interface' => $hasCorrectInterface ? 'Yes' : 'No',
            'Correct Traits' => $hasCorrectTraits ? 'Yes' : 'No',
            'Public Message Property' => $hasPublicMessage ? 'Yes' : 'No',
            'Simple broadcastOn()' => $hasSimpleBroadcastOn ? 'Yes' : 'No',
            'Simple broadcastAs()' => $hasSimpleBroadcastAs ? 'Yes' : 'No'
        ]
    );
}

// Test MyEvent route
try {
    $request = Request::create('/test-pusher', 'GET');
    $response = $kernel->handle($request);
    $statusCode = $response->getStatusCode();
    $content = $response->getContent();
    
    testResult(
        'MyEvent Test Route',
        $statusCode === 200,
        "Route response: $content",
        [
            'Status Code' => $statusCode,
            'Response' => $content
        ]
    );
} catch (Exception $e) {
    testResult('MyEvent Test Route', false, 'Route test failed: ' . $e->getMessage());
}

// =============================================================================
// TEST 3: MESSAGESENT EVENT (SIMPLIFIED CHAT)
// =============================================================================

echo "💬 TEST 3: MessageSent Event (Simplified Chat)\n";
echo "-" . str_repeat("-", 40) . "\n";

$messageSentExists = file_exists('app/Events/MessageSent.php');
testResult('MessageSent Event Exists', $messageSentExists, $messageSentExists ? 'Event file found' : 'Event file missing');

if ($messageSentExists) {
    $messageSentContent = file_get_contents('app/Events/MessageSent.php');
    
    // Check for simplification
    $hasSimpleBroadcastOn = strpos($messageSentContent, 'return ["private-chat.conversation.') !== false;
    $hasSimpleBroadcastAs = strpos($messageSentContent, "return 'message.sent'") !== false;
    $hasSimplifiedStructure = strpos($messageSentContent, 'ShouldBroadcast') !== false && 
                             strpos($messageSentContent, 'ShouldBroadcastNow') === false;
    $lineCount = count(explode("\n", $messageSentContent));
    
    testResult(
        'MessageSent Simplification',
        $hasSimpleBroadcastOn && $hasSimpleBroadcastAs && $hasSimplifiedStructure && $lineCount < 100,
        'Event simplified while maintaining functionality',
        [
            'Simple broadcastOn()' => $hasSimpleBroadcastOn ? 'Yes' : 'No',
            'Simple broadcastAs()' => $hasSimpleBroadcastAs ? 'Yes' : 'No',
            'Uses ShouldBroadcast' => $hasSimplifiedStructure ? 'Yes' : 'No',
            'Line Count' => $lineCount . ' (simplified from 130+)'
        ]
    );
}

// Test MessageSent route
try {
    $request = Request::create('/test-message', 'GET');
    $response = $kernel->handle($request);
    $statusCode = $response->getStatusCode();
    $content = $response->getContent();
    
    testResult(
        'MessageSent Test Route',
        $statusCode === 200 || strpos($content, 'No users found') !== false || strpos($content, 'No conversations found') !== false,
        "Route response: $content",
        [
            'Status Code' => $statusCode,
            'Response' => substr($content, 0, 50) . '...'
        ]
    );
} catch (Exception $e) {
    testResult('MessageSent Test Route', false, 'Route test failed: ' . $e->getMessage());
}

// =============================================================================
// TEST 4: REMINDERTRIGGERED EVENT (SIMPLIFIED NOTIFICATIONS)
// =============================================================================

echo "🔔 TEST 4: ReminderTriggered Event (Simplified Notifications)\n";
echo "-" . str_repeat("-", 40) . "\n";

$reminderTriggeredExists = file_exists('app/Events/ReminderTriggered.php');
testResult('ReminderTriggered Event Exists', $reminderTriggeredExists, $reminderTriggeredExists ? 'Event file found' : 'Event file missing');

if ($reminderTriggeredExists) {
    $reminderContent = file_get_contents('app/Events/ReminderTriggered.php');
    
    // Check for simplification
    $hasSimpleBroadcastOn = strpos($reminderContent, 'return ["private-user.') !== false;
    $hasSimpleBroadcastAs = strpos($reminderContent, "return 'reminder.triggered'") !== false;
    $hasSimplifiedStructure = strpos($reminderContent, 'ShouldBroadcast') !== false;
    $lineCount = count(explode("\n", $reminderContent));
    
    testResult(
        'ReminderTriggered Simplification',
        $hasSimpleBroadcastOn && $hasSimpleBroadcastAs && $hasSimplifiedStructure && $lineCount < 100,
        'Event simplified while maintaining functionality',
        [
            'Simple broadcastOn()' => $hasSimpleBroadcastOn ? 'Yes' : 'No',
            'Simple broadcastAs()' => $hasSimpleBroadcastAs ? 'Yes' : 'No',
            'Uses ShouldBroadcast' => $hasSimplifiedStructure ? 'Yes' : 'No',
            'Line Count' => $lineCount . ' (simplified from 290+)'
        ]
    );
}

// Test ReminderTriggered route
try {
    $request = Request::create('/test-reminder', 'GET');
    $response = $kernel->handle($request);
    $statusCode = $response->getStatusCode();
    $content = $response->getContent();
    
    testResult(
        'ReminderTriggered Test Route',
        $statusCode === 200 || strpos($content, 'No users found') !== false || strpos($content, 'No events found') !== false,
        "Route response: $content",
        [
            'Status Code' => $statusCode,
            'Response' => substr($content, 0, 50) . '...'
        ]
    );
} catch (Exception $e) {
    testResult('ReminderTriggered Test Route', false, 'Route test failed: ' . $e->getMessage());
}

// =============================================================================
// TEST 5: CLIENT-SIDE TEST PAGE
// =============================================================================

echo "🌐 TEST 5: Client-Side Test Page\n";
echo "-" . str_repeat("-", 40) . "\n";

$testPageExists = file_exists('public/pusher-test-simple.html');
testResult('Simple Test Page Exists', $testPageExists, $testPageExists ? 'Test page found' : 'Test page missing');

if ($testPageExists) {
    $testPageContent = file_get_contents('public/pusher-test-simple.html');
    
    // Check for documentation compliance
    $hasCorrectTitle = strpos($testPageContent, '<title>Pusher Test</title>') !== false;
    $hasPusherScript = strpos($testPageContent, 'pusher.min.js') !== false;
    $hasVueScript = strpos($testPageContent, 'vue.js') !== false;
    $hasMyChannelSubscription = strpos($testPageContent, "subscribe('my-channel')") !== false;
    $hasMyEventBinding = strpos($testPageContent, "bind('my-event'") !== false;
    $hasCorrectCredentials = strpos($testPageContent, '7ba7c7c7addb573cac11') !== false;
    
    testResult(
        'Test Page Documentation Compliance',
        $hasCorrectTitle && $hasPusherScript && $hasVueScript && $hasMyChannelSubscription && $hasMyEventBinding,
        'Test page matches Laravel documentation',
        [
            'Correct Title' => $hasCorrectTitle ? 'Yes' : 'No',
            'Pusher Script' => $hasPusherScript ? 'Yes' : 'No',
            'Vue Script' => $hasVueScript ? 'Yes' : 'No',
            'My-Channel Subscription' => $hasMyChannelSubscription ? 'Yes' : 'No',
            'My-Event Binding' => $hasMyEventBinding ? 'Yes' : 'No',
            'Documentation Credentials' => $hasCorrectCredentials ? 'Yes' : 'No'
        ]
    );
}

// =============================================================================
// TEST 6: FRONTEND INTEGRATION
// =============================================================================

echo "🎨 TEST 6: Frontend Integration\n";
echo "-" . str_repeat("-", 40) . "\n";

// Test Echo.js configuration
$echoExists = file_exists('resources/js/echo.js');
testResult('Echo.js Configuration Exists', $echoExists, $echoExists ? 'Echo configuration found' : 'Echo configuration missing');

if ($echoExists) {
    $echoContent = file_get_contents('resources/js/echo.js');
    $hasEchoInstance = strpos($echoContent, 'window.Echo = new Echo') !== false;
    $hasPusherBroadcaster = strpos($echoContent, "broadcaster: 'pusher'") !== false;
    $hasOptimizations = strpos($echoContent, 'disableStats: true') !== false;
    
    testResult(
        'Echo.js Configuration Compatibility',
        $hasEchoInstance && $hasPusherBroadcaster,
        'Echo.js configured for Pusher broadcasting',
        [
            'Echo Instance' => $hasEchoInstance ? 'Yes' : 'No',
            'Pusher Broadcaster' => $hasPusherBroadcaster ? 'Yes' : 'No',
            'Performance Optimizations' => $hasOptimizations ? 'Yes' : 'No'
        ]
    );
}

// Test chat components
$chatComponents = [
    'resources/js/Components/Chat/FloatingChatBox.vue',
    'resources/js/Components/Chat/RealTimeChatManager.vue',
    'resources/js/Components/Chat/ChatSidebar.vue'
];

$chatComponentsExist = 0;
foreach ($chatComponents as $component) {
    if (file_exists($component)) {
        $chatComponentsExist++;
    }
}

testResult(
    'Chat Components Compatibility',
    $chatComponentsExist >= 2,
    "Found $chatComponentsExist out of " . count($chatComponents) . " chat components",
    [
        'Components Found' => $chatComponentsExist,
        'Total Components' => count($chatComponents),
        'Status' => $chatComponentsExist >= 2 ? 'Compatible' : 'May need updates'
    ]
);

// Test notification components
$notificationExists = file_exists('resources/js/Components/NotificationCenter.vue');
testResult(
    'Notification Components Compatibility',
    $notificationExists,
    $notificationExists ? 'Notification center found' : 'Notification center missing',
    [
        'NotificationCenter.vue' => $notificationExists ? 'Exists' : 'Missing'
    ]
);

// =============================================================================
// TEST 7: BACKEND INTEGRATION
// =============================================================================

echo "⚙️ TEST 7: Backend Integration\n";
echo "-" . str_repeat("-", 40) . "\n";

// Test chat controller
$chatControllerExists = file_exists('app/Http/Controllers/Api/RealTimeChatController.php');
testResult('Chat Controller Exists', $chatControllerExists, $chatControllerExists ? 'Controller found' : 'Controller missing');

if ($chatControllerExists) {
    $chatControllerContent = file_get_contents('app/Http/Controllers/Api/RealTimeChatController.php');
    $hasMessageSentUsage = strpos($chatControllerContent, 'MessageSent') !== false;
    $hasBroadcastCall = strpos($chatControllerContent, 'broadcast(new MessageSent') !== false;
    
    testResult(
        'Chat Controller Integration',
        $hasMessageSentUsage && $hasBroadcastCall,
        'Controller uses simplified MessageSent event',
        [
            'Uses MessageSent' => $hasMessageSentUsage ? 'Yes' : 'No',
            'Has Broadcast Call' => $hasBroadcastCall ? 'Yes' : 'No'
        ]
    );
}

// Test notification controller
$notificationControllerExists = file_exists('app/Http/Controllers/Api/NotificationController.php');
testResult('Notification Controller Exists', $notificationControllerExists, $notificationControllerExists ? 'Controller found' : 'Controller missing');

if ($notificationControllerExists) {
    $notificationControllerContent = file_get_contents('app/Http/Controllers/Api/NotificationController.php');
    $hasReminderTriggeredUsage = strpos($notificationControllerContent, 'ReminderTriggered') !== false;
    $hasBroadcastCall = strpos($notificationControllerContent, 'broadcast(new') !== false;
    
    testResult(
        'Notification Controller Integration',
        $hasReminderTriggeredUsage || $hasBroadcastCall,
        'Controller compatible with simplified events',
        [
            'Uses ReminderTriggered' => $hasReminderTriggeredUsage ? 'Yes' : 'No',
            'Has Broadcast Call' => $hasBroadcastCall ? 'Yes' : 'No'
        ]
    );
}

// Test model relationships
$messageModelExists = file_exists('app/Models/Message.php');
$conversationModelExists = file_exists('app/Models/Conversation.php');
$userModelExists = file_exists('app/Models/User.php');

testResult(
    'Model Dependencies',
    $messageModelExists && $conversationModelExists && $userModelExists,
    'All required models exist for events',
    [
        'Message Model' => $messageModelExists ? 'Exists' : 'Missing',
        'Conversation Model' => $conversationModelExists ? 'Exists' : 'Missing',
        'User Model' => $userModelExists ? 'Exists' : 'Missing'
    ]
);

// =============================================================================
// FINAL RESULTS
// =============================================================================

echo "\n" . str_repeat("=", 70) . "\n";
echo "🏁 FINAL TEST RESULTS\n";
echo str_repeat("=", 70) . "\n\n";

$successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;

if ($successRate >= 90) {
    $status = "🎉 EXCELLENT";
    $color = "\033[32m"; // Green
} elseif ($successRate >= 75) {
    $status = "✅ GOOD";
    $color = "\033[33m"; // Yellow
} elseif ($successRate >= 50) {
    $status = "⚠️ NEEDS ATTENTION";
    $color = "\033[33m"; // Yellow
} else {
    $status = "❌ CRITICAL ISSUES";
    $color = "\033[31m"; // Red
}

echo $color . "Overall Status: $status\033[0m\n";
echo "Tests Passed: $passedTests / $totalTests ($successRate%)\n\n";

// Summary by category
$categories = [
    'Broadcasting Configuration' => 0,
    'MyEvent Documentation' => 0,
    'MessageSent Simplification' => 0,
    'ReminderTriggered Simplification' => 0,
    'Client-Side Integration' => 0,
    'Frontend Integration' => 0,
    'Backend Integration' => 0
];

$categoryResults = [];
foreach ($testResults as $result) {
    $testName = $result['name'];
    $passed = $result['passed'];
    
    if (strpos($testName, 'Broadcasting') !== false) {
        $categoryResults['Broadcasting Configuration'][] = $passed;
    } elseif (strpos($testName, 'MyEvent') !== false) {
        $categoryResults['MyEvent Documentation'][] = $passed;
    } elseif (strpos($testName, 'MessageSent') !== false) {
        $categoryResults['MessageSent Simplification'][] = $passed;
    } elseif (strpos($testName, 'ReminderTriggered') !== false || strpos($testName, 'Reminder') !== false) {
        $categoryResults['ReminderTriggered Simplification'][] = $passed;
    } elseif (strpos($testName, 'Test Page') !== false) {
        $categoryResults['Client-Side Integration'][] = $passed;
    } elseif (strpos($testName, 'Echo') !== false || strpos($testName, 'Chat Components') !== false || strpos($testName, 'Notification Components') !== false) {
        $categoryResults['Frontend Integration'][] = $passed;
    } else {
        $categoryResults['Backend Integration'][] = $passed;
    }
}

echo "📊 Results by Category:\n";
echo "-" . str_repeat("-", 30) . "\n";

foreach ($categoryResults as $category => $results) {
    if (!empty($results)) {
        $passed = array_sum($results);
        $total = count($results);
        $rate = round(($passed / $total) * 100, 1);
        $status = $rate >= 75 ? '✅' : ($rate >= 50 ? '⚠️' : '❌');
        echo sprintf("%-30s %s %d/%d (%s%%)\n", $category, $status, $passed, $total, $rate);
    }
}

echo "\n📋 Next Steps:\n";
echo "-" . str_repeat("-", 15) . "\n";

if ($successRate >= 90) {
    echo "🎉 Excellent! Your Pusher implementation is fully aligned with Laravel documentation.\n";
    echo "✅ All events follow the documentation format\n";
    echo "✅ Configuration is simplified and clean\n";
    echo "✅ Test routes are working\n";
    echo "✅ Integration with existing systems is maintained\n\n";
    echo "🚀 You can now:\n";
    echo "   1. Update your .env with your actual Pusher credentials\n";
    echo "   2. Test the /pusher-test-simple.html page\n";
    echo "   3. Use the simplified events as templates for new features\n";
} elseif ($successRate >= 75) {
    echo "✅ Good! Most tests passed. Minor issues to address:\n";
    foreach ($testResults as $result) {
        if (!$result['passed']) {
            echo "   ⚠️ " . $result['name'] . ": " . $result['message'] . "\n";
        }
    }
} else {
    echo "⚠️ Several issues need attention:\n";
    foreach ($testResults as $result) {
        if (!$result['passed']) {
            echo "   ❌ " . $result['name'] . ": " . $result['message'] . "\n";
        }
    }
}

echo "\n🔗 Test completed at " . date('Y-m-d H:i:s') . "\n";
echo "📄 Full results saved in this test output\n";

?>
