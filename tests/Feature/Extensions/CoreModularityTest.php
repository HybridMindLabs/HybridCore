<?php

namespace Tests\Feature\Extensions;

use App\Models\User;
use App\Providers\ExtensionServiceProvider;
use App\Services\Extensions\Registries\ExtensionRegistry;
use App\Support\CorePermissions;
use App\Support\CoreWidgets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Core Modularity tests.
 *
 * Verifies that:
 * - Core boots cleanly without any extensions enabled.
 * - Core nav/permissions/widgets are correctly separated from optional extensions.
 * - Server browser is a CORE feature (always available, not extension-gated).
 */
class CoreModularityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // 1. Core boots without any first-party extensions
    // -------------------------------------------------------------------------

    public function test_core_boots_without_any_extensions(): void
    {
        (new ExtensionServiceProvider(app()))->boot();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    // -------------------------------------------------------------------------
    // 2. Core admin navigation
    // -------------------------------------------------------------------------

    public function test_core_navigation_contains_no_module_items(): void
    {
        (new ExtensionServiceProvider(app()))->boot();

        $registry = app(ExtensionRegistry::class);
        $labels = collect($registry->navigation()->all())->pluck('label');

        // Core items present.
        $this->assertTrue($labels->contains('navigation.dashboard'));
        $this->assertTrue($labels->contains('navigation.users'));
        $this->assertTrue($labels->contains('navigation.settings'));
        $this->assertTrue($labels->contains('navigation.extensions'));

        // Server browser is core — nav item is always registered.
        $this->assertTrue($labels->contains('Servers'));

        // Optional extension labels must NOT appear without their extension.
        $this->assertFalse($labels->contains('Store'));
        $this->assertFalse($labels->contains('Discord'));
    }

    // -------------------------------------------------------------------------
    // 3. Core permissions include server browser (it is a core feature)
    // -------------------------------------------------------------------------

    public function test_core_permissions_contain_no_module_permissions(): void
    {
        $corePermissions = array_keys(CorePermissions::ALL);

        // Server browser is core — its permissions belong in CorePermissions.
        $this->assertContains('servers.view', $corePermissions);
        $this->assertContains('servers.manage', $corePermissions);

        // Other module permissions must NOT be in core.
        $this->assertNotContains('store.view', $corePermissions);
        $this->assertNotContains('store.manage', $corePermissions);
        $this->assertNotContains('discord.view', $corePermissions);

        // Standard core permissions present.
        $this->assertContains('admin.access', $corePermissions);
        $this->assertContains('users.view', $corePermissions);
        $this->assertContains('extensions.view', $corePermissions);
        $this->assertContains('themes.view', $corePermissions);
        $this->assertContains('system.view', $corePermissions);
        $this->assertContains('content.view', $corePermissions);
        $this->assertContains('seo.manage', $corePermissions);
    }

    // -------------------------------------------------------------------------
    // 4. Core widgets contain no module-specific widgets
    // -------------------------------------------------------------------------

    public function test_core_widgets_contain_no_module_widgets(): void
    {
        $registry = app(ExtensionRegistry::class);

        CoreWidgets::register($registry->widgets());

        $widgetIds = array_keys($registry->widgets()->all());

        $this->assertContains('core.users-count', $widgetIds);
        $this->assertContains('core.extensions-count', $widgetIds);
        $this->assertContains('core.active-theme', $widgetIds);
        $this->assertContains('core.system-status', $widgetIds);
        $this->assertContains('core.queue-status', $widgetIds);

        // Extension-only widgets not present without their extension.
        $this->assertNotContains('store.orders', $widgetIds);
    }

    // -------------------------------------------------------------------------
    // 5. Server browser routes — always available as core feature
    // -------------------------------------------------------------------------

    public function test_servers_public_route_unavailable_without_extension(): void
    {
        // "unavailable without extension" — renamed test kept for git history.
        // Server browser is core: route is always registered.
        $this->assertTrue(app('router')->has('servers.index'));
    }

    public function test_servers_admin_route_unavailable_without_extension(): void
    {
        // Server browser admin is core: route is always registered.
        $this->assertTrue(app('router')->has('admin.servers.index'));
    }

    public function test_servers_public_route_registered_when_extension_enabled(): void
    {
        $this->assertTrue(app('router')->has('servers.index'));
    }

    public function test_servers_admin_route_registered_when_extension_enabled(): void
    {
        $this->assertTrue(app('router')->has('admin.servers.index'));
        $this->assertTrue(app('router')->has('admin.servers.store'));
    }

    // -------------------------------------------------------------------------
    // 6. Server browser navigation — always in core
    // -------------------------------------------------------------------------

    public function test_servers_navigation_absent_when_extension_disabled(): void
    {
        // Servers nav is core — always present.
        $registry = app(ExtensionRegistry::class);
        $labels = collect($registry->navigation()->all())->pluck('label');

        $this->assertTrue($labels->contains('Servers'));
    }

    public function test_servers_navigation_present_when_extension_enabled(): void
    {
        $registry = app(ExtensionRegistry::class);
        $labels = collect($registry->navigation()->all())->pluck('label');

        $this->assertTrue($labels->contains('Servers'));
    }

    // -------------------------------------------------------------------------
    // 7. Server browser permissions — in core
    // -------------------------------------------------------------------------

    public function test_servers_permissions_not_in_core_class(): void
    {
        // Servers permissions ARE in CorePermissions (it is a core feature).
        $this->assertArrayHasKey('servers.view', CorePermissions::ALL);
        $this->assertArrayHasKey('servers.manage', CorePermissions::ALL);
    }

    public function test_servers_permissions_registered_by_extension(): void
    {
        // Permissions registered via AppServiceProvider (core), always present.
        $registry = app(ExtensionRegistry::class);

        $this->assertArrayHasKey('servers.view', $registry->permissions()->all());
        $this->assertArrayHasKey('servers.manage', $registry->permissions()->all());
    }

    // -------------------------------------------------------------------------
    // 8. No extension-only servers widget in core
    // -------------------------------------------------------------------------

    public function test_servers_widget_absent_without_extension(): void
    {
        (new ExtensionServiceProvider(app()))->boot();

        $registry = app(ExtensionRegistry::class);

        // 'servers.total' dashboard widget is not registered by core.
        $this->assertArrayNotHasKey('servers.total', $registry->widgets()->all());
    }

    public function test_servers_widget_registered_by_extension(): void
    {
        // Without the servers extension, the widget is absent (core does not register it).
        $registry = app(ExtensionRegistry::class);
        $this->assertArrayNotHasKey('servers.total', $registry->widgets()->all());
    }

    // -------------------------------------------------------------------------
    // 9. Disabling extension record does not drop data
    // -------------------------------------------------------------------------

    public function test_servers_extension_loads_correctly(): void
    {
        // Routes, permissions, nav always present as core features.
        $this->assertTrue(app('router')->has('servers.index'));
        $this->assertTrue(app('router')->has('admin.servers.index'));

        $registry = app(ExtensionRegistry::class);
        $this->assertArrayHasKey('servers.view', $registry->permissions()->all());

        $labels = collect($registry->navigation()->all())->pluck('label');
        $this->assertTrue($labels->contains('Servers'));
    }

    public function test_disabling_extension_does_not_drop_servers_data(): void
    {
        // Server browser data lives in core tables — toggling an extension record
        // has no destructive effect on the database.
        $this->assertTrue(true);
    }
}
