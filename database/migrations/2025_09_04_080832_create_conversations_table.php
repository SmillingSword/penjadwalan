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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('private'); // private, group
            $table->string('title')->nullable(); // for group chats
            $table->text('description')->nullable();
            $table->string('avatar')->nullable(); // for group chats
            $table->foreignUuid('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('last_message_at')->nullable();
            $table->json('settings')->nullable(); // chat settings like mute, etc
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['type', 'is_active']);
            $table->index('last_message_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
