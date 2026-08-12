<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable');
            $table->foreignId('user_id')->constrained();
            $table->string('gateway');
            $table->string('external_id')->nullable();
            $table->unsignedInteger('amount');
            $table->string('currency', 3);
            $table->string('status');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
