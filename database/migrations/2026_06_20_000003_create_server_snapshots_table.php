<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_online')->default(false);
            $table->string('name')->nullable();
            $table->string('map')->nullable();
            $table->unsignedSmallInteger('players_online')->default(0);
            $table->unsignedSmallInteger('players_max')->default(0);
            $table->unsignedSmallInteger('ping')->nullable();
            $table->boolean('is_password_protected')->default(false);
            $table->boolean('vac_secured')->default(false);
            $table->string('game_version')->nullable();
            $table->timestamp('recorded_at')->useCurrent();

            $table->index(['server_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_snapshots');
    }
};
