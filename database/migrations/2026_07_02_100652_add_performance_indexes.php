<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Unread-count queries filter on read_at within a conversation.
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'read_at'], 'messages_conversation_read_index');
        });

        // Player-history aggregates scan a recorded_at range across all
        // servers; the existing (server_id, recorded_at) index can't serve
        // a pure time-range scan.
        Schema::table('server_snapshots', function (Blueprint $table) {
            $table->index('recorded_at', 'server_snapshots_recorded_at_index');
        });

        // Online-member counts filter last_seen_at >= now-5min.
        Schema::table('users', function (Blueprint $table) {
            $table->index('last_seen_at', 'users_last_seen_at_index');
        });

        // Published-article listings filter status + order by published_at.
        Schema::table('news_articles', function (Blueprint $table) {
            $table->index(['status', 'published_at'], 'news_articles_status_published_index');
        });
    }

    public function down(): void
    {
        Schema::table('messages', fn (Blueprint $table) => $table->dropIndex('messages_conversation_read_index'));
        Schema::table('server_snapshots', fn (Blueprint $table) => $table->dropIndex('server_snapshots_recorded_at_index'));
        Schema::table('users', fn (Blueprint $table) => $table->dropIndex('users_last_seen_at_index'));
        Schema::table('news_articles', fn (Blueprint $table) => $table->dropIndex('news_articles_status_published_index'));
    }
};
