<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            // A purchased theme's credential, same shape as extensions.php's
            // license_key: encrypted at rest, text not string because
            // ciphertext overflows 255 chars even for a short key.
            $table->text('license_key')->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->dropColumn('license_key');
        });
    }
};
