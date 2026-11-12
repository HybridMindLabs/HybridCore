<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\User;
use App\Services\Extensions\Registries\ExtensionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtensionPermissionSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed.lock'), 'installed');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    public function test_role_editor_lists_permissions_registered_by_extensions(): void
    {
        app(ExtensionRegistry::class)->permissions()->register('example.manage', 'Manage Example', 'example');
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get(route('admin.roles.create'))->assertOk();

        $this->assertDatabaseHas('permissions', ['slug' => 'example.manage', 'name' => 'Manage Example', 'group' => 'example']);
    }

    public function test_sync_only_writes_new_or_changed_permissions(): void
    {
        $permissions = app(ExtensionRegistry::class)->permissions();
        $permissions->register('example.view', 'View Example', 'example');

        $this->assertGreaterThan(0, $permissions->sync());
        $this->assertSame(0, $permissions->sync());

        $id = Permission::where('slug', 'example.view')->value('id');
        $permissions->register('example.view', 'View the example', 'example');

        $this->assertSame(1, $permissions->sync());
        $this->assertSame($id, Permission::where('slug', 'example.view')->value('id'));
    }

    public function test_extensions_sync_command_persists_permissions(): void
    {
        app(ExtensionRegistry::class)->permissions()->register('example.export', 'Export Example', 'example');

        $this->artisan('hybridcore:extensions:sync')->assertSuccessful();

        $this->assertDatabaseHas('permissions', ['slug' => 'example.export']);
    }
}
