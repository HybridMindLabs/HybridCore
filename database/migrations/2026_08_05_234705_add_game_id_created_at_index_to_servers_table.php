<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The admin server list filters by game_id and sorts by created_at — a
     * plain LIKE '%search%' on ip/name can't use an index either way, so this
     * targets the filter+sort path instead of the free-text search.
     */
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->index(['game_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropIndex(['game_id', 'created_at']);
        });
    }
};
