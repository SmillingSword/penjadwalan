<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable()->after('timezone');
            $table->boolean('browser_notifications_enabled')->default(true)->after('notification_preferences');
            $table->boolean('email_notifications_enabled')->default(true)->after('browser_notifications_enabled');
            $table->boolean('realtime_notifications_enabled')->default(true)->after('email_notifications_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'notification_preferences',
                'browser_notifications_enabled',
                'email_notifications_enabled',
                'realtime_notifications_enabled'
            ]);
        });
    }
};
