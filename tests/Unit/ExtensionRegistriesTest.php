<?php

namespace Tests\Unit;

use App\Services\Extensions\Registries\NavigationRegistry;
use App\Services\Extensions\Registries\PermissionRegistry;
use App\Services\Extensions\Registries\SettingsRegistry;
use App\Services\Extensions\Registries\WidgetRegistry;
use Tests\TestCase;

class ExtensionRegistriesTest extends TestCase
{
    public function test_navigation_registry_collects_items(): void
    {
        $nav = new NavigationRegistry;
        $nav->register('Test', 'admin.dashboard', 'Server', 'Testing', null, 5);

        $this->assertCount(1, $nav->all());
        $this->assertSame('Test', $nav->all()[0]['label']);
    }

    public function test_navigation_compose_skips_unknown_routes(): void
    {
        $nav = new NavigationRegistry;
        $nav->register('Real', 'admin.dashboard', 'Server');
        $nav->register('Fake', 'nonexistent.route', 'Server');

        $sections = $nav->compose();
        $labels = collect($sections)->flatMap(fn ($s) => collect($s['items'])->pluck('label'));

        $this->assertTrue($labels->contains('Real'));
        $this->assertFalse($labels->contains('Fake'));
    }

    public function test_navigation_compose_sorts_by_sort_order(): void
    {
        $nav = new NavigationRegistry;
        $nav->register('Second', 'admin.users.index', 'Users', 'G', null, 20);
        $nav->register('First', 'admin.dashboard', 'Server', 'G', null, 10);

        $items = $nav->compose()[0]['items'];

        $this->assertSame('First', $items[0]['label']);
        $this->assertSame('Second', $items[1]['label']);
    }

    public function test_widget_registry_resolves_data_callbacks(): void
    {
        $widgets = new WidgetRegistry;
        $widgets->register('test.widget', 'Test', 'stat', fn () => ['value' => 42]);

        $composed = $widgets->compose();

        $this->assertCount(1, $composed);
        $this->assertSame(42, $composed[0]['props']['value']);
    }

    public function test_widget_registry_skips_throwing_widgets(): void
    {
        $widgets = new WidgetRegistry;
        $widgets->register('bad', 'Bad', 'stat', fn () => throw new \RuntimeException('boom'));
        $widgets->register('good', 'Good', 'stat', fn () => ['value' => 1]);

        $composed = $widgets->compose();

        $this->assertCount(1, $composed);
        $this->assertSame('good', $composed[0]['id']);
    }

    public function test_widget_registry_unregister(): void
    {
        $widgets = new WidgetRegistry;
        $widgets->register('a', 'A', 'stat');
        $widgets->unregister('a');

        $this->assertCount(0, $widgets->all());
    }

    public function test_permission_registry_collects(): void
    {
        $perms = new PermissionRegistry;
        $perms->register('store.manage', 'Manage Store', 'store');
        $perms->registerMany(['store.view' => ['name' => 'View Store', 'group' => 'store']]);

        $this->assertCount(2, $perms->all());
    }

    public function test_settings_registry_skips_unknown_routes(): void
    {
        $settings = new SettingsRegistry;
        $settings->register('real', 'Real', 'admin.settings.index');
        $settings->register('fake', 'Fake', 'nonexistent.route');

        $this->assertCount(1, $settings->compose());
    }
}
