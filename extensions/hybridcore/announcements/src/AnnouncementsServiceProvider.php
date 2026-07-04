<?php

namespace Hybridcore\Announcements;

use App\Services\Extensions\Registries\ExtensionRegistry;
use App\Services\SettingsService;
use App\Support\Slots;
use Hybridcore\Announcements\Services\AnnouncementService;
use Illuminate\Support\ServiceProvider;

class AnnouncementsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register the service as a singleton so any class can inject it.
        $this->app->singleton(AnnouncementService::class);
    }

    public function boot(ExtensionRegistry $registry): void
    {
        // ── Permissions ───────────────────────────────────────────────────────
        // Group slug 'announcements' groups these in the admin role editor.
        $registry->permissions()->register('announcements.view', 'View Announcements', 'announcements');
        $registry->permissions()->register('announcements.manage', 'Manage Announcements', 'announcements');

        // ── Admin navigation ──────────────────────────────────────────────────
        // Appears under the "Content" section in the left sidebar.
        $registry->navigation()->register(
            label: 'Announcements',
            route: 'admin.announcements.index',
            icon: 'Megaphone',
            section: 'Content',
            permission: 'announcements.view',
            sort: 50,
        );

        // ── Admin dashboard widget ────────────────────────────────────────────
        // Shows a stat card: total active announcements.
        $registry->widgets()->register(
            id: 'announcements.active_count',
            title: 'Active Announcements',
            component: 'stat',
            data: fn () => [
                'value' => $this->app->make(AnnouncementService::class)->active()->count(),
                'icon' => 'Megaphone',
                'label' => 'active now',
                'accent' => 'blue',
            ],
            permission: 'announcements.view',
            sort: 10,
        );

        // ── Extension settings page ───────────────────────────────────────────
        // URL:   /admin/settings/extensions/announcements
        // Route: admin.settings.extensions.announcements  (defined in routes/admin.php)
        $registry->settings()->register(
            slug: 'announcements',
            label: 'Announcements',
            permission: 'announcements.manage',
        );

        // ── Home page slot ────────────────────────────────────────────────────
        // Registers HybridcoreAnnouncementsWidget.vue into the top of the right
        // column on the home page. The data closure is lazy — runs only when
        // the home page is requested and only if show_on_home is enabled.
        //
        // The component receives { announcements: [...] } as props.
        $registry->slots()->register(
            slot: Slots::HOME_RIGHT_TOP,
            component: 'HybridcoreAnnouncementsWidget',
            data: function () {
                $settings = $this->app->make(SettingsService::class);

                if (! $settings->get('announcements.show_on_home', true)) {
                    return null; // returning null skips the component entirely
                }

                $limit = (int) $settings->get('announcements.max_shown', 3);
                $items = $this->app->make(AnnouncementService::class)->active($limit);

                return [
                    'announcements' => $items->map(fn ($a) => [
                        'id' => $a->id,
                        'title' => $a->title,
                        'body' => $a->body,
                        'type' => $a->type,
                    ])->values()->all(),
                ];
            },
            priority: 10, // render before other right-column extensions
        );
    }
}
