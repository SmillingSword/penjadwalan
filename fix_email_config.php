<?php

require_once 'vendor/autoload.php';

use Illuminate\Foundation\Application;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔧 Email Configuration Fixer - CalendarPro\n";
echo "==========================================\n\n";

echo "📋 Current Configuration Status:\n";
echo "================================\n";

// Check current config values
$currentConfig = [
    'MAIL_MAILER' => config('mail.default'),
    'MAIL_HOST' => config('mail.mailers.smtp.host'),
    'MAIL_PORT' => config('mail.mailers.smtp.port'),
    'MAIL_USERNAME' => config('mail.mailers.smtp.username'),
    'MAIL_PASSWORD' => config('mail.mailers.smtp.password') ? '***SET***' : 'NOT SET',
    'MAIL_ENCRYPTION' => env('MAIL_ENCRYPTION', 'NOT SET'),
    'MAIL_FROM_ADDRESS' => config('mail.from.address'),
    'MAIL_FROM_NAME' => config('mail.from.name'),
];

foreach ($currentConfig as $key => $value) {
    $status = !empty($value) && $value !== 'NOT SET' ? '✅' : '❌';
    echo sprintf("%s %-18s: %s\n", $status, $key, $value);
}

echo "\n📝 Recommended .env Configuration:\n";
echo "==================================\n";
echo "Based on your EMAIL_SETUP.md, your .env file should contain:\n\n";

$recommendedConfig = [
    'MAIL_MAILER=smtp',
    'MAIL_HOST=mail.reynrastore.com',
    'MAIL_PORT=465',
    'MAIL_USERNAME=support@reynrastore.com',
    'MAIL_PASSWORD=Wkqjdlbw123!@#',
    'MAIL_ENCRYPTION=ssl',
    'MAIL_FROM_ADDRESS=support@reynrastore.com',
    'MAIL_FROM_NAME="CalendarPro"'
];

foreach ($recommendedConfig as $config) {
    echo $config . "\n";
}

echo "\n🔍 Checking .env file existence:\n";
echo "================================\n";

if (file_exists('.env')) {
    echo "✅ .env file exists\n";
    
    // Check if .env is readable
    if (is_readable('.env')) {
        echo "✅ .env file is readable\n";
    } else {
        echo "❌ .env file is not readable - check permissions\n";
    }
} else {
    echo "❌ .env file does not exist\n";
    echo "💡 Copy .env.example to .env: cp .env.example .env\n";
}

echo "\n🛠️  Manual Steps to Fix:\n";
echo "========================\n";
echo "1. Ensure .env file exists in project root\n";
echo "2. Add/update the email configuration lines above\n";
echo "3. Clear Laravel config cache: php artisan config:clear\n";
echo "4. Clear Laravel cache: php artisan cache:clear\n";
echo "5. Re-run diagnostics: php email_diagnostics.php\n\n";

echo "⚠️  Important Notes:\n";
echo "===================\n";
echo "- Port 465 requires MAIL_ENCRYPTION=ssl\n";
echo "- Port 587 requires MAIL_ENCRYPTION=tls\n";
echo "- Make sure no spaces around = in .env file\n";
echo "- Restart web server after .env changes\n\n";

// Try to clear config cache
echo "🧹 Attempting to clear Laravel caches...\n";
try {
    \Illuminate\Support\Facades\Artisan::call('config:clear');
    echo "✅ Config cache cleared\n";
} catch (Exception $e) {
    echo "⚠️  Could not clear config cache: " . $e->getMessage() . "\n";
}

try {
    \Illuminate\Support\Facades\Artisan::call('cache:clear');
    echo "✅ Application cache cleared\n";
} catch (Exception $e) {
    echo "⚠️  Could not clear application cache: " . $e->getMessage() . "\n";
}

echo "\n🎯 Next Steps:\n";
echo "=============\n";
echo "1. Update your .env file with the configuration above\n";
echo "2. Run: php artisan config:clear\n";
echo "3. Run: php email_diagnostics.php\n";
echo "4. Run: php test_basic_email.php\n";
echo "5. Check email: kacaribureynaldi@gmail.com\n\n";

echo "Configuration check completed at: " . date('Y-m-d H:i:s T') . "\n";
