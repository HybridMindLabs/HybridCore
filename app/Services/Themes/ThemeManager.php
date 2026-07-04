<?php

namespace App\Services\Themes;

use App\Models\Theme;
use App\Services\ActivityLogService;
use App\Services\SettingsService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

class ThemeManager
{
    public function __construct(
        private readonly ThemeDiscoveryService $discovery,
        private readonly ActivityLogService $activity,
        private readonly SettingsService $settings,
    ) {}

    /**
     * Scan the themes/ directory and upsert discovered themes into the database.
     * Already-installed themes retain their active state.
     *
     * @return int Number of themes synced.
     */
    public function sync(): int
    {
        $manifests = $this->discovery->discover();
        $count = 0;

        foreach ($manifests as $manifest) {
            Theme::updateOrCreate(
                ['slug' => $manifest['slug']],
                [
                    'name' => $manifest['name'],
                    'version' => $manifest['version'],
                    'author' => $manifest['author'] ?? 'Unknown',
                    'description' => $manifest['description'] ?? '',
                    'type' => $manifest['type'] ?? 'community',
                    'path' => $manifest['path'],
                    'preview_image' => $manifest['preview_image'] ?? null,
                    'metadata' => $manifest,
                    'installed_at' => now(),
                ],
            );
            $count++;
        }

        $this->activity->log('themes.synced', "Synced {$count} theme(s) from disk");

        return $count;
    }

    /**
     * Activate a theme and deactivate all others.
     * Updates the active_theme setting for fast resolution without a DB join.
     */
    public function activate(Theme $theme): void
    {
        if (Schema::hasTable('themes')) {
            Theme::query()->update(['active' => false]);
        }

        $theme->update([
            'active' => true,
            'activated_at' => now(),
        ]);

        $this->settings->set('active_theme', $theme->slug);

        $this->activity->log('theme.activated', "Activated theme: {$theme->name}", $theme);
    }

    /**
     * Deactivate a theme. Does not activate a fallback automatically.
     * Guard: cannot deactivate if it is the only active theme and no others exist.
     */
    public function deactivate(Theme $theme): void
    {
        $theme->update(['active' => false]);

        // If the deactivated theme was the active_theme setting, clear it.
        if ($this->settings->get('active_theme') === $theme->slug) {
            $fallback = Theme::where('active', true)->where('id', '!=', $theme->id)->first();
            $this->settings->set('active_theme', $fallback?->slug ?? '');
        }

        $this->activity->log('theme.deactivated', "Deactivated theme: {$theme->name}", $theme);
    }

    public function getActive(): ?Theme
    {
        return Theme::where('active', true)->first();
    }

    /** @return Collection<int, Theme> */
    public function getInstalled(): Collection
    {
        return Theme::whereNotNull('installed_at')->orderBy('name')->get();
    }
}
