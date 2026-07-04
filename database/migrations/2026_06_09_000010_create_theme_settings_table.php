<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, bool, int, json, color
            $table->timestamps();

            $table->unique(['theme_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_settings');
    }
};
