<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->boolean('success');
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
