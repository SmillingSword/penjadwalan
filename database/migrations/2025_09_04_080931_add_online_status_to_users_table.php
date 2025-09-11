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
            $table->boolean('is_online')->default(false)->after('email_verified_at');
            $table->timestamp('last_seen_at')->nullable()->after('is_online');
            $table->string('status')->default('available')->after('last_seen_at'); // available, busy, away, invisible
            $table->text('status_message')->nullable()->after('status');
            $table->boolean('show_online_status')->default(true)->after('status_message');
            
            $table->index(['is_online', 'show_online_status']);
            $table->index('last_seen_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_online', 'show_online_status']);
            $table->dropIndex(['last_seen_at']);
            $table->dropColumn([
                'is_online',
                'last_seen_at',
                'status',
                'status_message',
                'show_online_status'
            ]);
        });
    }
};
