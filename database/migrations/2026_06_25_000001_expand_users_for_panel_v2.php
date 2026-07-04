<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Friendly display name (optional — falls back to username when blank)
            $table->string('display_name')->nullable()->after('username');

            // Profile banner image path
            $table->string('banner')->nullable()->after('avatar');

            // Cooldown tracking for username changes
            $table->timestamp('username_changed_at')->nullable()->after('display_name');

            // Profile visibility: public (everyone), members (logged-in), private (only self)
            $table->enum('profile_privacy', ['public', 'members', 'private'])
                ->default('public')
                ->after('website');

            // Email notification preferences (bitfield stored as JSON array of opted-out keys)
            $table->json('notification_preferences')->nullable()->after('profile_privacy');
        });

        // Back-fill display_name from existing name for current users
        DB::table('users')->update(['display_name' => DB::raw('name')]);

        // Make username required: fill any nulls from name before adding NOT NULL
        DB::table('users')
            ->whereNull('username')
            ->orWhere('username', '')
            ->eachById(function ($user) {
                $base = preg_replace('/[^a-z0-9_]/', '_', strtolower($user->name));
                $base = trim($base, '_') ?: 'user';
                $slug = substr($base, 0, 28);
                $candidate = $slug;
                $i = 1;
                while (DB::table('users')->where('username', $candidate)->where('id', '!=', $user->id)->exists()) {
                    $candidate = $slug.'_'.$i++;
                }
                DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('username')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username')->nullable()->change();
            $table->dropColumn(['display_name', 'banner', 'username_changed_at', 'profile_privacy', 'notification_preferences']);
        });
    }
};
