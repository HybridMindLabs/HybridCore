<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Provisions the administrator the end-to-end suite signs in as.
 *
 * tests/e2e/admin.spec.ts reads ADMIN_EMAIL / ADMIN_PASSWORD and falls back to
 * the same defaults used here, so a plain `db:seed --class=E2ESeeder` is enough
 * to make the admin specs runnable.
 *
 * Idempotent, but intended for disposable databases only — never run it against
 * a real deployment, as it sets a known password.
 */
class E2ESeeder extends Seeder
{
    public function run(): void
    {
        abort_if(app()->isProduction(), 403, 'E2ESeeder must never run against a production environment.');

        // Roles come from the migration; permissions and the owner grant do not.
        $this->call(CorePermissionsSeeder::class);

        $email = env('ADMIN_EMAIL', 'admin@hybridcore.test');
        $password = env('ADMIN_PASSWORD', 'password');

        $admin = User::firstOrNew(['email' => $email]);
        $admin->name = $admin->name ?: 'E2E Administrator';
        $admin->username = $admin->username ?: 'e2eadmin';
        $admin->password = Hash::make($password);
        $admin->is_admin = true;
        $admin->email_verified_at = now();
        $admin->save();

        if ($owner = Role::where('slug', 'owner')->first()) {
            $admin->roles()->syncWithoutDetaching([$owner->id]);
        }

        $this->command?->info("E2E admin ready: {$email}");
    }
}
