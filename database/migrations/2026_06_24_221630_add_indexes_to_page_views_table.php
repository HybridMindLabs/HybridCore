<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_views', function (Blueprint $table): void {
            $table->index(['is_bot', 'created_at'], 'pv_bot_created');
            $table->index(['is_bot', 'user_id', 'created_at'], 'pv_bot_user_created');
            $table->index(['is_bot', 'session_id', 'created_at'], 'pv_bot_session_created');
            $table->index(['created_at', 'is_bot'], 'pv_created_bot');
        });
    }

    public function down(): void
    {
        Schema::table('page_views', function (Blueprint $table): void {
            $table->dropIndex('pv_bot_created');
            $table->dropIndex('pv_bot_user_created');
            $table->dropIndex('pv_bot_session_created');
            $table->dropIndex('pv_created_bot');
        });
    }
};
