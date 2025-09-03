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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // created, updated, deleted, etc.
            $table->string('entity'); // Calendar, Event, etc.
            $table->uuid('entity_id');
            $table->json('meta')->nullable(); // additional metadata
            $table->timestamp('created_at');

            $table->index(['entity', 'entity_id']);
            $table->index(['actor_id', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
