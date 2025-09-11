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
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->string('role')->default('member'); // admin, member
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('last_read_at')->nullable();
            $table->foreignId('last_read_message_id')->nullable()->constrained('messages')->onDelete('set null');
            $table->boolean('is_muted')->default(false);
            $table->timestamp('muted_until')->nullable();
            $table->json('settings')->nullable(); // participant-specific settings
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['conversation_id', 'user_id']);
            $table->index(['user_id', 'is_active']);
            $table->index('last_read_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_participants');
    }
};
