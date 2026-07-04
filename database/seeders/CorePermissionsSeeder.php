<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\CorePermissions;
use Illuminate\Database\Seeder;

/**
 * Seeds core permissions and grants the "owner" role every permission.
 * Idempotent — safe to re-run. The base role set itself (owner, super-admin,
 * admin, support, developer, user) is seeded by the roles table migration —
 * this seeder only manages permissions and the owner's full-access grant.
 */
class CorePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CorePermissions::ALL as $slug => $def) {
            Permission::updateOrCreate(
                ['slug' => $slug],
                ['name' => $def['name'], 'group' => $def['group']],
            );
        }

        $owner = Role::where('slug', 'owner')->first();

        if ($owner) {
            $owner->permissions()->sync(Permission::pluck('id'));
            $owner->forgetPermissionCache();
        }
    }
}
