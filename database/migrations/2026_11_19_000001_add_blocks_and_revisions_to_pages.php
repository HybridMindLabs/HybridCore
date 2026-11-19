<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->json('blocks')->nullable()->after('body');
        });

        Schema::create('page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('format', 20);
            $table->string('layout', 20);
            $table->longText('body')->nullable();
            $table->json('blocks')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['page_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_revisions');

        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('blocks');
        });
    }
};
