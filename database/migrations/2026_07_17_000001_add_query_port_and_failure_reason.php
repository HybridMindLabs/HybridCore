<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            // The query port is often not the game port (Rust: game+2, ARK:
            // 27015, 7 Days to Die / Unturned: game+1). Null means "same as the
            // game port", which is correct for every Source-engine game.
            $table->unsignedSmallInteger('query_port')->nullable()->after('port');
        });

        Schema::table('server_snapshots', function (Blueprint $table) {
            // Why a query came back offline, so the panel can show a reason
            // instead of a silent red dot.
            $table->string('failure_reason')->nullable()->after('is_online');
        });
    }

    public function down(): void
    {
        Schema::table('servers', fn (Blueprint $table) => $table->dropColumn('query_port'));
        Schema::table('server_snapshots', fn (Blueprint $table) => $table->dropColumn('failure_reason'));
    }
};
