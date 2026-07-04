<?php

namespace Hybridcore\Demo;

use App\Services\Extensions\Registries\ExtensionRegistry;
use App\Support\Slots;
use Illuminate\Support\ServiceProvider;

class DemoServiceProvider extends ServiceProvider
{
    public function boot(ExtensionRegistry $registry): void
    {
        $registry->permissions()->register('demo.view', 'View Demo', 'demo');
        $registry->permissions()->register('demo.manage', 'Manage Demo', 'demo');

        $registry->navigation()->register(
            label: 'Demo',
            route: 'admin.demo.index',
            icon: 'Puzzle',
            section: 'Extensions',
            permission: 'demo.view',
            sort: 100,
        );
        $registry->widgets()->register(
            id: 'demo.example',
            title: 'Demo',
            component: 'stat',
            data: fn () => ['value' => 'It works!', 'icon' => 'Puzzle', 'accent' => 'accent'],
            permission: 'demo.view',
            sort: 200,
        );
        // Settings URL: /admin/settings/extensions/demo
        // Route name:   admin.settings.extensions.demo
        $registry->settings()->register(
            slug: 'demo',
            label: 'Demo Settings',
            permission: 'demo.manage',
        );

        // Slot component registered into the home page right column.
        // Component file: extensions/hybridcore/demo/resources/js/components/HybridcoreDemoWidget.vue
        // Vite discovers it via import.meta.glob in app.ts and registers it globally by filename.
        $registry->slots()->register(
            slot: Slots::HOME_RIGHT_BOTTOM,
            component: 'HybridcoreDemoWidget',
            data: fn () => ['message' => 'Hello from Demo extension!'],
            permission: null,
            priority: 100,
        );
    }
}
