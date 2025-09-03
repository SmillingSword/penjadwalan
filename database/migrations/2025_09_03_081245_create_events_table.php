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
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('calendar_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description_md')->nullable();
            $table->string('location')->nullable();
            $table->string('meeting_link')->nullable();
            $table->timestampTz('start_at');
            $table->timestampTz('end_at');
            $table->boolean('all_day')->default(false);
            $table->string('timezone', 64)->default('UTC');
            $table->string('rrule')->nullable(); // RFC 5545 recurrence rule
            $table->json('exdates')->nullable(); // exception dates
            $table->boolean('is_private')->default(false);
            $table->timestamps();

            $table->index(['calendar_id', 'start_at']);
            $table->index(['start_at', 'end_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
