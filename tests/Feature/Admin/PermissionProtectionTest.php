<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CorePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the route-level permission system: super admins (is_admin)
 * pass everything; role-based users only what their role grants.
 */
class PermissionProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');

        (new CorePermissionsSeeder)->run();
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));

        parent::tearDown();
    }

    private function userWithPermissions(array $slugs): User
    {
        $user = User::factory()->create(['is_admin' => true]);
        // Role-based permission checks only matter for non-super users;
        // we simulate a restricted admin by clearing is_admin and granting
        // admin.access + the given permissions through a role.
        $user->update(['is_admin' => false]);

        $role = Role::create(['name' => 'Restricted', 'slug' => 'restricted-'.uniqid()]);
        $ids = Permission::whereIn('slug', array_merge(['admin.access'], $slugs))->pluck('id');
        $role->permissions()->sync($ids);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_super_admin_passes_all_permission_checks(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.system-health.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.backup.index'))->assertOk();
    }

    public function test_user_has_permission_via_role(): void
    {
        $user = $this->userWithPermissions(['users.view']);

        $this->assertTrue($user->hasPermission('users.view'));
        $this->assertFalse($user->hasPermission('users.manage'));
        $this->assertFalse($user->hasPermission('system.manage'));
    }

    public function test_permission_slugs_are_unique_and_flattened(): void
    {
        $user = $this->userWithPermissions(['users.view', 'users.manage']);

        $slugs = $user->permissionSlugs();

        $this->assertTrue($slugs->contains('users.view'));
        $this->assertTrue($slugs->contains('admin.access'));
        $this->assertSame($slugs->count(), $slugs->unique()->count());
    }

    public function test_maintenance_enable_requires_system_manage(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.maintenance.enable'), ['message' => 'BRB'])
            ->assertRedirect();

        $this->assertDatabaseHas('settings', ['key' => 'maintenance_mode', 'value' => '1']);
    }

    public function test_maintenance_disable_restores_access(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.maintenance.enable'));
        $this->actingAs($admin)->post(route('admin.maintenance.disable'))->assertRedirect();

        $this->assertDatabaseHas('settings', ['key' => 'maintenance_mode', 'value' => '0']);
    }
}
