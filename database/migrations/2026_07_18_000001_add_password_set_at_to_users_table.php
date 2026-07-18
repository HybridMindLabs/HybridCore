<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records whether the user ever chose their own password.
 *
 * Accounts created through OAuth are given Hash::make(Str::random(40)) because
 * the column is not nullable, so a hash alone cannot tell "has a password" from
 * "has a placeholder nobody knows". Without that distinction, disconnecting the
 * last provider silently locks the account out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_set_at')->nullable()->after('password');
        });

        // Anyone with no linked provider must have registered with a password,
        // so they are safe to backfill. Where a provider is linked the origin
        // is genuinely unknown, and the column stays null: the worst case is
        // being asked to set a password before unlinking, which is recoverable.
        // Guessing the other way would leave the lockout in place.
        DB::table('users')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('connected_accounts')
                    ->whereColumn('connected_accounts.user_id', 'users.id');
            })
            ->update(['password_set_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_set_at');
        });
    }
};
