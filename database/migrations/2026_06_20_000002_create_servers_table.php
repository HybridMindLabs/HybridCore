<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('ip');
            $table->unsignedSmallInteger('port');
            $table->string('name')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_queried_at')->nullable();
            $table->timestamps();

            $table->unique(['ip', 'port']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
