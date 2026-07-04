<?php

namespace Tests\Feature\Extensions;

use App\Models\Extension;
use App\Providers\ExtensionServiceProvider;
use App\Services\Extensions\Registries\ExtensionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Boots the bundled hybridcore/demo reference extension end-to-end:
 * autoloading, provider, permissions, navigation, widget, routes,
 * translations and migration path discovery.
 */
class DemoExtensionTest extends TestCase
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

    private function enableDemo(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(base_path('extensions/hybridcore/demo/extension.json')),
            true,
        );

        Extension::create([
            'name' => $manifest['name'],
            'slug' => $manifest['id'],
            'version' => $manifest['version'],
            'author' => $manifest['author'],
            'description' => $manifest['description'],
            'type' => $manifest['type'] ?? 'custom',
            'path' => 'hybridcore/demo',
            'metadata' => $manifest,
            'enabled' => true,
            'installed_at' => now(),
        ]);

        // Re-boot the extension layer now that the demo is enabled.
        (new ExtensionServiceProvider(app()))->boot();
    }

    public function test_demo_extension_loads_correctly(): void
    {
        $this->enableDemo();

        $registry = app(ExtensionRegistry::class);

        // Permissions registered.
        $this->assertArrayHasKey('demo.view', $registry->permissions()->all());
        $this->assertArrayHasKey('demo.manage', $registry->permissions()->all());

        // Widget registered.
        $this->assertArrayHasKey('demo.example', $registry->widgets()->all());

        // Navigation item registered.
        $labels = collect($registry->navigation()->all())->pluck('label');
        $this->assertTrue($labels->contains('Demo'));

        // Routes registered with names.
        $this->assertTrue(app('router')->has('admin.demo.index'));
        $this->assertTrue(app('router')->has('demo.index'));

        // Translations namespaced and localized.
        $this->assertSame('Welcome to the Demo extension!', trans('demo::messages.welcome'));
        app()->setLocale('bg');
        $this->assertSame('Добре дошли в разширението Demo!', trans('demo::messages.welcome'));
    }

    public function test_demo_admin_route_enforces_permission_middleware(): void
    {
        $this->enableDemo();

        $route = app('router')->getRoutes()->getByName('admin.demo.index');

        $this->assertContains('perm:demo.view', $route->gatherMiddleware());
        $this->assertContains('web', $route->gatherMiddleware());
    }

    public function test_demo_web_route_is_registered_and_dispatchable(): void
    {
        $this->enableDemo();

        // NOTE: in a real request cycle extension routes register during
        // provider boot (before routes/web.php), so /demo wins over the
        // pages.show catch-all. In this test the catch-all is already
        // registered, so we assert the route itself instead of HTTP order.
        $route = app('router')->getRoutes()->getByName('demo.index');

        $this->assertNotNull($route);
        $this->assertSame('demo', $route->uri());

        $controller = $route->getController();
        $response = $controller->index();

        $this->assertSame('Welcome to the Demo extension!', $response->getData(true)['message']);
    }

    public function test_extensions_migrate_command_discovers_demo_path(): void
    {
        $this->enableDemo();

        // Demo has no migration files — command must skip it cleanly.
        $this->artisan('hybridcore:extensions:migrate', ['--extension' => 'hybridcore/demo'])
            ->expectsOutputToContain('skipping')
            ->assertSuccessful();
    }

    public function test_migrate_command_handles_unknown_extension(): void
    {
        $this->artisan('hybridcore:extensions:migrate', ['--extension' => 'nope/nope'])
            ->expectsOutputToContain('No extension found')
            ->assertSuccessful();
    }
}
