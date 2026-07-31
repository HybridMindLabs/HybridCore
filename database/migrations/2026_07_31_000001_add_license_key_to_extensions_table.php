<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            // Paid extensions live in private repositories, so fetching their
            // releases needs a credential the buyer receives. Text, not string:
            // the value is encrypted at rest and the ciphertext of even a short
            // key overflows 255 characters.
            $table->text('license_key')->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            $table->dropColumn('license_key');
        });
    }
};
