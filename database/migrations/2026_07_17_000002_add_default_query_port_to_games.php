<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // The query port a game uses when it differs from the game port
            // (Rust, ARK, 7 Days to Die, Unturned). Null means "same as the
            // game port". The admin add-server form pre-fills the server's
            // query_port from this, so nobody has to know the per-game offset.
            $table->unsignedSmallInteger('default_query_port')->nullable()->after('default_port');
        });
    }

    public function down(): void
    {
        Schema::table('games', fn (Blueprint $table) => $table->dropColumn('default_query_port'));
    }
};
