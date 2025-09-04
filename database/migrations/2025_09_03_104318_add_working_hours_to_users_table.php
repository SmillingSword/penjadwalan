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
            $table->time('working_hours_start')->default('09:00')->after('locale');
            $table->time('working_hours_end')->default('17:00')->after('working_hours_start');
            $table->json('working_days')->nullable()->after('working_hours_end'); // ['monday', 'tuesday', etc.]
            $table->integer('buffer_minutes')->default(15)->after('working_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['working_hours_start', 'working_hours_end', 'working_days', 'buffer_minutes']);
        });
    }
};
