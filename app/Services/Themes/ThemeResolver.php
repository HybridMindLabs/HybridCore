<?php

namespace App\Services\Themes;

use App\Models\Theme;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves the currently active theme for use by the public frontend.
 *
 * v0.5 scope: reads active theme from DB, provides slug and path helpers.
 * Full Blade view-namespace injection and per-theme asset pipeline are deferred to v0.6+.
 *
 * Public pages can call ThemeResolver::activeSlug() to know which theme is active.
 * The active theme slug is also shared into all Inertia pages via HandleInertiaRequests.
 */
class ThemeResolver
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Returns the active Theme model, falling back to the Default theme by slug,
     * then to the first installed theme, then null if none exist.
     */
    public function resolve(): ?Theme
    {
        try {
            if (! Schema::hasTable('themes')) {
                return null;
            }

            return Theme::where('active', true)->first()
                ?? Theme::where('slug', 'default')->first()
                ?? Theme::whereNotNull('installed_at')->first();
        } catch (\Throwable) {
            // No reachable database (fresh install, outage). Schema::hasTable()
            // throws rather than returning false here, so the check above is not
            // enough on its own. Callers fall back to the default theme.
            return null;
        }
    }

    public function activeSlug(): string
    {
        return $this->resolve()?->slug
            ?? $this->settings->get('active_theme', 'default')
            ?? 'default';
    }

    /**
     * Returns the absolute filesystem path of the active theme directory.
     * Returns null when no theme is resolved (themes directory empty).
     */
    public function activeThemePath(): ?string
    {
        $theme = $this->resolve();

        return $theme ? base_path('themes/'.$theme->path) : null;
    }
}
