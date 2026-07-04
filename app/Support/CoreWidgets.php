<?php

namespace App\Support;

use App\Models\Extension;
use App\Models\Theme;
use App\Models\User;
use App\Services\Extensions\Registries\WidgetRegistry;
use Illuminate\Support\Facades\Schema;

/**
 * Core dashboard widgets. Extensions register theirs through the same registry.
 */
class CoreWidgets
{
    public static function register(WidgetRegistry $widgets): void
    {
        $widgets->register(
            id: 'core.users-count',
            title: 'Registered Users',
            component: 'stat',
            data: fn () => [
                'value' => Schema::hasTable('users') ? User::count() : 0,
                'icon' => 'Users',
                'accent' => 'accent',
            ],
            permission: 'users.view',
            sort: 10,
        );

        $widgets->register(
            id: 'core.extensions-count',
            title: 'Extensions',
            component: 'stat',
            data: fn () => [
                'value' => Schema::hasTable('extensions') ? Extension::where('enabled', true)->count() : 0,
                'description' => Schema::hasTable('extensions') ? Extension::count().' installed' : null,
                'icon' => 'Puzzle',
                'accent' => 'warning',
            ],
            permission: 'extensions.view',
            sort: 20,
        );

        $widgets->register(
            id: 'core.active-theme',
            title: 'Active Theme',
            component: 'stat',
            data: function () {
                $theme = Schema::hasTable('themes') ? Theme::where('active', true)->first() : null;

                return [
                    'value' => $theme?->name ?? 'Default',
                    'description' => $theme ? 'v'.$theme->version : null,
                    'icon' => 'Paintbrush',
                    'accent' => 'success',
                ];
            },
            permission: 'themes.view',
            sort: 30,
        );

        $widgets->register(
            id: 'core.system-status',
            title: 'System',
            component: 'stat',
            data: fn () => [
                'value' => app()->environment('production') && ! config('app.debug') ? 'Healthy' : 'Dev Mode',
                'description' => 'PHP '.PHP_VERSION,
                'icon' => 'HeartPulse',
                'accent' => app()->environment('production') && ! config('app.debug') ? 'success' : 'warning',
            ],
            permission: 'system.view',
            sort: 40,
        );

        $widgets->register(
            id: 'core.queue-status',
            title: 'Queue',
            component: 'stat',
            data: fn () => [
                'value' => ucfirst((string) config('queue.default')),
                'description' => 'Scheduler status coming soon',
                'icon' => 'Timer',
                'accent' => 'default',
            ],
            permission: 'system.view',
            sort: 50,
        );
    }
}
