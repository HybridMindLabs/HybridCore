<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('participant_1_id');
            $table->unsignedBigInteger('participant_2_id');
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();

            // Enforce unique pair regardless of order via CHECK (handled by unique index on sorted pair)
            $table->unique(['participant_1_id', 'participant_2_id']);

            $table->foreign('participant_1_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('participant_2_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Fast unread count per conversation
            $table->index(['conversation_id', 'created_at']);
            // Rate-limit queries: how many messages has this user sent today?
            $table->index(['sender_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
