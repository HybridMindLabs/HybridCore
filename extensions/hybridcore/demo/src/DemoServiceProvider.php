<?php

namespace Hybridcore\Demo;

use App\Services\Extensions\Registries\ExtensionRegistry;
use App\Services\SettingsService;
use App\Support\Filters;
use App\Support\Hooks;
use App\Support\Slots;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Reference implementation for the Extension SDK — every registry documented
 * in extensions/BUILDING_EXTENSIONS.md gets a short, working example here.
 * Copy this extension as the starting point for a new one; see README.md for
 * a map of which file demonstrates which registry.
 */
class DemoServiceProvider extends ServiceProvider
{
    public function boot(ExtensionRegistry $registry): void
    {
        $registry->permissions()->register('demo.view', 'View Demo', 'demo');
        $registry->permissions()->register('demo.manage', 'Manage Demo', 'demo');

        $registry->abilities()->register('demo:ping', 'Ping Demo Endpoint', 'demo');

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
        // Persisted through the core SettingsService, prefixed "demo.*".
        $registry->settings()->register(
            slug: 'demo',
            label: 'Demo Settings',
            permission: 'demo.manage',
        );

        // Slot component registered into the home page right column.
        $registry->slots()->register(
            slot: Slots::HOME_RIGHT_BOTTOM,
            component: 'HybridcoreDemoWidget',
            data: fn () => ['message' => 'Hello from Demo extension!'],
            permission: null,
            priority: 100,
        );

        $registry->publicNavigation()->register(
            label: 'demo::messages.nav',
            route: 'demo.index',
            icon: 'Puzzle',
            sort: 900,
            permission: null,
        );

        $registry->userMenu()->register(
            label: 'demo::messages.nav',
            route: 'demo.index',
            icon: 'Puzzle',
            sort: 900,
            permission: null,
        );

        $registry->footerLinks()->register(
            label: 'demo::messages.nav',
            route: 'demo.index',
            sort: 900,
            permission: null,
        );

        $registry->accountTabs()->register(
            key: 'demo',
            label: 'demo::messages.account_tab',
            route: 'account.demo.index',
            icon: 'Puzzle',
            sort: 900,
        );

        $registry->profileTabs()->register(
            key: 'demo',
            label: 'demo::messages.profile_tab',
            component: 'HybridcoreDemoProfilePanel',
            data: fn ($user) => ['username' => $user->username ?? $user->name],
            icon: 'Puzzle',
            sort: 900,
        );

        $registry->search()->register(
            key: 'demo',
            label: 'demo::messages.nav',
            resolver: fn (string $term, int $limit) => str_contains(strtolower('demo extension'), strtolower($term))
                ? [['title' => 'Demo extension', 'url' => route('demo.index'), 'meta' => 'demo::messages.nav']]
                : [],
            icon: 'Puzzle',
            permission: null,
        );

        $registry->quickActions()->register(
            label: 'demo::messages.nav',
            route: 'admin.demo.index',
            icon: 'Puzzle',
            permission: 'demo.view',
            keywords: ['demo', 'example', 'sdk'],
        );

        $registry->notificationTypes()->register(
            type: 'demo.example',
            icon: 'Puzzle',
            accent: 'blue',
        );

        $registry->activityFeed()->register(
            'demo.example',
            fn (int $limit) => [[
                'username' => null,
                'avatar' => null,
                'text' => trans('demo::messages.activity_feed_row'),
                'url' => route('demo.index'),
                'at' => now(),
            ]],
        );

        $registry->onboardingSteps()->register(
            key: 'demo',
            component: 'HybridcoreDemoOnboarding',
            title: 'demo::messages.onboarding_title',
            icon: 'Puzzle',
            sort: 900,
            when: fn () => (bool) app(SettingsService::class)->get('demo.show_onboarding_step', false),
        );

        $registry->scheduledReports()->register(
            fn () => [['text' => trans('demo::messages.digest_row'), 'url' => route('demo.index')]],
        );

        // Side-effect-free — safe to leave registered permanently.
        $registry->hooks()->listen(Hooks::USER_LOGIN, function ($user): void {
            Log::info('[demo extension] user.login hook fired', ['user_id' => $user->id]);
        });

        // Additive only — never removes or overwrites an existing shared prop.
        $registry->filters()->add(Filters::INERTIA_SHARED, function (array $shared) {
            $shared['demo_extension_active'] = true;

            return $shared;
        });

        // Registration-only proof the API is a one-liner — no real bridge
        // traffic reaches this in tests or in a default install.
        $registry->bridgeEvents()->on('player.kill', function ($server, array $data): void {
            Log::info('[demo extension] player.kill bridge event received', ['server_id' => $server->id, 'data' => $data]);
        });
    }
}
