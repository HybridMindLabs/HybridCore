<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table): void {
            $table->id();
            $table->string('session_id', 40)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_hash', 64);
            $table->string('path', 500);
            $table->string('route_name', 150)->nullable();
            $table->string('device_type', 10)->default('desktop');
            $table->string('country_code', 2)->nullable();
            $table->boolean('is_bot')->default(false)->index();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
