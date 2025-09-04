<?php

require_once 'vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Mail;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Email Server Diagnostics - CalendarPro\n";
echo "=========================================\n\n";

// Function to test DNS resolution
function testDNS($hostname) {
    $ip = gethostbyname($hostname);
    return $ip !== $hostname ? $ip : false;
}

// Function to test port connectivity
function testPort($host, $port, $timeout = 10) {
    $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if ($connection) {
        fclose($connection);
        return true;
    }
    return false;
}

// Function to format status
function formatStatus($status, $message = '') {
    $icon = $status ? '✅' : '❌';
    $text = $status ? 'OK' : 'FAILED';
    return "{$icon} {$text}" . ($message ? " - {$message}" : '');
}

echo "1. 📧 Laravel Mail Configuration Check\n";
echo "=====================================\n";

$mailConfig = [
    'Default Mailer' => config('mail.default'),
    'SMTP Host' => config('mail.mailers.smtp.host'),
    'SMTP Port' => config('mail.mailers.smtp.port'),
    'SMTP Username' => config('mail.mailers.smtp.username'),
    'SMTP Password' => config('mail.mailers.smtp.password') ? '***CONFIGURED***' : 'NOT SET',
    'From Address' => config('mail.from.address'),
    'From Name' => config('mail.from.name'),
    'Encryption' => env('MAIL_ENCRYPTION', 'Not set'),
];

foreach ($mailConfig as $key => $value) {
    echo sprintf("%-15s: %s\n", $key, $value ?: 'NOT SET');
}

echo "\n2. 🌐 Network Connectivity Tests\n";
echo "================================\n";

$smtpHost = config('mail.mailers.smtp.host');
$smtpPort = config('mail.mailers.smtp.port');

// Test DNS resolution
echo "DNS Resolution: ";
$dnsResult = testDNS($smtpHost);
if ($dnsResult) {
    echo formatStatus(true, "Resolved to {$dnsResult}") . "\n";
} else {
    echo formatStatus(false, "Cannot resolve {$smtpHost}") . "\n";
}

// Test port connectivity
echo "Port Connectivity: ";
$portResult = testPort($smtpHost, $smtpPort, 10);
echo formatStatus($portResult, "Port {$smtpPort} on {$smtpHost}") . "\n";

// Test alternative ports
$alternativePorts = [25, 587, 465, 2525];
echo "\nAlternative SMTP Ports:\n";
foreach ($alternativePorts as $port) {
    if ($port == $smtpPort) continue;
    $result = testPort($smtpHost, $port, 5);
    echo sprintf("Port %-4d: %s\n", $port, formatStatus($result));
}

echo "\n3. 🔧 Environment Variables Check\n";
echo "=================================\n";

$envVars = [
    'MAIL_MAILER',
    'MAIL_HOST', 
    'MAIL_PORT',
    'MAIL_USERNAME',
    'MAIL_PASSWORD',
    'MAIL_ENCRYPTION',
    'MAIL_FROM_ADDRESS',
    'MAIL_FROM_NAME'
];

foreach ($envVars as $var) {
    $value = env($var);
    $status = !empty($value);
    $displayValue = $var === 'MAIL_PASSWORD' && $value ? '***SET***' : ($value ?: 'NOT SET');
    echo sprintf("%-18s: %s %s\n", $var, formatStatus($status), $displayValue);
}

echo "\n4. 📊 System Information\n";
echo "=======================\n";

echo "PHP Version: " . PHP_VERSION . "\n";
echo "Laravel Version: " . app()->version() . "\n";
echo "OpenSSL Support: " . formatStatus(extension_loaded('openssl')) . "\n";
echo "cURL Support: " . formatStatus(extension_loaded('curl')) . "\n";
echo "Socket Support: " . formatStatus(extension_loaded('sockets')) . "\n";
echo "Mail Function: " . formatStatus(function_exists('mail')) . "\n";

echo "\n5. 🧪 SMTP Connection Test\n";
echo "=========================\n";

try {
    echo "Testing SMTP connection...\n";
    
    // Create a test transport
    $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
        $smtpHost,
        $smtpPort,
        env('MAIL_ENCRYPTION') === 'ssl'
    );
    
    if (config('mail.mailers.smtp.username')) {
        $transport->setUsername(config('mail.mailers.smtp.username'));
        $transport->setPassword(config('mail.mailers.smtp.password'));
    }
    
    // Test connection
    $transport->start();
    echo formatStatus(true, "SMTP connection successful") . "\n";
    $transport->stop();
    
} catch (Exception $e) {
    echo formatStatus(false, "SMTP connection failed") . "\n";
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n6. 📝 Recommendations\n";
echo "====================\n";

$recommendations = [];

if (config('mail.default') !== 'smtp') {
    $recommendations[] = "⚠️  Set MAIL_MAILER=smtp in .env file";
}

if (!config('mail.mailers.smtp.host')) {
    $recommendations[] = "⚠️  Configure MAIL_HOST in .env file";
}

if (!config('mail.mailers.smtp.username')) {
    $recommendations[] = "⚠️  Configure MAIL_USERNAME in .env file";
}

if (!config('mail.mailers.smtp.password')) {
    $recommendations[] = "⚠️  Configure MAIL_PASSWORD in .env file";
}

if (!env('MAIL_ENCRYPTION')) {
    $recommendations[] = "⚠️  Set MAIL_ENCRYPTION=ssl for port 465";
}

if (!$dnsResult) {
    $recommendations[] = "❌ DNS resolution failed - check internet connection";
}

if (!$portResult) {
    $recommendations[] = "❌ Port connectivity failed - check firewall settings";
}

if (empty($recommendations)) {
    echo "✅ All checks passed! Email configuration looks good.\n";
    echo "🚀 You can now run: php test_basic_email.php\n";
} else {
    echo "Found " . count($recommendations) . " issue(s) to fix:\n\n";
    foreach ($recommendations as $i => $rec) {
        echo ($i + 1) . ". {$rec}\n";
    }
}

echo "\n7. 📋 Next Steps\n";
echo "===============\n";
echo "1. Fix any issues mentioned above\n";
echo "2. Run: php test_basic_email.php\n";
echo "3. Run: php test_html_email.php\n";
echo "4. Run: php test_email_reminder.php\n";
echo "5. Check email inbox: kacaribureynaldi@gmail.com\n\n";

echo "🔍 For more detailed logs, check:\n";
echo "   tail -f storage/logs/laravel.log\n\n";

echo "Diagnostics completed at: " . date('Y-m-d H:i:s T') . "\n";
