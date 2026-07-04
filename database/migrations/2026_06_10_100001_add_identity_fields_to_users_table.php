<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('banned_at');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->string('timezone')->nullable()->after('last_login_ip');
            $table->string('locale', 10)->nullable()->after('timezone');
            $table->string('avatar')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_login_at', 'last_login_ip', 'timezone', 'locale', 'avatar']);
        });
    }
};
