<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('to');
            $table->string('subject');
            $table->string('template_slug')->nullable();
            $table->enum('status', ['sent', 'failed', 'skipped'])->default('sent');
            $table->text('error')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['to', 'created_at']);
            $table->index('template_slug');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
