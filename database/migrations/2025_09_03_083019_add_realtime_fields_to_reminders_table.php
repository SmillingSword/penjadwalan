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
        Schema::table('reminders', function (Blueprint $table) {
            $table->string('reminder_type')->nullable()->after('minutes_before');
            $table->boolean('is_automatic')->default(false)->after('reminder_type');
            
            // Add sent_at column if it doesn't exist
            if (!Schema::hasColumn('reminders', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('is_automatic');
            }
            
            // Add indexes for better performance
            $table->index(['reminder_type']);
            $table->index(['is_automatic']);
            $table->index(['sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropIndex(['reminder_type']);
            $table->dropIndex(['is_automatic']);
            $table->dropIndex(['sent_at']);
            
            $table->dropColumn([
                'reminder_type',
                'is_automatic'
            ]);
        });
    }
};
