<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            // The plugin's own id for this event — used to de-duplicate an
            // at-least-once resend. Scoped per server.
            $table->string('event_id', 128);
            $table->string('type', 64);
            $table->json('data')->nullable();
            $table->timestamp('occurred_at')->nullable(); // reported by the plugin
            $table->timestamp('created_at')->nullable();   // received by the site

            $table->unique(['server_id', 'event_id']);
            $table->index(['server_id', 'type', 'created_at']);
            $table->index('created_at'); // for pruning
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_events');
    }
};
