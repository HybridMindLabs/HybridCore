<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            // SHA-256 of the bearer token — the plain token is shown once
            // at generation time and never stored.
            $table->string('bridge_token_hash', 64)->nullable()->unique();
            $table->boolean('bridge_enabled')->default(false);
            $table->timestamp('bridge_last_seen_at')->nullable();
        });

        Schema::create('server_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('command', 500);
            $table->string('source', 64)->default('core');
            $table->string('status', 16)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('acked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_commands');

        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['bridge_token_hash', 'bridge_enabled', 'bridge_last_seen_at']);
        });
    }
};
