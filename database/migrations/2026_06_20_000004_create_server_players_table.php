<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('server_snapshots')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->integer('score')->default(0);
            $table->unsignedInteger('duration')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_players');
    }
};
