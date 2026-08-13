<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * payments had an index on (payable_type, payable_id) via morphs(), and
     * an implicit one on user_id via its FK — but nothing on `status` alone
     * or paired with payable_type/user_id. Every dashboard, ledger, and
     * "sold units" query in the app filters by status, and most also filter
     * by payable_type or user_id — at scale that's a full table scan on the
     * single most-queried table in the system.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->index('status');
            $table->index(['payable_type', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['payable_type', 'status']);
            $table->dropIndex(['user_id', 'status']);
        });
    }
};
