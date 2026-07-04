<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_achievements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 64);
            $table->timestamp('awarded_at')->useCurrent();

            $table->unique(['user_id', 'slug']);
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_achievements');
    }
};
